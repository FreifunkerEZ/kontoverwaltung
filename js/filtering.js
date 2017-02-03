function tagFilterToggle(tagDiv) {
	//first acutally toggle tag
	if ('true' === $(tagDiv).attr('data-showTag'))
		$(tagDiv).attr('data-showTag', 'false');
	else 
		$(tagDiv).attr('data-showTag', 'true');
	
	$(tagDiv).find('i.fa-eye, i.fa-eye-slash')
			.toggleClass('fa-eye fa-eye-slash tagHidden tagVisible')
	;
	tagFilterShow('filtered');
}

/**
 * shows or hides rows in the buchungen table.
 * 
 * @param {string} showWhat - OPTIONAL.
 * can be 
 * 'filtered': shows buchungen according to their tags and their show/hide-settings.
 * 'all': shows all buchungen.
 * 'untagged': shows only buchungen with no tags on.
 * if omitted, the last sort-method is applied again.
 * @returns {undefined}
 */
function tagFilterShow(showWhat) {
	if (is_string(showWhat)) {
		//write down what the requested view is.
		setUrlBarParams('filter', showWhat);
	}
	else {//load what the last filter was from URL-bar
		showWhat = getUrlQueryParams('filter');
		if (!showWhat) //default to 'all' if that did not work.
			showWhat = 'all';
	}
	
	var rows = $(document).find('table.records tr');
	//reset playing field: make everything go away and show headers.
	rows.hide().find('th').parent().show();
	
	if ('filtered' === showWhat) {
		//find a list of all the things that should be visible
		var selected = $('.tagBrowser').find('div[data-showTag=true]');
		//make them show up one after another
		for (var i = 0; i < selected.length; i++) {
			var tagId = $(selected[i]).attr('data-ID');
			//the ~= selector searches for whole words in the attr's value.
			rows.filter('[data-hasTags~='+tagId+']').show();
		}
	}
	else if ('all' === showWhat) {
		rows.show();
	}
	else if ('untagged' === showWhat) {
		rows.filter(':not([data-hasTags])').show()
	}
	else if ('none' === showWhat) {
		//nothing. just the header from above.
	}
	else {
		alert("unknown filtermethod " + showWhat);
	}
	
	filterDate();
	filterLuxus();
	filterFullText();
	statsUpdateSum();
	statsUpdateCount();
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

function tagFilterShowAll(button) {
	var tagDiv = $(button).parent();
	$(tagDiv).find('[data-showTag]').attr('data-showTag', 'true');
	$(tagDiv).find('i.fa-eye, i.fa-eye-slash')
			.removeClass('fa-eye-slash tagHidden')
			.addClass('fa-eye tagVisible')
	;
	tagFilterShow('all');
}

function tagFilterShowNone(button) {
	var tagDiv = $(button).parent();
	$(tagDiv).find('[data-showTag]').attr('data-showTag', 'false');
	$(tagDiv).find('i.fa-eye, i.fa-eye-slash')
			.removeClass('fa-eye tagVisible')
			.addClass('fa-eye-slash tagHidden')
	;
	tagFilterShow('none');
}

