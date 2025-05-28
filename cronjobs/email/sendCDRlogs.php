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

for($z=1; $z<=3; $z++)
{	

$month="0".$z;	
$filedate="2023_".$month;	
$filename='/root/cronjobs/email/storage/cdr/CDR_'.$filedate.'.zip';

//Create an instance; passing `true` enables exceptions
$mail = new PHPMailer(true);

try {
    //Server settings
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
    $mail->isSMTP();                                            //Send using SMTP
    $mail->Host       = 'mail.inventions-technologies.com';                     //Set the SMTP server to send through
    $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
    $mail->Username   = 'test@inventions-technologies.com';                     //SMTP username
    $mail->Password   = '123qaz!@#WSX';                               //SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
    $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

    //Recipients
    $mail->setFrom('test@inventions-technologies.com', 'Afyacll');
    $mail->addAddress('johnhaule@gmail.com', 'Afyacall');     //Add a recipient
//    $mail->addAddress('kshalom@afyacall.co.tz');
  $mail->addAddress('sireri@afyacall.co.tz');
   // $mail->addAddress('akmutoka@yahoo.com');
    $mail->addReplyTo('test@inventions-technologies.com', 'Information');
  // $mail->addCC('cc@example.com');
   // $mail->addBCC('bcc@example.com');

    //Attachments
    $mail->addAttachment($filename);         //Add attachments
    //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name

    //Content
    $mail->isHTML(true);                                  //Set email format to HTML
    $mail->Subject = 'Afyacall CDR Report 2023 '.$month;
    $mail->Body    = 'This is the automated email for CDR calls';
//    $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

    $mail->send();
    echo 'Message has been sent';
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
}
