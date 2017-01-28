<?php
class CashDBbasics extends SQLite3 {

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
			throw new Exception($this->lastErrorMsg());
		} else {
			return $ret;
		}
	}
	
	/**
	 * turns an runQuery() return into an array by running 
	 * fetchArray(SQLITE3_ASSOC) and stuffing the result into
	 * a numeric array until it ends.
	 * @param SQLite3Result  $ret
	 * @return numeric array full of hashes, one hash for each line.
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
	
	/**
	 * if the result has only a single row, you may use this to turn the
	 * sqlite response directly into a single, numeric array.
	 * @param SQLite3Result  $ret
	 * @return hash containing the lines of the result-set.
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

	
}