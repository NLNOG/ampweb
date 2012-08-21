<?php
/*
 * AMP Data Display Interface 
 *
 * Graph display page
 */
require("amplib.php");


/*********************************************************************/
/**** Initialiase the AMP display system ****/
initialise();

/**** Page Specific Setup ****/
  
/* Page Settings Stored in Session */
if ( isset($_REQUEST["reset"]) ) {

  /* Resetting, page needs default settings */
  unset($_SESSION["page_settings"]);
  session_unregister("page_settings");
  $page_settings = array();
  $_SESSION["page_settings"] = $page_settings;
} else if(isset($_POST["do"])) {
  
  /* Extract settings out of session and store new ones */
  $page_settings = $_SESSION["page_settings"];
  if ( isset($_POST["form"]) ) {
    $form = $_POST["form"];
    $prefix = $_POST["form_prefix"];
    $page_settings[$form] = array();
    foreach ( $_POST as $key=>$value ) {
      if ( strncmp($key, $prefix, strlen($prefix)) != 0 ) {
        continue;
      }
      $page_settings[$form][$key] = $value;
    }
  }
  $_SESSION["page_settings"] = $page_settings;
  
} else {

  /* no form being submitted, use settings as they are */
  global $page_settings;
  if ( isset($_SESSION["page_settings"]) ) {
    $page_settings = $_SESSION["page_settings"];
  } else {
    $_SESSION["page_settings"] = $page_settings;
  }
}

/* Long names for src/dst */
$siteDb = dbconnect($GLOBALS["sitesDB"]);
if($siteDb) {
  $srcName = getLongSiteName($siteDb, $src);
  $dstName = getLongSiteName($siteDb, $dst);
  dbclose($siteDb);
} else {
  // if we can't open the db, just use the shortnames
  $srcName = $src;
  $dstName = $dst;
}

/* Date for page to display data from */
$timeZone = get_preference(PREF_GLOBAL, GP_TIMEZONE, PREF_GLOBAL);
$res = timeInZone($timeZone);
$timeInZone = $res->time;
unset($res);

// we can't use any functions that deal in localtime unless we set
// ourselves to be in the correct timezone
putenv("TZ=$timeZone");

/* 
 * If date isn't set, make it todays date.
 * Using mktime we can find the UTC timestamp at the beginning of the day
 */
if(isset($_REQUEST["date"]) && $_REQUEST["date"] != "") {
    $parts = explode("-", $_REQUEST["date"]);
    $startTimeSec = mktime(0, 0, 0, $parts[1], $parts[2], $parts[0], -1);

} else {
    $parts = getdate(strtotime("now"));
    $startTimeSec = mktime(0, 0, 0, 
      $parts{'mon'}, $parts{'mday'}, $parts{'year'}, -1);
}



/*********************************************************************/
/* Time frame and range of graphs being shown */
$pageClass = PREF_SHORTTERM;

if(isset($_REQUEST["rge"]))
{
  $tmp = explode("-", $_REQUEST["rge"]);
  $duration = $tmp[0];
  $xAxisType = $tmp[1];

  // anything longer than a day is considered longterm
  switch($xAxisType)
  {
    case "day":
      $pageClass = PREF_SHORTTERM;
      $secOfData = 86400;
      break;

    case "week":
      $pageClass = PREF_LONGTERM;
      $secOfData = 604800;

      // if this isn't sunday localtime then take off as many days
      // as we need to find the start of the week (day 0)
      $wdayLocal = (int)date("w", $startTimeSec);
      $startTimeSec -= $wdayLocal * 86400;
      $periodStart = getdate($startTimeSec);
      break;

    case "month":
      $pageClass = PREF_LONGTERM;
      $secOfData = 86400 * date("t", $startTimeSec);
      
      // if this isn't day 1 of the month localtime, then take off as 
      // many days as we need to find the start of the month
      $mdayLocal = (int)date("j", $startTimeSec);
      $startTimeSec -= ($mdayLocal -1 ) * 86400;
      $periodStart = getdate($startTimeSec);
      break;

    default:
      // for now, just use a day
      $pageClass = PREF_SHORTTERM;
      $secOfData = 86400;
      break;
  };

  // if we've changed the start time based on the period we are interested in,
  // then we need to update the start timestamp (UTC)
  if(isset($periodStart[0]))
    $startTimeSec = $periodStart[0];

}
else
{
  $duration = 1;
  
  $pageClass = PREF_SHORTTERM;
  $xAxisType = "day";
  $secOfData = 86400;
}

// how many of what period are we looking at each page?
$navigate = $duration . " " . $xAxisType;
if($duration > 1)
  $navigate .= "s";



/**************************************************************/
/* store all the bits of the url that we want to use elsewhere */

// if graph is set, then we care about the options for it.
// if its not set, then the only options we can really use are the
// ones in page_settings, so just leave the optstring here blank
if(isset($_REQUEST["graph"])) {
  $graphstring = "&graph=" . urlencode($_REQUEST["graph"]);
  $optstring = "";
} else {
  $graphstring = "";

  if(isset($_REQUEST["opts"]))
    $optstring = "&opts=" . urlencode($_REQUEST["opts"]);
  else
    $optstring = "";
}

if(isset($_REQUEST["ymax"]))
  $ystring = "&ymax=" . urlencode($_REQUEST["ymax"]);
else
  $ystring = "";

// this has to have a value or it breaks the hidden range input
// i dont think i thought this through very well
if(isset($_REQUEST["rge"]))
  $rgestring = "&rge=" . urlencode($_REQUEST["rge"]);
else
  $rgestring = "&rge=1-day";



/***********************************************************/
/* Build the list of display items for the page */
$sources = expandSites($src);
$destinations = expandSites($dst);
foreach($sources as $source) {
  foreach($destinations as $destination) {
    if ( $source == $destination )
      continue;
    initialise_display_items($source, $destination, $startTimeSec);
  }
}

/**** HTML From Here ON ****/
templateTop();
echo "<script type='text/javascript' language='javascript'>";
echo "var options = new Array();";
echo "</script>";

//Opera insists on not reloading the image from the server, so this is needed
//to force it to do so
if ( strstr($_SERVER["HTTP_USER_AGENT"], "Opera") ) {
  if ( !isset($_GET["reload"]) ) {
    echo "<script type=\"text/javascript\">\n";
    if ( !empty($_GET) ) {
      $addText = "&reload=1";
    } else {
      $addText = "?reload=1";
    }
    
    echo "location.replace(location.href + \"$addText\")\n";
    echo "</script>";
  } else {
    unset($_GET["reload"]);
  }
}


/*********************************************************************/
/* Page heading */
echo '<h2 class="dataheading">'.htmlspecialchars($srcName).' to '.htmlspecialchars($dstName).'</h2>'."\n";


/*********************************************************************/
/* Navigate by source and destination */
echo "<div><b>View</b>&nbsp;&nbsp;";
echo "<a href=\"src.php\">other sources</a>&nbsp;&nbsp;";
echo '|&nbsp;&nbsp;<a href="src.php?src='.urldecode($src).'">other ' .
  'destinations for '.htmlspecialchars($src).'</a>';

/* Add reverse link if available */
$dstr = "&date=" . date("Y-m-d", $startTimeSec);
displayReverseLink($src, $dst, $dstr, 
  $graphstring . $rgestring . $ystring, $optstring);
echo "</div>";






/**********************************************************************/
/* select time frame shown */

$ranges = array(
    "day" => "Day(s)",
    "week" => "Week(s)",
    "month" => "Month(s)",
    //"year" => "Year(s)",
);


if(isset($_REQUEST["graph"]))
{
  $id = 'updatelink';
  $updateFunction = "updateTime";
}
else
{ 
  // nothing else has a name like this, so while horribly overused
  // in places, this works as an id for an input field/link
  $id = 'rge';
  $updateFunction = "updateAllTimes";
}

/* display time period select options */
echo "Show ";
echo "<input type='text' size=1 maxlength=1 id='duration' " .
  "value=$duration onchange='$updateFunction(\"$id\")'/> ";
echo "<select id='range' onchange='$updateFunction(\"$id\")'>";

foreach($ranges as $value=>$display)
{
  echo '<option value="'.htmlspecialchars($value).'"';
  if($xAxisType == $value)
    echo " SELECTED";
  echo '>'.htmlspecialchars($display);
}
echo "</select>";
echo " worth of data: ";

// remove any mention of rge from the url we use in this form
// so that it can be set properly later
$formurl = preg_replace("/&rge=[\w-]*/", "", $page_name);
echo "<form action=\"$formurl\" method=\"post\" class=\"graphoptions\">\n";
echo "<input type='submit' class='graphoptionssubmit' " .
  "value='Refresh Graph &gt;&gt;' >\n";
// 'do' needs to be set to get our page_settings to carry over?
echo "<input type='hidden' name='do' value='yes'>\n";
echo "<input type='hidden' name='rge' value='" . 
  (isset($_REQUEST["rge"])?($_REQUEST["rge"]):"1-day") . "' id='rge' />\n";

echo "</form>";
echo "<br />";



/*********************************************************************/
/* Navigate by date */
echo "<br />";
echo "<div>\n";

/* go LEFT */
echo '<a href="graph.php?src='.urlencode($src).'&dst='.urlencode($dst) .
  $graphstring .  $optstring .  $ystring .  $rgestring .
  "&date=" . date("Y-m-d", strtotime("-$navigate", $startTimeSec)) .
  "\">&lt;&lt;" .
  " go back " . htmlspecialchars($navigate) . "</a>";
 
 
/* go RIGHT */
// if the current time now is less than the end of the period being
// viewed there can be no more data to the right
if (($startTimeSec + $secOfData) > time()) {
  /* Use CSS to hide the link rather than not outputting it so that
   * centering of the title looks ok, when there are some graphs with
   * the link and some without
   */
  $s = " style=\"visibility: hidden;\"";
} else {
  echo " || ";
  $s = "";
}

echo '<a href="graph.php?src='.urlencode($src).'&dst='.urlencode($dst) .
  $graphstring . $optstring . $ystring . $rgestring .
  "&date=" . date("Y-m-d", strtotime("+$navigate", $startTimeSec)) .
  "\"$s>" .
  " go forward " . htmlspecialchars($navigate) .
  " &gt;&gt;</a>";

echo "</div>\n";


/********************************************************************/
/* 
 * loop over all the different types of graphs, and display n of each
 * graph as asked for by the time period select options
 */
$pageStart = $startTimeSec;
$c=0;
foreach ( $display_items as $key=>$ditem ) {

  $startTimeSec = $pageStart;

  // if graphtype is set in the url, then allow this to override any
  // preferences the user may have saved already, otherwise we just
  // draw what is in their preferences
  if(isset($_REQUEST["graph"]))
  {
    // if graphtype is set, display only this graph
    if($key != $_REQUEST["graph"])
      continue;
  }
  else
  {
    /* Check if this object is to be displayed */
    $object = get_display_object($ditem->displayObject);
    $d = is_item_displayed($ditem->name, $object->displayPref, $pageClass);
    if ( ! $d ) {
      continue;
    }
  }

    /******************************************************************/
    for ( $displaycount=0; $displaycount<$duration; $displaycount++ ) {

      $imagemap = "";
      
      // convert timestamp into localtime for display
      $graphDate = strftime("%Y-%m-%d", $startTimeSec);
      if($xAxisType == "week") {
        /* display links to daily pages */
        for ( $day=6; $day>=0; --$day ) {
          if ( ($startTimeSec + ($day * 86400)) > $timeInZone ) {
            continue;
          }
          /* TODO: Check that data for the day is actually available */
          // convert timestamp into localtime for link targets
          $link = "graph.php?src=$src&amp;dst=$dst&amp;date=" .
            strftime("%Y-%m-%d", $startTimeSec + ($day * 86400)) .
            $graphstring . $optstring . $ystring;
          $links[$day] = $link;
        } //create link for each day
        unset($day);

        $imagemap = printWeeklyMap($graphDate, $links);

      } else if($xAxisType == "month") {
        /* display links to daily pages */
        for ( $day=date("t", $startTimeSec); $day>=0; --$day ) {
          if ( ($startTimeSec + ($day * 86400)) > $timeInZone ) {
            continue;
          }
          /* TODO: Check that data for the day is actually available */
          // convert timestamp into localtime for link targets
          $link = "graph.php?src=$src&amp;dst=$dst&amp;date=" .
            strftime("%Y-%m-%d", $startTimeSec + ($day * 86400)) .
            $graphstring . $optstring . $ystring;
          $links[$day] = $link;
        } //create link for each day
        unset($day);

        $imagemap = printMonthlyMap($graphDate, $links);

      }

      /******************************************************************/
      /* Display it */
      item_display($ditem->name, $startTimeSec, $secOfData, $graphDate, 
          $xAxisType,  $pageClass, $imagemap, $displaycount);

      // go back one period to draw the next graph
      $startTimeSec = strtotime("-1 $xAxisType", $startTimeSec);
      // update secOfData ony if we are changing months
      if($xAxisType == "month")
        $secOfData = 86400 * date("t", $startTimeSec);
    }
    $c++;
  }
  if ( $c == 0 ) {
    graphError("No Graphs Selected!");
  }


/* Finish System, Close HTML */
endPage();

?>


<script type="text/javascript" language="javascript">

//var options = new Array();

function updateAllTimes(which)
{
  //alert(which);
  var links = document.getElementsByName(which);
  var rangeInput = document.getElementById("range");
  var durationInput = document.getElementById("duration");
  var range_string;

  // work out our new range
  range_string = durationInput.value + "-" + rangeInput.value;

  // update all the hidden_rge input fields with this value
  for(var i=0; i<links.length; i++) {
    links[i].value = range_string;     
  }

}

function updateTime(which)
{
<?
/*
if(isset($_REQUEST["graph"]))
  echo "  var link = document.getElementById(which);\n";
else
  echo "  var link = document.getElementsByName(which);\n";
*/
?>
  var link = document.getElementById(which);
  var rangeInput = document.getElementById("range");
  var durationInput = document.getElementById("duration");
  var range_string;
  var old_url;

  // work out our new range
  range_string = durationInput.value + "-" + rangeInput.value;
  
  // grab the parts of the old url so we can keep
  // everything else the same
  old_url = link.href;
  parts = old_url.split("\&");

  link.href = parts[0];
  // put all the parts back together, but change the option
  // value to the new one
  for(i=1; i<parts.length; i++)
  {
    if(parts[i].substr(0, 3) == "rge")
      link.href += "&rge=" + range_string; 
    else
      link.href += "&" + parts[i];
  }

  // update the link by the time options too
  var hidden_link = document.getElementById("rge");
  hidden_link.value = range_string;

}

function updateOptions(which, option, value)
{
  //var link = document.getElementById("updatelink");
  var link = document.getElementById(which);
  var hidden_opts = document.getElementById("hidden_" + which);
  var old_url;
  var base;
  var parts;
  var newopt;
  var option_string;
  var i;


  // set the changed value in our array
  //options[option] = value;
  options[which][option] = value;

  // calculate the binary value of all the options
  // could do this with one step seeing as we know
  // only one option has changed, and which one it is
  //base = options.length-1; 
  base = 3;
  newopt = 65;
  option_string = "";

  for(i=0; i<options[which].length; i++)
  {
    if(options[which][i] == true)
    {
      //alert("increasing by" + Math.pow(2, base-i));
      newopt += Math.pow(2, base-i);
      //alert(newopt);
    }
    // every 4 bits we turn into a character
    if( (i+1) % 4 == 0)
    {
      //alert("building char");
      option_string += String.fromCharCode(newopt);
      newopt = 65;
      base += 4;
    }
  }

  // if we haven't just made a character, use up the last of
  // the options value to make a final one
  if( (i) % 4 != 0)
  {
    //alert("building char2");
    option_string += String.fromCharCode(newopt);
  }


  // grab the parts of the old url so we can keep
  // everything else the same
  old_url = link.href;
  parts = old_url.split("\&");

  link.href = parts[0];
  // put all the parts back together, but change the option
  // value to the new one
  for(i=1; i<parts.length; i++)
  {
    if(parts[i].substr(0, 4) == "opts")
      link.href += "&opts=" + option_string; 
    else
      link.href += "&" + parts[i];
  }

  if(hidden_opts != null)
    hidden_opts.value = option_string;
  
}


function updateTextfields(which, option, value)
{
  var link = document.getElementById(which);
  var hidden_opts = document.getElementById("hidden_" + which + "_ymax");
  var old_url;
  var parts;

  // lets just hard code this for now cause its shorter
  option = "ymax";

  // grab the parts of the old url so we can keep
  // everything else the same
  old_url = link.href;
  parts = old_url.split("\&");

  link.href = parts[0];
  // put all the parts back together, but change the option
  // value to the new one
  for(i=1; i<parts.length; i++)
  {
    if(parts[i].substr(0, option.length) == option)
      link.href += "&" + option + "=" + value; 
    else
      link.href += "&" + parts[i];
  }
  
  if(hidden_opts != null)
    hidden_opts.value = value;

}
</script>
<?
// Emacs Control
// Local Variables:
// eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
