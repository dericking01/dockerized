#!/usr/bin/php -q
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

date_default_timezone_set("Africa/Nairobi");

// List of IP addresses and ports to check
$ipPorts = [
    '197.250.9.191:23000' => 'ICG',
    '197.250.9.149:6202'  => 'Middleware Vodacom',
    '41.217.203.61:30010' => '<b>IPG</b>',
    '41.223.4.174:6695'   => 'SMSC'
];

// Email settings
$toEmails = [
    'derricking01@gmail.com',
    'derrick@afyacall.co.tz',
];
$fromEmail = 'reports@afyacall.co.tz';
$fromName = 'Afyacall';
$smtpHost = 'mail.afyacall.co.tz';
$smtpUser = 'reports@afyacall.co.tz';
$smtpPassword = 'reports';
$smtpPort = 465;

function checkConnectivity($ip, $port) {
    $output = [];
    $return_var = 1; // Default to failed connection

    // Use netcat to check the connection
    exec("nc -zv $ip $port 2>&1", $output, $return_var);

    // Return true if connection is successful, false otherwise
    return $return_var === 0;
}

function sendNotification($successfulConnections) {
    global $toEmails, $fromEmail, $fromName, $smtpHost, $smtpUser, $smtpPassword, $smtpPort;

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF;                       // Disable verbose debug output
        $mail->isSMTP();                                          // Send using SMTP
        $mail->Host       = $smtpHost;                            // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                 // Enable SMTP authentication
        $mail->Username   = $smtpUser;                            // SMTP username
        $mail->Password   = $smtpPassword;                        // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;          // Enable implicit TLS encryption
        $mail->Port       = $smtpPort;                            // TCP port to connect to

        // Recipients
        $mail->setFrom($fromEmail, $fromName);
        foreach ($toEmails as $email) {
            $mail->addAddress($email);
        }

        // Content
        $mail->isHTML(true);                                      // Set email format to HTML
        $mail->Subject = '<b>IP Connectivity Alert</b>';
        $mail->Body     = 'Dear Team,<br><br>'
        . 'This is to notify you that the following IPs have SUCCEEDED<br><br>'
        . implode('<br>', $successfulConnections)
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

$successfulConnections = [];

foreach ($ipPorts as $ipPort => $description) {
    list($ip, $port) = explode(':', $ipPort);
    if (checkConnectivity($ip, $port)) {
        $successfulConnections[] = "$description (IP: $ip, Port: $port)";
    }
}

if (!empty($successfulConnections)) {
    sendNotification($successfulConnections);
}
?>
