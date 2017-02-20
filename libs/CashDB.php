<?php
class CashDB extends CashDBInit {
	/**
	 * a numeric array with the column-headers of the raw CSV format.
	 * Will be filled dynamically from an exploded string (below) during construct.
	 * is used to construct SQL-Statements and nice output tables.
	 * @var array
	 */
	private $csvHeaders = array();
	private $headerString = 'Kontonummer,Buchungstag,Wertstellung,AuftraggeberEmpfaenger,Buchungstext,'
				.'VWZ1,VWZ2,VWZ3,VWZ4,VWZ5,VWZ6,VWZ7,VWZ8,VWZ9,VWZ10,VWZ11,VWZ12,VWZ13,VWZ14,'
				.'Betrag,Kontostand,Waehrung';
	
	/**
	 * where the DB file is.
	 * @var string
	 */
	private $dbPath = '';
	
	/**
	 * 
	 * @param string $path path to the db-file.
	 * if the file does not exist, it will be created and initialized.
	 */
	function __construct($path)	{
		$this->dbPath = $path;
		$this->dbBackupCreate();
		$this->openDB();
		$this->initDatabase();
		$this->checkPlausibility();
		$this->setCsvHeaders();
	}
	
	private function dbBackupCreate() {
		if (!file_exists($this->dbPath)) #no DB, no backup
			return;
		
		$backupFile = $this->dbBackupFileName();
		if (file_exists($backupFile))
			return;
		
		if (copy($this->dbPath, $backupFile))
			d("Tägliches Datenbank-Backup erfolgreich.");
		else 
			e("Tägliches Datenbank-Backup fehlgeschlagen.");
		
	}
	
	/**
	 * restore the db to an older state.
	 * 
	 * @param string $when - 'today' uses the today's backup.
	 * @throws Exception if unknown $when is used.
	 * if the file to restore to cannot be touched.
	 */
	public function dbBackupRestore($when) {
		if ('today' == $when) {
			$pathToRestore = $this->dbBackupFileName();
		}
		else {
			throw new Exception("unexpected restore option.");
		}

		#verify backup
		if (!touch($pathToRestore))
			throw new Exception ("cannot touch restore-file $pathToRestore. permissions problem / does not exist?");
			
		#move current db out of the way to .old
		$oldDBpath = $this->dbPath.'.old';
		$this->close();
		@unlink($oldDBpath); #this might fail, if there is no .old file.
		rename($this->dbPath, $oldDBpath);
		
		#do restore
		copy($pathToRestore, $this->dbPath);
		d("Backup restored from $pathToRestore. original DB moved to .old");
	}
	
	protected function dbBackupFileName() {
		$fullPath = realpath($this->dbPath);
		return dirname($fullPath) .'/'. date('Y-m-d')."-Backup-KontoDatenbank.SQLite3";
	}
	
	private function openDB() {
		$path = $this->dbPath;
		if (!touch($path) )
			throw new Exception ("cannot touch file");
		
		if (is_readable($path) && is_file($path))
			$this->open($path);
		else
			throw new Exception ("file not readable or not a file");
		d("DB opened from ".realpath($path));
	}
	
	private function setCsvHeaders() {
		$this->csvHeaders = explode(',', $this->headerString);
	}
	
	public function importProcessUpload() {
		d("Accepting upload");
		$csvFile          = $this->importCutCSVfile();
		$dupCount         = 0;
		$recordCount      = 0;
		$dbRecordCountPre = $this->countBuchungen();
		foreach ($csvFile as $line) {
			#recognize and ignore header-line
			if (preg_match('/VWZ1.VWZ2.VWZ3.VWZ4.VWZ5.VWZ6.VWZ7.VWZ8.VWZ9.VWZ10.VWZ11.VWZ12.VWZ13.VWZ14/', $line))
				continue;
			if (!trim($line)) #ignore empty lines
				continue;
			
			$recordCount++;
			
			if ($this->importIsDuplicateRecord($line)) {
				$dupCount++;
				continue;
			}
			
			$rawDataAr = preg_split('/;/',$line);
			$this->importValidateLine($line, $rawDataAr);
			$this->importNormalizeRecord($rawDataAr);

			$ID = $this->importStoreRecord($line, $rawDataAr);
			$this->importApplyAllRules($ID);
		}
		
		d("$recordCount records processed, {$dupCount} thereof duplicates.");
		
		$counterAdded   = $recordCount - $dupCount;
		$dbRecordsAdded = $this->countBuchungen() - $dbRecordCountPre;
		if ($dbRecordsAdded != $counterAdded) {
			e("increase in record numbers via import-count ($counterAdded) "
					. "and db-count ($dbRecordsAdded) do not agree"
			);
		}
    } 
	
	private function importCutCSVfile(){
		if ($_FILES['csvfile']['error'] !== UPLOAD_ERR_OK)
			throw new Exception(sprintf("Problem with upload: %s \n%s: "
				,decodeUploadError($_FILES['csvfile']['error'])
				,print_r($_FILES, 'ret')
			));
			
		#fetch records, cut into array manually at CR-LF
		$csvFile = file_get_contents($_FILES['csvfile']['tmp_name']);
		$csvFile = iconv('Windows-1252', 'UTF-8', $csvFile);
		
		/* well, this was for the initial import of the big blob i had collected manually.
		 * on the bank-provided, diretly imported files we need to cut the lines at LFs only.
		#using \r\n should leave the VWZ with the single LF intact
		return preg_split('/\r\n/', $csvFile); 
		 * 
		 */
		return preg_split('/\n/', $csvFile); 
	}

	
	public function countBuchungen() {
		$sql = "SELECT count(*) FROM buchungen;";
		$ret = $this->runQuery($sql);
		$out = $ret->fetchArray();
		return $out[0];
	}
	
	/**
	 * checks if the data to be inspected has at least the following attributes set:
	 * #date
	 * #vwz1
	 * #betrag
	 * 
	 * checks if the line has the right number of data-elements
	 * @param string $line the raw CSV-data.
	 * @param array $rawDataAr The line cut into fields already
	 * @throws Exception if something is not right.
	 */
	private function importValidateLine($line, $rawDataAr) {
		/*
		   [0]=>  string(11) "Kontonummer"
		   [1]=>  string(11) "Buchungstag"				<- important
		   [2]=>  string(12) "Wertstellung"
		   [3]=>  string(22) "Auftraggeber/Empfänger"
		   [4]=>  string(12) "Buchungstext"				<- important
		   [5]=>  string(4)  "VWZ1"
		   [6]=>  string(4)  "VWZ2"
		   [7]=>  string(4)  "VWZ3"
		   [8]=>  string(4)  "VWZ4"
		   [9]=>  string(4)  "VWZ5"
		  [10]=>  string(4)  "VWZ6"
		  [11]=>  string(4)  "VWZ7"
		  [12]=>  string(4)  "VWZ8"
		  [13]=>  string(4)  "VWZ9"
		  [14]=>  string(5)  "VWZ10"
		  [15]=>  string(5)  "VWZ11"
		  [16]=>  string(5)  "VWZ12"
		  [17]=>  string(5)  "VWZ13"
		  [18]=>  string(5)  "VWZ14"
		  [19]=>  string(6)  "Betrag"					<- important
		  [20]=>  string(10) "Kontostand"
		  [21]=>  string(7)  "Währung"		*/
		
		#set which record-indexes are needed to pass the validation
		$required	   = array(1,4,19);
		
		$expectedCount = count($this->csvHeaders);
		$actualCount   = count($rawDataAr);
		if ($actualCount != $expectedCount)
			throw new Exception("expected $expectedCount elements, but got $actualCount elements on line: $line");
			
		foreach ($required as $index) {
			if (!empty($rawDataAr[$index]))
				continue;
			d($rawDataAr);
			throw new Exception("No ".$this->csvHeaders[$index]." (index $index) in above record --^ line: $line");
		}
	}
	
	/**
	 * tells you if the line was already imported into the db.
	 * compares the raw-ascii-line to be imported with the lines which are stored in the DB.
	 * 
	 * @param string $line
	 * @return boolean true if dup.
	 */
	private function importIsDuplicateRecord($line) {
		#small overlaps are caused on purpose during bank-export and should show up as expected.
		#there is no import-stage. if the import messes up, restore today's backup.
		$sql = "select * from buchungen where rawCSV = '$line'";
		$ret = $this->runQuery($sql);
		$dup = $ret->fetchArray(SQLITE3_ASSOC);
		
		if ($dup) {
			d("Duplicate records found: $line");
			return true;
		}
		else
			return false;
	}
	
	/**
	 * sets BuchungstagSortable and importdate.
	 * puts the record into the database.
	 * 
	 * @param string $line the raw ASCII-CSV-data
	 * @param array $rawDataAr
	 * @return the ID of the new record.
	 * @throws Exception
	 */
	private function importStoreRecord($line, $rawDataAr) {
		#if we do lots of records at once, it might run long!
		set_time_limit(30); 
		#get rid of dangrous single-quotes in all text elements
		foreach ($rawDataAr as &$content) {
			$content = str_replace("'", '_', $content);
		}
			
		$sql = sprintf("INSERT INTO buchungen (%s,rawCSV,BuchungstagSortable,importdate) VALUES ('%s','%s','%s','%s');"
				#headers convert to fieldnames
				, implode(',', $this->csvHeaders)
				#values to be inserted
				, implode("','", $rawDataAr)
				, $line
				, $this->dateToYmd($rawDataAr[1])
				, time()
		);
		$success = $this->exec($sql);
		if (!$success)
			throw new Exception("broken SQL something. error: '".$this->lastErrorMsg()."' because of sql: $sql");
		
		return $this->lastInsertRowID();
	}
	
	private function importApplyAllRules($buchungID) {
		$sqlRules = "SELECT ID FROM rules";
		$retRules = $this->runQuery($sqlRules);
		foreach ($this->toArraySingleRow($retRules) as $ruleID) {
			$this->ruleApply($ruleID, $buchungID);
		}
	}
	
	private function dateToYmd($dateIn) {
		if (preg_match('/(\d\d)\.(\d\d)\.(\d\d\d\d)/', $dateIn, $m))
			return sprintf("%s-%s-%s",$m[3],$m[2],$m[1]);
		
		if (preg_match('/(\d\d)\.(\d\d)\.(\d\d)/', $dateIn, $m))
			return sprintf("%s-%s-20%s",$m[3],$m[2],$m[1]);
		
		e("Funny date: $dateIn");
		return '';			
	}
	
	/**
	 * makes sure that money values are in a format that can be sql processed later.
	 * fix those:
		  [19]=>  string(6)  "Betrag"	
		  [20]=>  string(10) "Kontostand"
	 * input things like 200 or -200 or 8,5 or 12.345,67 or 12.345,67€
	 * expect this format -nnn.nn 
	 * @param array $rawDataAr - BY REFERENCE
	 */
	private function importNormalizeRecord(&$rawDataAr) {
		$rawDataAr[19] = $this->normalizeNumber($rawDataAr[19]); 
		$rawDataAr[20] = $this->normalizeNumber($rawDataAr[20]); 
	}
	
	private function normalizeNumber($n) {
		if ('' == trim($n))
			return $n;
		
                $matches = array();
		if (!preg_match('/(-?)([\d.]*),?(\d{0,2})\D?/', $n, $matches))
			e("funny money. regex does not match: $n");
		
		$matches[2] = str_replace('.', '', $matches[2]); #
		#d( "$n = ".$matches[1].$matches[2].'.'.$matches[3]);
		return $matches[1].$matches[2].'.'.$matches[3];
	}
	
	private function checkPlausibility() {
		#TODO check plausibility
		#check for holes in the records bigger than NNN days.
		#check for excessive amounts
		#compare given sum to calculated sum
	}
	
	/**
	 * Prints a nice table with all the buchungen in the database.
	 * i know it should not do this, but currently i got no better idea quickly.
	 * 
	 * @param string $class which CSS-class the table should have.
	 */
	public function printData($class) {
		$headers = array(
			'Buchungstag',
			'AuftraggeberEmpfaenger',
			'Buchungstext',
			'Verwendungszweck',
			'Kommentar',
			'Betrag',
			'Wdh',
			'Luxus',
			'Tags',
		);
		$colNames = array(
			'Buchungstag',
			'AuftraggeberEmpfaenger',
			'Buchungstext',
			'Verwendungszweck',
			'comment',
			'Betrag',
			'recurrence',
			'luxus',
			'tags',
		);
		$data = array(
			'ID',
			'Kontostand',
			'importdate',
			'BuchungstagSortable',
			'rawCSV',
			'luxus',
			'Betrag',
		);
		
		$sql  = "SELECT * FROM buchungen";
		$ret  = $this->runQuery($sql);
		$buchungen = $this->toArray($ret);
		uasort($buchungen, array($this, 'sortCompare'));
		
		print "<table class='$class'>";
		
		print "<tr>";
		print '<th onclick="buchungToggleSelectionAll(this);" >'
				. '<i class="fa fa-square-o" aria-hidden="true"></i>'
				. '</th>'
		;
		print '<th>Edit</th>'
		;
		foreach ($headers as $header) {
			print("<th>$header</th>");
		}
		print "</tr>\n";
		
		foreach ($buchungen as $buchung) {
			$this->collapseVWZ($buchung);
			$buchungTags		= $this->getTagsForBuchung($buchung['ID']);
			$buchung['tags']	= implode(',',$buchungTags);
			$tagNames			= $this->getTagsName($buchungTags);
			$buchung['tagTitle']= implode(', ', $tagNames);
			
			#set table-row attributes
			print "<tr "
					. "style='display:none' "
					. "title='ID: {$buchung['ID']}'"
			;
			foreach ($data as $columnName) {
				printf("data-$columnName='%s' ", $buchung[$columnName]);
			}
			if (!empty($buchungTags))
				printf("data-hasTags='%s' ", implode(' ', $buchungTags));
			print " >";
			
			#insert row-selector-checkbox-TD
			print '<td onclick="buchungToggleSelection(this);">'
					. '<i class="fa fa-square-o rowSelector" aria-hidden="true"></i>'
					. '</td>'
			;
			
			#insert edit-pencil-TD
			print '<td onclick="buchungEditorOpen(this);">'
					. '<i class="fa fa-pencil" aria-hidden="true"></i>'
					. '</td>'
			;
			
			#print values
			foreach ($colNames as $columnName) {
				if ('Betrag' == $columnName)
					$extraClass = $this->classifyBetrag($buchung[$columnName]);
				else
					$extraClass = '';
				
				$titleTip = ('tags' == $columnName ? $buchung['tagTitle'] : '');
				
				printf("<td class='%s %s' title='%s'>%s</td>\n"
						,"buchung$columnName"	#so we can find our values easier
						,$extraClass			#to make something shiny
						,$titleTip				#mouseover hint
						,$buchung[$columnName]	#the acutal text
				);
			}
			print "</tr>";
		}
		print "</table>";
	}
	
	private function classifyBetrag($betrag) {
		$ranges = array(
			#v-- is betrag bigger than
						#v-- assign this class
			'0'		=> 'Income',
			'-50'	=> 'Mild',
			'-100'	=> 'Advanced',
			'-501'	=> 'Alarming',
			'-99999'=> 'Crazy',
		);
		foreach ($ranges as $number => $class) {
			if ($betrag > $number)
				return 'betrag'.$class;
		}
	}
	/**
	 * gets you all the tagIDs associated with the buchungID.
	 * @param integer $buchungID
	 * @return array
	 */
	public function getTagsForBuchung($buchungID) {
		$sql = "SELECT tagID FROM buchungXtag WHERE buchungID = $buchungID";
		$ret = $this->runQuery($sql);
		return $this->toArraySingleRow($ret);
	}
	
	/**
	 * turns a list of tag-ids into names.
	 * 
	 * @param array $tags numeric tag-IDs
	 * @return array names of the tags in no specific order.
	 */
	public function getTagsName($tags) {
		$tagNames = array();
		$IDs = implode(',', $tags);
		$sql = "SELECT name, ID FROM tags WHERE ID in ($IDs)";
		$ret = $this->runQuery($sql);
		return $this->toArraySingleRow($ret);
	}
	
	/**
	 * finds all VWZ-elements 1..14.
	 * sticks the ones which are not empty together with br-tags.
	 * puts them into a new field called "Verwendungszweck"
	 * @param has $row BY REFERENCE - gets a new field called "Verwendungszweck"
	 */
	private function collapseVWZ(&$row) {
		$bucket = array();
		for ($i = 1; $i <= 14; $i++) {
			$vwz = "VWZ$i";
			if ($row[$vwz])
				$bucket[] = $row[$vwz];
		}
		$row['Verwendungszweck'] = implode('<br>', $bucket);
	}
	
	

	/**
	 * gets you tags with all columns.
	 * 
	 * @param string $filter something you would like in a WHERE clause.
	 * like "ID = '2'"
	 * @return numeric array filled with hashes.
	 */
	public function tagsList($filter = '') {
		$sql = "SELECT * FROM tags".($filter ? ' WHERE '.$filter : '');
		return $this->toArray($this->runQuery($sql));
	}
	
	public function tagSave() {
		if (empty($_POST['params']))
			throw new Exception ("no saving without params");
		
		$input = json_decode($_POST['params'],'array');
		if ('NEW' == $input['ID']) 
			$this->tagCreate($input);
		elseif (is_numeric($input['ID']))
			$this->tagUpdate($input);
		else
			throw new Exception("something wrong with the tag's ID: ".$input['ID']);
	}
	
	private function tagCreate($input) {
		$sql = sprintf("INSERT INTO `tags`(`name`,`comment`,`justifies`,`color`) VALUES ('%s','%s','%s','%s')"
				, $input['name']
				, $input['comment']
				, $input['justifies']
				, $input['color']
		);
		$ret = $this->runQuery($sql);
	}
	private function tagUpdate($input) {
		$sql = sprintf("UPDATE `tags` "
				. "SET `name`='%s', 'comment'='%s', 'justifies'='%s', 'color'='%s' "
				. "WHERE ID='%s';"
				,$input['name']
				,$input['comment']
				,($input['justifies']?1:0)
				,$input['color']
				,$input['ID']
		);
		print $sql;
		$this->runQuery($sql);
	}
	public function tagDelete() {
		if (!isset($_POST['ID']))
			throw new Exception("no ID");
		
		
		throw new Exception("no code");
		#TODO tags should not be deletable if there are still buchungen with them. that would cause inconsistency.
	}
	public function rulesList($filter = '') {
		$sql = "SELECT * FROM rules".($filter ? ' WHERE '.$filter : '');
		return $this->toArray($this->runQuery($sql));
	}
	
	/**
	 * gets a list of Tag-IDs for this rule.
	 * 
	 * @param numeric $ruleID
	 * @return hash: 'has' => array of Tag-IDs assigned to this rule 
	 * + 'canHave' => array of all other available Tag-IDs.
	 */
	public function ruleGetTags($ruleID) {
		$sql = "SELECT tagID FROM ruleXtag WHERE ruleID = '$ruleID'";
		$ret = $this->runQuery($sql);
		$has = $this->toArraySingleRow($ret);
		
		$hasString = implode(',', $has);
		$sql = "SELECT ID FROM tags WHERE ID NOT IN ($hasString)";
		$ret = $this->runQuery($sql);
		$canHave = $this->toArraySingleRow($ret);
		return array('has' => $has, 'canHave' => $canHave);
	}
	
	public function ruleSave() {
		if (empty($_POST['params']))
			throw new Exception ("no saving without params");
		
		$input = json_decode($_POST['params'],'array');
		if ('NEW' == $input['ID']) {
			$input['ID'] = $this->ruleCreate(); #create empty shell
			print "newRuleId:'{$input['ID']}'"; #set content in next step
		}
		
		if (is_numeric($input['ID']))
			$this->ruleUpdate($input);
		else
			throw new Exception("something wrong with the tag's ID: ".$input['ID']);
	}
	
	/**
	 * creates a new and empty rule.
	 * inserts the current epoch-timestamp as name.
	 * @return numeric the ID of the newly created record.
	 */
	protected function ruleCreate() {
		$now = time();
		$sql = "INSERT INTO `rules` (`name`) VALUES ('$now')";
		$this->runQuery($sql);
		
		$sql = "SELECT LAST_INSERT_ROWID();";
		$ret = $this->runQuery($sql);
		$out = $this->toArraySingleRow($ret);
		return array_shift($out);
	}
	
	protected function ruleUpdate($input) {
		$sql = sprintf("UPDATE `rules` "
			. "SET `name`='%s', 'comment'='%s', 'filter'='%s', 'luxus'='%s', 'recurrence'='%s' "
			. "WHERE ID=%s "
			,$input['name']
			,$input['comment']
			,$input['filter']
			,$input['luxus']
			,$input['recurrence']
			,$input['ID']
		);
		#d($sql);
		$this->runQuery($sql);
		
		$this->ruleResetTags($input);
	}
	
	/**
	 * removes all tags from the rule.
	 * puts the tags from $input back in.
	 * @param hash $input
	 */
	protected function ruleResetTags($input) {
		#first remove all tags
		$ruleID = $input['ID'];
		$sql = "DELETE FROM ruleXtag WHERE ruleID=$ruleID";
		$this->runQuery($sql);
		
		#then set all tags anew
		foreach ($input['tags'] as $tagID) {
			$sql = "INSERT INTO 'ruleXtag' ('ruleID', 'tagID') VALUES ('$ruleID', '$tagID')";
			$this->runQuery($sql);
		}
	}
	
	public function ruleDelete() {
		if (empty($_POST['ID']) || !is_numeric($_POST['ID']))
			throw new Exception ("need ID");
		
		$id  = $_POST['ID'];
		$sql = "DELETE FROM ruleXtag WHERE ruleID = '$id'";
		$this->runQuery($sql);
		
		$sql = "DELETE FROM rules WHERE ID = $id";
		$this->runQuery($sql);
	}
	
	/**
	 * applies a single rule to one or all buchungen.
	 * @param int $ruleID the ID to apply
	 * @param int $buchungID OPTIONAL - the buchung which to apply to.
	 * if not given, all buchungen are inspected.
	 */
	public function ruleApply($ruleID, $buchungID = NULL) {
		#get list of tags that go with this rule.
		$tags = $this->ruleGetTags($ruleID);
		if (!$tags['has']) { #no assigned tags, no work.
			#d("Rule $ruleID has no tags");
			return;
		}
		#d("will apply following tags: ".implode(', ', $tags['has']));
		
		$filter  = $this->ruleGetFilter($ruleID);
		#d("Rule $ruleID has filter '$filter'");
		if (!$filter)
			return;
		
		#get a list of one/all buchungen
		$sql = "SELECT * FROM buchungen" . ($buchungID ? " WHERE ID=$buchungID" : '');
		$retBuchungen = $this->runQuery($sql);
		
		foreach ($this->toArray($retBuchungen) as $buchungID => $buchung) {
			if (!$this->buchungMatchesFilter($buchung, $filter))
				continue;
			
			$this->buchungApplyTags($buchungID, $tags['has'], $ruleID);
			$this->buchungApplyRecurrence($buchungID, $ruleID);
			$this->buchungApplyLuxus($buchungID, $ruleID);
			d("applied rule $ruleID to buchung $buchungID");
		}
	}

	/**
	 * get the regex for the rule
	 * 
	 * @param int $ruleID
	 * @return string
	 */
	private function ruleGetFilter($ruleID) {
		$sqlRule = "SELECT filter FROM 'rules' WHERE ID = $ruleID";
		$retRule = $this->runQuery($sqlRule);
		return $this->toSingleValue($retRule);
	}
	
	private function buchungApplyTags($buchungID, $tags, $ruleID) {
		$templateInsert = "INSERT OR REPLACE "
				. "INTO 'buchungXtag' (buchungID, tagID, origin) "
				. "VALUES ('%s','%s','$ruleID')"
		;
		
		foreach ($tags as $tagID) {
				$sqlInsert = sprintf($templateInsert, $buchungID, $tagID);
				$this->runQuery($sqlInsert);
		}
	}
	
	private function buchungApplyLuxus($buchungID, $ruleID) {
		$this->copyValueFromRuleToBuchung($ruleID, $buchungID, 'luxus');
	}
	private function buchungApplyRecurrence($buchungID, $ruleID) {
		$this->copyValueFromRuleToBuchung($ruleID, $buchungID, 'recurrence');
	}
	
	/**
	 * looks at the rules value called $name.
	 * if value is set, it tries to apply it to buchung.
	 * if buchung already has a value at $name set, they must match.
	 * if not -> exception because that is thought to be a corruption.
	 * it must be fixed by the user.
	 * 
	 * @param int $buchungID
	 * @param int  $ruleID
	 * @param string $name the name of the value to be copied.
	 * must be the same in table buchungen and rules to work.
	 * @return NULL
	 * @throws Exception if there is a change in $name's value detected.
	 */
	private function copyValueFromRuleToBuchung($ruleID, $buchungID, $name) {
		$sqlGetNewVal = "SELECT $name FROM rules WHERE ID=$ruleID";
		$newVal = $this->toSingleValue($this->runQuery($sqlGetNewVal));
		
		if ('' === $newVal || NULL === $newVal)
			return; #no value, no copy
		
		$sqlGetCurrentVal = "SELECT $name FROM buchungen WHERE ID=$buchungID";
		$currentVal = $this->toSingleValue($this->runQuery($sqlGetCurrentVal));
		
		if ($currentVal === $newVal)
			return; #both the same? no work
		
		if ($currentVal === '' || NULL === $currentVal) { #no old value? set.
			$sqlSetVal = "UPDATE `buchungen` SET `$name`=$newVal WHERE `ID`=$buchungID;";
			$this->runQuery($sqlSetVal);
			return; #work done
		}
		
		#still here? last thing left: old value != new value. corruption
		throw new Exception ("Cannot apply rule $ruleID's $name to buchung $buchungID because buchung already has $name of '$currentVal' set and rule requires '$newVal'");
		#TODO this corruption-exception probably going to fly in my face rather soon. 
		#no idea how to handle that for now. 
		#i think it requires some sort of manual resolving. 
		#not sure how that is supposed to look like. 
		#it should only come from rule-error, 
		#as a buchung can never have two different recurrences.
		
	}
	
	/**
	 * looks at a buchung.
	 * figures out if the filter applies.
	 * @param hash $buchung the record as it comes from the DB
	 * @param string $filter a complete RegEx pattern
	 * @return boolean
	 */
	private function buchungMatchesFilter($buchung, $filter) {
		$dataAr = array();
		foreach ($buchung as $key => $value) {
			$dataAr[] = "$key=$value";
		}
		$dataStr = implode(',', $dataAr);
		
		if (preg_match($filter, $dataStr))
			return true;
		else
			return false;
	}
	
	public function buchungEdit() {
		$input = $this->scrubInputs($_GET);
		if (empty($input['ID']))
			throw new Exception ("cannot work like this!");
		
		#var_dump($input);
		$sql = sprintf("UPDATE buchungen SET %s WHERE ID=:ID"
				,'key=value'
				,$input['ID']
		);
		
		$keys = array('comment', 'luxus', 'recurrence');
		$stmt = $this->formatSetStatement(
				'UPDATE buchungen SET %s WHERE ID=:ID', 
				$input, 
				$keys
		);
		$stmt->bindValue('ID', $input['ID']);
		if (false === $stmt->execute())
			throw new Exception("failed to edit buchung");
				
		#remember to set tags
		$clean = $this->prepare("DELETE FROM buchungXtag WHERE buchungID=:ID");
		$clean->bindParam(':ID', $input['ID']);
		if (false === $clean->execute())
			throw new Exception("failed to delete tags from buchung");
		
		foreach ($input['tags'] as $tagID) {
			$setTags = $this->prepare(
					"INSERT INTO buchungXtag "
					. "(buchungID, tagID, origin) VALUES (?,?,'manual')"
			);
			$setTags->bindParam(1, $input['ID']);
			$setTags->bindParam(2, $tagID);
			if (false === $setTags->execute())
				throw new Exception ("failed re-insert tags on buchung");
		}
	}
	
}
