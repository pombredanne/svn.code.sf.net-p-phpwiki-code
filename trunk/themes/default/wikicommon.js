// Common Javascript support functions.
// $Id$

/* Globals:
var data_path = '/phpwiki-cvs';
var pagename  = 'HomePage';
var script_url= '/wikicvs';
var stylepath = data_path+'/themes/MonoBook/';
*/

function WikiURL(page) {
    if (typeof page == "undefined")
        page = pagename;
    if (use_path_info) {
        return script_url + '/' + escapeQuotes(page) + '?';
    } else {
        return script_url + '?pagename=' + escapeQuotes(page) + '&';
    }
}

function flipAll(formObj) {
  var isFirstSet = -1;
  for (var i=0; i < formObj.length; i++) {
      fldObj = formObj.elements[i];
      if ((fldObj.type == 'checkbox') && (fldObj.name.substring(0,2) == 'p[')) {
         if (isFirstSet == -1)
           isFirstSet = (fldObj.checked) ? true : false;
         fldObj.checked = (isFirstSet) ? false : true;
       }
   }
}

function toggletoc(a, open, close, toclist) {
  var toc=document.getElementById(toclist)
  if (toc.style.display=='none') {
    toc.style.display='block'
    a.title='"._("Click to hide the TOC")."'
    a.src = open
  } else {
    toc.style.display='none';
    a.title='"._("Click to display")."'
    a.src = close
  }
}

// Global external objects used by this script.
/*extern ta, stylepath, skin */

// add any onload functions in this hook (please don't hard-code any events in the xhtml source)
var doneOnloadHook;

if (!window.onloadFuncts) {
	var onloadFuncts = [];
}

function addOnloadHook(hookFunct) {
	// Allows add-on scripts to add onload functions
	onloadFuncts[onloadFuncts.length] = hookFunct;
}

function hookEvent(hookName, hookFunct) {
	if (window.addEventListener) {
		window.addEventListener(hookName, hookFunct, false);
	} else if (window.attachEvent) {
		window.attachEvent("on" + hookName, hookFunct);
	}
}

// Todo: onloadhook to re-establish folder state in pure js, no cookies. same for toc.
function showHideFolder(id) {
    var div = document.getElementById(id+'-body');
    var img = document.getElementById(id+'-img');
    var expires = new Date(); // 30 days
    expires.setTime(expires.getTime() + (30 * 24 * 60 * 60 * 1000));
    var suffix = " expires="+expires.toGMTString();   //+"; path="+data_path;
    //todo: invalidate cache header
    if ( div.style.display == 'none' ) {
        div.style.display = 'block';
        img.src = stylepath + 'images/folderArrowOpen.png';
        document.cookie = "folder_"+id+"=Open;"+suffix;
    } else {
        div.style.display = 'none';
        img.src = stylepath + 'images/folderArrowClosed.png';
        document.cookie = "folder_"+id+"=Closed;"+suffix;
    }
}

function setupshowHideFolder() {
    var ids = ["p-tb", "p-tbx", "p-tags", "p-rc" /*,"toc"*/];
    for (var i = 0; i < ids.length; i++) {
        if (ids[i]) {
            var id = ids[i];
            var cookieStr = "folder_"+id+"=";
            var cookiePos = document.cookie.indexOf(cookieStr);
            if (cookiePos > -1) {
                document.getElementById(id+'-body').style.display = document.cookie.charAt(cookiePos + cookieStr.length) == "C" ? 'block' : 'none';
                showHideFolder(id)
                    }
        }
    }
}

hookEvent("load", setupshowHideFolder);

/*
 * Table sorting script  by Joost de Valk, check it out at http://www.joostdevalk.nl/code/sortable-table/.
 * Based on a script from http://www.kryogenix.org/code/browser/sorttable/.
 * Distributed under the MIT license: http://www.kryogenix.org/code/browser/licence.html .
 *
 * Copyright (c) 1997-2006 Stuart Langridge, Joost de Valk.
 *
 * @todo don't break on colspans/rowspans (bug 8028)
 * @todo language-specific digit grouping/decimals (bug 8063)
 * @todo support all accepted date formats (bug 8226)
 */

var ts_image_up = "sort_up.gif";
var ts_image_down = "sort_down.gif";
var ts_image_none = "sort_none.gif";
var wgContentLanguage = "en";
var ts_europeandate = true;
var ts_alternate_row_colors = true;
var SORT_COLUMN_INDEX;

function sortables_init() {
	var idnum = 0;
	// Find all tables with class sortable and make them sortable
	var tables = getElementsByClassName(document, "table", "sortable");
	for (var ti = 0; ti < tables.length ; ti++) {
		if (!tables[ti].id) {
			tables[ti].setAttribute('id','sortable_table_id_'+idnum);
			++idnum;
		}
		ts_makeSortable(tables[ti]);
	}
}

function ts_makeSortable(table) {
	var firstRow;
        var ts_image_path = stylepath+"images/";
	if (table.rows && table.rows.length > 0) {
		if (table.tHead && table.tHead.rows.length > 0) {
			firstRow = table.tHead.rows[table.tHead.rows.length-1];
		} else {
			firstRow = table.rows[0];
		}
	}
	if (!firstRow) return;

	// We have a first row: assume it's the header, and make its contents clickable links
	for (var i = 0; i < firstRow.cells.length; i++) {
		var cell = firstRow.cells[i];
		if ((" "+cell.className+" ").indexOf(" unsortable ") == -1) {
			cell.innerHTML += '&nbsp;&nbsp;<a href="#" class="sortheader" onclick="ts_resortTable(this);return false;"><span class="sortarrow"><img src="'+ ts_image_path + ts_image_none + '" alt="&darr;"/></span></a>';
		}
	}
	if (ts_alternate_row_colors) {
		ts_alternate(table);
	}
}

function ts_getInnerText(el) {
	if (typeof el == "string") return el;
	if (typeof el == "undefined") { return el };
	if (el.innerText) return el.innerText;	// Not needed but it is faster
	var str = "";

	var cs = el.childNodes;
	var l = cs.length;
	for (var i = 0; i < l; i++) {
		switch (cs[i].nodeType) {
			case 1: //ELEMENT_NODE
				str += ts_getInnerText(cs[i]);
				break;
			case 3:	//TEXT_NODE
				str += cs[i].nodeValue;
				break;
		}
	}
	return str;
}

function ts_parseFloat(num) {
	if (!num) return 0;
	num = parseFloat(num.replace(/,/, ""));
	return (isNaN(num) ? 0 : num);
}

function ts_sort_date(a,b) {
	var aa = ts_dateToSortKey(a[1]);
	var bb = ts_dateToSortKey(b[1]);
	return (aa < bb ? -1 : aa > bb ? 1 : a[2] - b[2]);
}

function ts_sort_currency(a,b) {
	var aa = ts_parseFloat(a[1].replace(/[^0-9.]/g,''));
	var bb = ts_parseFloat(b[1].replace(/[^0-9.]/g,''));
	return (aa != bb ? aa - bb : a[2] - b[2]);
}

function ts_sort_numeric(a,b) {
	var aa = ts_parseFloat(a[1]);
	var bb = ts_parseFloat(b[1]);
	return (aa != bb ? aa - bb : a[2] - b[2]);
}

function ts_sort_caseinsensitive(a,b) {
	var aa = a[1].toLowerCase();
	var bb = b[1].toLowerCase();
	return (aa < bb ? -1 : aa > bb ? 1 : a[2] - b[2]);
}

function ts_sort_default(a,b) {
	return (a[1] < b[1] ? -1 : a[1] > b[1] ? 1 : a[2] - b[2]);
}

function ts_alternate(table) {
	// Take object table and get all it's tbodies.
	var tableBodies = table.getElementsByTagName("tbody");
	// Loop through these tbodies
	for (var i = 0; i < tableBodies.length; i++) {
		// Take the tbody, and get all it's rows
		var tableRows = tableBodies[i].getElementsByTagName("tr");
		// Loop through these rows
		// Start at 1 because we want to leave the heading row untouched
		for (var j = 0; j < tableRows.length; j++) {
			// Check if j is even, and apply classes for both possible results
			var oldClasses = tableRows[j].className.split(" ");
			var newClassName = "";
			for (var k = 0; k < oldClasses.length; k++) {
				if (oldClasses[k] != "" && oldClasses[k] != "even" && oldClasses[k] != "odd")
					newClassName += oldClasses[k] + " ";
			}
			tableRows[j].className = newClassName + (j % 2 == 0 ? "even" : "odd");
		}
	}
}

/*
 * End of table sorting code
 */

function runOnloadHook() {
	// don't run anything below this for non-dom browsers
	if (doneOnloadHook || !(document.getElementById && document.getElementsByTagName)) {
		return;
	}

	// set this before running any hooks, since any errors below
	// might cause the function to terminate prematurely
	doneOnloadHook = true;

	histrowinit();
	unhidetzbutton();
	tabbedprefs();
	updateTooltipAccessKeys( null );
	akeytt( null );
	scrollEditBox();
	setupCheckboxShiftClick();
	sortables_init();

	// Run any added-on functions
	for (var i = 0; i < onloadFuncts.length; i++) {
		onloadFuncts[i]();
	}
}

//note: all skins should call runOnloadHook() at the end of html output,
//      so the below should be redundant. It's there just in case.
hookEvent("load", runOnloadHook);

//hookEvent("load", mwSetupToolbar);

// This script was provided for free by
// http://www.howtocreate.co.uk/tutorials/javascript/domcss
// See http://www.howtocreate.co.uk/jslibs/termsOfUse.html
function getAllSheets() {
  if( !window.ScriptEngine && navigator.__ice_version ) { return document.styleSheets; }
  if( document.getElementsByTagName ) { var Lt = document.getElementsByTagName('link'), St = document.getElementsByTagName('style');
  } else if( document.styleSheets && document.all ) { var Lt = document.all.tags('LINK'), St = document.all.tags('STYLE');
  } else { return []; } for( var x = 0, os = []; Lt[x]; x++ ) {
    var rel = Lt[x].rel ? Lt[x].rel : Lt[x].getAttribute ? Lt[x].getAttribute('rel') : '';
    if( typeof( rel ) == 'string' && rel.toLowerCase().indexOf('style') + 1 ) { os[os.length] = Lt[x]; }
  } for( var x = 0; St[x]; x++ ) { os[os.length] = St[x]; } return os;
}
function changeStyle() {
  for( var x = 0, ss = getAllSheets(); ss[x]; x++ ) {
    if( ss[x].title ) { ss[x].disabled = true; }
    for( var y = 0; y < arguments.length; y++ ) {
     if( ss[x].title == arguments[y] ) { ss[x].disabled = false; }
} } }
function PrinterStylesheet() {
  changeStyle('Printer');
}
