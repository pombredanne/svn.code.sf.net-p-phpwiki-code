/*==============================================================================

 * Copyright(c) STMicroelectronics, 2006
 *
 * Originally written by Jean-Nicolas GEREONE, STMicroelectronics, 2006. 

COPYRIGHT:

    Copyright (c) 2005 Socialtext Corporation 
    655 High Street
    Palo Alto, CA 94301 U.S.A.
    All rights reserved.

Wikiwyg is free software. 

This library is free software; you can redistribute it and/or modify it
under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation; either version 2.1 of the License, or (at
your option) any later version.

This library is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser
General Public License for more details.

    http://www.gnu.org/copyleft/lesser.txt

 =============================================================================*/

var wikiwyg_divs = [];

proto = new Subclass('Wikiwyg.Phpwiki', 'Wikiwyg');

proto.submit_action_form = function(action, value) {
    this.div.value = value;
}

// Convert to wikitext mode if needed
// and save changes in the textarea of phpwiki 
proto.saveChanges = function() {

    var self = this;
    var submit_changes = function(wikitext) {
        self.submit_action_form(
            'wikiwyg_save_wikitext',wikitext
        );
    }  
    //   var self = this;
    if (this.current_mode.classname.match(/(Wysiwyg|Preview)/)) {
        this.current_mode.toHtml(
            function(html) {
                var wikitext_mode = self.mode_objects['Wikiwyg.Wikitext.Phpwiki'];
                wikitext_mode.convertHtmlToWikitext(
                    html,
                    function(wikitext) { submit_changes(wikitext) }
                );
            }
        );
    }
    else {
        submit_changes(this.current_mode.toWikitext());
    }
}

proto.modeClasses = [
       'Wikiwyg.Wikitext.Phpwiki',
       'Wikiwyg.Wysiwyg'
       // 'Wikiwyg.HTML'
];

proto.call_action = function(action, content, func) {
  var postdata = 'action=wikitohtml' + 
                   '&content=' + encodeURIComponent(content);
  Wikiwyg.liveUpdate(
		     'POST',
		     script_url,
		     postdata,
		     func
		     );
}

proto = new Subclass('Wikiwyg.Wikitext.Phpwiki', 'Wikiwyg.Wikitext');

proto.convertWikitextToHtml = function(wikitext, func) {
    this.wikiwyg.call_action('wikiwyg_wikitext_to_html', wikitext, func);
}

proto.markupRules = {
    link: ['bound_phrase', '[', ']'],
    bold: ['bound_phrase', '<b>', '</b>'],
    italic: ['bound_phrase', '<i>', '</i>'],
    underline: ['bound_phrase', '<u>', '</u>'],
    strike: ['bound_phrase', '<strike>', '</strike>'],
    pre: ['bound_phrase', '\n\n<pre>\n', '\n</pre>\n'],
    h2: ['start_line', '!!! '],
    h3: ['start_line', '!! '],
    h4: ['start_line', '! '],
    ordered: ['start_lines', '#'],
    unordered: ['start_lines', '*'],
    indent: ['start_lines', ' '],
    hr: ['line_alone', '----'],
    table: ['line_alone', 'A |\n B |\n  C\n |\n  |\n   \n |\n  |\n   \n'],
    link: ['bound_phrase', '[ Name of the link | ', ' ]' ],
    verbatim: ['bound_phrase', '<verbatim>\n','\n</verbatim>\n'],
    richtable:['line_alone', 
   '<?plugin RichTable *border=1, cellpadding=4, cellspacing=0,\n-\n|ligne1\n|ligne1\n|ligne1\n-\n|ligne2\n|ligne2\n|ligne2\n\n ?>']
};

/*==============================================================================
Code to convert from html to wikitext. Hack for phpwiki and IE
 =============================================================================*/
proto.convert_html_to_wikitext = function(html) {
    this.copyhtml = html;
    var dom = document.createElement('div');
    html = html.replace(/<!-=-/g, '<!--').
                replace(/-=->/g, '-->');

    // Change * to ~* for phpwiki
    html = html.replace(/\*/g,'~*');
    // Change _ to ~_ for phpwiki 
    html = html.replace(/\_/g,'~_');

    // Hack for IE 
    // convert <p>&nbsp;</p> into Â ( unknown char :)
    html = html.replace(/\&nbsp;/g,'');

    dom.innerHTML = html;
    this.output = [];
    this.list_type = [];
    this.indent_level = 0;

    this.walk(dom);

    // add final whitespace
    this.assert_new_line();

    return this.join_output(this.output);
}

/*==============================================================================
Code to convert from html to wikitext.
 =============================================================================*/

/*==============================================================================
[Wikitext.js] Support of Headings in phpwiki 
 =============================================================================*/

// Adding match headings : '!', for phpwiki.
proto.add_markup_lines = function(markup_start) {
    var already_set_re = new RegExp( '^' + this.clean_regexp(markup_start), 'gm');
    var other_markup_re = /^(\^+|\=+|\*+|#+|>+|!+|    )/gm;

    var match;
    // if paragraph, reduce everything.
    if (! markup_start.length) {
        this.sel = this.sel.replace(other_markup_re, '');
        this.sel = this.sel.replace(/^\ +/gm, '');
    }
    // if pre and not all indented, indent
    else if ((markup_start == '    ') && this.sel.match(/^\S/m))
        this.sel = this.sel.replace(/^/gm, markup_start);
    // if not requesting heading and already this style, kill this style
    else if (
        (! markup_start.match(/[\!\^]/)) &&
        this.sel.match(already_set_re)
    ) {
        this.sel = this.sel.replace(already_set_re, '');
        if (markup_start != '    ')
            this.sel = this.sel.replace(/^ */gm, '');
    }
    // if some other style, switch to new style
    else if (match = this.sel.match(other_markup_re))
        // if pre, just indent
        if (markup_start == '    ')
            this.sel = this.sel.replace(/^/gm, markup_start);
        // if heading, just change it
        else if (markup_start.match(/[\!\^]/))
            this.sel = this.sel.replace(other_markup_re, markup_start);
        // else try to change based on level
        else
            this.sel = this.sel.replace(
                other_markup_re,
                function(match) {
                    return markup_start.times(match.length);
                }
            );
    // if something selected, use this style
    else if (this.sel.length > 0)
        this.sel = this.sel.replace(/^(.*\S+)/gm, markup_start + ' $1');
    // just add the markup
    else
        this.sel = markup_start + ' ';

    var text = this.start + this.sel + this.finish;
    var start = this.selection_start;
    var end = this.selection_start + this.sel.length;
    this.set_text_and_selection(text, start, end);
    this.area.focus();
}

/*==============================================================================
End Hack for headings in phpwiki
 =============================================================================*/


/*==============================================================================
Support for incremental numbers :
When there is 
# list1
## list 2
phpwiki convert it with a <p> element inside the <li> element
So the <p> element have to be ignored
 =============================================================================*/

proto.format_p = function(element) {

  // Hack to avoid \n to be inserted if an li element is parent
  if( element.parentNode.nodeType == '1' 
      && element.parentNode.nodeName.toLowerCase() == "li") {
    this.walk(element);
  }
  else {
    var style = element.getAttribute('style','true');
    if ( style ) {
      if ( !Wikiwyg.is_ie ) {
	this.assert_blank_line();
	this.assert_space_or_newline();
	if (style.match(/\bbold\b/))
	  this.appendOutput(this.config.markupRules.bold[1]);
	if (style.match(/\bitalic\b/))
	  this.appendOutput(this.config.markupRules.italic[1]);
	if (style.match(/\bunderline\b/))
	  this.appendOutput(this.config.markupRules.underline[1]);
	if (style.match(/\bline-through\b/))
	  this.appendOutput(this.config.markupRules.strike[1]);
	
	this.no_following_whitespace();
	this.walk(element);
	
	if (style.match(/\bline-through\b/))
	  this.appendOutput(this.config.markupRules.strike[2]);
	if (style.match(/\bunderline\b/))
	  this.appendOutput(this.config.markupRules.underline[2]);
	if (style.match(/\bitalic\b/))
	  this.appendOutput(this.config.markupRules.italic[2]);
	if (style.match(/\bbold\b/))
	  this.appendOutput(this.config.markupRules.bold[2]);
	
	this.assert_blank_line();
      } // end if(!is_ie) 
      else{
	this.assert_blank_line();
	this.walk(element);
	this.assert_blank_line();   
      }
    }  // end if (style)   
    else {
      this.assert_blank_line();
      this.walk(element);
      this.assert_blank_line();   
    }
  }
}


/*==============================================================================
End Support for incremental numbers
 =============================================================================*/

/*==============================================================================
Support for <br> tag
 =============================================================================*/

  proto.format_br = function(element) {
    this.assert_new_line();
  }

/*==============================================================================
End Support for <br> tag
 =============================================================================*/


/*==============================================================================
Support for links in phpwiki
 =============================================================================*/

proto.make_wikitext_link = function(label, href, element) {

  // comes with label = ?
  // element = anchor element

  // href have the link

  // come here with phpwiki conversion of the link
  // So this.output contains something like :
  // [....,<u>,object,label,</u>]

  //alert(' in link');

    var before = '[';
    var after  = ']';

    // XXX Hack to remove <u> and </u> on the label name
    // because phpwiki convert links in : 
    // <u> label </u> <a href=link?action=create> ? </a>

    //alert('before ?');

    if (label == '?') {
      // Verify if the output poped is </u>

      //alert(' ? found ');

      if ( this.output[this.output.length-1] == '</u>' ) {
	//alert('</u> found');
	
	// removed_u1 is </u> and have to be removed
	var removed_u1 = this.output.pop();

	// XXX Verify if the output poped is text ( => =textnode => 3)
	var oldlabel = label;
	label = this.output.pop();
	
	// XXX Verify if the output poped is  ... an object ?
	var object = this.output.pop();

	//Verify if the output poped is <u>
	if ( this.output[this.output.length-1] == '<u>' ) {
	  var removed_u2 = this.output.pop();
	  
	}
	// If <u> not found
	// the ouput will be restored
	else {
	  alert('Error : <u> not found in the link');
	  this.appendOutput(removed_u2);
	  this.appendOutput(object);
	  this.appendOutput(oldlabel);
	  this.appendOutput(removed_u1);
	}
      } 
    }

    // XXX If the link really have a '?'
    // if the link cointains ?
    // it takes the first part
    if ( href.match(/\?/) )
	 href = href.split('?')[0];

    //    alert('label '+label );
    //    alert('href '+href);

    this.assert_space_or_newline();
    if (! href) {
        this.appendOutput(before + label + after);
    }
    else if (href == label) {
      if (this.camel_case_link(label))
	this.appendOutput(label);
      else
        this.appendOutput(before + href + after);
    }
    else if (this.href_is_wiki_link(href)) {
        if (this.camel_case_link(label))
            this.appendOutput(label);
        else
            this.appendOutput(before + label + '|' + href + after);
    }
    else {
        this.appendOutput(before + label + '|' + href + after);
    }
}

// IE support of links
proto.format_span = function(element) {
    if (this.is_opaque(element)) {
        this.handle_opaque_phrase(element);
        return;
    }

    var style = element.getAttribute('style','true');

    if (!style ) {
        this.pass(element);
        return;
    }

    if ( !Wikiwyg.is_ie ) {

      this.assert_space_or_newline();
      if (style.match(/\bbold\b/))
        this.appendOutput(this.config.markupRules.bold[1]);
      if (style.match(/\bitalic\b/))
        this.appendOutput(this.config.markupRules.italic[1]);
      if (style.match(/\bunderline\b/))
        this.appendOutput(this.config.markupRules.underline[1]);
      if (style.match(/\bline-through\b/))
        this.appendOutput(this.config.markupRules.strike[1]);

    }

    this.no_following_whitespace();
    this.walk(element);


    if ( !Wikiwyg.is_ie ) {

      if (style.match(/\bline-through\b/))
        this.appendOutput(this.config.markupRules.strike[2]);
      if (style.match(/\bunderline\b/))
        this.appendOutput(this.config.markupRules.underline[2]);
      if (style.match(/\bitalic\b/))
        this.appendOutput(this.config.markupRules.italic[2]);
      if (style.match(/\bbold\b/))
        this.appendOutput(this.config.markupRules.bold[2]);
    }
}

/*==============================================================================
End Support for links in phpwiki
 =============================================================================*/


/*==============================================================================
Support for plugin RichTable in phpwiki
 =============================================================================*/

proto.format_table = function(element) {
    this.assert_blank_line();
    this.appendOutput('<?plugin RichTable *border=1, cellpadding=4, cellspacing=0,\n\n');
    this.walk(element);
    this.appendOutput(' \n ?> \n\n');
    this.assert_blank_line();
}

proto.format_tr = function(element) {
    this.appendOutput('-\n');
    this.walk(element);
}

proto.format_td = function(element) {
    this.appendOutput('|');
    this.walk(element);
    this.appendOutput('\n');
}

proto.format_th = function(element) {
    this.appendOutput('|');
    this.walk(element);
    this.appendOutput(' \n');
}

/*==============================================================================
END RichTable 
 =============================================================================*/

proto.do_verbatim = Wikiwyg.Wikitext.make_do('verbatim');
proto.do_line_break = Wikiwyg.Wikitext.make_do('line_break');
proto.do_richtable = Wikiwyg.Wikitext.make_do('richtable');

proto = new Subclass('Wikiwyg.Preview.Phpwiki', 'Wikiwyg.Preview');

proto.fromHtml = function(html) {
    if (this.wikiwyg.previous_mode.classname.match(/(Wysiwyg|HTML)/)) {
        var wikitext_mode = this.wikiwyg.mode_objects['Wikiwyg.Wikitext.Phpwiki'];
        var self = this;
        wikitext_mode.convertWikitextToHtml(
            wikitext_mode.convert_html_to_wikitext(html),
            function(new_html) { self.div.innerHTML = new_html }
        );
    }
    else {
        this.div.innerHTML = html;
    }
}

/*==============================================================================
Support for Internet Explorer in Wikiwyg
 =============================================================================*/
if (Wikiwyg.is_ie) {

if (window.ActiveXObject && !window.XMLHttpRequest) {
  window.XMLHttpRequest = function() {
    return new ActiveXObject((navigator.userAgent.toLowerCase().indexOf('msie 5') != -1) ? 'Microsoft.XMLHTTP' : 'Msxml2.XMLHTTP');
  };
}

} // end of global if statement for IE overrides
