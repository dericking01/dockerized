<?php
$sum=13000;

//if($sendStatus=="yes")
{	

$command='curl -X POST -d "key=Uncxy7VvNcUjdfjLLdkfjdsdssxcfXX&sum=$sum" http://192.168.1.49/callfile/revenue.php';
$call_output = shell_exec($command);

}
?>
