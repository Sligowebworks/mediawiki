<?php
# Calendar Extension - based on calendar extension at http://meta.wikimedia.org/wiki/Calendar_extension
# Creates a calendar for the month and optional 'upcoming events' box beneath
# This extension creates links to my Special:Events page to show the events for a particular day or month
#
#  Events much be stored in articles with [[Category:Events]] and a category for the date, eg [[Category:2006/07/12]]
#
# To install, add this line to LocalSettings.php: require_once("extensions/Calendar.php");
#
# eg <calendar/> shows current month and upcoming events
# or <calendar>upcoming=off</calendar> to not show upcoming events
#

$wgExtensionFunctions[] = "wfCalendarExtension";
$wgExtensionCredits['parserhook'][] = array(
        'name' => 'Calendar',
        'author' => 'Barrylb',
        'description' => 'adds <calender> tag',
        'url' => 'http://www.mediawiki.org/wiki/User:Barrylb'
);

/* DO NOT EDIT BEYOND THIS LINE */

function wfCalendarExtension() {
    global $wgParser;
    $wgParser->setHook( "calendar", "createmwCalendar" );
}

# The callback function for converting the input text to HTML output
function createmwCalendar($input)
{
        /**
        * check if date in $_GET-parameter
        * fallback on default this month
        **/

        if(isset($_GET['month'])&&(isset($_GET['year'])))
        {
                $month = intval($_GET['month']);
                $month = ($month<10?"0".$month:$month);
                $year = $_GET['year'];
        }
        else
        {
                $month = date("m");
                $year = date("Y");
        }
        
        $mwCalendar = new mwCalendar();
        $mwCalendar->dateNow($month, $year);
        if (strpos($input,'upcoming=off') === false)
        $mwCalendar->ShowUpcoming(true);
        else
                $mwCalendar->ShowUpcoming(false);
        return $mwCalendar->showThisMonth();
}


class mwCalendar
{
        var $cal = "CAL_GREGORIAN";
        var $format = "%Y%m%d";
        var $today;
        var $day;
        var $month;
        var $year;
        var $pmonth;
        var $pyear;
        var $nmonth;
        var $nyear;
        var $bShowUpcoming;
        var $wday_names = array("S","M","T","W","T","F","S"); 
        var $wmonth_names = array("January","February","March","April","May","June","July","August","September","October","November","December");
        
        function mwCalendar()
        {
                $this->day = "1";
                $today = "";
                $month = "";
                $year = "";
                $pmonth = "";
                $pyear = "";
                $nmonth = "";
                $nyear = "";
        }
        
        function ShowUpcoming($b)
        {
                $this->bShowUpcoming = $b;
        }
        
        
        function dateNow($month,$year)
        {
                $this->month = $month;
                $this->year = $year;
                $this->today = strftime("%d",time());
                $this->pmonth = $this->month - 1;
                $this->pyear = $this->year - 1;
                $this->nmonth = $this->month + 1;
                $this->nyear = $this->year + 1;
        }
        
        function daysInMonth($month,$year)
        {
                if (empty($year))
                {
                        $year = mwCalendar::dateNow("%Y");
                }
                if (empty($month))
                {
                        $month = mwCalendar::dateNow("%m");
                }
                if($month == "2")
                {
                        if (mwCalendar::isLeapYear($year))
                                return 29;
                        else
                                return 28;
                }
                else if ($month == "4" || $month == "6" || $month == "9" || $month == "11")
                        return 30;
                else
                        return 31;
        }
        
        function isLeapYear($year)
        {
                return (($year % 4 == "0" && $year % 100 != "0") || $year % 400 == "0");
        }
        
        function dayOfWeek($month,$year)
        {
                if ($month > 2) {
                        $month -= 2;
                }
                else
                {
                        $month += 10;
                        $year--;
                }

                $day =  ( floor((13 * $month - 1) / 5) +
                        $this->day + ($year % 100) +
                        floor(($year % 100) / 4) +
                        floor(($year / 100) / 4) - 2 *
                        floor($year / 100) + 77);

                $weekday_number = (($day - 7 * floor($day / 7))); 

                return $weekday_number;
        }
        function getWeekDay()
        {
                $week_day = mwCalendar::dayOfWeek($this->month,$this->year);
                return $week_Day;
        }
        
        function showThisMonth()
        {
                global $wgScript, $wgArticlePath, $wgUser;
                
                $lastyear = ($this->month==1?$this->year - 1:$this->year);
                $nextyear = ($this->month==12?$this->year + 1:$this->year);
                $lastmonth = ($this->month==1? 12 : $this->month - 1);
                $nextmonth = ($this->month==12? 1 : $this->month + 1);
                
                $lastmonth = ($lastmonth<10?"0".$lastmonth:$lastmonth);
                $nextmonth = ($nextmonth<10?"0".$nextmonth:$nextmonth);
                
                $sk =& $wgUser->getSkin();
                
                $dbr =& wfGetDB( DB_SLAVE );
                $sPageTable = $dbr->tableName( 'page' );
                $categorylinks = $dbr->tableName( 'categorylinks' );

                $res = $dbr->query(
                  "SELECT page_title, clike1.cl_to catlike1 " . 
                  "FROM $sPageTable INNER JOIN $categorylinks AS c1 ON page_id = c1.cl_from AND c1.cl_to='Events' INNER JOIN $categorylinks " . 
                        "AS clike1 ON page_id = clike1.cl_from AND clike1.cl_to LIKE '" . $this->year . "/" . $this->month . "/__' " .
                  "WHERE page_is_redirect = 0");
                while ($row = $dbr->fetchObject( $res ) ) 
                {
                        $dbDay = substr($row->catlike1,8,2);
                        if (isset($eventsByDay[$dbDay]) == '') 
                                $eventsByDay[$dbDay] = substr($row->page_title, 0, 200);
                        else
                        $eventsByDay[$dbDay] = '*multiple events*';             
                }       

                $output = '<table align="center" border="0" cellpadding="0" cellspacing="0" class="calendar">';
                $output .= '<tr class="calendarTop"><td><a href="'. str_replace('$1', "Special:Events?year=".$lastyear."&month=".$lastmonth, $wgArticlePath) .'"><</a></td><td colspan="5" class="cal-header"><center>'. 
                        '<a href="'. str_replace('$1', "Special:Events?year=".$this->year."&month=".$this->month, $wgArticlePath) .'">' . $this->wmonth_names[$this->pmonth] . ' ' .$this->year .'</a></center></td><td><a href="'. str_replace('$1', "Special:Events?year=".$nextyear."&month=".$nextmonth, $wgArticlePath) .'">></a></td></tr>';
                $output .= '<tr class="calendarDayNames">';
                for($i=0;$i<7;$i++)
                                                $output .= '<td>'. $this->wday_names[$i]. '</td>';
                $output .= '</tr>';
                $wday = mwCalendar::dayOfWeek($this->month,$this->year);
                $no_days = mwCalendar::daysInMonth($this->month,$this->year);
                $count = 1;
                $output .= '<tr>';
                for($i=1;$i<=$wday;$i++)
                {
                                                $output .= '<td> </td>';
                                                $count++;
                }
                /**
                * every day is a link to that day
                **/
                $todaysMonth = date("m");
                $todaysYear = date('Y');
                for($i=1;$i<=$no_days;$i++)
                {
                                $dayNr = ($i<10?"0".$i:$i);
                                $alinkedit = str_replace('$1', "Special:Events?year=".$this->year."&month=".$this->month."&day=".$dayNr, $wgArticlePath);
                                $alinkeditstyle = 'style="text-decoration:underline;"';

                                if (isset($eventsByDay[$dayNr]) == '')
                                        $full_link = $i;
                                else
                                        $full_link = '<a title="' . str_replace('_',' ',$eventsByDay[$dayNr]) . '" href="'.$alinkedit.'" '.$alinkeditstyle.'>' . $i . '</a>';
                                
                                if($count > 6)
                                {
                                        if (($i == $this->today) && ($this->month == $todaysMonth) && ($this->year == $todaysYear))
                                        {
                                                $output .= '<td><b>' . $full_link . '</b></td></tr>';
                                        }
                                        else
                                        {
                                                $output .= '<td class="cal-weekend">' . $full_link . '</a></td></tr>';
                                        }
                                        $count = 0;
                                }
                                else
                                {
                                        if (($i == $this->today) && ($this->month == $todaysMonth) && ($this->year == $todaysYear))
                                                $output .= '<td class="cal-today"><b>' . $full_link . '</a></b></td>';
                                        else
                                                $output .= '<td>' . $full_link . '</a></td>';
                                }
                                $count++;
                }
                for($i=$count;$i<=7;$i++)
                                $output .= "<td> </td>";
                $output .= '</tr></table>';
                                
                if ($this->bShowUpcoming)
                {
                        /**
                        * Show upcoming events
                        **/
                        $output .= '<table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" class="calendarupcoming">' .
                                '<tr><td class="calendarupcomingTop">Events Upcoming</td></tr>';
                        $sql =  "SELECT page_title, page_namespace, clike1.cl_to catlike1 " . 
                                "FROM $sPageTable INNER JOIN $categorylinks AS c1 ON page_id = c1.cl_from AND c1.cl_to='Events' INNER JOIN $categorylinks " . 
                                "AS clike1 ON page_id = clike1.cl_from AND clike1.cl_to LIKE '____/__/__' AND clike1.cl_to >= '" . date('Y/m/d') . "' " .
                                "WHERE page_is_redirect = 0 " .
                                "ORDER BY clike1.cl_to ASC " .
                                "LIMIT 5";
                        $res = $dbr->query($sql);
                        $rowClass = "calendarupcomingRow1";
                        while ($row = $dbr->fetchObject( $res ) ) 
                        {
                                $title = Title::makeTitle($row->page_namespace, $row->page_title);
                                
                                $title_text = $title->getSubpageText();
                                $title_text = str_replace('_',' ',$title_text);
                                $eventDate =  substr($row->catlike1,8,2) . '-' . substr($row->catlike1,5,2) . '-' . substr($row->catlike1,0,4);
                                $output .= '<tr><td class="' . $rowClass . '">' . $sk->makeKnownLinkObj($title, "» " . $title_text . '<br>' . $eventDate) . '</td></tr>';
                                $rowClass = "calendarupcomingRow2";
                        }
                        $title = Title::newFromText('Special:Events');
                        $output .= '<tr><td class="calendarupcomingBottom">' . $sk->makeKnownLinkObj($title, 'More »') . '</td></tr>';
                        $output .= '</table>';
                }
                return $output;
        }
}
?>
