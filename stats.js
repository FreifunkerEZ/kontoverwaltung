function statsUpdateSum() {
	var buchungen = $('table.records tr:visible');
	var sum = 0;
	for (var i=0; i < buchungen.length; i++) {
		var value = $(buchungen[i]).attr('data-Betrag');
		if (!isNaN(value))
			sum += parseFloat(value);
	}
	$('.statsSum').text(sum.toFixed(2));
}
function statsUpdateCount() {
	var buchungen = $('table.records tr:visible');
	$('span.statsCountTotal').text(buchungen.length);
}
