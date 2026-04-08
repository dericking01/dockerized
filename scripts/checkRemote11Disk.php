#!/usr/bin/php -q
<?php

date_default_timezone_set("Africa/Dar_es_Salaam");

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/../vendor/autoload.php';

$logFile = __DIR__ . '/logs/debug_checkRemote11Disk.log';
$statusFile = __DIR__ . '/logs/disk_status_remote11.json';
$cronLogFile = '/var/log/cron_disk_remote11.log';

file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] Script started\n", FILE_APPEND);

function loadEnvFile($path)
{
    if (!file_exists($path)) {
        return [];
    }

    $values = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        $firstChar = substr($value, 0, 1);
        $lastChar = substr($value, -1);
        if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
            $value = substr($value, 1, -1);
        }

        $values[$key] = $value;
    }

    return $values;
}

function sendAlertEmail($subject, $bodyHtml, $recipients, $fromName, $fromEmail, $smtpHost, $smtpUser, $smtpPassword, $smtpPort)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $smtpPort;

        $mail->setFrom($fromEmail, $fromName);
        foreach ($recipients as $email) {
            $mail->addAddress($email);
        }

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;

        $mail->send();
    } catch (Exception $e) {
        file_put_contents($GLOBALS['logFile'], '[' . date('Y-m-d H:i:s') . "] Email error: {$mail->ErrorInfo}\n", FILE_APPEND);
    }
}

function getRemoteDiskUsage($remoteHost, $remoteUser, $remotePassword, $mountPoint, &$commandOutput = null, &$exitCode = null)
{
    $remoteCommand = 'df --output=pcent ' . escapeshellarg($mountPoint) . ' | tail -1';
    $command = 'sshpass -p ' . escapeshellarg($remotePassword)
        . ' ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 '
        . escapeshellarg($remoteUser . '@' . $remoteHost)
        . ' ' . escapeshellarg($remoteCommand);

    $output = [];
    exec($command, $output, $resultCode);

    $commandOutput = $output;
    $exitCode = $resultCode;

    if ($resultCode !== 0) {
        return null;
    }

    $usedPercent = (int) str_replace('%', '', trim($output[0] ?? ''));
    return $usedPercent > 0 ? $usedPercent : 0;
}

$env = loadEnvFile(__DIR__ . '/../.env');

$remoteHost = $env['PROD_DB_HOST'] ?? null;
$remoteUser = $env['PROD_DB_USERNAME'] ?? null;
$remotePassword = $env['PROD_DB_PASSWORD'] ?? null;

if ($remoteHost === null || $remoteUser === null || $remotePassword === null) {
    file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] Missing PROD_DB_* values in .env\n", FILE_APPEND);
    exit(1);
}

$mountPoints = ['/root', '/var'];
$thresholds = [75, 85, 95, 98];

$toEmails = [
    'derrick@afyacall.co.tz',
    'svmgata@afyacall.co.tz',
    'salhat.masunga@afyacall.co.tz',
    'fmodamba@afyacall.co.tz',
    'smwamba@afyacall.co.tz',
    'ivan.kakorozya@afyacall.co.tz',
    'bennet.kakorozya@afyacall.co.tz',
    'wvmgata@afyacall.co.tz',
    'modamba@gmail.com',
    'valencemuganda@gmail.com',
    'njunwawamavoko@gmail.com',
];

$fromEmail = $env['MAIL_USERNAME'] ?? 'alerts@afyacall.co.tz';
$fromName = 'AFYACALL REMOTE .11 DISK ALERTS';
$smtpHost = $env['MAIL_HOST'] ?? 'mail.afyacall.co.tz';
$smtpUser = $env['MAIL_USERNAME'] ?? 'alerts@afyacall.co.tz';
$smtpPassword = $env['MAIL_PASSWORD'] ?? '';
$smtpPort = isset($env['MAIL_PORT']) ? (int) $env['MAIL_PORT'] : 465;

$prevStatus = file_exists($statusFile) ? json_decode(file_get_contents($statusFile), true) : [];
$currentStatus = [];
$alerts = [];
$recoveries = [];
$time = date('Y-m-d H:i:s');

foreach ($mountPoints as $mountPoint) {
    $previousMountStatus = $prevStatus[$mountPoint] ?? [];
    $prevLevel = $previousMountStatus['level'] ?? null;

    $commandOutput = [];
    $exitCode = 0;
    $usedPercent = getRemoteDiskUsage($remoteHost, $remoteUser, $remotePassword, $mountPoint, $commandOutput, $exitCode);

    if ($usedPercent === null) {
        $currentStatus[$mountPoint] = [
            'used_percent' => $previousMountStatus['used_percent'] ?? null,
            'last_checked' => $time,
            'level' => $prevLevel,
            'last_error' => 'Remote command failed',
        ];

        file_put_contents(
            $logFile,
            '[' . $time . '] Failed to read disk usage for ' . $mountPoint . ' on ' . $remoteHost . '. Exit code: ' . $exitCode . '. Output: ' . implode(' | ', $commandOutput) . "\n",
            FILE_APPEND
        );
        continue;
    }

    $newLevel = null;
    foreach ($thresholds as $threshold) {
        if ($usedPercent >= $threshold) {
            $newLevel = $threshold;
        }
    }

    $currentStatus[$mountPoint] = [
        'used_percent' => $usedPercent,
        'last_checked' => $time,
        'level' => $prevLevel,
        'last_error' => null,
    ];

    file_put_contents(
        $cronLogFile,
        '[' . $time . '] Remote disk usage on ' . $remoteHost . ' ' . $mountPoint . ': ' . $usedPercent . "%\n",
        FILE_APPEND
    );

    if ($newLevel !== null && $newLevel !== $prevLevel) {
        $alerts[] = [
            'mount_point' => $mountPoint,
            'used_percent' => $usedPercent,
            'level' => $newLevel,
        ];
        $currentStatus[$mountPoint]['level'] = $newLevel;
        continue;
    }

    if ($prevLevel !== null && $usedPercent <= 70) {
        $recoveries[] = [
            'mount_point' => $mountPoint,
            'used_percent' => $usedPercent,
        ];
        $currentStatus[$mountPoint]['level'] = null;
    }
}

if (!empty($alerts)) {
    $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #e74c3c;'>🚨 ALERT: Remote Disk Usage Critical</h2>
            <p style='font-size: 16px; color: #555;'>Hi Team,</p>
            <p style='font-size: 16px;'>The following mount points on .11 have crossed configured thresholds:</p>
            <ul style='font-size: 15px; color: #555;'>";

    foreach ($alerts as $alert) {
        $body .= '<li><strong>' . $alert['mount_point'] . '</strong> reached <strong style="color:red;">' . $alert['used_percent'] . '%</strong> (threshold ' . $alert['level'] . '%)</li>';
    }

    $body .= "
            </ul>
            <hr style='margin: 20px 0;'>
            <p style='font-size: 14px; color: #888;'>🕒 Alert triggered on <strong>$time</strong></p>
            <p style='font-size: 14px; color: #999;'>This is an automated notification from the Afyacall Monitoring System.</p>
            <hr style='margin: 30px 0;'>
            <p style='font-size: 14px; color: #555;'>
                📩 <strong>Contact:</strong> <a href='mailto:derrick@afyacall.co.tz'>derrick@afyacall.co.tz</a><br>
                📞 <strong>Phone:</strong> <a href='tel:+255715083985'>+255 715 083 985</a>
            </p>
        </div>";

    sendAlertEmail(
        '🚨 REMOTE .11 ALERT: Disk usage threshold crossed',
        $body,
        $toEmails,
        $fromName,
        $fromEmail,
        $smtpHost,
        $smtpUser,
        $smtpPassword,
        $smtpPort
    );
}

if (!empty($recoveries)) {
    $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #27ae60;'>✅ Recovery Notice</h2>
            <p style='font-size: 16px;'>The following mount points on .11 have returned to normal usage:</p>
            <ul style='font-size: 15px; color: #555;'>";

    foreach ($recoveries as $recovery) {
        $body .= '<li><strong>' . $recovery['mount_point'] . '</strong> is now at <strong style="color:green;">' . $recovery['used_percent'] . '%</strong></li>';
    }

    $body .= "
            </ul>
            <hr style='margin: 20px 0;'>
            <p style='font-size: 14px; color: #888;'>🕒 Recovery recorded on <strong>$time</strong></p>
            <p style='font-size: 14px; color: #999;'>This is an automated notification from the Afyacall Monitoring System.</p>
            <hr style='margin: 30px 0;'>
            <p style='font-size: 14px; color: #555;'>
                📩 <strong>Contact:</strong> <a href='mailto:derrick@afyacall.co.tz'>derrick@afyacall.co.tz</a><br>
                📞 <strong>Phone:</strong> <a href='tel:+255715083985'>+255 715 083 985</a>
            </p>
        </div>";

    sendAlertEmail(
        '✅ REMOTE .11 RECOVERY: Disk usage normalized',
        $body,
        $toEmails,
        $fromName,
        $fromEmail,
        $smtpHost,
        $smtpUser,
        $smtpPassword,
        $smtpPort
    );
}

file_put_contents($statusFile, json_encode($currentStatus, JSON_PRETTY_PRINT));
file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] Script finished\n", FILE_APPEND);
