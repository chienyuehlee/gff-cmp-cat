<?php
require_once('config.inc');

if(!isset($_GET['pid']))	{exit;}
if(!preg_match('/[a-f0-9]/', $_GET['pid'])) {exit;}

$output_dir = $upload_dir . '/' . $_GET['pid'] . '/';
if(isset($_POST["op"]) && $_POST["op"] == "delete" && isset($_POST['name']))
{
	$fileName = preg_replace('/\["(.+)"\]/','\1',$_POST['name']);
	$fileName = str_replace("..",".",$fileName); //required. if somebody is trying parent folder files	
	$filePath = $output_dir . $fileName;
	if (file_exists($filePath)) 
	{
        unlink($filePath);
		echo "Delete $filePath";
    }
	else
	{
		echo "Not delete $filePath";
	}
	//echo "Deleted File ".$fileName."<br>";
}

?>