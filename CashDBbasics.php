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
	

	
}