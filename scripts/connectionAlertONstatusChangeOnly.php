#!/usr/bin/php -q
<?php

date_default_timezone_set("Africa/Dar_es_Salaam");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

// === Setup paths and logging ===
$logFile = __DIR__ . '/logs/debug_checkConnection.log';
$statusFile = __DIR__ . '/logs/status.json';
$cronLogFile = '/var/log/cron_connection.log';

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Script started\n", FILE_APPEND);

// === Configuration ===
$ipPorts = [
    '197.250.9.191:23000' => 'ICG',
    '197.250.9.149:6202'  => 'Middleware',
    '41.217.203.61:30010' => 'IPG',
    '41.223.4.174:6695'   => 'SMSC'
];

$toEmails = [
    'derrick@afyacall.co.tz',
    // 'svmgata@afyacall.co.tz',
    // 'salhat.masunga@afyacall.co.tz',
    // 'm.macha@afyacall.co.tz',
    // 'kshalom@afyacall.co.tz',
    // 'annette@afyacall.co.tz',
    // 'fmodamba@afyacall.co.tz',
    // 'smwamba@afyacall.co.tz',
    // 'julius.john@afyacall.co.tz',
    // 'johnhaule@gmail.com',
    // 'wvmgata@afyacall.co.tz',
    // 'modamba@gmail.com',
];

$fromEmail = 'alerts@afyacall.co.tz';
$fromName = 'AFYACALL CONNECTIVITY ALERTS';
$smtpHost = 'mail.afyacall.co.tz';
$smtpUser = 'alerts@afyacall.co.tz';
$smtpPassword = '321qaz!@#WSX';
$smtpPort = 465;

// === Load Previous Status
$prevStatus = file_exists($statusFile) ? json_decode(file_get_contents($statusFile), true) : [];
$currentStatus = [];
$failed = [];
$time = date('Y-m-d H:i:s');

// === Port Checking ===
foreach ($ipPorts as $ipPort => $label) {
    list($ip, $port) = explode(':', $ipPort);

    $output = [];
    exec("timeout 3 nc -zv $ip $port 2>&1", $output, $returnVar);

    $isUp = ($returnVar === 0);
    $statusMsg = $isUp ? 'UP' : 'DOWN';

    // Store both label and IP:port status
    $currentStatus[$label] = $statusMsg;
    $currentStatus[$ipPort] = [
        'status' => $statusMsg,
        'last_checked' => $time
    ];

    // Log status
    file_put_contents($cronLogFile, "[$time] $label ($ip:$port) - $statusMsg\n", FILE_APPEND);

    // Track failures
    if (!$isUp) {
        $failed[$ipPort] = $label;
    }
}

// === Save new status
file_put_contents($statusFile, json_encode($currentStatus, JSON_PRETTY_PRINT));

// === Email Function ===
function sendAlertEmail($subject, $bodyHtml, $recipients, $fromName, $fromEmail, $smtpHost, $smtpUser, $smtpPassword, $smtpPort)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $smtpPort;

        $mail->setFrom($fromEmail, $fromName);
        foreach ($recipients as $email) {
            $mail->addAddress($email);
        }

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;

        $mail->send();
    } catch (Exception $e) {
        file_put_contents($GLOBALS['logFile'], "[" . date('Y-m-d H:i:s') . "] Email error: {$mail->ErrorInfo}\n", FILE_APPEND);
    }
}

// === Check & Send Alerts ===
if (!empty($failed)) {
    // DOWN ALERT
    $body =  "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #2C3E50;'>🚨 ALERT: Connectivity Issues Detected</h2> 
            <p style='font-size: 16px; color: #555;'>Hi Team,</p>
            <p style='font-size: 16px;'>The following services are currently <strong style='color:red;'>DOWN</strong>:</p>
            <ul style='color: red; font-size: 15px;'>";

    foreach ($failed as $ipPort => $label) {
        $body .= "<li><strong>$label</strong> ($ipPort)</li>";
    }

    $body .=  "
            </ul>
            <hr style='margin: 20px 0;'>
            <p style='font-size: 14px; color: #888;'>🕒 Alert triggered on <strong>$time</strong></p>
            <p style='font-size: 14px; color: #999;'>This is an automated notification from the Afyacall Cron Monitoring System.</p>
            <hr style='margin: 30px 0;'>
            <p style='font-size: 14px; color: #555;'>
                📩 <strong>Contact:</strong> <a href='mailto:derrick@afyacall.co.tz'>derrick@afyacall.co.tz</a><br>
                📞 <strong>Phone:</strong> <a href='tel:+255715083985'>+255 715 083 985</a>
            </p>
            </div>";

    sendAlertEmail("🚨 ALERT: Connectivity Issues Detected", $body, $toEmails, $fromName, $fromEmail, $smtpHost, $smtpUser, $smtpPassword, $smtpPort);

} else {
    // RECOVERY
    $wasDown = false;
    foreach ($ipPorts as $ipPort => $label) {
        if (isset($prevStatus[$label]) && $prevStatus[$label] === 'DOWN') {
            $wasDown = true;
            break;
        }
    }

    if ($wasDown) {
        $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                <h2 style='color: #27ae60;'>✅ Recovery Notice</h2>
                <p style='font-size: 16px;'>All monitored services are now <strong style='color:green;'>UP</strong>:</p>
                <ul style='color: green; font-size: 15px;'>";

        foreach ($ipPorts as $ipPort => $label) {
            $body .= "<li><strong>$label</strong> ($ipPort)</li>";
        }

        $body .=  "
            </ul>
            <hr style='margin: 20px 0;'>
            <p style='font-size: 14px; color: #888;'>🕒 Recovery recorded on <strong>$time</strong></p>
            <p style='font-size: 14px; color: #999;'>This is an automated notification from the Afyacall Cron Monitoring System.</p>
            <hr style='margin: 30px 0;'>
            <p style='font-size: 14px; color: #555;'>
                📩 <strong>Contact:</strong> <a href='mailto:derrick@afyacall.co.tz'>derrick@afyacall.co.tz</a><br>
                📞 <strong>Phone:</strong> <a href='tel:+255715083985'>+255 715 083 985</a>
            </p>
            </div>";

        sendAlertEmail("✅ RECOVERY: All Systems Operational", $body, $toEmails, $fromName, $fromEmail, $smtpHost, $smtpUser, $smtpPassword, $smtpPort);
    }
}

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Script finished\n", FILE_APPEND);
