<!-- $Id: transform.php,v 1.5 2000-10-25 14:45:53 ahollosi Exp $ -->
<?php
   // expects $pagehash and $html to be set


   function tokenize($str, $pattern, &$orig, &$ntokens) {
      global $FieldSeparator;
      // Find any strings in $str that match $pattern and
      // store them in $tokens[], replacing them with a token
      // replaced strings are stored in $orig
      $new = "";      
      while (preg_match("/^(.*?)($pattern)/", $str, $matches)) {
         $linktoken = $FieldSeparator . $FieldSeparator . ($ntokens++) . $FieldSeparator;
         $new .= $matches[1] . $linktoken;
	 $orig[] = $matches[2];
         $str = substr($str, strlen($matches[0]));
      }
      $new .= $str;
      return $new;
   }


   // Prepare replacements for references [\d+]
   for ($i = 1; $i < (NUM_LINKS + 1); $i++) {
      if (! empty($pagehash['refs'][$i])) {
         if (preg_match("/($InlineImages)$/i", $pagehash['refs'][$i])) {
            // embed images
            $embedded[$i] = LinkImage($pagehash['refs'][$i]);
         } else {
            // ordinary link
            $embedded[$i] = "<a href=\"" . $pagehash['refs'][$i] . "\">[$i]</a>";
         }
      }
   }

   $numlines = count($pagehash["content"]);

   // only call these once, for efficiency
   $quick_search_box  = RenderQuickSearch();
   $full_search_box   = RenderFullSearch();
   $most_popular_list = RenderMostPopular();


   // Loop over all lines of the page and apply transformation rules
   for ($index = 0; $index < $numlines; $index++) {
      unset($tokens);
      unset($replacements);
      $ntokens = 0;
      $replacements = array();
      
      $tmpline = $pagehash["content"][$index];

      if (!strlen($tmpline) || $tmpline == "\r") {
         // this is a blank line, send <p>
         $html .= SetHTMLOutputMode("p", ZERO_DEPTH, 0);
         continue;
      }

/* If your web server is not accessble to the general public, you may
allow this code below, which allows embedded HTML. If just anyone can reach
your web server it is highly advised that you do not allow this.

      elseif (preg_match("/(^\|)(.*)/", $tmpline, $matches)) {
         // HTML mode
         $html .= SetHTMLOutputMode("", ZERO_DEPTH, 0);
         $html .= $matches[2];
         continue;
      }
*/


      //////////////////////////////////////////////////////////
      // New linking scheme: links are in brackets. This will
      // emulate typical HTML linking as well as Wiki linking.
	
      // First need to protect [[. 
      $oldn = $ntokens;
      $tmpline = tokenize($tmpline, "\[\[", $replacements, $ntokens);
      while ($oldn < $ntokens)
         $replacements[$oldn++] = "[";

      // Now process the [\d+] links which are numeric references	
      $oldn = $ntokens;
      $tmpline = tokenize($tmpline, "\[\s*\d+\s*\]", $replacements ,$ntokens);
      while ($oldn < $ntokens) {
	 $num = (int)substr($replacements[$oldn], 1);
         if (! empty($embedded[$num]))
            $replacements[$oldn] = $embedded[$num];
	 $oldn++;
      }

      // match anything else between brackets 
      $oldn = $ntokens;
      $tmpline = tokenize($tmpline, "\[.+?\]", $replacements, $ntokens);
      while ($oldn < $ntokens) {
	$link = ParseAndLink($replacements[$oldn]);	
	$replacements[$oldn] = $link['link'];
	$oldn++;
      }

      //////////////////////////////////////////////////////////
      // replace all URL's with tokens, so we don't confuse them
      // with Wiki words later. Wiki words in URL's break things.

      $tmpline = tokenize($tmpline, "\b($AllowedProtocols):[^\s<>\[\]\"'()]*[^\s<>\[\]\"'(),.?]", $replacements, $ntokens);
      while ($oldn < $ntokens) {
	$replacements[$oldn] = LinkURL($replacements[$oldn]);
        $oldn++;
      }

      //////////////////////////////////////////////////////////
      // Link Wiki words
      // Wikiwords preceeded by a '!' are not linked

      $oldn = $ntokens;
      $tmpline = tokenize($tmpline, "!?$WikiNameRegexp", $replacements, $ntokens);
      while ($oldn < $ntokens) {
        $old = $replacements[$oldn];
        if ($old[0] == '!') {
	  $replacements[$oldn] = substr($old,1);
	} elseif (IsWikiPage($dbi, $old)) {
	  $replacements[$oldn] = LinkExistingWikiWord($old);
	} else {
	  $replacements[$oldn] = LinkUnknownWikiWord($old);
	}
	$oldn++;
      }

      // escape HTML metachars
      $tmpline = str_replace("&", "&amp;", $tmpline);
      $tmpline = str_replace(">", "&gt;", $tmpline);
      $tmpline = str_replace("<", "&lt;", $tmpline);

      // four or more dashes to <hr>
      $tmpline = ereg_replace("^-{4,}", "<hr>", $tmpline);

      // %%% are linebreaks
      $tmpline = str_replace("%%%", "<br>", $tmpline);

      // bold italics
      $tmpline = preg_replace("|(''''')(.*?)(''''')|",
                              "<strong><em>\\2</em></strong>", $tmpline);

      // bold
      $tmpline = preg_replace("|(''')(.*?)(''')|",
                              "<strong>\\2</strong>", $tmpline);

      // bold
      $tmpline = preg_replace("|(__)(.*?)(__)|",
                              "<strong>\\2</strong>", $tmpline);

      // italics
      $tmpline = preg_replace("|('')(.*?)('')|",
                              "<em>\\2</em>", $tmpline);


      // HTML modes: pre, unordered/ordered lists, term/def  (using TAB)
      if (preg_match("/(^\t+)(.*?)(:\t)(.*$)/", $tmpline, $matches)) {
         // this is a dictionary list item
         $numtabs = strlen($matches[1]);
         $html .= SetHTMLOutputMode("dl", SINGLE_DEPTH, $numtabs);
	 $tmpline = '';
	 if(trim($matches[2]))
            $tmpline = "<dt>" . $matches[2];
	 $tmpline .= "<dd>" . $matches[4];

      } elseif (preg_match("/(^\t+)(\*|\d+|#)/", $tmpline, $matches)) {
         // this is part of a list
         $numtabs = strlen($matches[1]);
         if ($matches[2] == "*") {
            $listtag = "ul";
         } else {
            $listtag = "ol"; // a rather tacit assumption. oh well.
         }
         $tmpline = preg_replace("/^(\t+)(\*|\d+|#)/", "", $tmpline);
         $html .= SetHTMLOutputMode($listtag, SINGLE_DEPTH, $numtabs);
         $html .= "<li>";

      // tabless markup for unordered and ordered lists
      // list types can be mixed, so we only look at the last
      // character. Changes e.g. from "**#*" to "###*" go unnoticed.
      // and wouldn't make a difference to the HTML layout anyway.

      // unordered lists <UL>: "*"
      } elseif (preg_match("/^([#*]*\*)[^#]/", $tmpline, $matches)) {
         // this is part of an unordered list
         $numtabs = strlen($matches[1]);
         $listtag = "ul";

         $tmpline = preg_replace("/^([#*]*\*)/", "", $tmpline);
         $html .= SetHTMLOutputMode($listtag, SINGLE_DEPTH, $numtabs);
         $html .= "<li>";

      // ordered lists <OL>: "#"
      } elseif (preg_match("/^([#*]*\#)/", $tmpline, $matches)) {
         // this is part of an ordered list
         $numtabs = strlen($matches[1]);
         $listtag = "ol";

         $tmpline = preg_replace("/^([#*]*\#)/", "", $tmpline);
         $html .= SetHTMLOutputMode($listtag, SINGLE_DEPTH, $numtabs);
         $html .= "<li>";

      // definition lists <DL>: ";text:text"
      } elseif (preg_match("/(^;+)(.*?):(.*$)/", $tmpline, $matches)) {
         // this is a dictionary list item
         $numtabs = strlen($matches[1]);
         $html .= SetHTMLOutputMode("dl", SINGLE_DEPTH, $numtabs);
	 $tmpline = '';
	 if(trim($matches[2]))
            $tmpline = "<dt>" . $matches[2];
	 $tmpline .= "<dd>" . $matches[3];


      } elseif (preg_match("/^\s+/", $tmpline)) {
         // this is preformatted text, i.e. <pre>
         $html .= SetHTMLOutputMode("pre", ZERO_DEPTH, 0);

      } elseif (preg_match("/^(!{1,3})[^!]/", $tmpline, $whichheading)) {
	 // lines starting with !,!!,!!! are headings
	 if($whichheading[1] == '!') $heading = "h3";
	 elseif($whichheading[1] == '!!') $heading = "h2";
	 elseif($whichheading[1] == '!!!') $heading = "h1";
	 $tmpline = preg_replace("/^!+/", "", $tmpline);
	 $html .= SetHTMLOutputMode($heading, ZERO_DEPTH, 0);

      } else {
         // it's ordinary output if nothing else
         $html .= SetHTMLOutputMode("", ZERO_DEPTH, 0);
      }

      $tmpline = str_replace("%%Search%%", $quick_search_box, $tmpline);
      $tmpline = str_replace("%%Fullsearch%%", $full_search_box, $tmpline);
      $tmpline = str_replace("%%Mostpopular%%", $most_popular_list, $tmpline);

      ///////////////////////////////////////////////////////
      // Replace tokens

      for ($i = 0; $i < $ntokens; $i++)
	  $tmpline = str_replace($FieldSeparator.$FieldSeparator.$i.$FieldSeparator, $replacements[$i], $tmpline);


      $html .= "$tmpline\n";
   }

   $html .= SetHTMLOutputMode("", ZERO_DEPTH, 0);
?>
