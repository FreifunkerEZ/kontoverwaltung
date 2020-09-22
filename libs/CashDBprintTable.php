<?php

class CashDBprintTable extends CashDBImport {
	private $html_table_headers = array(
		'Edit',
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
		private $html_table_column_names = array(
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
	private $html_table_data_field_names = array(
		'ID',
		'Kontostand',
		'importdate',
		'BuchungstagSortable',
		'rawCSV',
		'luxus',
		'Betrag',
	);
	private function _printHeaderRow() {
		print "<tr>";
		print '<th onclick="buchungToggleSelectionAll(this);" >'
				. '<i class="fa fa-square-o" aria-hidden="true"></i>'
				. '</th>'
		;
		foreach ($this->html_table_headers as $header) {
			print("<th>$header</th>");
		}
		print "</tr>\n";

	}

	private function row_selector_checkbox_TD() {
		return '<td onclick="buchungToggleSelection(this);">'
			. '<i class="fa fa-square-o rowSelector" aria-hidden="true"></i>'
			. '</td>';
	}

	private function edit_pencil_TD() {
		return '<td onclick="buchungEditorOpen(this);">'
				. '<i class="fa fa-pencil" aria-hidden="true"></i>'
				. '</td>';
	}
	
	protected function _get_all_buchungen_sorted() {
		$sql  = "SELECT * FROM buchungen";
		$ret  = $this->runQuery($sql);
		$buchungen = $this->toArray($ret);
		uasort($buchungen, array($this, 'sortCompare'));
		return $buchungen;
	}
	
	/**
	 * Prints a nice table with all the buchungen in the database.
	 * i know it should not do this, but currently i got no better idea quickly.
	 * 
	 * @param string $class which CSS-class the table should have.
	 */
	public function printData($class) {
		$buchungen = $this->_get_all_buchungen_sorted();
		print "<table class='$class'>";
		
		$this->_printHeaderRow();
		
		foreach ($buchungen as $buchung) {
			$this->_print_data_row($buchung);
		}
		print "</table>";
	}

	private function _print_data_row($buchung) {
		$this->collapseVWZ($buchung);
		$buchung['tags_array']	= $this->getTagsForBuchung($buchung['ID']);
		$buchung['tags']		= implode(',',$buchung['tags_array']);
		
		$tagNames				= $this->getTagsName($buchung['tags_array']);
		$buchung['tagTitle']	= implode(', ', $tagNames);

		print "<tr ";
			$this->_print_data_row_attributes($buchung);
		print " >";

		print $this->row_selector_checkbox_TD();
		print $this->edit_pencil_TD();

		#print values
		$this->_print_data_row_value_tds($buchung);
		print "</tr>";
	}
	
	private function _print_data_row_attributes($buchung) {
		print "style='display:none' "
			. "title='ID: {$buchung['ID']}'"
		;
		foreach ($this->html_table_data_field_names as $columnName) {
			printf("data-$columnName='%s' ", $buchung[$columnName]);
		}
		if (!empty($buchung['tags_array']))
			printf("data-hasTags='%s' ", implode(' ', $buchung['tags_array']));
	}
	
	private function _print_data_row_value_tds($buchung) {
		foreach ($this->html_table_column_names as $columnName) {
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
	private function getTagsForBuchung($buchungID) {
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
	private function getTagsName($tags) {
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
	
}
