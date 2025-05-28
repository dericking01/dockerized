#!/usr/bin/php -q
<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

date_default_timezone_set("Africa/Nairobi");

$host = '192.168.1.11';
$db = 'afyacallproduction';
$user = 'revenue';
$password = 'AfadBpA22w0d1cQvV';

$dsn = "mysql:host=$host;dbname=$db;charset=UTF8";

try {
	$pdo = new PDO($dsn, $user, $password);

	if ($pdo) {
		$sql = "SELECT SUM(amount_IN) FROM transactions WHERE DATE(created_at)=CURDATE() AND status=1";
		$statement = $pdo->query($sql);
		$row = $statement->fetch();
	        $amount=$row[0];	
			
		
	}
} catch (PDOException $e) {
	echo $e->getMessage();
	
}


$hr=date("H");

if($hr==13)
	$minimum=500000;
elseif($hr==16)
	$minimum=500000;
elseif($hr==23)
	$minimum=500000;
else
	$minimum=510000;





if($amount<$minimum)
{
$mail = new PHPMailer(true);

try {
    //Server settings
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
    $mail->isSMTP();                                            //Send using SMTP
    $mail->Host       = 'mail.afyacall.co.tz';                     //Set the SMTP server to send through
    $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
    $mail->Username   = 'alerts@afyacall.co.tz';                     //SMTP username
    $mail->Password   = '321qaz!@#WSX';                               //SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
    $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

    //Recipients
    $mail->setFrom('alerts@afyacall.co.tz', 'Afyacall');
    $mail->addAddress('johnhaule@gmail.com', 'Afyacall');     //Add a recipient
   
    $mail->addAddress('kshalom@afyacall.co.tz');
    $mail->addAddress('svmgata@afyacall.co.tz');
    $mail->addAddress('sireri@afyacall.co.tz');
    $mail->addAddress('derrick@afyacall.co.tz');
    $mail->addAddress('smwamba@afyacall.co.tz');
    $mail->addAddress('fmodamba@afyacall.co.tz');
    $mail->addAddress('annette@afyacall.co.tz');
    $mail->addAddress('r.adolf@afyacall.co.tz');
    $mail->addAddress('julius.john@afyacall.co.tz');
    //$mail->addAddress('akmutoka@yahoo.com');
    $mail->addAddress('wvmgata@afyacall.co.tz');
    
    $mail->addReplyTo('test@inventions-technologies.com', 'Information');
  // $mail->addCC('cc@example.com');
   // $mail->addBCC('bcc@example.com');

    //Attachments
 //   $mail->addAttachment($filename);         //Add attachments
    //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name

    //Content
    $mail->isHTML(true);                                  //Set email format to HTML
    $mail->Subject = 'Revenue Alert '.$amount;
    $mail->Body    = 'The revenue is below minimum ';
//    $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

    $mail->send();
    echo 'Message has been sent';
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
}
