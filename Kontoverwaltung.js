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


function is_string(thing) {
	return (typeof thing === 'string' || thing instanceof String);
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

function buchungToggleSelection(td) {
	$(td).find('i').toggleClass('fa-square-o fa-check-square-o');
}
function buchungToggleSelectionAll(th) {
	var icon = $(th).find('i');
	icon.toggleClass('fa-square-o fa-check-square-o');
	
	if (icon.hasClass('fa-check-square-o')) //everything should be selected.
		$('table.records td i.rowselector')
			.removeClass('fa-square-o')
			.addClass('fa-check-square-o')
		;
	else //nothing should be selected
		$('table.records td i.rowselector')
			.removeClass('fa-check-square-o')
			.addClass('fa-square-o')
		;
		
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

function initLuxusSlider() {
	$('.luxus-slider').jRange({
		from: 0,
		to: 300,
		step: 1,
		scale: ['notwendig','schwer verzichtbar','verzichtbar','aussergewöhnlich'],
		format: '%s',
		width: 500,
		showLabels: true,
		isRange : true
	});
	
}

/**
 * gets you the params from the URL-bar.
 * 
 * @param {string} whichParam - OPTIONAL
 * if given, a single value is returned.
 * gets you the value if set or undefined if not.
 * if not given the all params are returned as object.
 * @returns {string|Object}
 */
function getQueryParams(whichParam) {
       var query = window.location.search.substring(1);
       var vars = query.split("&");
	   var output = {};
       for (var i=0;i<vars.length;i++) {
               var pair = vars[i].split("=");
               output[pair[0]] = pair[1];
       }
	   if (whichParam)
		   return output[whichParam];
	   else
			return output;
}

/**
 * from http://stackoverflow.com/a/11654436/1025430
 * Anyone interested in a regex solution I have put together this function to 
 * add/remove/update a querystring parameter. Not supplying a value will remove 
 * the parameter, supplying one will add/update the paramter. If no URL is 
 * supplied, it will be grabbed from window.location. This solution also takes 
 * the url's anchor into consideration.
 * 
 * @param {type} key
 * @param {type} value
 * @param {type} url
 * @returns {String}
 */
function UpdateQueryString(key, value, url) {
    if (!url) url = window.location.href;
    var re = new RegExp("([?&])" + key + "=.*?(&|#|$)(.*)", "gi"),
        hash;

    if (re.test(url)) {
        if (typeof value !== 'undefined' && value !== null)
            return url.replace(re, '$1' + key + "=" + value + '$2$3');
        else {
            hash = url.split('#');
            url = hash[0].replace(re, '$1$3').replace(/(&|\?)$/, '');
            if (typeof hash[1] !== 'undefined' && hash[1] !== null) 
                url += '#' + hash[1];
            return url;
        }
    }
    else {
        if (typeof value !== 'undefined' && value !== null) {
            var separator = url.indexOf('?') !== -1 ? '&' : '?';
            hash = url.split('#');
            url = hash[0] + separator + key + '=' + value;
            if (typeof hash[1] !== 'undefined' && hash[1] !== null) 
                url += '#' + hash[1];
            return url;
        }
        else
            return url;
    }
}
function setUrlBarParams(key, value) {
	var url = UpdateQueryString(key, value, window.location.href);
	history.pushState({}, null, url);
}
/**
 * will show or hide an element.
 * if the element is visible the selector will be added to the URL-bar.
 * 
 * @param {string} OPTIONAL - selector jquery selector like ".class". 
 * if left out, the location.href (URL-bar) is inspected.
 * every key that has the value "show" will be used as selector and tried to show().
 * @returns {undefined}
 */
function toggleElements(selector) {
	if (typeof selector === 'string' || selector instanceof String) {
		$(selector).toggle();
		if ($(selector+':visible').length)
			setUrlBarParams(selector, 'show');
		else 
			setUrlBarParams(selector);
	}
	else {
		var queryParams = getQueryParams();
		for (var key in queryParams) {
			if (queryParams[key] == 'show')
				$(key).show();
		}
	}
}

$(document).ready(initLuxusSlider);
$(document).ready(toggleElements);
$(document).ready(tagFilterShow);