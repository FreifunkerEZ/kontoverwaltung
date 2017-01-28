function array_values (input) { // eslint-disable-line camelcase
  //  discuss at: http://locutus.io/php/array_values/
  // original by: Kevin van Zonneveld (http://kvz.io)
  // improved by: Brett Zamir (http://brett-zamir.me)
  //   example 1: array_values( {firstname: 'Kevin', surname: 'van Zonneveld'} )
  //   returns 1: [ 'Kevin', 'van Zonneveld' ]

  var tmpArr = []
  var key = ''

  for (key in input) {
    tmpArr[tmpArr.length] = input[key]
  }

  return tmpArr
}

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
		console.log('no content - use all tags');
		console.log(tags)
		var tagsArray = array_values(tags);
		for (var i=0; i < tagsArray.length; i++) {
			var tagString = tagsArray[i].ID+': '+tagsArray[i].name;
			$('<option>')
					.text(tagString)
					.attr('value',tagsArray[i].ID)
					.attr('data-tagID',tagsArray[i].ID)
					.appendTo(box)
			;
		}
	}
	else {
		console.log('content present - use specific tags');
		for (var i=0; i < content.length; i++) {
			var tagID = content[i];
			var tagString = tagID+': '+tags[tagID].name;
			$('<option>')
					.text(tagString)
					.attr('value',tagID)
					.attr('data-tagID',tagID)
					.appendTo(box)
			;
		}
	}
}

function arbAddButton(button) {
	var arb = $(button).closest('.arbAddRemoveBoxes');
	//what is selected?
	var selectedOption = arb.find('.arbCanHaveBox option:selected');
	//remove that 
	selectedOption.remove();
	//add it
	arb.find('.arbHasBox select').append(selectedOption);
	selectBoxSort(arb.find('.arbHasBox select'));
}

function arbRemoveButton(button) {
	var arb = $(button).closest('.arbAddRemoveBoxes');
	//what is selected?
	var selectedOption = arb.find('.arbHasBox option:selected');
	//remove that 
	selectedOption.remove();
	//add it
	arb.find('.arbCanHaveBox select').append(selectedOption);
	selectBoxSort(arb.find('.arbCanHaveBox select'));
}

function arbCollectHasTags(button) {
	var hasOptions  = $(button).parent().find('.arbHasBox option');
	var outputArray = [];
	for (var i = 0; i < hasOptions.length; i++) {
		var tagID = $(hasOptions[i]).attr('data-tagID');
		outputArray.push(tagID);
	}
	return outputArray;
}

function selectBoxSort(box) {
	//http://stackoverflow.com/questions/278089/javascript-to-sort-contents-of-select-element
	var newContent = $(box).find("option").sort(
		function (a, b) {
			return a.value == b.value ? 0 : a.value < b.value ? -1 : 1;
		}
	);
	$(box).html(newContent);
}
function ruleNewOpen(button) {
	var editor = $('.ruleEditor').show();
	editor.find('[name=ID]').text('NEW');
	editor.find('input').val('');
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
		function (data) {console.log(data);location.reload();}
	)
		.fail(function(data){alert('save failed.');console.log(data);})
	;
}
