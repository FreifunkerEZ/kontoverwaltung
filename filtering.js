function tagFilterToggle(tagDiv) {
	//first acutally toggle tag
	if ('true' === $(tagDiv).attr('data-showTag'))
		$(tagDiv).attr('data-showTag', 'false');
	else 
		$(tagDiv).attr('data-showTag', 'true');
	
	$(tagDiv).find('i.fa-eye, i.fa-eye-slash')
			.toggleClass('fa-eye fa-eye-slash')
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
	if (!showWhat)
		showWhat = $('table.records').attr('data-tagFilter');
	else
		//write down what the current view is.
		$('table.records').attr('data-tagFilter',showWhat);
	
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
	
	statsSumUpdate();
}
/**
 * applies the content of the date-filter-box as regex against 
 * BuchungstagSortable
 * 
 * @returns {undefined}
 */
function filterDate() {
	//reset view by restoring previous filter
	tagFilterShow();
	
	var date = $('[name=filterDate]').val();
	var rows = $('table.records').find('tr:visible');
	for (var i=0; i < rows.length; i++) {
		var row = $(rows[i]);
		
		if (!row.attr('data-BuchungstagSortable'))
			continue; //must be header
		
		if ( !row.attr('data-BuchungstagSortable').match('^'+date))
			row.hide();
	}
	statsSumUpdate();
}

function tagFilterShowAll(button) {
	var tagDiv = $(button).parent();
	$(tagDiv).find('[data-showTag]').attr('data-showTag', 'true');
	$(tagDiv).find('i.fa-eye, i.fa-eye-slash')
			.removeClass('fa-eye-slash')
			.addClass('fa-eye')
	;
	tagFilterShow('all');
}

function tagFilterShowNone(button) {
	var tagDiv = $(button).parent();
	$(tagDiv).find('[data-showTag]').attr('data-showTag', 'false');
	$(tagDiv).find('i.fa-eye, i.fa-eye-slash')
			.removeClass('fa-eye')
			.addClass('fa-eye-slash')
	;
	tagFilterShow('none');
}

