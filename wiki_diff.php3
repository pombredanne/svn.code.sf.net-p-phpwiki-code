<?
// wiki_diff.php3
//
// A PHP diff engine for phpwiki.
//
// Copyright (C) 2000 Geoffrey T. Dairiki <dairiki@dairiki.org>
// You may copy this code freely under the conditions of the GPL.
//


/**
 * Class used internally by WikiDiff to actually compute the diffs.
 *
 * The algorithm and much of the code for this class is lifted straight
 * from analyze.c (from GNU diffutils-2.7).
 *
 * GNU diffutils can be found at:
 * ftp://gnudist.gnu.org/pub/gnu/diffutils/diffutils-2.7.tar.gz
 */
class _WikiDiffEngine {
    var $xv, $yv;		// Vectors being compared.
    var $xchanged, $ychanged;	// Flags for changed lines in XV and YV.
    var $edits;			// List of editing operation to convert XV to YV.
    
    function _WikiDiffEngine ($from_lines, $to_lines)
	{
	  $this->xv = $from_lines;
	  $this->yv = $to_lines;

	  $x = 0;
	  $y = 0;

	  $xlim = sizeof($this->xv);
	  $ylim = sizeof($this->yv);

	  // Compute xchanged and ychanged.
	  $this->_compareseq(0, $xlim, 0, $ylim);

	  while ($x < $xlim || $y < $ylim)
	    {
	      unset($edit);
	      
	      // Skip matching "snake".
	      while ($x < $xlim && $y < $ylim
		     && !$this->xchanged[$x] && !$this->ychanged[$y])
		{
		  ++$x;
		  ++$y;
		  ++$edit['cp'];
		}
	      
	      // Find deletes.
	      while ($x < $xlim && $this->xchanged[$x])
		{
		  ++$x;
		  ++$edit['del'];
		}
	      

	      // Find adds.
	      while ($y < $ylim && $this->ychanged[$y])
		  $edit['add'][] = $this->yv[$y++];

	      if ($edit['del'] || $edit['add'])
		  $this->edits[] = $edit;
	    }
	}

    /* Find the midpoint of the shortest edit script for a specified
       portion of the two files.

       Scan from the beginnings of the files, and simultaneously from the ends,
       doing a breadth-first search through the space of edit-sequence.
       When the two searches meet, we have found the midpoint of the shortest
       edit sequence.

       Returns (C, XMID, YMID).  C is the approximate edit cost; this is
       the total number of lines inserted or deleted (counting only lines
       before the midpoint).  (XMID, YMID) is the midpoint.

       This function assumes that the first lines of the specified portions
       of the two files do not match, and likewise that the last lines do not
       match.  The caller must trim matching lines from the beginning and end
       of the portions it is going to specify. */
    function _diag ($xoff, $xlim, $yoff, $ylim)
	{
	  $dmin = $xoff - $ylim; // Minimum valid diagonal.
	  $doff = 1 - $dmin;	// Offset to assure index is always non-neg.
	  $_dmin = 1;
	  $_dmax = $xlim - $yoff + $doff; // Maximum valid diagonal
	  $_fmid = $xoff - $yoff + $doff; // Center diagonal of top-down search
	  $_bmid = $xlim - $ylim + $doff; // Center diagonal of bottom-up search
	  $_fmin = $_fmid; $_fmax = $_fmid; // Limits of top-down search.
	  $_bmin = $_bmid; $_bmax = $_bmid; // Limits of bottom-up search.
	  $is_odd = ($_fmid - $_bmid) & 1; /* True if southeast corner is on
					    * an odd diagonal with respect to
					    * the northwest. */

	  $fd[$_fmid] = $xoff;
	  $bd[$_bmid] = $xlim;

	  for ($c = 1;; ++$c)
	    {
	      //int d;			/* Active diagonal. */
	      //int big_snake = 0;

	      // Extend the top-down search by an edit step in each diagonal.
	      if ($_fmin > $_dmin)
		  $fd[--$_fmin - 1] = -1;
	      else
		  ++$_fmin;
	      if ($_fmax < $_dmax)
		  $fd[++$_fmax + 1] = -1;
	      else
		  --$_fmax;

	      for ($_d = $_fmax; $_d >= $_fmin; $_d -= 2)
		{
		  $tlo = $fd[$_d - 1];
		  $thi = $fd[$_d + 1];

		  if ($tlo >= $thi)
		    $x = $tlo + 1;
		  else
		    $x = $thi;
		  $oldx = $x;
		  $y = $x - ($_d - $doff);
		  while ($x < $xlim && $y < $ylim
			 && $this->xv[$x] == $this->yv[$y])
		    {
		      ++$x;
		      ++$y;
		    }
		  $fd[$_d] = $x;
		  if ($is_odd && $_bmin <= $_d && $_d <= $_bmax
		      && $bd[$_d] <= $x)
		    {
		      return array(2 * $c - 1, $x, $y);
		    }
		}

	      /* Similarly extend the bottom-up search.  */
	      if ($_bmin > $_dmin)
		  $bd[--$_bmin - 1] = $xlim + 1;
	      else ++$_bmin;
	      if ($_bmax < $_dmax)
		  $bd[++$_bmax + 1] = $xlim + 1;
	      else
		  --$_bmax;
	      for ($_d = $_bmax; $_d >= $_bmin; $_d -= 2)
		{
		  $tlo = $bd[$_d - 1]; $thi = $bd[$_d + 1];

		  if ($tlo < $thi)
		    $x = $tlo;
		  else
		    $x = $thi - 1;
		  $oldx = $x;
		  $y = $x - ($_d - $doff);
		  while ($x > $xoff && $y > $yoff
			 && $this->xv[$x - 1] == $this->yv[$y - 1])
		    {
		      --$x;
		      --$y;
		    }
		  $bd[$_d] = $x;
		  if (!$is_odd && $_fmin <= $_d && $_d <= $_fmax
		      && $x <= $fd[$_d])
		    {
		      return array(2 * $c, $x, $y);
		    }
		}
	      /* FIXME: add heuristics to avoid slowness?
	       *
	       * I've deleted from the diffutils algorithm two hairy
	       * heuristics which are desingned to keep the algorithm
	       * efficient even on large files.  (The algorithm as
	       * implemented here on O(n^2).)
	       *
	       * I believe that As long as we're dealing with files
	       * under a few hundred lines long this is not really
	       * an issue.
	       */
	    }
	}

    /* Compare in detail contiguous subsequences of the two files
       which are known, as a whole, to match each other.
       
       The results are recorded in the vectors $this->{x,y}changed[], by
       storing a 1 in the element for each line that is an insertion
       or deletion.
       
       The subsequence of file 0 is [XOFF, XLIM) and likewise for file 1.
       
       Note that XLIM, YLIM are exclusive bounds.
       All line numbers are origin-0 and discarded lines are not counted. */
    function _compareseq ($xoff, $xlim, $yoff, $ylim)
	{
	  /* Slide down the bottom initial diagonal. */
	  while ($xoff < $xlim && $yoff < $ylim
		 && $this->xv[$xoff] == $this->yv[$yoff])
	    {
	      ++$xoff;
	      ++$yoff;
	    }
	  
	  /* Slide up the top initial diagonal. */
	  while ($xlim > $xoff && $ylim > $yoff
		 && $this->xv[$xlim - 1] == $this->yv[$ylim - 1])
	    {
	      --$xlim;
	      --$ylim;
	    }
	  
	  /* Handle simple cases. */
	  if ($xoff == $xlim)
	    {
	      while ($yoff < $ylim)
		  $this->ychanged[$yoff++] = 1;
	    }
	  else if ($yoff == $ylim)
	    {
	      while ($xoff < $xlim)
		  $this->xchanged[$xoff++] = 1;
	    }
	  else
	    {
	      // Find a point of correspondence in the middle of the files.
	      list ($c, $xmid, $ymid) = $this->_diag($xoff, $xlim,
						     $yoff, $ylim);

	      if ($c <= 1)
		{
		  /* This should be impossible, because it implies that
		     one of the two subsequences is empty,
		     and that case was handled above without calling `diag'.
		     Let's verify that this is true.  */
		  die("This is impossible"); //FIXME
		}
	      
	      // Use the partitions to split this problem into subproblems.
	      $this->_compareseq ($xoff, $xmid, $yoff, $ymid);
	      $this->_compareseq ($xmid, $xlim, $ymid, $ylim);
	    }
	}
}

    

class WikiDiff 
{
    var $edits;
    
    function WikiDiff($from_lines = false, $to_lines = false)
	{
	  if ($from_lines && $to_lines)
	    {
	      $compute = new _WikiDiffEngine($from_lines, $to_lines);
	      $this->edits = $compute->edits;
	    }
	  else if ($from_lines)
	    {
	      // $from_lines is not really from_lines, but rather
	      // a serialized WikiDiff.
	      $this->edits = unserialize($from_lines);
	    }
	}

    function apply ($from_lines)
	{
	  $x = 0;
	  $xlim = sizeof($from_lines);

	  for ( reset($this->edits);
		$edit = current($this->edits);
		next($this->edits) )
	    {
	      
	      for ($i = 0; $i < $edit['cp']; $i++)
		  $output[] = $from_lines[$x++];
	      $x += $edit['del'];
	      if ($adds = $edit['add'])
		  for ( reset($adds); $add = current($adds); next($adds) )
		      $output[] = $add;
	    }
	  while ($x < $xlim)
	      $output[] = $from_lines[$x++];
	  if ($x != $xlim)
	      die("WikiDiff::apply: line count mismatch: $x != $xlim");
	  return $output;
	}
    
    function serialize ()
	{
	  return serialize($this->edits);
	}

    function unserialize ($serial)
	{
	  $this->edits = unserialize($serial);
	}
}

class WikiDiffFormatter {

    var $context_lines;
    var $context_prefix, $deletes_prefix, $adds_prefix;

    function WikiDiffFormatter ()
	{
	  $this->context_lines = 0;
	  $this->context_prefix = '&nbsp;&nbsp;';
	  $this->deletes_prefix = '&lt;&nbsp;';
	  $this->adds_prefix = '&gt;&nbsp;';
	}

    function format ($diff, $from_lines)
	{
	  $html = '<table width="100%" bgcolor="black"' .
		  "cellspacing=2 cellpadding=2 border=0\n";
	  $html .= $this->_format($diff->edits, $from_lines);
	  $html .= "</table>\n";

	  return $html;
	}
    
    function _format ($edits, $from_lines)
	{
	  $x = 0; $y = 0;
	  $end_last_context = 0;
	  $xlim = sizeof($from_lines);
	  
	  reset($edits);
	  while ($edit = current($edits))
	    {
	      $x += $edit['cp'];
	      $y += $edit['cp'];
	      
	      // Copy leading context.
	      $cc = max($end_last_context, $x - $this->context_lines);
	      if (!$hunks)
		{
		  $xoff = $cc;
		  $yoff = $xoff + $y - $x;
		}
	      while ($cc < $x)
		  $hunk['context'][] = $from_lines[$cc++];

	      // Copy deletes
	      for ($i = 0; $i < $edit['del']; $i++)
		  $hunk['deletes'][] = $from_lines[$x++];

	      // Copy adds
	      if ($adds = $edit['add'])
		  for ( reset($adds); $add = current($adds); next($adds) )
		    {
		      $hunk['adds'][] = $add;
		      ++$y;
		    }

	      $hunks[] = $hunk;
	      $hunk = array();

	      // Copy trailing context.
	      $cc = $x;
	      while ($cc < min($x + $this->context_lines, $xlim))
		  $hunk['context'][] = $from_lines[$cc++];
	      $end_last_context = $cc;
	      $xlen = $cc - $xoff;
	      $ylen = $cc + $y - $x - $yoff;;
	      
	      if (!($edit = next($edits))
		  || $edit['cp'] > 2 * $this->context_lines)
		{
		  $hunks[] = $hunk;
		  $hunk = array();
		  $xbeg = $xlen ? $xoff + 1 : $xoff;
		  $ybeg = $ylen ? $yoff + 1 : $yoff;
		  $html .= $this->_emit_diff( $xbeg,$xlen,$ybeg,$ylen, $hunks );
		  unset($hunks);
		}
	    }

	    return $html;
	}

    function _emit_lines($lines,  $prefix, $color)
	{
	  if (! is_array($lines))
	      return '';

	  for (reset($lines); $line = current($lines); next($lines))
	    {
	      $html .= "<tr bgcolor=\"$color\"><td><tt>$prefix</tt>";
	      $html .= htmlspecialchars($line) . "</td></tr>\n";
	    }

	  return $html;
	}

    function _emit_diff ($xbeg,$xlen,$ybeg,$ylen,$hunks)
	{
	  $html = '<tr><td><table width="100%" bgcolor="white"'
		. " cellspacing=0 border=0 cellpadding=4>\n"
		. '<tr><td>'
		. $this->_diff_header($xbeg, $xlen, $ybeg, $ylen)
		. "</td></tr>\n<tr><td>\n"
		. "<table width=\"100%\" cellspacing=0 border=0 cellpadding=2>\n";

	  for (reset($hunks); $hunk = current($hunks); next($hunks))
	    {
	      $html .= $this->_emit_lines($hunk['context'],
				 $this->context_prefix,
				 '#cccccc');
	      $html .= $this->_emit_lines($hunk['deletes'],
				 $this->deletes_prefix,
				 '#ffcccc');
	      $html .= $this->_emit_lines($hunk['adds'],
				 $this->adds_prefix,
				 '#ccffcc');
	    }

	  $html .= "</table></td></tr></table></td></tr>\n";
	  return $html;
	}

    function _diff_header ($xbeg,$xlen,$ybeg,$ylen)
	{
	  $what = $xlen ? ($ylen ? 'c' : 'd') : 'a';
	  $xlen = $xlen > 1 ? "," . ($xbeg + $xlen - 1) : '';
	  $ylen = $ylen > 1 ? "," . ($ybeg + $ylen - 1) : '';

	  return "$xbeg$xlen$what$ybeg$ylen";
	}
}

class WikiUnifiedDiffFormatter extends WikiDiffFormatter {
    function WikiUnifiedDiffFormatter ($context_lines = 3)
	{
	  $this->context_lines = $context_lines;
	  $this->context_prefix = '&nbsp;';
	  $this->deletes_prefix = '-';
	  $this->adds_prefix = '+';
	}
    
    function _diff_header ($xbeg,$xlen,$ybeg,$ylen)
	{
	  $xlen = $xlen == 1 ? '' : ",$xlen";
	  $ylen = $ylen == 1 ? '' : ",$ylen";

	  return "@@ -$xbeg$xlen +$ybeg$ylen @@";
	}
}



/////////////////////////////////////////////////////////////////

$pagename = $diff;

$wiki = RetrievePage($dbi, $pagename);
$dba = OpenDataBase($ArchiveDataBase);
$archive= RetrievePage($dba, $pagename);

if((!is_array($wiki)) || (!is_array($archive))) {
   $html = 'There exists no archived version of the page, or the page itself does not exist.';
}
else {
   $diff = new WikiDiff($archive['content'], $wiki['content']);
   $plain_fmt = new WikiDiffFormatter();
   $html = $plain_fmt->format($diff, $archive['content']);
}

GeneratePage('MESSAGE', $html, 'Diff of '.htmlspecialchars($pagename), 0);
?>
