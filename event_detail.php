<?php
require("amplib.php");

initialise();

$timeZone = get_preference(PREF_GLOBAL, GP_TIMEZONE, PREF_GLOBAL);
putenv("TZ=$timeZone");

if(isset($_REQUEST["date"]) && $_REQUEST["date"] != "") {
    $parts = explode("-", $_REQUEST["date"]);
    $startTimeSec = mktime(0, 0, 0, $parts[1], $parts[2], $parts[0], -1);
    $dateStr = "&date=" . $_REQUEST["date"];

} else {
    $parts = getdate(strtotime("now"));
    $startTimeSec = mktime(0, 0, 0,
	    $parts{'mon'}, $parts{'mday'}, $parts{'year'}, -1);
    $dateStr = "";
}


templateTop();

echo '<h1>'.htmlspecialchars($src).' to '.htmlspecialchars($dst).'</h1>'."\n";

/* Navigation Links */
$numWeeks = get_preference(PREF_GLOBAL, GP_LT_NUM_WEEKS, PREF_GLOBAL);
echo "<b>View</b>&nbsp;&nbsp;<a href=\"src.php\">other sources</a>" .
    "&nbsp;&nbsp;|&nbsp;&nbsp;<a href=\"src.php?src=$src\">other " .
    "dests for $src</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href=\"" .
    "graph.php?src=$src&dst=$dst&rge=$numWeeks-week$dateStr\"" .
    ">weekly graphs</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href=\"" .
    "graph.php?src=$src&dst=$dst&rge=1-day$dateStr\">" .
    "daily graphs</a>\n<p>\n";

echo "<h2>Events detail</h2><Br />\n";

$dbConn = dbconnect($GLOBALS[eventsDB]);
if ( ! $dbConn ) {
  echo "Could not connect to events database.  Please try again later.\n";
  exit;
}

$startTime = $startTimeSec;
$endTime = $startTime + 86400;

$query = "select event_type,description,time_start,time_end,source,dest";
$query .= " from events where";
$query .=   "( (source = '$src' and dest = '')";
$query .= " or (source = '' and dest = '$dst')";
$query .= " or (source = '' and dest = '')";
$query .= " or (source = '$src' and dest = '$dst') )";

//$query .= " (source = '$src' or dest = '$dst') or (source = '' and dest = '')";
//$query .= " order by time_end desc";

$result = queryAndCheckDB($dbConn, $query);

$rows = $result{'rows'};

if ( $rows > 0 ) {

  print "<TABLE BORDER><TR><TH>Type</TH><TH>Start</TH><TH>End</TH>";
  print "<TH>Affecting</TH><TH>Description</TH></TR>";

  for ( $rowNum = 0; $rowNum < $rows; ++$rowNum ) {
    $row = $result{'results'}[$rowNum];
    print "<TR>";
    print "<a name=" . $row["event_type"] . $row["time_start"];
    print $row["time_end"] . "/>";
    print "<TD>" . $row["event_type"] . "</TD>";
    print "<TD>" . date("D, j M Y G:i T (\G\M\TO)", $row["time_start"]) . "</TD>";
    print "<TD>" . date("D, j M Y G:i T (\G\M\TO)", $row["time_end"]) . "</TD>";
    if ( $row["source"] == '' ) {
      if ( $row["dest"] == '' ) {
        print "<TD>All</TD>";
      } else {
        print "<TD>->" . $row["dest"] . "</TD>";
      }
    } else {
      if ( $row["dest"] == '' ) {
        print "<TD>" . $row["source"] . "-></TD>";
      } else {
        print "<TD>" . $row["source"] . "->" . $row["dest"] . "</TD>";
      }
    }
    print "<TD>" . $row["description"] . "</TD>";
    print "</TR>";

  }
  print "</TABLE>";
}

endPage();

  // Emacs control
  // Local Variables:
  // eval: (c++-mode)

?>
