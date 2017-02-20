/**
 * will toggle the visibility-setting of the tag-div.
 * you should call filtersApply() afterwards to make the changes visible.
 * 
 * @param {DOMelement} tagDiv
 * @returns {undefined}
 */
function tagFilterToggle(tagDiv) {
	//first acutally toggle tag
	if ('true' === $(tagDiv).attr('data-showTag'))
		$(tagDiv).attr('data-showTag', 'false');
	else 
		$(tagDiv).attr('data-showTag', 'true');
	
	$(tagDiv).find('i.fa-eye, i.fa-eye-slash')
			.toggleClass('fa-eye fa-eye-slash tagHidden tagVisible')
	;
}

/**
 * sets a tag-button to a desired state.
 * 
 * @param {jQuery-input} tagDiv
 * @param {string} desiredState - available states are 'visible' and 'hidden'
 * @returns {undefined}
 */
function tagFilterSet(tagDiv, desiredState) {
	if ('visible' === desiredState) { //want visible
		if('true' === $(tagDiv).attr('data-showTag')) //and is already visible
			return; //then ignore
	}
	else if ('hidden' === desiredState) {
		if('false' === $(tagDiv).attr('data-showTag'))
			return;
	}
	else {
		alert("wrong desired state");
	}
	tagFilterToggle(tagDiv);
}

/**
 * sets all tag-buttons to the desired state
 * 
 * @param {type} desiredState - available states are 'visible' and 'hidden'
 * @returns {undefined}
 */
function tagFilterSetAll(desiredState) {
	var tagSwitches = $('.tagBrowser div[data-showTag]');
	for (var i=0; i<tagSwitches.length; i++) {
		tagFilterSet(tagSwitches[i], desiredState)
	}
}

/**
 * shows or hides rows in the buchungen table.
 * call this whenever you changed the filtering conditions.
 * 
 * @param {string} showWhat - OPTIONAL.
 * can be 
 * 'filtered': shows buchungen according to their tags and their show/hide-settings.
 * 'all': shows all buchungen.
 * 'untagged': shows only buchungen with no tags on.
 * if omitted, the last sort-method is applied again.
 * @returns {undefined}
 */
function filtersApply(showWhat) {
	if (is_string(showWhat)) {
		//write down what the requested view is.
		setUrlBarParams('filter', showWhat);
	}
	else {//load what the last filter was from URL-bar
		showWhat = getUrlQueryParams('filter');
		if (!showWhat) //default to 'all' if that did not work.
			showWhat = 'all';
	}
	
	var rows = $('table.records tr');
	//reset playing field: make everything go away and show headers.
	rows.hide().find('th').parent().show();
	
	if ('filtered' === showWhat) {
		filterShowByTags(rows);
	}
	else if ('all' === showWhat) {
		tagFilterSetAll('visible');
		rows.show();
	}
	else if ('untagged' === showWhat) {
		rows.filter(':not([data-hasTags])').show();
	}
	else if ('none' === showWhat) {
		tagFilterSetAll('hidden');
		//show nothing. just the header from above.
	}
	else {
		alert("unknown filtermethod " + showWhat);
	}
	
	filterHighlightButton(showWhat);
	filterDate();
	filterLuxus();
	filterFullText();
	statsUpdateSum();
	statsUpdateCount();
	statsUpdateTags();
}

function filterHighlightButton(showWhat) {
	//reset color of all filter buttons
	$('button[data-filter]').css({'background-color':''});
	//set color of right filter button
	$('button[data-filter='+showWhat+']').css({'background-color':'lemonchiffon'});
}

/**
 * looks at the tags.
 * sees if they are supposed to be displayed or not.
 * combines that with the filter-mode.
 * shows the correct buchungen according to that.
 * all buchungen must be hidden before calling this or you get funny results.
 * 
 * @param {jQuery} rows the table-rows to display.
 * @returns {undefined}
 */
function filterShowByTags(rows) {
	//first figure out the filter-mode
	var mode = $('input[name=filterMode]:checked').val();

	//find a list of all the things that should be visible
	var selected = $('.tagBrowser div[data-showTag=true]');
	
	if ('and' === mode) {
		var filterstring = '';
		//create filterstring by putting all the selected tags together
		for (var i = 0; i < selected.length; i++) {
			var tagId = $(selected[i]).attr('data-ID');
			//the ~= selector searches for whole words in the attr's value.
			filterstring += '[data-hasTags~='+tagId+']';
		}
		//make buchungen show up according to filterstring
		rows.filter(filterstring).show();
	}
	else if ('or' === mode) {
		//make them show up one after another
		for (var i = 0; i < selected.length; i++) {
			var tagId = $(selected[i]).attr('data-ID');
			//the ~= selector searches for whole words in the attr's value.
			rows.filter('[data-hasTags~='+tagId+']').show();
		}
	}
	else {
		alert("logic filter broken?!");
	}
}


/**
 * applies the content of the date-filter-box as regex against 
 * BuchungstagSortable
 * 
 * @returns {undefined}
 */
function filterDate() {
	var date = $('[name=filterDate]').val();
	setUrlBarParams('date', date);
	
	if (!date) //no date, no work
		return;
	
	var rows = $('table.records').find('tr:visible[data-luxus]');
	for (var i=0; i < rows.length; i++) {
		var row = $(rows[i]);
		
		if ( !row.attr('data-BuchungstagSortable').match('^'+date))
			row.hide();
	}
}
/**
 * applies the content of the date-filter-box as regex against 
 * BuchungstagSortable
 * 
 * @returns {undefined}
 */
function filterLuxus() {
	var luxus = $('.luxus-slider').val().split(',');
	
	if (luxus.length != 2) //no value, no work
		return;
	
	var rows = $('table.records').find('tr:visible[data-luxus]');
	for (var i=0; i < rows.length; i++) {
		var row = $(rows[i]);
		//plus turns the results into numbers
		if (	   +row.attr('data-luxus') < luxus[0] 
				|| +row.attr('data-luxus') > luxus[1] 
		)
			row.hide();
	}
}

function filterMode() {
	//add persistence
	var defaultValue = 'or';
	var desiredValue = $('input[name=filterMode]:checked').val();
	if (defaultValue === desiredValue)
		setUrlBarParams('filterMode');
	else
		setUrlBarParams('filterMode', desiredValue);
}

function filterFullText() {
	var filter = $('input[name=filterFullText]').val();
	setUrlBarParams('fts', filter);
	
	if (!filter)
		return;
	
	var rows = $('table.records').find('tr:visible[data-luxus]');
	for (var i=0; i < rows.length; i++) {
		var rawData = $(rows[i]).attr('data-rawCSV');
		var regex = RegExp(filter, 'i');
		if (! rawData.match(regex))
			$(rows[i]).hide();
	}
	
		
}