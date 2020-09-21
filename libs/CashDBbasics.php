<?php
class CashDBbasics extends SQLite3 {

	protected function scrubInputs($input) {
		#TODO scrubbing!!!!!
		$output = $input;
		return $output;
	}
	
	protected function tableExists($tableName)  {
		$sql = "SELECT count(*) FROM sqlite_master WHERE type='table' AND name='$tableName'";
		$ret = $this->runQuery($sql);
		$row = $ret->fetchArray(SQLITE3_NUM);
		return $row[0];
	}
	
	/**
	 * @param string $sql what to exec
	 * @return the return of the query. get at your content with fetchArray(SQLITE3_ASSOC)
	 * @throws Exception if there was no return. message contains lastErrorMsg()
	 */
	protected function runQuery($sql) {
		$ret = $this->query($sql);
		if(!$ret){
			throw new Exception("Problem ".$this->lastErrorMsg()." on SQL Statement: $sql");
		} else {
			return $ret;
		}
	}
	
	/**
	 * creates an sqlite SET statement.
	 * builds the fields to set from your input.
	 * add values outside of the SET (like WHERE ____) later with $stmt->bindValue();
	 * 
	 * @param string $sqlFormat an sql instruction.
	 * must contain a printf-string-placeholder (%s) where the fields to SET go.
	 * ex: "UPDATE buchungen SET %s WHERE ID=:ID"
	 * @param hash $inputHash the data which to insert
	 * @param array $keysToUse OPTIONAL - 
	 * if given the list of keys which to read from the $inputHash.
	 * if not given, the whole $inputHash is used.
	 * @return SQLite3Stmt
	 */
	protected function formatSetStatement($sqlFormat, $inputHash, $keysToUse = null) {
		if ($keysToUse === null)
			$keysToUse = array_keys($inputHash);
		
		$setParts = array();
		foreach ($keysToUse as $key) {
			$setParts[] = "$key=:$key";
		}
		$sql = sprintf($sqlFormat, implode(', ', $setParts));
		
		$stmt = $this->prepare($sql);
		foreach ($keysToUse as $field) {
			$stmt->bindValue(":$field", $inputHash[$field]);
		}
		return $stmt;
	}

	
	/**
	 * turns an runQuery() return into an array by running 
	 * fetchArray(SQLITE3_ASSOC) and stuffing the result into
	 * a numeric array until it ends.
	 * @param SQLite3Result  $ret
	 * @return array full of hashes, one hash for each line.
	 * if a column 'ID' is present, the array is keyed by the ID-value.
	 * otherwise not.
	 */
	protected function toArray($ret) {
		$output = array();
		while ($row = $ret->fetchArray(SQLITE3_ASSOC)) {
			if (isset($row['ID']))
				$output[$row['ID']] = $row;
			else
				$output[] = $row;
		}
		return $output;
	}
	
	public function countBuchungen() {
		$sql = "SELECT count(*) FROM buchungen;";
		$ret = $this->runQuery($sql);
		$out = $ret->fetchArray();
		return $out[0];
	}
	
	/**
	 * if the result has only a single row, you may use this to turn the
	 * sqlite response directly into a single, numeric array.
	 * if the response has multiple rows, the first row is used.
	 * 
	 * @param SQLite3Result  $ret
	 * @return hash containing the first row-value of the lines of the result-set.
	 * keyed by the field 'ID' if present.
	 * if no ID is present, the array is numeric.
	 */
	protected function toArraySingleRow($ret) {
		$output = array();
		while ($row = $ret->fetchArray(SQLITE3_ASSOC)) {
			#turn row-hash into simple array
			$ar = array_values($row);
			#then shift off the first element and store it.
			if (isset($ar['ID']))
				$output[$ar['ID']] = array_shift($ar);
			else
				$output[] = array_shift($ar);
		}
		return $output;
	}
	
	/**
	 * gets the first column of the first row of the return.
	 * 
	 * @param SQLiteResponse $ret
	 * @return mixed
	 */
	protected function toSingleValue($ret) {
		$array = $this->toArraySingleRow($ret);
		return array_shift($array);
	}
	
	/**
	 * use this in usort.
	 * brings the buchungen into chronological order by comparing field 'BuchungstagSortable'.
	 * 
	 * @param hash $a buchung
	 * @param hash $b buchung
	 * @return int
	 */
	protected function sortCompare($a, $b) {
		if ($a['BuchungstagSortable'] == $b['BuchungstagSortable']) {
			return 0;
		}
		return ($a['BuchungstagSortable'] > $b['BuchungstagSortable']) ? -1 : 1;
	}
			
	
}