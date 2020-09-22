<?php

class CashDB extends CashDBprintTable {
	/**
	 * a numeric array with the column-headers of the raw CSV format.
	 * Will be filled dynamically from an exploded string (below) during construct.
	 * is used to construct SQL-Statements and nice output tables.
	 * @var array
	 */
	private $csvHeaders = array();
	
	/*
	 *  the 2019 headers							the DB fields
	 *  0 "Buchungstag";							Buchungstag
	 *  1 "Valuta";									Wertstellung
	 *  2 "Auftraggeber/Zahlungsempfänger";			VWZ2
	 *  3 "Empfänger/Zahlungspflichtiger";			AuftraggeberEmpfaenger
	 *  4 "Konto-Nr.";	leer						Kontonummer
	 *  5 "IBAN";	wir, bei überweisungen die		VWZ3
	 *  6 "BLZ";	leer							VWZ4
	 *  7 "BIC";									VWZ5
	 *  8 "Vorgang/Verwendungszweck";				VWZ1
	 *  9 "Kundenreferenz";	leer					VWZ6
	 * 10 "Währung";								Waehrung
	 * 11 "Umsatz";	niemals minus. check S/H --v	Betrag
	 * 12 " "	soll/haben							VWZ7
	 * 
	 * and how that must look like for the database --v
	 */
	private $headerString = 'Buchungstag,Wertstellung,VWZ2,AuftraggeberEmpfaenger,Kontonummer,VWZ3,VWZ4,VWZ5,VWZ1,VWZ6,Waehrung,Betrag,VWZ7';
	
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

	private function checkPlausibility() {
		#TODO check plausibility
		#check for holes in the records bigger than NNN days.
		#check for excessive amounts
		#compare given sum to calculated sum
	}
	
	/*
	 * Find buchungen which are too far apart to see where data is missing.
	 */
	public function findHoles() {
		$buchungen = $this->_get_all_buchungen_sorted();
		$max_diff_secs = 10 * 24*3600;
		$previous = reset($buchungen);  # prime with the first buchung by resetting the array-pointer.
		foreach ($buchungen as $current) {
			$this->fix_BuchungstagSortable($current);
			$diff = abs($this->dateToEpoch($current) - $this->dateToEpoch($previous));
			if ($diff > $max_diff_secs) {
				d(sprintf("Loch %.0f tage zwischen %s und %s", 
						$diff / (24*3600), 
						$current['Buchungstag'],
						$previous['Buchungstag']
				));
			}
			$previous = $current;
		}
	}
	
	/*
	 * Fix bad values in 'BuchungstagSortable', which are derrived from
	 * the original value of 'Buchungstag' in a wrong way. (That bug is fixed.)
	 * 
	 * Will only work on broken values. 
	 * so it will self-deactivate once all values are fixed.
	 */
	private function fix_BuchungstagSortable($buchung) {
		if (preg_match('/-\d\d$/', $buchung['BuchungstagSortable']))
			return;
		d($buchung);
		$sql = sprintf("UPDATE `buchungen` "
				. "SET `BuchungstagSortable`='%s' "
				. "WHERE ID='%s';"
				,$this->dateToYmd($buchung['Buchungstag'])
				,$buchung['ID']
		);
		$this->runQuery($sql);
		
	}
	
	private function dateToEpoch($buchung) {
		if (preg_match('/(\d\d)\.(\d\d)\.(\d\d\d\d)/', $buchung['Buchungstag'], $m)) {
			return mktime( 0, 0, 0, $m[2], $m[1], $m[3] );
		}
		if (preg_match('/(\d\d)\.(\d\d)\.(\d\d)/', $buchung['Buchungstag'], $m))
			return mktime( 0, 0, 0, $m[2], $m[1], "20".$m[3] );
		e($m);
		e($buchung);
		throw new Exception("crash on ".$buchung['Buchungstag']);
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
		$this->runQuery($sql);
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
		$sql2 = "SELECT ID FROM tags WHERE ID NOT IN ($hasString)";
		$ret = $this->runQuery($sql2);
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
		
		$sql2 = "SELECT LAST_INSERT_ROWID();";
		$ret = $this->runQuery($sql2);
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
		
		$sql2 = "DELETE FROM rules WHERE ID = $id";
		$this->runQuery($sql2);
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
	 * @param int $ruleID
	 * @param int  $buchungID
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
