<?
// PHP settings for larger files upload
ini_set('upload_max_filesize', '512M');
ini_set('post_max_size', '512M');
ini_set('memory_limit', '768M');
ini_set('max_execution_time', '600');

$upload_dir = sys_get_temp_dir();
?>
