#!/usr/bin/php -q
<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

$env = parse_ini_file(__DIR__ . '/../.env');


if (!$env) {
    die("❌ Failed to load environment variables from .env\n");
}

date_default_timezone_set("Africa/Dar_es_Salaam");

$host     = $env['DB_HOST'];
$db       = $env['DB_NAME'];
$user     = $env['DB_USERNAME'];
$password = $env['DB_PASSWORD'];

$dsn = "mysql:host=$host;dbname=$db;charset=UTF8";
$stateFile = __DIR__ . '/logs/doctor_status_state.txt'; // Store previous doctor count

try {
    $pdo = new PDO($dsn, $user, $password);
    echo "✅ Successfully connected to the database.\n";

    // Fetch current doctor count
    $statement = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 0");
    $currentCount = (int)$statement->fetchColumn();

    // Read previous state (default to 10)
    $previousCount = file_exists($stateFile) ? (int)trim(file_get_contents($stateFile)) : 10;

    // Update the state file
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
        'svmgata@afyacall.co.tz',
        'salhat.masunga@afyacall.co.tz',
        'm.macha@afyacall.co.tz',
        'kshalom@afyacall.co.tz',
        'annette@afyacall.co.tz',
        'fmodamba@afyacall.co.tz',
        'smwamba@afyacall.co.tz',
        'julius.john@afyacall.co.tz',
        'johnhaule@gmail.com',
        'wvmgata@afyacall.co.tz',
        'modamba@gmail.com',
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

    // 🔥 Critical Alert: count <= 3
    if ($currentCount <= 3) {
        $subject = '🚨 Doctor Status Alert';

        if ($currentCount == 0) {
            $body .= "<p style='font-size: 18px; color: #c0392b;'><strong>❌ No doctor</strong> is currently online.</p>";
        } elseif ($currentCount == 1) {
            $body .= "<p style='font-size: 18px; color: #e74c3c;'><strong>⚠️ Only 1 doctor</strong> is currently online.</p>";
        } elseif ($currentCount == 2) {
            $body .= "<p style='font-size: 18px; color: #e67e22;'><strong>⚠️ Only 2 doctors</strong> are currently online.</p>";
        } elseif ($currentCount == 3) {
            $body .= "<p style='font-size: 18px; color: #e67e22;'><strong>⚠️ Only 3 doctors</strong> are currently online.</p>";
        }

        $body .= "
            <hr style='margin: 20px 0;'>
            <p style='font-size: 14px; color: #888;'>🕒 Alert triggered on <strong>$time</strong></p>
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
        echo "🚨 Critical alert sent for $currentCount doctor(s) online.\n";
    }

    // ✅ Recovery Alert: Transition from critical (<=3) → normal (>3)
    elseif ($previousCount <= 3 && $currentCount > 3) {
        $subject = '✅ Doctor Recovery Notice';

        // Get list of current online doctors
        $onlineDoctors = [];
        $doctorQuery = $pdo->query("SELECT name FROM users WHERE status = 0 ORDER BY name ASC LIMIT 50");
        while ($user = $doctorQuery->fetch(PDO::FETCH_ASSOC)) {
            $onlineDoctors[] = htmlspecialchars($user['name']);
        }

        $doctorListHtml = "<ul style='padding-left: 20px; color: #2c3e50;'>";
        foreach ($onlineDoctors as $docName) {
            $doctorListHtml .= "<li>$docName</li>";
        }
        $doctorListHtml .= "</ul>";

        $body .= "
            <p style='font-size: 18px; color: #2ecc71;'><strong>✅ Recovery:</strong> There are now <strong>$currentCount doctors</strong> online.</p>
            <p style='font-size: 16px;'>🧑‍⚕️ <strong>Currently Online Doctors:</strong></p>
            $doctorListHtml
            <hr style='margin: 20px 0;'>
            <p style='font-size: 14px; color: #888;'>🕒 Recovery recorded on <strong>$time</strong></p>
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
        echo "✅ Recovery alert sent — $currentCount doctors online now.\n";
    }

    else {
        echo "ℹ️ No alert needed. Previous: $previousCount | Current: $currentCount\n";
    }

} catch (PDOException $e) {
    echo "❌ DB Error: " . $e->getMessage();
} catch (Exception $e) {
    echo "❌ Mailer Error: {$mail->ErrorInfo}";
}