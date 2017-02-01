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
		case 'editComment':
			$db->editComment();
			break;
		case 'tagSave':
			$db->tagSave();
			break;
		case 'tagDelete':
			$db->tagDelete();
			break;
		case 'ruleSave':
			$db->ruleSave();
			break;
		case 'ruleDelete':
			$db->ruleDelete();
			break;
		case 'ruleApply':
			if (!isset($_POST['ruleID']))
				throw new Exception("no rule id, no applying");
			$db->ruleApply($_POST['ruleID']);
			break;

		default:
			throw new Exception("Unknown action $action");
			break;
	}
}

function printTagBox($tag) { 
	?>
	<div 
		class			="tagElement" 
		style			="background-color: <?php print $tag['color'];?>;"
		title			="<?php print $tag['comment']?>" 
		data-ID		    ="<?php print $tag['ID'];?>"
		data-name		="<?php print $tag['name'];?>"
		data-justifies	="<?php print $tag['justifies'];?>"
		data-color		="<?php print $tag['color'];?>"
		data-showTag	="true"
		onclick			="tagFilterToggle(this)"
	>
		<?php print ($tag['justifies'] 
				? '<i class="fa fa-fw fa-check"    title="Dieses Tag setzt die Buchung auf erklärt."></i>' 
				: '<i class="fa fa-fw fa-question" title="Dieses Tag modifiziert den Erklärt-Status nicht."></i>');?>
		<?php print $tag['name'];?>

		<div class="tagOnTheRight" title="Werden Buchungen mit diesem Tag angezeigt?">
			<i class="fa fa-eye" aria-hidden="true"></i>
		</div>
		<div  onclick	="arguments[0].stopPropagation(); tagOpenEditor(this);"
			  title		="Bearbeiten"
			  class		="tagOnTheRight"
		>
			<i class="fa fa-pencil" aria-hidden="true"></i> 
		</div>
	</div>
	<?php 
}

function printRuleBox($rule, $db) {
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
<?php	
}



try {
	ob_start();
	header('Content-Type: text/html; charset=utf-8');

	$db = new CashDB('c:\temp\KontoDatenbank.SQLite3');
	if(!$db)
		throw new Exception( $db->lastErrorMsg());

	if (isset($_GET['action'])) {
		ob_clean();
		routeAction($_GET['action'], $db);
		exit;
	}
	
} catch (Exception $e) {
	http_response_code(500);
	ob_end_flush();
	print "<h1>EGGSEPTSCHUN!</h1>";
	e($e->getMessage());
	e($e->getTraceAsString());
	exit;
}

?>
<!DOCTYPE html>
<html>
	<head>
		<title>Kontoverwaltung</title>
		
		<script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
		<script src="https://use.fontawesome.com/de9a1cbb80.js"></script>
		<script src="Kontoverwaltung.js"></script>
		<script src="filtering.js"></script>
		<script src="tagStuff.js"></script>
		<script src="ruleStuff.js"></script>
		<script src="addRemoveBox.js"></script>
		<script type="text/javascript">
			var tagsBase64 = "<?php print base64_encode(json_encode($db->tagsList()));?>";
			var tagsJSON = atob(tagsBase64);
			tags = JSON.parse(tagsJSON);
		</script>
		<link rel="stylesheet" href="Kontoverwaltung.css">
	</head>

<body>
	<?php ob_end_flush();?>
	<div class="loadingIndicator fa fa-cog fa-spin fa-3x fa-fw"
		 title="Speichert gerade"
		 data-operationsInProgress="0"
	></div>
	
	
	<button onclick="$('.ruleBrowser').toggle()">View Rules</button>
	<button onclick="$('.uploadForm').toggle()">Upload Data</button>
	
	
	<div class="elementBrowser tagBrowser">
		<h2>Buchungen Filtern mit Tags:</h2>
		
		<button onclick="tagNewOpen();">				New Tag			</button>
		<button onclick="tagFilterShow('untagged');">	Show Untagged	</button>
		<button onclick="tagFilterShowAll(this);">		Show All		</button>
		<button onclick="tagFilterShowNone(this);">		Show None		</button>
		<button onclick="tagFilterShow('filtered');">	Show Filtered	</button>
		
		<table class="elementGrid">
			<tr>
				<td>
					<?php 
					$tags = $db->tagsList();;
					$displayColumnCount = 5; #how wide should the tag-browser be?
					$tagsPerColumn = ceil(count($tags) / $displayColumnCount);
					$count = 0;
					foreach ($tags as $tag) { 
						printTagBox($tag);
						$count++;
						if ($count % $tagsPerColumn === 0)
							print "</td><td>"; #start new column.
					} 
					?>
				</td>
			</tr>
		</table>
	</div>

	<div class="elementEditor tagEditor" style="display: none">
		<h2>Tag <span name="ID">your ID here.</span></h2>
		<label>Name:           <input type="text"      name="name">			</label>
		<label>Kommentar:      <input type="text"      name="comment">		</label>
		<label>Farbe:          <input type="color"     name="color">		</label>
		<label>Gerechtfertigt: <input type="checkbox"  name="justifies">	</label>
		<button onclick="$(this).parent().hide();">Close</button>
		<button onclick="tagSave(this);">Save</button>
		<button onclick="tagDelete(this);">Delete</button>
	</div>

	<div class="elementBrowser ruleBrowser" style="display:none;">
		<h2>Available Rules:</h2>
		<button onclick="ruleNewOpen(this);">New Rule</button>
		<table class="elementGrid">
			<tr>
				<td>
			<?php 
				$rules = $db->rulesList();
				$displayColumnCount = 5; #how wide should the rule-browser be?
				$tagsPerColumn = ceil(count($rules) / $displayColumnCount);
				$count = 0;
				foreach ($rules as $rule) { 
					printRuleBox($rule, $db);
					$count++;
					if ($count % $tagsPerColumn === 0)
						print "</td><td>"; #start new column.
				} 
			?>
				</td>
			</tr>
		</table>
	</div>
	
	<div class="elementEditor ruleEditor" style="display: none">
		<h2>View / Edit Rule <span name="ID">your ID here.</span></h2>
		<label						>Name:          <input type="text"      name="name">			</label>
		<label						>Kommentar:     <input type="text"      name="comment">		</label>
		<label class="fa fa-search"	>RegEx:         <input type="text"      name="filter">		</label>
		<label class="fa fa-glass"	>Luxus:         <input type="text"      name="luxus">		</label>
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
					<button onclick="arbAddButton(this);">
						<i class="fa fa-arrow-left" aria-hidden="true"></i>
						<i class="fa fa-plus" aria-hidden="true"></i>
					</button>
					<br><br>
					<button onclick="arbRemoveButton(this);">
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
		<button onclick="ruleSave(this);">Save & Apply</button>
		<button onclick="ruleDelete(this);">Löschen</button>
		<button onclick="ruleApply(this);">Anwenden</button>
	</div>


	<div class="elementBrowser">
		<h2>Stats</h2>
		Sichtbare Buchungen nach Datum filtern (YYYY-MM-DD): 
		<input name="filterDate" value="2016">
		<button onclick="filterDate();">GO</button>
		<br>
		
		Summe der angezeigten Buchungen: <span class="statsSum">So viel.</span>
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
		$db->printData('records');
	?>
</body>
</html>