<?
   /*
      display.php3: render a page. This has all the display 
      logic in it, except for the search boxes.
   */
 
   // if we got GET data, the first item is always a page name
   // if it wasn't this file would not have been included

   if ($argv[0]) {
      // match pagename in GET data, or fail
      if (ereg("^(([A-Z][a-z]+){2,})", $argv[0], $regs)) {
         $pagename = $regs[1];
      } else {
         // error, no valid page name passed in
         echo "Error: invalid page name";
         exit();
      }
   } else { 
      $pagename = "FrontPage"; 

      // if there is no FrontPage, create a basic set of Wiki pages
      if (! IsWikiPage($dbi, $pagename)) {
         include "wiki_setupwiki.php3";
      }
   }

   WikiHeader($pagename);

   echo "<h1>$LogoImage ";
   echo "<a href=\"$ScriptUrl?full=$pagename\">$pagename</a></h1>\n";

   $pagehash = RetrievePage($dbi, $pagename);
   if (is_array($pagehash)) {
      // we render the page if it's a hash, else ask the user to write
      // one.

      // Set up inline links and images
      for ($i = 1; $i < (NUM_LINKS + 1); $i++) {
         if (! empty($pagehash["r$i"])) {
            if (preg_match("/png$/i", $pagehash["r$i"])) {
               // embed PNG images
               $embedded[$i] = "<img src='" . $pagehash["r$i"] . "'>";
            } else {
               // ordinary embedded link
               $embedded[$i] = "<a href='" . $pagehash["r$i"] . "'>[$i]</a>";
            }
         }
      }

      $numlines = count($pagehash["text"]);

      // Loop over all lines of tha page and apply transformation rules
      for ($index = 0; $index < $numlines; $index++) {
         $tmpline = $pagehash["text"][$index];

         // workaround for null array elements bug
         // This was affecting RecentChanges but no more
         if (strlen($tmpline) == 0) {
            continue;
         }

         if (strlen($tmpline) == 1) {
            // this is a blank line, send <p>
            SetHTMLOutputMode("p", ZERO_DEPTH, 0);
            continue;

         }
/* If your web server is not accessble to the general public, you may allow this code below, 
   which allows embedded HTML. If anyone can reach your web server it is highly advised that
   you do not allow this.

         elseif (preg_match("/(^\|)(.*)/", $tmpline, $matches)) {
            // HTML mode
            SetHTMLOutputMode("", ZERO_DEPTH, 0);
            echo $matches[2];
            continue;
         }
*/
         // escape HTML metachars
         $tmpline = ereg_replace("[&]", "&amp;", $tmpline);
         $tmpline = ereg_replace("[>]", "&gt;", $tmpline);
         $tmpline = ereg_replace("[<]", "&lt;", $tmpline);

         // four or more dashes to <hr>
         $tmpline = ereg_replace("^-{4,}", "<hr>", $tmpline);


         // replace all URL's with tokens, so we don't confuse them
         // with Wiki words later. Wiki words in URL's break things.

         $hasURLs = preg_match_all("/\b((http)|(ftp)|(mailto)|(news)|(file)|(gopher)):[^\s\<\>\[\]\"'\(\)]*[^\s\<\>\[\]\"'\(\)\,\.\?]/", $tmpline, $urls);

         // workaround: php can only do global search and replace which
         // renders wrong when the domain appears in two consecutive URL's 
         // on the same line, but the second is longer i.e. 
         // http://c2.com followed by http://c2.com/wiki 
         rsort($urls[0]);
         reset($urls[0]);

         for ($i = 0; $i < $hasURLs; $i++) {
            $inplaceURL = preg_quote($urls[0][$i]);
            $URLtoken = "${FieldSeparator}${i}${FieldSeparator}";
            $tmpline = preg_replace("|$inplaceURL|",
                                    $URLtoken,
                                    $tmpline);
         }

         // bold italics
         $tmpline = eregi_replace("(''''')(.*)(''''')",
                                 "<strong><em>\\2</em></strong>",
                                 $tmpline);

         // bold
         $tmpline = eregi_replace("(''')(.*)(''')",
                                 "<strong>\\2</strong>",
                                 $tmpline);

         // italics
         $tmpline = eregi_replace("('')(.*)('')",
                                 "<em>\\2</em>",
                                 $tmpline);

         // Link Wiki words
         if (preg_match_all("#\b(([A-Z][a-z]+){2,})\b#",
                            $tmpline, 
                            $link)) {

            // uniq the list of matches
            $hash = "";
            for ($i = 0; $link[0][$i]; $i++) {
               // $realfile = $link[0][$i];
               $hash[$link[0][$i]]++;
            }

            reset($hash);
            while (list($realfile, $val) = each($hash)) {
               if (IsWikiPage($dbi, $realfile)) {
                  $tmpline = preg_replace("|\b$realfile\b|",
                              LinkExistingWikiWord($realfile),
                              $tmpline);
               } else {
                  $tmpline = preg_replace("|\b$realfile\b|",
                              LinkUnknownWikiWord($realfile),
                              $tmpline);
               }
            }

         }

         // put URLs back, linked
         for ($i = 0; $i < $hasURLs; $i++) {
            $inplaceURL = "<a href='" . $urls[0][$i] . "'>";
            $inplaceURL .=  $urls[0][$i] . "</a>";
            $URLtoken = "${FieldSeparator}${i}${FieldSeparator}";
            $tmpline = preg_replace("|$URLtoken|", 
                                    $inplaceURL,
                                    $tmpline);
         }


         // Insert search boxes, if needed
         $tmpline = ereg_replace("\[Search]", RenderQuickSearch(), $tmpline);
         $tmpline = ereg_replace("\[Fullsearch]", RenderFullSearch(), $tmpline);

         // match and replace all user-defined links ([1], [2], [3]...)
         preg_match_all("|\[(\d)\]|", $tmpline, $match);
         if (count($match[0])) {
            for ($k = 0; $k < count($match[0]); $k++) {
               if (! empty($embedded[$match[1][$k]])) {
                  $linkpattern = preg_quote($match[0][$k]);
                  $tmpline = preg_replace("|$linkpattern|",
                                          $embedded[$match[1][$k]],
                                          $tmpline);
               }
            }
         }

         // HTML modes: pre, unordered/ordered lists, term/def
         if (preg_match("/(^\t)(.*?)(:\t)(.*$)/", $tmpline, $matches)) {
            // this is a dictionary list item
            SetHTMLOutputMode("dl", SINGLE_DEPTH, 1);
            $tmpline = "<dt>" . $matches[2] . "<dd>" . $matches[4];

         } elseif (preg_match("/(^\t+)(\*|\d|#)/", $tmpline, $matches)) {
            // this is part of a list
            $numtabs = strlen($matches[1]);
            if ($matches[2] == "*") {
               $listtag = "ul";
            } else {
               $listtag = "ol"; // a rather tacit assumption. oh well.
            }
            $tmpline = preg_replace("/^(\t+)(\*|\d|#)/", "", $tmpline);
            SetHTMLOutputMode($listtag, SINGLE_DEPTH, $numtabs);
            echo "<li>";

         } elseif (preg_match("/^\s+/", $tmpline)) {
            // this is preformatted text, i.e. <pre>
            SetHTMLOutputMode("pre", ZERO_DEPTH, 0);

         } else {
            // it's ordinary output if nothing else
            SetHTMLOutputMode("", ZERO_DEPTH, 0);
         }

         echo "$tmpline"; // at last, emit the code
      }

   } else {
      echo "Describe $pagename<a href='$ScriptUrl?edit=$pagename'>?</a> here.\n";
   }

   SetHTMLOutputMode("", ZERO_DEPTH, 0);
   WikiToolBar();
   WikiFooter();
?>

