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
	 * collects how many duplicate records were encountered during an import operation.
	 * @var int
	 */
	private $importDupCounter = 0;
	
	function __construct($path)	{
		$this->createDBbackup($path);
		$this->openDB($path);
		$this->initDatabase();
		$this->checkPlausibility();
		$this->setCsvHeaders();
	}
	
	private function createDBbackup($path) {
		if (!file_exists($path)) #no DB, no backup
			return;
		
		$fullPath = realpath($path);
		$backupFile = dirname($fullPath) .'/'. date('Y-m-d')."-Backup-KontoDatenbank.SQLite3";
		if (file_exists($backupFile))
			return;
		
		if (copy($path, $backupFile))
			d("Tägliches Datenbank-Backup erfolgreich.");
		else 
			e("Tägliches Datenbank-Backup fehlgeschlagen.");
		
	}
	
	private function openDB($path) {
		#TODO create backup copy once per day
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
	
	public function processUpload() {
		d("Accepting upload");
		if ($_FILES['csvfile']['error'] !== 0)
			throw new Exception(sprintf("Problem with upload %s \n%s: "
				,decodeUploadError($_FILES['csvfile']['error'])
				,print_r($_FILES, 'ret')
			));
			
		#get content right into arrays. breaks at least one record that has a LineFeed in the VWZ.
		#$csvFile = file($_FILES['csvfile']['tmp_name']);
		
		#fetch records again, this time cut into array manually at CR-LF
		$csvFile = file_get_contents($_FILES['csvfile']['tmp_name']);
		$csvFile = preg_split('/\r\n/', $csvFile); #that should leave the VWZ with the single LF intact
		
		$this->importDupCounter = 0;
		$recordCount            = 0;
		$dbRecordCountPre       = $this->countBuchungen();
		$data                   = [];
		foreach ($csvFile as $line) {
			#recognize and ignore header-line
			if (strstr($line,'VWZ1,VWZ2,VWZ3,VWZ4,VWZ5,VWZ6,VWZ7,VWZ8,VWZ9,VWZ10,VWZ11,VWZ12,VWZ13,VWZ14'))
				continue;
			if (!trim($line)) #ignore empty lines
				continue;
			
			$data[] = str_getcsv($line);
			$this->validateAndStore($line);
			$recordCount++;
		}
		
		d("$recordCount records processed, {$this->importDupCounter} thereof duplicates.");
		
		$counterAdded   = $recordCount - $this->importDupCounter;
		$dbRecordsAdded = $this->countBuchungen() - $dbRecordCountPre;
		if ($dbRecordsAdded != $counterAdded) {
                    throw new Exception("increase in record numbers via import-count ($counterAdded) and db-count ($dbRecordsAdded) do not agree");
                }
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
	 * checks for duplicates.
	 * 
	 * checks if the line has the right number of data-elements
	 * 
	 * if no problem is found, the record is added to the database.
	 * @param unknown $rawDataAr
	 */
	private function validateAndStore($line) {
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
		$required    = array(1,4,19);
		
		$rawDataAr     = str_getcsv($line);
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
		
		if ($this->isDuplicateRecord($line))
			return;
		
		$this->normalizeAndStoreRecord($rawDataAr, $line);
	}
	
	private function isDuplicateRecord($line) {
		#TODO test isDuplicateRecord
		#TODO which data-points to compare then? what makes a record unique? buchungstag, buchungstext, betrag are guaranteed to have data.
		#can data even change? bits are bits. if the dataformat changes everything is broken anyways. 
		#small overlaps are caused on purpose during bank-export and should show up as expected.
		#TODO add "prepare import page" which shows days of last known buchungen + what happened on that day. must show again on import to manually verify duplicates are present as expected.
		#TODO have import-stage? could be looking at records before inserting them into db and messing things up. is that necessary with backups?
		$sql = "select * from buchungen where rawCSV = '$line'";
		$ret = $this->runQuery($sql);
		$dup = $ret->fetchArray(SQLITE3_ASSOC);
		
		if ($dup) {
			$this->importDupCounter++;
			d("Duplicate records found: $line");
			return true;
		}
		
		
		
		
		return false;
	}
	
	/**
	 * sets BuchungstagSortable and importdate.
	 * normalizes money values.
	 * 
	 * @param unknown $rawDataAr
	 * @param unknown $line
	 * @throws Exception
	 */
	private function normalizeAndStoreRecord($rawDataAr, $line) {
		$rawDataAr = $this->normalizeRecord($rawDataAr);
		
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
	 * @param unknown $rawDataAr
	 */
	private function normalizeRecord($rawDataAr) {
		$rawDataAr[19] = $this->normalizeNumber($rawDataAr[19]); 
		$rawDataAr[20] = $this->normalizeNumber($rawDataAr[20]); 
		return $rawDataAr;
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
			'ID',
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
			'ID',
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
			'Betrag',
		);
		
		$sql = "SELECT * FROM buchungen";
		$ret = $this->runQuery($sql);
		
		print "<table class='$class' data-tagFilter='all'>";
		
		print "<tr>";
		foreach ($headers as $header) {
			print("<th>$header</th>");
		}
		print "</tr>";
		
		while ($buchung = $ret->fetchArray(SQLITE3_ASSOC)) {
			$this->collapseVWZ($buchung);
			$buchungTags		= $this->getTagsForBuchung($buchung['ID']);
			$buchung['tags']	= implode(',',$buchungTags);
			
			#set table-row attributes
			print "<tr ";
			foreach ($data as $columnName) {
				printf("data-$columnName='%s' ", $buchung[$columnName]);
			}
			if (!empty($buchungTags))
				printf("data-hasTags='%s' ", implode(' ', $buchungTags));
			print " >";
			
			#print values
			foreach ($colNames as $columnName) {
				if ('comment' == $columnName){
					#needs to go into one line because PRE formatting would cause 
					# an endless amount of empty lines on the canvas
					?>
					<td data-state="read"><i class='fa fa-pencil' onclick="editComment(this);" ></i><span class="commentContent"><?php print $buchung[$columnName] ?></span></td>
					<?php
				}
				else 
					print "<td>$buchung[$columnName]</td>\n";
			}
			print "</tr>";
		}
		print "</table>";
	}
	/**
	 * gets you all the tagIDs associated with the buchungID.
	 * @param integer $buchungID
	 * @return array
	 */
	public function getTagsForBuchung($buchungID) {
		$sql = "SELECT tagID from buchungXtag WHERE buchungID = $buchungID";
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
	
	
	public function editComment() {
		if (empty($_GET['ID']))
			throw new Exception ('No ID, no comment');
		if (empty($_GET['comment']))
			return;
		$sql = sprintf("UPDATE buchungen SET comment='%s' WHERE ID='%s'"
			,$_GET['comment']
			,$_GET['ID']
		);
		$this->runQuery($sql);
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
		if ('NEW' == $input['ID']) 
			$input['ID'] = $this->ruleCreate();
		
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
	 * applies a single rule to all buchungen.
	 */
	public function ruleApply($ruleID) {
		#TODO apply LUXUS and RECURRING to buchung
		
		#get list of tags that go with this rule.
		$sqlTags = "SELECT tagID FROM 'ruleXtag' WHERE ruleID = $ruleID";
		$retTags = $this->runQuery($sqlTags);
		$tags = $this->toArraySingleRow($retTags);
		if (!$tags) { #no tags, no work.
			d("Rule $ruleID has no tags");
			return;
		}
		d("will apply following tags: ".implode(', ', $tags));
		
		#get the regex for the rule
		$sqlRule = "SELECT filter FROM 'rules' WHERE ID = $ruleID";
		$retRule = $this->runQuery($sqlRule);
		$ruleAr  = $this->toArraySingleRow($retRule);
		$filter  = array_shift($ruleAr);
		d("Rule $ruleID has filter '$filter'");
		if (!$filter)
			return;
		
		#get a list of all buchungen
		$sql = "SELECT * FROM buchungen";
		$retBuchungen = $this->runQuery($sql);
		
		foreach ($this->toArray($retBuchungen) as $buchungID => $buchung) {
			if (!$this->buchungMatchesFilter($buchung, $filter))
				continue;
			
			$this->buchungApplyTags($buchungID, $tags, $ruleID);
			$this->buchungApplyRecurrence($buchungID, $ruleID);
			$this->buchungApplyLuxus($buchungID, $ruleID);
		}
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
		
		if ($newVal == '')
			return; #no value, no copy
		
		$sqlGetCurrentVal = "SELECT $name FROM buchungen WHERE ID=$buchungID";
		$currentVal = $this->toSingleValue($this->runQuery($sqlGetCurrentVal));
		
		if ($currentVal == $newVal)
			return; #both the same? no work
		
		if ($currentVal == '') { #no old value? set.
			$sqlSetVal = "UPDATE `buchungen` SET `$name`=$newVal WHERE `ID`=$buchungID;";
			$this->runQuery($sqlSetVal);
			return; #work done
		}
		
		#still here? last thing left: old value != new value. corruption
		throw new Exception ("Cannot apply rule $ruleID's $name to buchung $buchungID because buchung already has $name of $currentVal set and rule requires $newVal");
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
}
