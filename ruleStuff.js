function ruleOpenEditor(ruleDiv) {
	var editor = $('.ruleEditor').show();
	var input = $(ruleDiv);
	
	editor.find('[name=ID]').text(input.attr('data-id'));
	editor.find('[name=name]').val(input.attr('data-name')).focus();
	editor.find('[name=comment]').val(input.attr('title'));
	editor.find('[name=filter]').val(input.attr('data-filter'));
	editor.find('[name=luxus]').val(input.attr('data-luxus'));
	editor.find('[name=recurrence]').val(input.attr('data-recurrence'));
	
	var has    = JSON.parse(input.attr('data-tagHas'));
	var hasBox = editor.find('.arbHasBox select');
	arbAddRemoveBoxFill(hasBox, has);
	
	var canHave    = JSON.parse(input.attr('data-tagCanHave'));
	var canHaveBox = editor.find('.arbCanHaveBox select');
	arbAddRemoveBoxFill(canHaveBox, canHave);
}

function ruleNewOpen(button) {
	var editor = $('.ruleEditor').show();
	editor.find('input[name=name]').focus();
	
	editor.find('[name=ID]').text('NEW');
	editor.find('input').val('');
	editor.find('input[name=filter]').val('//');
	var hasBox = editor.find('.arbHasBox > select');
	arbAddRemoveBoxFill(hasBox,[]); //empty has box as new tags don't have anything
	var canHaveBox = editor.find('.arbCanHaveBox > select');
	arbAddRemoveBoxFill(canHaveBox); //add all tags to the canhave box
}

function ruleSave(button){
	var editor  = $(button).parent();
	var hasTags = '';
	var params  = {
		ID:			editor.find('[name=ID]').text(),
		name:		editor.find('[name=name]').val(),
		comment:	editor.find('[name=comment]').val(),
		filter:		editor.find('[name=filter]').val(),
		luxus:		editor.find('[name=luxus]').val(),
		recurrence:	editor.find('[name=recurrence]').val(),
		tags:		arbCollectHasTags(button)
	};
	//console.log(params, JSON.stringify(params));
	jQuery.post('?action=ruleSave',
		{params : JSON.stringify(params)},
		function (data) {console.log(data);ruleApply(button);}
	)
		.fail(function(data){alert('save failed.');console.log(data);})
	;
}
function ruleDelete(button) {
	if (confirm("Diese Regel wirklich löschen? \n\
			Die Buchungen bleiben davon unberührt.\n\
			Die Regel kann nicht wiederhergestellt werden."
	))
		deleteSomething(button, 'ruleDelete');
}
function ruleApply(button) {
	var ruleID = $(button).parent().find("[name=ID]").text();
	$.post(
		'?action=ruleApply',
		{ruleID : ruleID},
		function (data) {console.log(data);location.reload();}
	)
	.fail(function(data){alert("Failed to apply rule.");console.log(data);})
	;
}
