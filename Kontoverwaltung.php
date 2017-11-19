<?php
require_once 'libs/helpers.php';
require_once 'templates/templates.php';
require_once 'router.php';

spl_autoload_register(function($class) {
    include 'libs/' . $class . '.php';
});


try {
	ob_start();
	header('Content-Type: text/html; charset=utf-8');

	$db = new CashDB('F:\My Documents\Kontoverwaltung-Daten\KontoDatenbank.SQLite3');
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
	print "<h1>EGGSEPTSCHUN!</h1><pre>";
	e($e->getMessage());
	e($e->getTraceAsString());
	print "</pre>";
	exit;
}

?>
<!DOCTYPE html>
<html>
	<head>
		<title>Kontoverwaltung</title>
		
		<script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
		<script src="https://use.fontawesome.com/de9a1cbb80.js"></script>
		
		<link rel="stylesheet" href="jRange/jquery.range.css">
		<script src="jRange/jquery.range.js"></script>
		
		<script src="/js/helper.js"></script>
		<script src="/js/filtering.js"></script>
		<script src="/js/stats.js"></script>
		<script src="/js/buchungStuff.js"></script>
		<script src="/js/tagStuff.js"></script>
		<script src="/js/ruleStuff.js"></script>
		<script src="/js/addRemoveBox.js"></script>
		<script src="/js/Kontoverwaltung.js"></script>
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
	
	
	<button onclick="toggleElements('.ruleBrowser');">View Rules</button>
	<button onclick="toggleElements('.uploadForm');">Upload Data</button>
	
	<div class="elementBrowser tagBrowser">
		<h2>Buchungen Filtern mit Tags:</h2>
		
		<div class="elementHeaderBox">
			<button onclick="tagNewOpen();">
				New Tag			</button>
			<button onclick="filtersApply('untagged');" data-filter="untagged" title="Nur Buchungen anzeigen die keine Tags haben.">
				Show Untagged	</button>
			<button onclick="filtersApply('all');"      data-filter="all" >
				Show All		</button>
			<button onclick="filtersApply('none');"     data-filter="none">
				Show None		</button>
			<button onclick="filtersApply('filtered');" data-filter="filtered"
				title="Buchungen müssen mindestens ein Tag haben um angezeigt zu werden.">
				Show Filtered	</button>
			
			Tag-Verknüpfung:
			<label>
				<input type="radio" name="filterMode" value="and" onclick="filterMode();filtersApply();">
				UND
			</label>
			<label>
				<input type="radio" name="filterMode" value="or" checked onclick="filterMode();filtersApply();">
				ODER
			</label>
			<span title="Angezeigte Buchungen ohne Tags">
				Untagged 
				Summe:  <span class="untaggedSum"  >Viel</span>
				Anzahl: <span class="untaggedCount">Viel</span>
			</span>
		</div>
		
		
		<table class="elementGrid">
			<tr>
				<td>
					<?php 
					#TODO FIXME rule: edit -> save & apply -> nothing happens. vs rule -> edit -> apply --> works 
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
		<button onclick="ruleSave(this);">Speichern & Anwenden</button>
		<button onclick="ruleDelete(this);">Löschen</button>
		<button onclick="ruleApply(this);">Anwenden</button>
	</div>


	<div class="stats elementBrowser">
		<h2>Stats</h2>
		Buchungen nach Datum filtern (YYYY-MM-DD): 
		<input name="filterDate" onkeyup="if (arguments[0].keyCode == 13) filtersApply();">
		<br>
		
		Luxusfilter
		<div style="width:310px; height: 10px;margin: 20px;">
			<input type="hidden" class="luxus-slider" value="300" />
			
		</div>
		<br>
		
		Volltextsuche
		<input name="filterFullText" onkeyup="if (arguments[0].keyCode == 13) filtersApply();">
		<br>
		
		<button onclick="filtersApply();">Filter Anwenden</button>
		<br>
		
		
		Summe der angezeigten Buchungen: <span class="statsSum">viel</span>
		Anzahl der Buchungen: <span class='statsCountTotal'>viele</span>
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
		printTemplate('templates/buchungsEditorTemplate.html');
		$db->printData('records');
	?>
</body>
</html>