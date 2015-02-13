<?php
require_once('config.inc');

if(!isset($_GET['pid']))	{exit;}
if(!preg_match('/[a-f0-9]/', $_GET['pid'])) {exit;}

$output_dir = $upload_dir . '/' . $_GET['pid'] . '/';

if(isset($_POST["op"]) && $_POST["op"] == "load")
{
	$files = scandir($dir);

	$ret= array();
	foreach($files as $file)
	{
		if($file == "." || $file == "..")
			continue;
		$ret[]=$file;

	}
}

echo json_encode($ret);
?>