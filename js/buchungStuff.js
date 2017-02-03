function buchungToggleSelection(td) {
	$(td).find('i').toggleClass('fa-square-o fa-check-square-o');
}
function buchungToggleSelectionAll(th) {
	var icon = $(th).find('i');
	icon.toggleClass('fa-square-o fa-check-square-o');
	
	if (icon.hasClass('fa-check-square-o')) //everything should be selected.
		$('table.records td i.rowselector')
			.removeClass('fa-square-o')
			.addClass('fa-check-square-o')
		;
	else //nothing should be selected
		$('table.records td i.rowselector')
			.removeClass('fa-check-square-o')
			.addClass('fa-square-o')
		;
		
}
function buchungEditorOpen(td) {
	$('.buchungEditor').remove(); //destroy all old clones
	var tr = $(td).closest('tr');
	
	//clone and insert editor.
	var editor = $('div.buchungEditorTemplate').clone('with events');
	editor
			.toggleClass('buchungEditorTemplate buchungEditor')
			.appendTo($('body'))
	;
	editor.find('input[name=comment]').focus();
	
	//load values
	var ID = tr.attr('data-ID');
	editor.find('span.ID').text(ID);
	
	var things = ['comment', 'luxus', 'recurrence'];
	for (var i=0; i<things.length; i++) {
		var value = tr.find('.buchung'+things[i]).text();
		editor.find('input[name='+things[i]+']').val(value);
	}
	
	var hasBox = editor.find('.arbHasBox > select');
	arbAddRemoveBoxFill(hasBox,[]); //empty has-box for now
	var canHaveBox = editor.find('.arbCanHaveBox > select');
	arbAddRemoveBoxFill(canHaveBox); //add all tags to the canhave-box
	
	var hasTagsAr = ($(td).closest('tr').attr('data-hasTags') 
					? $(td).closest('tr').attr('data-hasTags').split(' ')
					: []
	);
	//'click' the add button for each tag we have, 
	//thus moving it over from CanHave to Has
	for (var i=0; i < hasTagsAr.length; i++) {
		editor.find('.arbCanHaveBox select').val( hasTagsAr[i] );
		arbAddButton( editor.find('.arbControls') );
	}
}

function buchungEditorSave(button) {
	var editor = $(button).closest('.buchungEditor');
	var params = {
		ID:			editor.find('span.ID').text(),
		tags:		arbCollectHasTags(button)
	};
	
	var things = ['comment', 'luxus', 'recurrence'];
	for (var i=0; i<things.length; i++) {
		params[things[i] ] = editor.find('input[name='+things[i]+']').val()
	}
	//console.log(params);
	
	$.get(
			'?action=buchungEdit',
			params,
			function(data) {
				console.log(data);
				location.reload();
			}
	)
	.fail(
		function(data) {
			console.log(data);
			alert('Saving has failed :(');
		}
	);
}