#!/usr/bin/php -q
<?php
$host = "192.168.1.49";
$username = "rpbx";
$password = "1nv3nt123";
$database = "AFYACALL2";
$port = "";


$conn = mysqli_connect($host, $username, $password, $database);



$string='callingDate,msisdn,service,language,selectedAudio,paymentMethod,paymentStatus,duration,callingStage,callDir'.PHP_EOL;
for ($z = 1; $z <=1; $z++) {

date_default_timezone_set("Africa/Nairobi");
$yesterday = date("Y-m-d", strtotime("-$z days"));

$filename='/root/cronjobs/email/storage/CDR_DAILY_'.date("Y_m_d", strtotime("-$z days")).'.csv';

$selectCDR="SELECT msisdn,lang,selectedAudio,paymentMethod,paymentStatus,duration,service,callingStage,callDir,callingDate FROM afya_cdr_logs WHERE DATE(callingDate)='$yesterday'";
$queryCDR= mysqli_query($conn, $selectCDR);


while($data=mysqli_fetch_row($queryCDR))
{
$msisdn=$data[0];
$lang=$data[1];
$selectedAudio=$data[2];
$paymentMethod=$data[3];
$paymentStatus=$data[4];
$duration=$data[5];
$service=$data[6];
$callingStage=$data[7];
$callDir=$data[8];
$callingDate=$data[9];

if($callDir!="INBOUND")
$callDir="OBD";

$string=$string.$callingDate.','.$msisdn.','.$service.','.$lang.','.$selectedAudio.','.$paymentMethod.','.$paymentStatus.','.$duration.','.$callingStage.','.$callDir.PHP_EOL;

}

$file = fopen("$filename","w");
fwrite($file,$string);
fclose($file);

}


?>


