function tagOpenEditor(tagDiv) {
	var editor = $('.tagEditor').show();
	var input = $(tagDiv);
	var justifies = (input.attr('data-justifies') === '1' ? true : false);
	editor.find('[name=ID]').text(input.attr('data-id'));
	editor.find('[name=name]').val(input.attr('data-name'));
	editor.find('[name=comment]').val(input.attr('title'));
	editor.find('[name=color]').val(input.attr('data-color'));
	editor.find('[name=justifies]').prop('checked', justifies);
}
function tagNewOpen() {
	var editor = $('.tagEditor').show();
	editor.find('[name=ID]').text('NEW');
	editor.find('input').val('');
	editor.find('[name=justifies]').prop('checked', false);
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
function ruleOpenEditor(ruleDiv) {
	var editor = $('.ruleEditor').show();
	var input = $(ruleDiv);
	
	editor.find('[name=ID]').text(input.attr('data-id'));
	editor.find('[name=name]').val(input.attr('data-name'));
	editor.find('[name=comment]').val(input.attr('title'));
	editor.find('[name=filter]').val(input.attr('data-filter'));
	editor.find('[name=luxus]').val(input.attr('data-luxus'));
	editor.find('[name=recurrence]').val(input.attr('data-recurrence'));
	
	var has    = JSON.parse(input.attr('data-tagHas'));
	var hasBox = editor.find('.arbHasBox select');
	hasBox.find('*').remove();
	for (var i=0;i<has.length;i++) {
		$('<option>').text(has[i]).appendTo(hasBox);
	}
	
	var canHave    = JSON.parse(input.attr('data-tagCanHave'));
	var canHaveBox = editor.find('.arbCanHaveBox select');
	canHaveBox.find('*').remove();
	for (var i=0; i < canHave.length; i++) {
		var tagString = canHave[i]+':'+tags[i].name;
		$('<option>').text(tagString).appendTo(canHaveBox);
		//TODO actually insert names here. not tag-ids
	}
}
function ruleSave(button){
	//TODO implement save
}
