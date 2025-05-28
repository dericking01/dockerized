#!/usr/bin/php -q
<?php
$host = "192.168.1.49";
$username = "rpbx";
$password = "1nv3nt123";
$database = "asteriskcdrdb";
$port = "";

$drArray = array(
    '0754775072' => 'Dr. Furaha Godwin',
    '0767196132' => 'Dr. David Mwilafi',
    '0759507610' => 'Dr. Victoria Lema',
    '0693680025' => 'Dr. Seraphine Mwankupili',
    '0743305282' => 'Dr. Matthias Mwita',
    '0684601358' => 'Dr. Peter Ngaiza',
    '0752820234' => 'Dr. Kelvin Robin',
    '0620134048' => 'Dr. Irene Mzokolo',
    '0769168841' => 'Dr. Irene Mzokolo',
    '0755887901' => 'Dr. Peter Ngaiza',
    '0754445850' => 'Dr. David Evod',
    '0756308638' => 'Dr. Chrisostom Sakibu',
    '0747616429' => 'Dr. Susan Ashery'
);

/*
$con = mysqli_connect($host, $username, $password, $database);

if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}

 */

for($z=1; $z<=38; $z++)
{	

$database="asteriskcdrdb";
$con = mysqli_connect($host, $username, $password, $database);

date_default_timezone_set("Africa/Nairobi");
$yesterday = date("Y-m-d", strtotime("-$z days"));
//$yesterday=date("2024-02-18");

echo $z;


$filename='/root/cronjobs/email/storage/DR_CALLS_'.$yesterday.'.csv';

//$filename='/home/dkamara/storage/DR_CALLS_'.date("Y_m_d", strtotime("-$z days")).'.csv';

//$filename='/root/cronjobs/email/storage/DR_CALLS_'.date("Y_m_d").'.csv';

$select = "SELECT calldate, CONCAT(SUBSTRING(src, 2, 10), 'XXX') AS caller, SUBSTRING(dst, 1, 10), disposition, billsec, duration-billsec FROM cdr WHERE dcontext='dr-queue' AND DATE(calldate)='$yesterday'";
$query = mysqli_query($con, $select);

if (!$query) {
    die("Query error: " . mysqli_error($con));
}

$string='Calldate,caller,Dr.Number,Dr.name,Status,Talktime,WaitingTime'.PHP_EOL;;

while ($data = mysqli_fetch_row($query)) {
    $calldate=$data[0];
	$caller=$data[1];
	$drNumber=$data[2];
	$status=$data[3];
	$taltime=$data[4];
	$waitingTime=$data[5];
	if (array_key_exists($drNumber, $drArray)) 

	$name=$drArray[$drNumber];
	else
		$name="Unknown";
	$string=$string.$calldate.','.$caller.','.$drNumber.','.$name.','.$status.','.$taltime.','.$waitingTime.PHP_EOL;
}

$database="AFYACALL2";
$conn = mysqli_connect($host, $username, $password, $database);



$selectLogout="select Msisdn,LoginDate,LogoutDate from adm_dr_session where logoutDate!='0000-00-00 00:00:00' and DATE(loginDate)='$yesterday'";
$querysession = mysqli_query($conn, $selectLogout);
$login='Name,phone,LoginDate,LogoutDate'.PHP_EOL;


while($i = mysqli_fetch_row($querysession))
{
  
  $phone=$i[0];
  $LoginDate=$i[1];
  $LogoutDate=$i[2];
  if (array_key_exists($phone, $drArray))

        $name=$drArray[$phone];
        else
                $name="Unknown";
  
  $login=$login.$name.','.$phone.','.$LoginDate.','.$LogoutDate.PHP_EOL;


}

$selectOnline="SELECT msisdn, LoginDate, LogoutDate FROM adm_dr_session WHERE (msisdn, LoginDate) IN (SELECT msisdn, MAX(LoginDate)   FROM adm_dr_session GROUP BY msisdn ) AND LogoutDate='0000-00-00 00:00:00' AND DATE(LoginDate)<='$yesterday' ORDER BY LoginDate DESC";
$queryOnline = mysqli_query($conn, $selectOnline);

while($j = mysqli_fetch_row($queryOnline))
{
  
  $phone=$j[0];
  $LoginDate=$j[1];
  // $LogoutDate=$j[2];
  $LogoutDate="Still Online";
  if (array_key_exists($phone, $drArray))

        $name=$drArray[$phone];
        else
                $name="Unknown";
  
  $login=$login.$name.','.$phone.','.$LoginDate.','.$LogoutDate.PHP_EOL;


}
$loginheader='Name,phone,LoginDate,LogoutDate'.PHP_EOL;
$spacebtn=',,,'.PHP_EOL.',,,'.PHP_EOL.',,,'.PHP_EOL;
$string=$string.$spacebtn.$login;

//echo $filename;

	 $file = fopen("$filename","w");
	 fwrite($file,$string);
	 fclose($file);
	  
	 mysqli_close($con);
	 mysqli_close($conn);
}
?>

