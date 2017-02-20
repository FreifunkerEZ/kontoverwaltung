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
	var ruleID  = editor.find('[name=ID]').text();
	var params  = {
		ID:			ruleID,
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
		function(data) {
			console.log(data);
			if (ruleID === 'NEW')
				ruleID = data.match(/newRuleId:'(\d+)'/);
			ruleApply(button, ruleID[1]);
		}
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

/**
 * 
 * @param {type} button
 * @param {type} ruleID OPTIONAL - 
 * overrides the ID retrieved from the canvas.
 * used when a new rule is created (which has no ID at that point) 
 * and applied right away.
 * @returns {undefined}
 */
function ruleApply(button, givenRuleID) {
	var ruleID = $(button).parent().find("[name=ID]").text();
	
	if (givenRuleID) //override the ruleID from the canvas. 
	// it will probably say 'NEW' when this is required
		ruleID = givenRuleID;
	
	$.post(
		'?action=ruleApply',
		{ruleID : ruleID},
		function(data) {console.log(data);location.reload();}
	)
	.fail(function(data){alert("Failed to apply rule.");console.log(data);})
	;
}
