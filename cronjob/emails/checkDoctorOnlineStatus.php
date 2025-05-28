#!/usr/bin/php -q
<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

date_default_timezone_set("Africa/Nairobi");

$host = '192.168.1.11';
$db = 'doctorproduction';
$user = 'prodafya';
$password = 'Afyacall@2021qazWSX';

$dsn = "mysql:host=$host;dbname=$db;charset=UTF8";

try {
    $pdo = new PDO($dsn, $user, $password);
        // Confirm successful connection
        echo "Successfully connected to the database.\n";

    if ($pdo) {
        // Check the number of users with status=0
        $sql = "SELECT COUNT(*) FROM users WHERE status=0";
        $statement = $pdo->query($sql);
        $row = $statement->fetch();
        $count = $row[0];

        // Initialize PHPMailer
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'mail.afyacall.co.tz';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'reports@afyacall.co.tz';
        $mail->Password   = 'reports';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->setFrom('reports@afyacall.co.tz', 'AFYACALL');
//        $mail->addAddress('derrick@afyacall.co.tz');
        $mail->addAddress('svmgata@afyacall.co.tz');
        $mail->addAddress('salhat.masunga@afyacall.co.tz');
        $mail->addAddress('kshalom@afyacall.co.tz');
        $mail->addAddress('annette@afyacall.co.tz');
        $mail->addAddress('fmodamba@afyacall.co.tz');
        $mail->addAddress('smwamba@afyacall.co.tz');
        $mail->addAddress('julius.john@afyacall.co.tz');
        $mail->addAddress('johnhaule@gmail.com',);
        $mail->addAddress('wvmgata@afyacall.co.tz');
        $mail->isHTML(true);

        // Send alerts based on the count
        if ($count == 2) {
            $mail->Subject = 'Doctor Status Alert';
            $mail->Body    = 'Only 2 doctors are online now.';
            $mail->send();
            echo 'Message has been sent: Only 2 doctors are online now';
        } elseif ($count == 1) {
            $mail->Subject = 'Doctor Status Alert';
            $mail->Body    = 'Only 1 doctor is online now.';
            $mail->send();
            echo 'Message has been sent: Only 1 doctors is online now';
        }  elseif ($count == 0) {
            $mail->Subject = 'Doctor Status Alert';
            $mail->Body    = 'There is no doctor online.';

            $mail->send();
            echo 'Message has been sent: There is no any doctor online';
        }
    }
} catch (PDOException $e) {
    echo $e->getMessage();
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
?>
