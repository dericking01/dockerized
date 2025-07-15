#!/usr/bin/php -q
<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';
// require 'vendor/autoload.php';

date_default_timezone_set("Africa/Dar_es_Salaam");

$host = '192.168.1.11';
$db = 'doctorproduction';
$user = 'prodafya';
$password = 'Afyacall@2021qazWSX';

$dsn = "mysql:host=$host;dbname=$db;charset=UTF8";

try {
    $pdo = new PDO($dsn, $user, $password);
    echo "Successfully connected to the database.\n";

    if ($pdo) {
        $sql = "SELECT COUNT(*) FROM users WHERE status=0";
        $statement = $pdo->query($sql);
        $row = $statement->fetch();
        $count = $row[0];

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'mail.afyacall.co.tz';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'alerts@afyacall.co.tz';
        $mail->Password   = '321qaz!@#WSX';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->setFrom('alerts@afyacall.co.tz', 'AFYACALL Doctor Alert');

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

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Subject = '🚨 Doctor Status Alert';

        $time = date("l, d M Y H:i:s");

        $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                <h2 style='color: #2C3E50;'>AFYACALL Doctor Availability Alert</h2>
                <p style='font-size: 16px; color: #555;'>Hi Team,</p>";

        if ($count == 2) {
            $body .= "<p style='font-size: 18px; color: #e67e22;'><strong>⚠️ Only 2 doctors</strong> are currently online.</p>";
        } elseif ($count == 1) {
            $body .= "<p style='font-size: 18px; color: #e74c3c;'><strong>⚠️ Only 1 doctor</strong> is currently online.</p>";
        } elseif ($count == 3) {
            $body .= "<p style='font-size: 18px; color: #e67e22;'><strong>⚠️ Only 3 doctors</strong> are currently online.</p>";
        }  elseif ($count == 0) {
            $body .= "<p style='font-size: 18px; color: #c0392b;'><strong>❌ No doctor</strong> is currently online.</p>";
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
            </div>
        ";

        if ($count <= 3) {
            $mail->Body = $body;
            $mail->send();
            echo "Message has been sent for $count doctors online.\n";
        } else {
            echo "✅ $count doctors are online — no alert sent.\n";
        }

    }
} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage();
} catch (Exception $e) {
    echo "Mailer Error: {$mail->ErrorInfo}";
}