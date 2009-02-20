// Common Javascript support functions.
// $Id$

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

