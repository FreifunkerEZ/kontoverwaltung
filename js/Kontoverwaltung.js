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
		function(data) {console.log(data);location.reload();}
	)
	.fail(function(data){alert(action+" failed");console.log(data);})
	;
}


function is_string(thing) {
	return (typeof thing === 'string' || thing instanceof String);
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
function getUrlQueryParams(whichParam) {
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
 * @param {string} key the parameter to modify.
 * @param {string} value OPTIONAL - what to set to. if empty, the key is removed.
 * @param {string} url OPTIONAL. if not given, window.location.href is used.
 * @returns {String} the modified url
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

/**
 * sets one key/value of window.location.href.
 * updates the browser's URL-bar without actually navigating.
 * 
 * @param {string} key
 * @param {string} value OPTIONAL - if left out, the key is removed.
 * @returns {undefined}
 */
function setUrlBarParams(key, value) {
	var url = UpdateQueryString(key, value);
	history.pushState({}, null, url);
}
/**
 * will show or hide the Upload and RuleExplorer elements.
 * if the element is visible the selector will be added to the URL-bar.
 * this gives us page-reload persistence of the settings.
 * 
 * @param {string} OPTIONAL - selector jquery selector like ".class". 
 * if left out, the location.href (URL-bar) is inspected.
 * every key that has the value "show" will be used as selector and tried to show().
 * @returns {undefined}
 */
function toggleElements(selector) {
	if (is_string(selector)) {
		$(selector).toggle();
		if ($(selector+':visible').length)
			setUrlBarParams(selector, 'show');
		else 
			setUrlBarParams(selector);
	}
	else { //selector is not a string - inspect URL for input 
		var queryParams = getUrlQueryParams();
		for (var key in queryParams) {
			if (queryParams[key] == 'show')
				$(key).show();
		}
	}
}
/**
 * the href in the URL-bar might have some parameters set to affect the page.
 * search for them and enact their intent.

 * @returns {undefined}
 */
function handlePersistence() {
	toggleElements(null);
	if (getUrlQueryParams('date'))
		$('input[name=filterDate]').val(getUrlQueryParams('date'));
	if (getUrlQueryParams('fts'))
		$('input[name=filterFullText]').val(getUrlQueryParams('fts'));
}

$(document).ready(initLuxusSlider);
$(document).ready(handlePersistence);
$(document).ready(tagFilterShow);