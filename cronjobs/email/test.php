#!/usr/bin/php -q
<?php
$host = "192.168.1.49";
$username = "rpbx";
$password = "1nv3nt123";
$database = "AFYACALL2";
$db="asteriskcdrdb";
$port = "";

$drNumber="";

$disposition="";

$string='callingDate,msisdn,service,callStage,selectedAudio,duration,DrNumber,Status,callDirection'.PHP_EOL;
for($i=1; $i<=8; $i++)
{

$conn = mysqli_connect($host, $username, $password, $database);
$month="0".$i;
 $selectData="SELECT callingDate,msisdn,lang,callDirection,selectedAudio,duration,uniqueid FROM afya_cdr WHERE YEAR(callingDate)='2023' AND MONTH(callingDate)=$month";
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
  $uniqueid=$data[6];

  if($callDirection=="")
  $callDirection="INBOUND";
 $con = mysqli_connect($host, $username, $password, $db);
  if($service=="")
   $callStage="1-WelcomeMessage";
  elseif($service=="ivr")
   {
     $drNumber="";
   
//     $con = mysqli_connect($host, $username, $password, $db);
     $masterCDR="Select lastdata from cdr where uniqueid='$uniqueid' and lastdata like '%/var/lib/asterisk/agi-bin/AFYACALL/AUDIOS/IVR/%'";
     $queryMaster = mysqli_query($con,$masterCDR);

     if(mysqli_num_rows($queryMaster)>0)
     {
	     $rowivr = mysqli_fetch_row($queryMaster);
    $callStage="3-IvrContent";
	$lastdata=$rowivr[0];
	$selectedAudio=substr($lastdata,-5);
	}
    else
	{
    $callStage="2-IVRSelection";
	$selectedAudio="";
	}
   }
   elseif($service=="dr")
   {
     $selectedAudio="";
     $selectDR="select uniqueid,src,dst,dcontext,disposition,billsec from cdr where uniqueid='$uniqueid' and lastdata like '%SIP/kamailio/%' and dcontext='dr-queue'";
     $queryDR = mysqli_query($con,$selectDR);
	 
	if(mysqli_num_rows($queryDR)>0)
	 {   $rowdr = mysqli_fetch_row($queryDR);
	
		 $disposition=$rowdr[4];
     $callStage="3-DrCalling";
	 $drNumber=$rowdr[2];
	}
	else{	 
	 $callStage="2-DrOptions";
        $drNumber="";
	}
   }
  else{
   $callStage="99-UNKNOWN";
   $selectedAudio="";
   }

  $string=$string. $callingDate.','.$customer.','.$service.','.$callStage.','.$selectedAudio.','.$duration.','.$drNumber.','.$disposition.','.$callDirection.PHP_EOL;

  }

  $header=$month.",,,,,,,,".PHP_EOL;;
  $outputString=$header.$string;

  $filename='/root/cronjobs/email/storage/cdr/CDR_'.$filedate.'.csv';

  $file = fopen("$filename","w");
  fwrite($file,$string);
  fclose($file);




  }


?>
   

