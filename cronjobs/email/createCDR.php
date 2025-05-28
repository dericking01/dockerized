#!/usr/bin/php -q
<?php
$host = "192.168.1.49";
$username = "rpbx";
$password = "1nv3nt123";
$database = "AFYACALL2";
$port = "";

$conn = mysqli_connect($host, $username, $password, $database);



$string='callingDate,msisdn,service,callStage,selectedAudio,duration,callDirection'.PHP_EOL;
for($i=1; $i<=8; $i++)
{
$month="0".$i;
 $selectData="SELECT callingDate,msisdn,lang,callDirection,selectedAudio,duration FROM afya_cdr WHERE YEAR(callingDate)='2023' AND MONTH(callingDate)=$month";
$query = mysqli_query($conn,$selectData);

 
 
 $filedate="2023_".$month;
 

while($data=mysqli_fetch_row($query))
{
  

  $callingDate=$data[0];
  $customer=substr($data[1],0,9)."XXX";
  $service=$data[2];
  $callDirection=$data[3];
  $selectedAudio=$data[4];
  $duration=$data[5];

  if($callDirection=="")
  $callDirection="INBOUND";	  

  if($service=="") 
   $callStage="1-WelcomeMessage";
  elseif($service=="ivr")
   {
     if($selectedAudio=="")
       $callStage="2-IvrSelection";
	 else
      $callStage="3-IvrContent"; 
   }
   elseif($service=="dr")
   {
     $callStage="2-DrCalling";
     $selectedAudio="N/A";	 
   }
  else{
   $callStage="99-UNKNOWN";
   $selectedAudio="N/A";
   }
   
  $string=$string. $callingDate.','.$customer.','.$service.','.$callStage.','.$selectedAudio.','.$duration.','.$callDirection.PHP_EOL;
   
  }
  
  $header=$month.",,,,,,".PHP_EOL;;
  $outputString=$header.$string;
     
  $filename='/root/cronjobs/email/storage/cdr/CDR_'.$filedate.'.csv';

  $file = fopen("$filename","w");
  fwrite($file,$string);
  fclose($file);

  
  
  
  }


  
   




?>
