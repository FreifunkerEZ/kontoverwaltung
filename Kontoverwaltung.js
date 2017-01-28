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
	arbAddRemoveBoxFill(hasBox, has);
	
	var canHave    = JSON.parse(input.attr('data-tagCanHave'));
	var canHaveBox = editor.find('.arbCanHaveBox select');
	arbAddRemoveBoxFill(canHaveBox, canHave);
}

/**
 * empties the select-box.
 * put options into the box
 * @param {jQuery} box the select-element which is to be filled
 * @param {array} OPTIONAL - content the strings to be added to the select-box.
 * if left out, all tags are added.
 * @returns {undefined}
 */
function arbAddRemoveBoxFill(box, content) {
	box.find('option').remove();
	if (!content) {
console.log(box);
		for (var i=0; i < tags.length; i++) {
			var tagString = tags[i].ID+': '+tags[i].name;
			$('<option>')
					.text(tagString)
					.attr('data-tagID',tags[i].ID)
					.appendTo(box)
			;
		}
	}
	else {
		for (var i=0; i < content.length; i++) {
			var tagID = content[i];
			var tagString = tagID+': '+tags[tagId].name;
			$('<option>')
					.text(tagString)
					.attr('data-tagID',tagID)
					.appendTo(box)
			;
		}
	}
}

function ruleNewOpen(button) {
	var editor = $('.ruleEditor').show();
	editor.find('[name=ID]').text('NEW');
	editor.find('input').val('');
	var canHaveBox = editor.find('.arbCanHaveBox > select');
	arbAddRemoveBoxFill(canHaveBox);
}

function ruleSave(button){
	//TODO implement save
}
