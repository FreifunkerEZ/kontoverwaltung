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
		$backupFile = dirname($fullPath) .'/'. date('Y-m-d')."-KontoDatenbank.SQLite3";
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
	
	public function printData() {
		$sql = "SELECT * FROM buchungen";
		$ret = $this->runQuery($sql);
		
		$headers = $this->catVWZ($this->csvHeaders, 'isheader');
		$headers[] = 'ID';
		$headers[] = 'importdate';
		$headers[] = 'Buchungstag';
		$headers[] = 'rawCSV';
		$headers[] = 'luxus';
		print "<table class='records'>";
		print "<tr>";
		foreach ($headers as $header) {
			print("<th>$header</th>");
		}
		
		print "</tr>";
		while ($row = $ret->fetchArray(SQLITE3_NUM)) {
			print "<tr>";
			$row = $this->catVWZ($row);
			foreach ($row as $index => $field) {
				print "<td> $field </td>\n";
			}
			print "</tr>";
		}
		print "</table>";
	}
	
	private function catVWZ($record, $isHeader = false) {
		#VWZ is index 5-18
		$output = array();
		$verwendungszweck = array();
		foreach ($record as $index => $field) {
			if ($index < 5 || $index > 18) { #if not VZW
				$output[] = $field;          #copy field and done
				continue;
			}
			
			if (trim($field))	#if not empty
				$verwendungszweck[] = $field; #put field into bucket
			
			if (18 == $index) #on the last VWZ either insert bucket or label
				$output[] = ($isHeader ? 'Verwendungszweck' : implode("\n", $verwendungszweck));
		}
		return $output;
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
	public function rulesList($filter = '') {
		$sql = "SELECT * FROM rules".($filter ? ' WHERE '.$filter : '');
		return $this->toArray($this->runQuery($sql));
	}
	
	function ruleGetTags($ruleID) {
		$sql = "SELECT tagID FROM ruleXtag WHERE ruleID = '$ruleID'";
		$ret = $this->runQuery($sql);
		$has = $this->toArraySingleRow($ret);
		
		$hasString = implode(',', $has);
		$sql = "SELECT ID FROM tags WHERE ID NOT IN ($hasString)";
		$ret = $this->runQuery($sql);
		$canHave = $this->toArraySingleRow($ret);
		return array('has' => $has, 'canHave' => $canHave);
	}
	
	/**
	 * turns an runQuery() return into an array by running 
	 * fetchArray(SQLITE3_ASSOC) and stuffing the result into
	 * a numeric array until it ends.
	 * @param SQLite3Result  $ret
	 * @return numeric array full of hashes, one hash for each line
	 */
	private function toArray($ret) {
		$output = array();
		while ($row = $ret->fetchArray(SQLITE3_ASSOC)) {
			$output[] = $row;
		}
		return $output;
	}
	
	/**
	 * if the result has only a single row, you may use this to turn the
	 * sqlite response directly into a single, numeric array.
	 * @param SQLite3Result  $ret
	 * @return numeric array containing the lines of the result-set.
	 */
	private function toArraySingleRow($ret) {
		$output = array();
		while ($row = $ret->fetchArray(SQLITE3_ASSOC)) {
			#turn row-hash into simple array
			$ar = array_values($row);
			#then shift off the first element and store it.
			$output[] = array_shift($ar);
		}
		return $output;
	}
}
