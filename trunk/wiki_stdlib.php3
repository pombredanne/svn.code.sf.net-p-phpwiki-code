<?
   /*
      Standard functions for Wiki functionality
         WikiToolBar() 
         WikiHeader($pagename) 
         WikiFooter() 
         GetCurrentDate()
         LinkExistingWikiWord($wikiword) 
         LinkUnknownWikiWord($wikiword) 
         RenderQuickSearch() 
         RenderFullSearch() 
         CookSpaces($pagearray) 
         class Stack
         SetHTMLOutputMode($newmode, $depth)
   */

   // render the Wiki toolbar at bottom of page
   function WikiToolBar() {
      global $ScriptUrl, $pagename, $pagehash;
      echo "<hr>\n";
      echo "<a href=\"$ScriptUrl?edit=$pagename\">EditText</a>\n";
      echo " of this page\n";
      if (is_array($pagehash)) {
         echo " (last edited ", $pagehash["date"], ")\n";
      }
      echo "<br>\n";

      echo "<a href='$ScriptUrl?FindPage&value=$pagename";
      echo "'>FindPage</a> by browsing or searching\n";
   }

   // top of page
   function WikiHeader($pagename) {
      global $LogoImage, $ScriptUrl;
      echo "<html>\n";
      echo "<head>\n";
      echo "<title>$pagename</title>\n";
      echo "</head>\n";
      echo "<body>\n";
   }

   function WikiFooter() {
      echo "</body>\n</html>\n";
   }

   function GetCurrentDate() {
      // format is like December 13, 1999
      return date("F j, Y");
   }
   
   function LinkExistingWikiWord($wikiword) {
      global $ScriptUrl;
      return "<a href=\"$ScriptUrl?$wikiword\">$wikiword</a>";
   }

   function LinkUnknownWikiWord($wikiword) {
      global $ScriptUrl;
      return "$wikiword<a href=\"$ScriptUrl?edit=$wikiword\">?</a>";

   }

   
   function RenderQuickSearch() {
      global $value, $ScriptUrl;
      static $formtext = "<form action='$ScriptUrl'>\n<input type='text' size='40' name='search' value='$value'>\n</form>\n";
      return $formtext;
   }

   function RenderFullSearch() {
      global $value, $ScriptUrl;
      static $formtext = "<form action='$ScriptUrl'>\n<input type='text' size='40' name='full' value='$value'>\n</form>\n";
      return $formtext;
   }

   // converts spaces to tabs
   function CookSpaces($pagearray) {
      return preg_replace("/ {3,8}/", "\t", $pagearray);
   }


   class Stack {
      var $items;
      var $size = 0;

      function push($item) {
         $this->items[$this->size] = $item;
         $this->size++;
         return true;
      }  
   
      function pop() {
         if ($this->size == 0) {
            return false; // stack is empty
         }  
         $this->size--;
         return $this->items[$this->size];
      }  
   
      function cnt() {
         return $this->size;
      }  

      function top() {
         return $this->items[$this->size - 1];
      }  

   }  
   // end class definition


   /* 
      Wiki HTML output can, at any given time, be in only one mode.
      It will be something like Unordered List, Preformatted Text,
      plain text etc. When we change modes we have to issue close tags
      for one mode and start tags for another.
   */

   // couldn't create a static version :-/
   // I couldn't move this to config.php3 because it 
   // wasn't declared yet.
   $stack = new Stack;

   function SetHTMLOutputMode($tag, $tagdepth, $tabcount) {
      global $stack;
   
      if ($tagdepth == SINGLE_DEPTH) {
   
         if ($tabcount < $stack->cnt()) {
   
            // there are fewer tabs than stack, reduce stack
            // to one less than tab count; then push new tag
            while ($stack->cnt() > ($tabcount - 1)) {
               $closetag = $stack->pop();
               if ($closetag == false) {
                  //echo "bounds error in tag stack";
                  //exit();
                  break;
               }
               echo "</$closetag>\n";
            }
   
            echo "<$tag>\n";
            $stack->push($tag);
   
         } elseif ($tabcount > $stack->cnt()) {
            // we add the diff to the stack
            // stack might be zero
            while ($stack->cnt() < $tabcount) {
               echo "<$tag>\n";
               $stack->push($tag);
               if ($stack->cnt() > 10) {
                  // arbitrarily limit tag nesting
                  echo "Stack bounds exceeded in SetHTMLOutputMode\n";
                  exit();
               }
            }
   
         } else {
            if ($tag == $stack->top()) {
               return;
            } else {
               $closetag = $stack->pop();
               echo "</$closetag>\n";
               echo "<$tag>\n";
               $stack->push($tag);
            }
         }
   
      } elseif ($tagdepth == ZERO_DEPTH) {
         // empty the stack for $depth == 0;
         // what if the stack is empty?
         if ($tag == $stack->top()) {
            return;
         }
         while ($stack->cnt() > 0) {
            $closetag = $stack->pop();
            echo "</$closetag>\n";
         }
   
         if ($tag) {
            echo "<$tag>\n";
            $stack->push($tag);
         }
   
      } else {
         // error
         echo "Passed bad tag depth value in SetHTMLOutputMode\n";
         exit();
      }
   }
   // end SetHTMLOutputMode


?>
