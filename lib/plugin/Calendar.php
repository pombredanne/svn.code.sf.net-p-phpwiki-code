<?php // -*-php-*-
rcs_id('$Id: Calendar.php,v 1.4 2001-12-16 18:33:25 dairiki Exp $');

if (!defined('SECONDS_PER_DAY'))
    define('SECONDS_PER_DAY', 24 * 3600);

// FIXME: Still needs:
//
//   o Better way to navigate to distant months.
//     (Maybe a form with selectors for month and year)?
//
//   o Docs.  Write a pgsrc/CalendarPlugin.
//
// It would be nice to have some way to get from the individual
// date pages back to the calendar page.  (Subpage support might
// make this easier.)

/**
 */
class WikiPlugin_Calendar
extends WikiPlugin
{
    function getName () {
        return _("Calendar");
    }

    function getDescription () {
        return _("Calendar");
    }
    
    function getDefaultArguments() {
        // FIXME: how to exclude multiple pages?
        return array('prefix'		=> '[pagename]:',
                     'date_format'	=> '%Y-%m-%d',
                     'year'		=> '',
                     'month'		=> '',
                     'month_offset'	=> 0,
                     
                     'month_format'	=> '%B, %Y',
                     'wday_format'	=> '%a',
                     'start_wday'	=> '0');
    }

    function __header($pagename, $time)  {
        $args = &$this->args;

        $t = localtime($time - SECONDS_PER_DAY, 1);
        $prev_url = WikiURL($pagename, array('month' => $t['tm_mon'] + 1,
                                             'year' => $t['tm_year'] + 1900));
        
        $t = localtime($time + 32 * SECONDS_PER_DAY, 1);
        $next_url = WikiURL($pagename, array('month' => $t['tm_mon'] + 1,
                                             'year' => $t['tm_year'] + 1900));

        $prev = QElement('a', array('href' => $prev_url,
                                    'class' => 'cal-arrow',
                                    'title' => gettext("Previous Month")),
                         '<');
        $next = QElement('a', array('href' => $next_url,
                                    'class' => 'cal-arrow',
                                    'title' => gettext("Next Month")),
                         '>');


        $row = Element('td', array('align' => 'left'), $prev);
        $row .= Element('td', array('align' => 'center'),
                        QElement('b', array('class' => 'cal-header'),
                                 strftime($args['month_format'], $time)));
        $row .= Element('td', array('align' => 'right'), $next);

        $row =  Element('table', array('width' => '100%'),
                        Element('tr', $row));

        return Element('tr', Element('td', array('colspan' => 7,
                                                 'align' => 'center'),
                                     $row));
    }
    

    function __daynames($start_wday) {
        $time = mktime(12, 0, 0, 1, 1, 2001);
        $t = localtime($time, 1);
        $time += (7 + $start_wday - $t['tm_wday']) * SECONDS_PER_DAY;

        $t = localtime($time, 1);
        assert($t['tm_wday'] == $start_wday);
        
        $fs = $this->args['wday_format'];
        for ($i = 0; $i < 7; $i++) {
            $days[$i] = QElement('td', array('class' => 'cal-dayname',
                                             'align' => 'center'),
                                 strftime($fs, $time));
            $time += SECONDS_PER_DAY;
        }
        return Element('tr', join('', $days));
    }

    function __date($dbi, $time) {
        $args = &$this->args;

        $page_for_date = $args['prefix'] . strftime($args['date_format'], $time);
        $t = localtime($time, 1);
        
        if ($dbi->isWikiPage($page_for_date)) {
            $date = Element('a', array('class' => 'cal-day',
                                       'href'  => WikiURL($page_for_date),
                                       'title' => $page_for_date),
                            QElement('b', $t['tm_mday']));
        }
        else {
            $date = QElement('a', array('class' => 'cal-hide',
                                        'href'  => WikiURL($page_for_date,
                                                           array('action' => 'edit')),
                                        'title' => sprintf(_("Edit %s"), $page_for_date)),
                             $t['tm_mday']);
        }

        return  Element('td', array('align' => 'center'),
                        "&nbsp;${date}&nbsp;");
    }
            
    function run($dbi, $argstr, $request) {
        $this->args = $this->getArgs($argstr, $request);
        $args = &$this->args;

        $now = localtime(time(), 1);
        foreach ( array('month' => $now['tm_mon'] + 1,
                        'year' => $now['tm_year'] + 1900)
                  as $param => $dflt) {
            if (!($args[$param] = intval($args[$param])))
                $args[$param] = $dflt;
            
        }

        $time = mktime(12, 0, 0,  // hh, mm, ss,
                       $args['month'] + $args['month_offset'], // month (1-12)
                       1, // mday (1-31)
                       $args['year']);
        
        $rows[] = $this->__header($request->getArg('pagename'), $time);
        $rows[] = $this->__daynames($args['start_wday']);

        $t = localtime($time, 1);
        $col = (7 + $t['tm_wday'] - $args['start_wday']) % 7;
        $row = $col > 0 ? Element('td', array('colspan' => $col)) : '';
        $done = false;
        
        while (!$done) {
            $row .= $this->__date($dbi, $time);

            if (++$col % 7 == 0) {
                $rows[] = Element('tr', $row);
                $col = 0;
                $row = '';
            }
            
            $time += SECONDS_PER_DAY;
            $t = localtime($time, 1);
            $done = $t['tm_mday'] == 1;
        }

        if ($row) {
            $row .= Element('td', array('colspan' => (42 - $col) % 7));
            $rows[] = Element('tr', $row);
        }
        
        return Element('table', array('cellspacing' => 0,
                                      'cellpadding' => 2,
                                      'class' => 'cal'),
                       "\n" . join("\n", $rows) . "\n");
    }
};

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
