<?php

class CashDBImport extends CashDBInit {
	private function _isUninterestingLine($line) {
			if (!trim($line)) #ignore empty lines
				return TRUE;
			
			$headers = <<<EOF
VWZ1.VWZ2.VWZ3.VWZ4.VWZ5.VWZ6.VWZ7.VWZ8.VWZ9.VWZ10.VWZ11.VWZ12.VWZ13.VWZ14
"GLS Bank"
"Umsatzanzeige"
"BLZ:";"43060967";;"Datum:";"
"Konto:";"8220778400";;"Uhrzeit:";"
"Abfrage von:";"Christian Kalk";;"Kontoinhaber:";"Christian und Steffi Kalk"
"Zeitraum:";"Alle Umsätze";"von:";.*;"bis:";.*
"Zeitraum:";;"von:";.*;"bis:";
"Betrag in EUR:";;"von:";" ";"bis:";" "
"Sortiert nach:";"Buchungstag";"absteigend"
"Buchungstag";"Valuta";"Auftraggeber.Zahlungsempfänger";"Empfänger.Zahlungspflichtiger";"Konto-Nr.";"IBAN";"BLZ";"BIC";"Vorgang.Verwendungszweck";"Kundenreferenz";"Währung";"Umsatz";" "
;;;;;;;;;"Anfangssaldo";"EUR"
;;;;;;;;;"Endsaldo";"EUR"
EOF;
			$header_array = preg_split('/\n/', $headers);
			$header_array = array_map('trim', $header_array);

			foreach ($header_array as $uninteresting) {
				if (preg_match("/$uninteresting/", $line))
					return TRUE;
			}
		return FALSE;
	}
	
	public function importProcessUpload() {
		d("Accepting upload");
		$csvFile          = $this->importCutCSVfile();
		$dbRecordCountPre = $this->countBuchungen();
		$dupCount         = 0;
		$recordCount      = 0;
		
		foreach ($csvFile as $line) {
			$line = trim($line);
			if ($this->_isUninterestingLine($line)) {
				d("Uninteresting: $line");
				continue;
			}
			$recordCount++;
			
			$line = $this->escapeString($line);
			if ($this->importIsDuplicateRecord($line)) {
				$dupCount++;
				continue;
			}
			
			$rawDataAr = preg_split('/;/',$line);
			foreach ($rawDataAr as &$field) {
				$field = trim($field);
				$field = trim($field, '"');
				$field = trim($field);
			}
			
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
		$this->importVerifyNoDuplicates();
    } 
	
	/**
	 * check all Buchungen against each other.
	 * Only basic data-points are used.
	 */
	private function importVerifyNoDuplicates(){
		d("Checking globally for duplicates");
		$allBuchungen = $this->runQuery("SELECT * FROM buchungen");
		$count = 0;
		$duplicates = 0;
		$exceptionsList = [ # add higher ID-number of dup to list.
			1805, #3x DB fahrkarte
			1806, #3x DB fahrkarte
			1807, #3x DB fahrkarte
			1829, #paypal selber betrag
			1990, #BankCard gebühr
			2333, #BankCard gebühr
			2263, #2x Auszahlung von unterschiedlichen automaten
			2919, #diverses
			3003, #ok
			3298,
		];
		foreach ($this->toArray($allBuchungen) as $buchung) {
			$count++;
			$sql = sprintf("SELECT * FROM buchungen WHERE Betrag='%s' AND Buchungstag='%s' AND ID > %s",
				$buchung['Betrag'],
				$buchung['Buchungstag'],
				$buchung['ID']
			);
			$possibleDuplicates = $this->runQuery($sql);
			foreach ($this->toArray($possibleDuplicates) as $dup) {
				if (in_array($dup['ID'], $exceptionsList))
					continue;
				
				if (   !empty($dup['Kontostand'])				# if both orig 
					&& !empty($buchung['Kontostand'])			# and dup have a kontostand
					&& $buchung['Kontostand'] != $dup['Kontostand']	# but they differ
				) continue;
					
				$duplicates++;
				e(sprintf(
					"<pre>Simple duplicate %i found on Buchung %i:\n%s\n%s</pre>"
					,$duplicates
					,$buchung['ID']
					,print_r($buchung,'ret')
					,print_r($dup,'ret')
				));
			}
		}
		
		$sql = "SELECT count(*) AS dups FROM buchungen GROUP BY rawCSV HAVING dups >= 2";
		$ret = $this->toArray($this->runQuery($sql));
		d("$count Buchungen checked for dups, $duplicates possible duplicates found.");
		if ($ret) d("duplicate rawCSV:\n".print_r($ret, 'ret'));
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
#		$csvFile = preg_replace('/\n/', '', $csvFile);  # new format (2019-ish) has /n for in-line breaks and /r for end of line.
		
		$return = preg_split('/\r\n/', $csvFile);
		$num = count($return);
		d("Split file into $num lines.");
		return $return; 
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
		$this->_assertElementCount($rawDataAr);
		
		#set which record-indexes are needed to pass the validation
		$required	   = array(0, 8, 11);  # Buchungstag, Vorgang/Verwendungszweck, Umsatz
		foreach ($required as $index) {
			if (empty($rawDataAr[$index])) {
				d($rawDataAr);
				throw new Exception("No ".$this->csvHeaders[$index]." (index $index) in above record --^ line: $line");
			}
		}
	}
	
	private function _assertElementCount($rawDataAr) {
		$expectedCount = count($this->csvHeaders);
		$actualCount   = count($rawDataAr);
		if ($actualCount != $expectedCount) {
			e($rawDataAr);
			throw new Exception("expected $expectedCount elements, but got $actualCount elements on input --^");
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
			d("Duplicate raw-CSV import found: $line");
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
	
	protected function dateToYmd($dateIn) {
		if (preg_match('/(\d\d)\.(\d\d)\.(\d\d\d\d)/', $dateIn, $m))
			return sprintf("%s-%s-%s",$m[3],$m[2],$m[1]);
		
		if (preg_match('/(\d\d)\.(\d\d)\.(\d\d)/', $dateIn, $m))
			return sprintf("20%s-%s-%s",$m[3],$m[2],$m[1]);
		
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
		$rawDataAr[11] = $this->normalizeNumber($rawDataAr[11]); 
		$rawDataAr[11] = $this->sollHabenNumber($rawDataAr); 
	}
	
	private function sollHabenNumber($rawDataAr) {
		$minus =  $rawDataAr[12] == 'S' ? '-' : '';
		return $minus . $rawDataAr[11];
	}
	
	private function normalizeNumber($n) {
		if ('' == trim($n))
			return $n;
		
		$matches = array();
		if (!preg_match('/(-?)([\d.]*),?(\d{0,2})\D?/', $n, $matches))
			e("funny money. regex does not match: $n");
		
		$matches[2] = str_replace('.', '', $matches[2]); #
		# d( "$n = ".$matches[1].$matches[2].'.'.$matches[3]);
		return $matches[1].$matches[2].'.'.$matches[3];
	}
}
