function statsUpdateSum() {
	var buchungen = $('table.records tr[data-ID]:visible');
	var sum = 0;
	for (var i=0; i < buchungen.length; i++) {
		var value = $(buchungen[i]).attr('data-Betrag');
		if (isNaN(value))
			console.log($(buchungen[i]));
		sum += parseFloat(value);
	}
	$('.statsSum').text(sum.toFixed(2));
}
function statsUpdateCount() {
	var buchungen = $('table.records tr[data-ID]:visible');
	$('span.statsCountTotal').text(buchungen.length);
}

function statsUpdateTags() {
	var buchungen = $('table.records tr[data-ID]:visible');
	
	var count = {untagged:0};
	var sumEu = {untagged:0};
	for (var i = 0; i<buchungen.length; i++) {
		//load all the tags from the buchung. split it to an array of single tags each.
		var tags = $(buchungen[i]).attr('data-hastags');
		if (tags)
			tags = tags.split(' ');
		else 
			tags = ['untagged'];
			
		var eu = $(buchungen[i]).attr('data-betrag');
		eu = parseFloat(eu);
		for (var p=0; p<tags.length; p++) {
			var ID = tags[p];
			count[ID] = (!count[ID] ?  1 : count[ID]+1);
			sumEu[ID] = (!sumEu[ID] ? eu : sumEu[ID]+eu);
		}
	}
	
	var allTags   = $('div.tagBrowser div.tagElement');
	allTags.find('span.tagCount').text('0');
	allTags.find('span.tagSum'  ).text('0.00');
	for (var i=0; i<allTags.length; i++) {
		var tagID = $(allTags[i]).attr('data-ID');
		if (count[tagID]) {
			$(allTags[i]).find('span.tagCount').text(count[tagID]);
			$(allTags[i]).find('span.tagSum'  ).text(sumEu[tagID].toFixed(2) + ' €');
		}
	}
	$('span.untaggedCount').text(count['untagged']);
	$('span.untaggedSum'  ).text(sumEu['untagged'].toFixed(2) + ' €');
}