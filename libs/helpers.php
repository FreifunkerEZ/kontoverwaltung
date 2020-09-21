<?php
function printTemplate($filename) {
	if (!is_readable($filename))
		throw new Exception ("template at $filename is not readable.");
	readfile($filename);
}
function d($msg) {
	if (!is_string($msg))
		$msg = print_r($msg,'ret');
	echo "<p class='console debug'><b>DEBUG: </b>$msg</p>\n";
}

function e($msg) {
	if (!is_string($msg))
		$msg = print_r($msg,'ret');
	echo "<p class='console error'><b>ERROR: </b>$msg</p>\n";
}

function decodeUploadError($code) {
	switch ($code) { 
		case UPLOAD_ERR_INI_SIZE: 
			$message = "The uploaded file exceeds the upload_max_filesize directive in php.ini"; 
			break; 
		case UPLOAD_ERR_FORM_SIZE: 
			$message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
			break; 
		case UPLOAD_ERR_PARTIAL: 
			$message = "The uploaded file was only partially uploaded"; 
			break; 
		case UPLOAD_ERR_NO_FILE: 
			$message = "No file was uploaded"; 
			break; 
		case UPLOAD_ERR_NO_TMP_DIR: 
			$message = "Missing a temporary folder"; 
			break; 
		case UPLOAD_ERR_CANT_WRITE: 
			$message = "Failed to write file to disk"; 
			break; 
		case UPLOAD_ERR_EXTENSION: 
			$message = "File upload stopped by extension"; 
			break; 

		default: 
			$message = "Unknown upload error"; 
			break; 
	} 
	return $message; 
}
?>