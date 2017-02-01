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


function deleteSomething(button,action) {
	var ID = $(button).parent().find("[name=ID]").text();
	$.post(
		'?action='+action,
		{ID : ID},
		function (data) {console.log(data);location.reload();}
	)
	.fail(function(data){alert(action+" failed");console.log(data);})
	;
}

function statsSumUpdate() {
	var buchungen = $('table.records tr:visible');
	var sum = 0;
	for (var i=0; i < buchungen.length; i++) {
		var value = $(buchungen[i]).attr('data-Betrag');
		if (!isNaN(value))
			sum += parseFloat(value);
	}
	$('.statsSum').text(sum.toFixed(2));
}

function editComment(elIcon) {
	var icon = $(elIcon);
	var contentSpan = icon.siblings('.commentContent');
	
	if (icon.hasClass('fa-pencil')) {
		var commentContent = contentSpan.text();
		var inputElement = $('<input type="text">').val(commentContent);
		contentSpan.html(inputElement);
		inputElement.focus();
	}
	else {
		var commentContent = contentSpan.find('input').val();
		contentSpan.text(commentContent);
		setLoadingIndicator(1);
		var params = {
			ID:		icon.closest('tr').attr('data-ID'),
			action:	'editComment',
			comment:commentContent
		};
		$.get(	'',
				params,
				function(){
					setLoadingIndicator(-1);
				}
		)
		.fail(	function(data){
					console.log(data);
					alert('Saving comment failed :(');
				}
		);
	}
	icon.toggleClass('fa-pencil fa-check');
}

function setLoadingIndicator(diff) {
	var indi  = $('.loadingIndicator');
	var state = indi.attr('data-operationsInProgress');
	state = +state + +diff;
	indi.attr('data-operationsInProgress', state);
	if (0 === state)
		indi.hide();
	else
		indi.show();
}

