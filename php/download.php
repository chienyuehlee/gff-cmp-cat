<?
require_once('config.inc');

if(!isset($_GET['pid']))	{exit;}
if(!preg_match('/[a-f0-9]/', $_GET['pid'])) {exit;}

$output_dir = $upload_dir . '/' . $_GET['pid'] . '/';

if(isset($_GET['filename']))
{
	$fileName = 'gff-cmp-cat_'.$_GET['filename'].'.zip';
	$fileName = str_replace("..",".",$fileName); //required. if somebody is trying parent folder files
	$file = $output_dir . $fileName;

	if(file_exists($file)) {
		ob_start();
 		header('Content-Type: application/octet-stream');
		header('Content-Transfer-Encoding: binary');
		header('Content-Description: File Transfer');
		header('Content-Disposition: attachment; filename="'.$fileName.'";');
		header('Content-Length: ' . filesize($file));
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header("Pragma: public\n");
				
		readfile($file);
		ob_end_flush();
		exit;
	}

}
?>
