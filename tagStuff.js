function tagOpenEditor(editDiv) {
	var editor = $('.tagEditor').show();
	var input = $(editDiv).parent();
	var justifies = (input.attr('data-justifies') === '1' ? true : false);
	editor.find('[name=ID]').text(input.attr('data-id'));
	editor.find('[name=name]').val(input.attr('data-name')).focus();
	editor.find('[name=comment]').val(input.attr('title'));
	editor.find('[name=color]').val(input.attr('data-color'));
	editor.find('[name=justifies]').prop('checked', justifies);
}
function tagNewOpen() {
	var editor = $('.tagEditor').show();
	editor.find('[name=ID]').text('NEW');
	editor.find('[name=name]').focus();
	editor.find('input').val('');
	editor.find('input[type=color]').val('#FFFFFF');
	editor.find('[name=justifies]').prop('checked', true);
}
function tagSave(button){
	var editor = $(button).parent();
	var params = {
		ID:			editor.find('[name=ID]').text(),
		name:		editor.find('[name=name]').val(),
		comment:	editor.find('[name=comment]').val(),
		color:		editor.find('[name=color]').val(),
		justifies:	editor.find('[name=justifies]').prop('checked')
	};
	jQuery.post('?action=tagSave',
		{params : JSON.stringify(params)},
		function (data) {console.log(data);location.reload();}
	)
		.fail(function(data){alert('save failed.');console.log(data);})
	;
}

function tagDelete(button) {
	if (confirm("Dieses Tag wirklich löschen? TODO noch nicht klar was dann passiert."))
		deleteSomething(button, 'tagDelete');
}
