#!/usr/bin/php -q
<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/sendSmsAlert.php';

// Load environment variables
$env = parse_ini_file(__DIR__ . '/../.env');

if (!$env) {
    die("❌ Failed to load environment variables from .env\n");
}

date_default_timezone_set("Africa/Dar_es_Salaam");

$voice_host = $env['VOICE_HOST'];
$voice_host_user = $env['VOICE_HOST_USER'];
$voice_host_password = $env['VOICE_HOST_PASSWORD'];
$voice_db_port = $env['VOICE_DB_PORT'];
$voice_db_name = $env['VOICE_DB_NAME'];
$voice_db_user = $env['VOICE_DB_USERNAME'];
$voice_db_password = $env['VOICE_DB_PASSWORD'];
$voice_sudo_user = $env['VOICE_SUDO_USER'] ?? $voice_host_user;
$stateFile = __DIR__ . '/logs/doctor_status_state.txt'; // Store previous provider count
// Allow overriding remote psql path if needed
$voice_psql_path = $env['VOICE_PSQL_PATH'] ?? '/usr/bin/psql';

function normalizeProviderFirstName(string $doctorName): string
{
    $doctorName = trim($doctorName);
    $doctorName = preg_replace('/^dr\.\s+/i', '', $doctorName);
    $doctorName = preg_replace('/^dr\s+/i', '', $doctorName);
    $parts = preg_split('/\s+/', $doctorName, 2, PREG_SPLIT_NO_EMPTY);
    return $parts[0] ?? '';
}

function fetchOnlineProviders(
    string $voiceHost,
    string $voiceHostUser,
    string $voiceHostPassword,
    string $voiceSudoUser,
    string $voiceDbPort,
    string $voiceDbName,
    string $voiceDbUser,
    string $voiceDbPassword,
    string $voicePsqlPath
): array {
    $sql = "SELECT d.provider_type, string_agg(d.doctor_name, ', ' ORDER BY d.doctor_name) AS available_providers, count(*) "
         . "FROM doctor d JOIN doctor_availability a USING(doctor_id) "
         . "WHERE a.availability_status = 'AVAILABLE' "
         . "GROUP BY d.provider_type;";

    // Escape SQL for embedding in a double-quoted shell argument
    $sql_escaped = str_replace('"', '\\"', $sql);

    // Build a small remote script: prefer local psql; if missing, try docker container exposing 5432
    $remoteScript = "if command -v psql >/dev/null 2>&1; then\n"
                  . "  PGPASSWORD=" . escapeshellarg($voiceDbPassword) . " ". $voicePsqlPath
                  . " -h localhost -p " . escapeshellarg($voiceDbPort)
                  . " -U " . escapeshellarg($voiceDbUser)
                  . " -d " . escapeshellarg($voiceDbName)
                  . " -At -F $'\\t' -c \"" . $sql_escaped . "\"\n"
                  . "else\n"
                  . "  container=\$(docker ps --filter 'publish=5432' --format '{{.Names}}' | head -n1)\n"
                  . "  if [ -n \"\$container\" ]; then\n"
                  . "    PGPASSWORD=" . escapeshellarg($voiceDbPassword) . " docker exec -i \"\$container\" " . $voicePsqlPath
                  . " -h localhost -p " . escapeshellarg($voiceDbPort)
                  . " -U " . escapeshellarg($voiceDbUser)
                  . " -d " . escapeshellarg($voiceDbName)
                  . " -At -F $'\\t' -c \"" . $sql_escaped . "\"\n"
                  . "  else\n"
                  . "    echo 'NO_PSQL_OR_CONTAINER'\n"
                  . "    exit 127\n"
                  . "  fi\n"
                  . "fi";

    // Encode the remote script to avoid complex shell quoting; decode and run under target user
    $b64 = base64_encode($remoteScript);
    $inner = "echo " . escapeshellarg($b64) . " | base64 -d | bash -s";
    $remoteCommand = sprintf(
        'sudo -i -u %s bash -lc %s',
        escapeshellarg($voiceSudoUser),
        escapeshellarg($inner)
    );

    // Pass the prepared remote command directly to ssh (don't re-escape the whole command)
    $sshCommand = sprintf(
        'sshpass -p %s ssh -o StrictHostKeyChecking=no -o BatchMode=no %s@%s %s 2>&1',
        escapeshellarg($voiceHostPassword),
        escapeshellarg($voiceHostUser),
        escapeshellarg($voiceHost),
        escapeshellarg($remoteCommand)
    );

    exec($sshCommand, $outputLines, $status);
    // Log raw SSH command, status and output for debugging
    $logFile = __DIR__ . '/logs/doctor_ssh_output.log';
    $logEntry = "=== " . date("Y-m-d H:i:s") . " SSH COMMAND:\n" . $sshCommand . "\n";
    $logEntry .= "STATUS: " . $status . "\nOUTPUT:\n" . implode("\n", $outputLines) . "\n\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
    if ($status !== 0) {
        throw new RuntimeException(
            "❌ SSH/Postgres query failed with status {$status}. Output: " . implode("\n", $outputLines)
        );
    }

    $providers = [];
    foreach ($outputLines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $parts = explode("\t", $line, 3);
        if (count($parts) !== 3) {
            continue;
        }

        [$providerType, $availableProviders, $count] = $parts;
        $count = (int)$count;
        $rawNames = array_filter(array_map('trim', explode(',', $availableProviders)));
        $firstNames = array_values(array_filter(array_map('normalizeProviderFirstName', $rawNames)));

        $providers[$providerType] = [
            'count' => $count,
            'rawNames' => $rawNames,
            'firstNames' => $firstNames,
        ];
    }

    return $providers;
}

function buildProviderHtml(array $providers): string
{
    if (empty($providers)) {
        return "<p style='font-size: 16px; color: #2c3e50;'><strong>No available providers found.</strong></p>";
    }

    $html = '';
    foreach ($providers as $providerType => $info) {
        $label = htmlspecialchars(ucfirst(strtolower($providerType)));
        $providerNames = htmlspecialchars(implode(', ', $info['rawNames']));
        $html .= "<p style='font-size: 16px; color: #2c3e50;'><strong>{$label} ({$info['count']})</strong>: {$providerNames}</p>";
    }

    return $html;
}

try {
    $providerData = fetchOnlineProviders(
        $voice_host,
        $voice_host_user,
        $voice_host_password,
        $voice_sudo_user,
        $voice_db_port,
        $voice_db_name,
        $voice_db_user,
        $voice_db_password,
        $voice_psql_path
    );

    $currentCount = array_sum(array_column($providerData, 'count'));
    $previousCount = file_exists($stateFile) ? (int)trim(file_get_contents($stateFile)) : 10;
    file_put_contents($stateFile, $currentCount);

    // Initialize PHPMailer
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $env['MAIL_HOST'];
    $mail->Port       = $env['MAIL_PORT'];
    $mail->Username   = $env['MAIL_USERNAME'];
    $mail->Password   = $env['MAIL_PASSWORD'];
    $mail->SMTPAuth   = true;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->setFrom($env['MAIL_USERNAME'], $env['MAIL_SENDER_NAME']);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';

    // Recipients
    $recipients = [
        'derrick@afyacall.co.tz',
        'ivan.kakorozya@afyacall.co.tz',
        'bennet.kakorozya@afyacall.co.tz',
        'wvmgata@afyacall.co.tz',
    ];

    foreach ($recipients as $email) {
        $mail->addAddress($email);
    }

    $time = date("l, d M Y H:i:s");
    $subject = '';
    $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #2C3E50;'>AFYACALL Doctor Availability Alert</h2>
            <p style='font-size: 16px; color: #555;'>Hi Team,</p>";

    if ($currentCount <= 3) {
        $subject = '🚨 Doctor Status Alert';
        $body .= "<p style='font-size: 18px; color: #c0392b;'><strong>❌ Only {$currentCount} available provider(s)</strong> are online.</p>";
        $body .= "<p style='font-size: 16px; color: #2c3e50;'><strong>Available providers</strong></p>";
        $body .= buildProviderHtml($providerData);
        $body .= "
            <hr style='margin: 20px 0;'>
            <p style='font-size: 14px; color: #888;'>🕒 Alert triggered on <strong>{$time}</strong></p>
            <p style='font-size: 14px; color: #999;'>This is an automated notification from the Afyacall Cron Monitoring System.</p>
            <hr style='margin: 30px 0;'>
            <p style='font-size: 14px; color: #555;'>
                📩 <strong>Contact:</strong> <a href='mailto:derrick@afyacall.co.tz'>derrick@afyacall.co.tz</a><br>
                📞 <strong>Phone:</strong> <a href='tel:+255715083985'>+255 715 083 985</a>
            </p>
        </div>";

        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        sendSmsAlert($currentCount, false, $providerData);

        echo "🚨 Critical alert sent for {$currentCount} available provider(s).\n";
    } elseif ($previousCount <= 3 && $currentCount > 3) {
        $subject = '✅ Doctor Recovery Notice';
        $body .= "<p style='font-size: 18px; color: #2ecc71;'><strong>✅ Recovery:</strong> There are now <strong>{$currentCount} available providers</strong>.</p>";
        $body .= "<p style='font-size: 16px; color: #2c3e50;'><strong>Currently available providers</strong></p>";
        $body .= buildProviderHtml($providerData);
        $body .= "
            <hr style='margin: 20px 0;'>
            <p style='font-size: 14px; color: #888;'>🕒 Recovery recorded on <strong>{$time}</strong></p>
            <p style='font-size: 14px; color: #999;'>This is an automated notification from the Afyacall Cron Monitoring System.</p>
            <hr style='margin: 30px 0;'>
            <p style='font-size: 14px; color: #555;'>
                📩 <strong>Contact:</strong> <a href='mailto:derrick@afyacall.co.tz'>derrick@afyacall.co.tz</a><br>
                📞 <strong>Phone:</strong> <a href='tel:+255715083985'>+255 715 083 985</a>
            </p>
        </div>";

        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        sendSmsAlert($currentCount, true, $providerData);

        echo "✅ Recovery alert sent — {$currentCount} available providers online now.\n";
    } else {
        echo "ℹ️ No alert needed. Previous: {$previousCount} | Current: {$currentCount}\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
