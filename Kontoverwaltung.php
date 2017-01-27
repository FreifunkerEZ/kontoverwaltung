<?php
spl_autoload_register(function ($class) {
    include $class . '.php';
});

function d($msg) {
	if (!is_string($msg))
		$msg = print_r($msg,'ret');
	echo "<p class='console debug'><b>DEBUG:</b> $msg</p>";
}

function e($msg) {
	if (!is_string($msg))
		$msg = print_r($msg,'ret');
	echo "<p class='console error'><b>ERROR:</b> $msg</p>";
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

/**
 * calls the right function, based on the requested action.
 * @param string $action
 * @param CashDB $db
 */
function routeAction($action, $db) {
	switch ($action) {
		case 'upload':
			$db->processUpload();
			break;
		case 'tagSave':
			$db->tagSave();
			break;
		case 'ruleSave':
			$db->ruleSave();
			break;

		default:
			break;
	}
}




try {
	ob_start();
	header('Content-Type: text/html; charset=utf-8');

	$db = new CashDB('c:\temp\KontoDatenbank.SQLite3');
	if(!$db)
		throw new Exception( $db->lastErrorMsg());

	if (isset($_GET['action'])) {
		ob_end_clean();
		routeAction($_GET['action'], $db);
		exit;
	}
	
} catch (Exception $e) {
	print "<h1>EGGSEPTSCHUN!</h1>";
	e($e->getMessage());
}

?>
<!DOCTYPE html>
<html>
	<head>
		<script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
		<script src="https://use.fontawesome.com/de9a1cbb80.js"></script>
		<script src="Kontoverwaltung.js"></script>
		<script type="text/javascript">
			var tagsBase64 = "<?php print base64_encode(json_encode($db->tagsList()));?>";
			var tagsJSON = atob(tagsBase64);
			tags = JSON.parse(tagsJSON);
		</script>
		<link rel="stylesheet" href="Kontoverwaltung.css">
	</head>

<body>
	<?php ob_end_flush();?>
	<div class="elementBrowser">
		<div>
			<h2>Available Tags:</h2>
			<button onclick="tagNewOpen();">New Tag</button>
			<?php foreach ($db->tagsList() as $tag) { ?>
				<div 
					class			="tagElement" 
					style			="background-color: <?php print $tag['color'];?>;"
					title			="<?php print $tag['comment']?>" 
					data-ID		    ="<?php print $tag['ID'];?>"
					data-name		="<?php print $tag['name'];?>"
					data-justifies	="<?php print $tag['justifies'];?>"
					data-color		="<?php print $tag['color'];?>"
					onclick			="tagOpenEditor(this);"
				>
					<?php print ($tag['justifies'] 
							? '<i class="fa fa-fw fa-check"    title="Dieses Tag setzt die Buchung auf erklärt."></i>' 
							: '<i class="fa fa-fw fa-question" title="Dieses Tag modifiziert den Erklärt-Status nicht."></i>');?>
					<?php print $tag['name'];?>
				</div>
			<?php } ?>
		</div>
		
	</div>

	<div class="elementEditor tagEditor" style="display: none">
		<h2>Tag <span name="ID">your ID here.</span></h2>
		<label>Name:           <input type="text"      name="name">			</label>
		<label>Kommentar:      <input type="text"      name="comment">		</label>
		<label>Farbe:          <input type="color"     name="color">		</label>
		<label>Gerechtfertigt: <input type="checkbox"  name="justifies">	</label>
		<button onclick="$(this).parent().hide();">Close</button>
		<button onclick="tagSave(this);">Save</button>
	</div>

	<div class="elementBrowser">
		<div>
			<h2>Available Rules:</h2>
			<?php 
				foreach ($db->rulesList() as $rule) { 
				$allTags = $db->ruleGetTags($rule['ID']);
			?>
				<div 
					class			="ruleElement" 
					title			="<?php print $rule['comment']?>" 
					data-ID		    ="<?php print $rule['ID'];?>"
					data-name		="<?php print $rule['name'];?>"
					data-filter		="<?php print $rule['filter'];?>"
					data-luxus		="<?php print $rule['luxus'];?>"
					data-recurrence	="<?php print $rule['recurrence'];?>"
					data-tagHas		="<?php print json_encode($allTags['has']);?>"
					data-tagCanHave	="<?php print json_encode($allTags['canHave']);?>"
					onclick			="ruleOpenEditor(this);"
				>
					<?php print $rule['name'];?>
					<br>
					<i class="fa fa-glass" aria-hidden="true">		<?php print $rule['luxus'];?></i>
					<i class="fa fa-refresh" aria-hidden="true">	<?php print $rule['recurrence'];?></i>
					<br>
					<?php 
						print implode(', ', $allTags['has']);
					?>
				</div>
			<?php } ?>
		</div>
	</div>
	
	<div class="elementEditor ruleEditor" style="display: none">
		<h2>View / Edit Rule <span name="ID">your ID here.</span></h2>
		<label>Name:           <input type="text"      name="name">			</label>
		<label>Kommentar:      <input type="text"      name="comment">		</label>
		<label class="fa fa-search">RegEx:          <input type="text"      name="filter">		</label>
		<label class="fa fa-glass"> Luxus:          <input type="text"      name="luxus">		</label>
		<label class="fa fa-refresh">Wiederholung:  <input type="text"      name="recurrence">	</label>
		
		<div class="arbAddRemoveBoxes">
		<h3>Tags</h3>
			<div class="arbHasBox">
				Ausgewählt<br>
				<select size=10>
				</select>
			</div>
			<div class="arbControlsBox">
				<div class="arbControls">
					<button>
						<i class="fa fa-arrow-left" aria-hidden="true"></i>
						<i class="fa fa-plus" aria-hidden="true"></i>
					</button>
					<br><br>
					<button>
						<i class="fa fa-times" aria-hidden="true"></i>
						<i class="fa fa-arrow-right" aria-hidden="true"></i>
					</button>
				</div>
			</div>

			<div class="arbCanHaveBox">
				Verfügbar<br>
				<select size=10>
				</select>
			</div>

		</div>
		
		
		<button onclick="$(this).parent().hide();">Close</button>
		<button onclick="ruleSave(this);">Save</button>
	</div>



	<div class="uploadForm">
		Neue Daten hochladen in CSV format:
		<form enctype="multipart/form-data" action="?action=upload" method="POST">
			<!-- MAX_FILE_SIZE must precede the file input field -->
			<input type="hidden" name="MAX_FILE_SIZE" value="999999999" />
			<!-- Name of input element determines name in $_FILES array -->
			Send this file: <input name="csvfile" type="file" />
			<input type="submit" value="Send File" />
		</form>
	</div>

	<?php 
		$db->printData();
	?>
</body>
</html>