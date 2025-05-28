#!/usr/bin/php -q
<?php
//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

//Load Composer's autoloader
require 'vendor/autoload.php';

date_default_timezone_set("Africa/Nairobi");

for($z=1; $z<=1; $z++)
{	

$day=date("Y_m_d", strtotime("-$z days"));

$filename='/root/cronjobs/email/storage/CDR_DAILY_'.date("Y_m_d", strtotime("-$z days")).'.csv';
//$filename='storage/DR_CALLS_'.date("Y_m_d", strtotime("-$z days")).'.csv';
//$filename='storage/DR_CALLS_'.date("Y_m_d").'.csv';
//Create an instance; passing `true` enables exceptions
$mail = new PHPMailer(true);

try {
    //Server settings
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
    $mail->isSMTP();                                            //Send using SMTP
    $mail->Host       = 'mail.afyacall.co.tz';                     //Set the SMTP server to send through
    $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
    $mail->Username   = 'reports@afyacall.co.tz';                     //SMTP username
    $mail->Password   = 'reports';                               //SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
    $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

    //Recipients
    $mail->setFrom('reports@afyacall.co.tz', 'Afyacall');
    $mail->addAddress('johnhaule@gmail.com', 'Afyacall');     //Add a recipient
    $mail->addAddress('kshalom@afyacall.co.tz');
    $mail->addAddress('sireri@afyacall.co.tz');
    $mail->addAddress('akmutoka@yahoo.com');
//    $mail->addAddress('derrick@afyacall.co.tz');
    $mail->addAddress('wvmgata@afyacall.co.tz');
    $mail->addAddress('smwamba@afyacall.co.tz');
    $mail->addReplyTo('test@inventions-technologies.com', 'Information');
  // $mail->addCC('cc@example.com');
   // $mail->addBCC('bcc@example.com');

    //Attachments
    $mail->addAttachment($filename);         //Add attachments
    //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name

    //Content
    $mail->isHTML(true);                                  //Set email format to HTML
    $mail->Subject = 'Afyacall CDR '.$day;
    $mail->Body    = 'This is the automated email for CDR';
//    $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

    $mail->send();
    echo 'Message has been sent';
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
}
