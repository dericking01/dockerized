#!/usr/bin/php -q
<?php

date_default_timezone_set("Africa/Dar_es_Salaam");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

// === Setup paths and logging ===
$logFile = __DIR__ . '/logs/debug_checkDisk.log';
$statusFile = __DIR__ . '/logs/disk_status.json';
$cronLogFile = '/var/log/cron_disk.log';

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Script started\n", FILE_APPEND);

// === Configuration ===
$mountPoint = "/root"; // the partition to monitor
$thresholds = [75, 85, 95, 98];

$toEmails = [
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
    'valencemuganda@gmail.com',
    'njunwawamavoko@gmail.com',
];

$fromEmail = 'alerts@afyacall.co.tz';
$fromName = 'AFYACALL DISK ALERTS';
$smtpHost = 'mail.afyacall.co.tz';
$smtpUser = 'alerts@afyacall.co.tz';
$smtpPassword = '321qaz!@#WSX';
$smtpPort = 465;

// === Load Previous Status
$prevStatus = file_exists($statusFile) ? json_decode(file_get_contents($statusFile), true) : [];
$prevLevel = $prevStatus['level'] ?? null;
$time = date('Y-m-d H:i:s');

// === Disk Usage Check ===
$output = [];
exec("df -h $mountPoint | awk 'NR==2 {print \$5}'", $output);
$usedPercent = (int)str_replace('%', '', $output[0] ?? 0);

$currentStatus = [
    'used_percent' => $usedPercent,
    'last_checked' => $time,
    'level' => $prevLevel // will update below
];

// Log disk usage
file_put_contents($cronLogFile, "[$time] Disk usage on $mountPoint: $usedPercent%\n", FILE_APPEND);

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

// === Determine New Level ===
$newLevel = null;
foreach ($thresholds as $t) {
    if ($usedPercent >= $t) {
        $newLevel = $t;
    }
}

// === Alert / Recovery Logic ===
if ($newLevel !== null && $newLevel !== $prevLevel) {
    // === ALERT (send once per threshold) ===
    $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #e74c3c;'>🚨 ALERT: Disk Usage Critical</h2>
            <p style='font-size: 16px; color: #555;'>Hi Team,</p>
            <p style='font-size: 16px;'>Disk usage on <strong>$mountPoint</strong> has reached <strong style='color:red;'>$usedPercent%</strong>.</p>
            <p style='font-size: 15px;'>Threshold triggered: <strong>$newLevel%</strong></p>
            <hr style='margin: 20px 0;'>
            <p style='font-size: 14px; color: #888;'>🕒 Alert triggered on <strong>$time</strong></p>
            <p style='font-size: 14px; color: #999;'>This is an automated notification from the Afyacall Monitoring System.</p>
            <hr style='margin: 30px 0;'>
            <p style='font-size: 14px; color: #555;'>
                📩 <strong>Contact:</strong> <a href='mailto:derrick@afyacall.co.tz'>derrick@afyacall.co.tz</a><br>
                📞 <strong>Phone:</strong> <a href='tel:+255715083985'>+255 715 083 985</a>
            </p>
        </div>";

    sendAlertEmail("🚨 ALERT: Disk Usage $usedPercent% on $mountPoint", $body, $toEmails, $fromName, $fromEmail, $smtpHost, $smtpUser, $smtpPassword, $smtpPort);

    $currentStatus['level'] = $newLevel;

} elseif ($prevLevel !== null && $usedPercent <= 70) {
    // === RECOVERY (only if below 70% and was in alert before) ===
    $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #27ae60;'>✅ Recovery Notice</h2>
            <p style='font-size: 16px;'>Disk usage on <strong>$mountPoint</strong> has dropped to <strong style='color:green;'>$usedPercent%</strong>.</p>
            <hr style='margin: 20px 0;'>
            <p style='font-size: 14px; color: #888;'>🕒 Recovery recorded on <strong>$time</strong></p>
            <p style='font-size: 14px; color: #999;'>This is an automated notification from the Afyacall Monitoring System.</p>
            <hr style='margin: 30px 0;'>
            <p style='font-size: 14px; color: #555;'>
                📩 <strong>Contact:</strong> <a href='mailto:derrick@afyacall.co.tz'>derrick@afyacall.co.tz</a><br>
                📞 <strong>Phone:</strong> <a href='tel:+255715083985'>+255 715 083 985</a>
            </p>
        </div>";

    sendAlertEmail("✅ RECOVERY: Disk Usage Normalized ($usedPercent%)", $body, $toEmails, $fromName, $fromEmail, $smtpHost, $smtpUser, $smtpPassword, $smtpPort);

    $currentStatus['level'] = null;
}

// === Save new status
file_put_contents($statusFile, json_encode($currentStatus, JSON_PRETTY_PRINT));

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Script finished\n", FILE_APPEND);
