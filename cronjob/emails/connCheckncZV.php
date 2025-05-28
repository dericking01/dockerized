#!/usr/bin/php -q
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

date_default_timezone_set("Africa/Nairobi");

// List of IP addresses and ports to check
$ipPorts = [
    '197.250.9.191:23000' => '<b>ICG</b>',
    '197.250.9.149:6202'  => '<b>Middleware</b>',
    '41.217.203.61:30010' => '<b>IPG</b>',
    '41.223.4.174:6695'   => '<b>SMSC</b>'
];

// Email settings
$toEmails = [
    'derrick@afyacall.co.tz',
    'julius.john@afyacall.co.tz',
    'john.haule@it.co.tz',
    'fmodamba@afyacall.co.tz',
    'wvmgata@afyacall.co.tz',
    'svmgata@afyacall.co.tz',
    'smwamba@afyacall.co.tz',
    'kshalom@afyacall.co.tz',
    'annette@afyacall.co.tz',
];
$fromEmail = 'reports@afyacall.co.tz';
$fromName = 'AFYACALL';
$smtpHost = 'mail.afyacall.co.tz';
$smtpUser = 'reports@afyacall.co.tz';
$smtpPassword = 'reports';
$smtpPort = 465;

function checkConnectivity($ip, $port) {
    $output = [];
    $return_var = 1; // Default to failed connection

    // Use netcat to check the connection
    exec("nc -zv $ip $port 2>&1", $output, $return_var);

    // Return false if connection fails, true otherwise
    return $return_var === 0;
}

function sendNotification($failedConnections) {
    global $toEmails, $fromEmail, $fromName, $smtpHost, $smtpUser, $smtpPassword, $smtpPort;

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // Disable verbose debug output
        $mail->isSMTP(); // Send using SMTP
        $mail->Host       = $smtpHost; // Set the SMTP server to send through
        $mail->SMTPAuth   = true; // Enable SMTP authentication
        $mail->Username   = $smtpUser; // SMTP username
        $mail->Password   = $smtpPassword; // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable implicit TLS encryption
        $mail->Port       = $smtpPort; // TCP port to connect to

        // Recipients
        $mail->setFrom($fromEmail, $fromName);
        foreach ($toEmails as $email) {
            $mail->addAddress($email);
        }

        // Content
        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = 'CONNECTIVITY ALERT !!!';
        $mail->Body    = 'Greetings Team,<br><br>'
                       . 'This is to notify you that the following IPs have failed to respond within the last 10 minutes:<br><br>'
                       . implode('<br>', $failedConnections)
                       . '<br><br>Please investigate the connectivity issues with these services to ensure continuous operations.<br><br>'
                       . 'If you have any questions or need further assistance, please don\'t hesitate to contact us.<br><br>'
                       . 'Best regards,<br>'
                       . 'Afyacall IT Support Team<br>'
                       . '<b>Email: derrick@afyacall.co.tz</b>';

        $mail->send();
        echo 'Notification email has been sent';
    } catch (Exception $e) {
        echo "Notification could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}


$failedConnections = [];

foreach ($ipPorts as $ipPort => $description) {
    list($ip, $port) = explode(':', $ipPort);

    $isConnectionFailed = true;

    // Check connectivity every minute for 10 minutes
    for ($i = 0; $i < 10; $i++) {
        if (checkConnectivity($ip, $port)) {
            $isConnectionFailed = false;
            break;
        }
        // Wait for 1 minute before retrying
        sleep(60);
    }

    if ($isConnectionFailed) {
        $failedConnections[] = "$description (IP: $ip, Port: $port)";
    }
}

if (!empty($failedConnections)) {
    sendNotification($failedConnections);
}
?>
