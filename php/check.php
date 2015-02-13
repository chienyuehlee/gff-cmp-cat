<?php
$output_dir = "uploads/";
if(isset($_POST["op"]) && $_POST["op"] == "check" && isset($_POST['name']))
{
	$_POST['name'] = preg_replace('/\["(.+)"\]/','\1',$_POST['name']);
	echo var_dump($_POST);
	$fileName =$_POST['name'];
	//$fileName=str_replace("..",".",$fileName); //required. if somebody is trying parent folder files	
	$filePath = $output_dir. $fileName;
	
	//echo "Check File ".$filePath;
}

?>