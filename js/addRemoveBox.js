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
		//console.log('no content - use all tags');
		//console.log(tags)
		var tagsArray = array_values(tags);
		for (var i=0; i < tagsArray.length; i++) {
			var tagString = tagsArray[i].ID+': '+tagsArray[i].name;
			$('<option>')
					.text(tagString)
					.attr('value',tagsArray[i].ID)
					.attr('data-tagID',tagsArray[i].ID)
					.attr('ondblclick','adbHandleDoubleClick(this)')
					.appendTo(box)
			;
		}
	}
	else {
		//console.log('content present - use specific tags');
		for (var i=0; i < content.length; i++) {
			var tagID = content[i];
			var tagString = tagID+': '+tags[tagID].name;
			$('<option>')
					.text(tagString)
					.attr('value',tagID)
					.attr('data-tagID',tagID)
					.attr('ondblclick','adbHandleDoubleClick(this)')
					.appendTo(box)
			;
		}
	}
}
function adbHandleDoubleClick(elOption) {
	if ($(elOption).parents('.arbCanHaveBox').length)
		arbAddButton(elOption);
	else
		arbRemoveButton(elOption);
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
