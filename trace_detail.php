<?php
require("amplib.php");

function printHop($count, $hop, $withMtu) {

  if ( $hop == "") {
    continue;
  }

  preg_match("/(.+)\((.+)\)/", $hop, $results);

  /* results[1] = hostname, results[2] = address */
  if ( $results[1] == "0.0.0.0" || $results[1] == "::" ) {
    $results[1] = $results[2] = "unknown";
  }
  if ( $withMtu < 0 ) {
    $withMtu = "unknown";
  }

  echo "<tr class=\"row" . ($count+1)%2 . "\">";
  echo "<td>$count</td>";
  echo "<td>" . htmlspecialchars($results[1]) . "</td>";
  echo "<td>" . htmlspecialchars($results[2]) . "</td>";
  if ( $withMtu )
    echo "<td>" . htmlspecialchars($withMtu) . "</td>";
  echo "</tr>\n";
}



function printTrace($traceData, $withMtu = false) {
  global $timeZone, $startTimeSec, $dst;

  foreach ($traceData as $set) {
    for ( $i = 0; $i < count($set["data"]); ++$i ) {
      $y = trim($set["data"][$i]["y"]);
      /* if there is no scamper data something is wrong */
      if ( $y == "same" || $y == NULL ) {
        continue;
      }
      $hops = explode(" ", $y);
      $time = $startTimeSec + $set["data"][$i]["x"];

      echo "<a name='" . htmlspecialchars($set["data"][$i]["x"]) . "' />";
      echo "<table cellspacing='0' class='shaded'>\n";
      echo "<tr>";
      echo "<th colspan='4'>" . date("D M d H:i:s Y", $time) ." $timeZone</th>";
      echo "</tr>\n";

      echo "<tr>\n";
      echo "<th>Hop</th>\n";
      echo "<th>Name</th>\n";
      echo "<th>Address</th>\n";
      if ( $withMtu )
        echo "<th>MTU</th>";
      echo "</tr>\n";

      $count = 0;
      foreach  ($hops as $hop) {
        //printHop($count, $hop, $withMtu);
        printHop($count, $hop, (!$withMtu)?false:$set["data"][$i]["mtu"][$count]);
        $count++;
      }

      /* 
       * if using old traceroute data then put the final hop on the end
       * just to make it more in line with the scamper data. Don't do this
       * if the trace timed out and didn't reach the destination. It uses
       * the hostname for the address also, but I'm not too worried about
       * that.
       */
      if ( !$withMtu && 
          strncmp($dst, $hops[sizeof($hops)-1], strlen($dst)) != 0  &&
          strcmp("0.0.0.0(0.0.0.0)", $hops[sizeof($hops)-1]) != 0 &&
          strcmp("::(::)", $hops[sizeof($hops)-1]) != 0 ) {
        printHop($count, "$dst($dst)", false);
      }
      echo "</table><br />";
    }
  }
}

initialise();

$timeZone = get_preference(PREF_GLOBAL, GP_TIMEZONE, PREF_GLOBAL);
putenv("TZ=$timeZone");

if(isset($_REQUEST["date"]) && $_REQUEST["date"] != "") {
  $parts = explode("-", $_REQUEST["date"]);
  $startTimeSec = mktime(0, 0, 0, $parts[1], $parts[2], $parts[0], -1);

} else {
  $parts = getdate(strtotime("now"));
  $startTimeSec = mktime(0, 0, 0,
      $parts{'mon'}, $parts{'mday'}, $parts{'year'}, -1);
}



/* Long names for src/dst */
$siteDb = dbconnect($GLOBALS["sitesDB"]);
$srcName = getLongSiteName($siteDb, $src);
$dstName = getLongSiteName($siteDb, $dst);
dbclose($siteDb);

templateTop();

echo '<h2>Traceroute details for '.htmlspecialchars($srcName).' to '.htmlspecialchars($dstName).'</h2>'."\n";

/* Dates */
echo "<div>\n";
echo '<a href="trace_detail.php?src='.urlencode($src).'&dst='.urlencode($dst).'&date=' .
  date("Y-m-d", $startTimeSec - 86400) . "\">&lt;&lt;" .
  date("D M d Y", $startTimeSec - 86400) . "</a>";
echo "&nbsp;&nbsp;<h3>" . date("D M d Y", $startTimeSec) . 
  "</h3>&nbsp;&nbsp;";

$s = "";
if (($startTimeSec + 86400) > time()) {
  /* Use CSS to hide the link rather than not outputting it so that
   * centering of the title looks ok, when there are some graphs with
   * the link and some without 
   */
  $s = " style=\"visibility: hidden;\"";
}
echo '<a href="trace_detail.php?src='.urlencode($src).'&dst='.urlencode($dst).'&date=' .
  date("Y-m-d", $startTimeSec + 86400) . "\"$s>" .
  date("D M d Y", $startTimeSec + 86400) . "&gt;&gt;</a>";
echo "</div>\n";
    
$numWeeks = get_preference(PREF_GLOBAL, GP_LT_NUM_WEEKS, PREF_GLOBAL);
/* Navigation Links */
echo "<div><b>View</b>&nbsp;&nbsp;<a href=\"src.php\">other sources</a>" .
  "&nbsp;&nbsp;|&nbsp;&nbsp;<a href=\"src.php?src=".urlencode($src)."\">other " .
  "destinations for ".htmlspecialchars($src)."</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href=\"" .
  "graph.php?src=".urlencode($src)."&dst=".urlencode($dst)."&rge=$numWeeks-week\">weekly graphs</a>";
/* Add reverse link if available */
$dstr = "&date=" . date("Y-m-d", $startTimeSec);
displayReverseLink($src, $dst, $dstr);
echo "</div>";

echo "<div style=\"text-align:left;\">\n";

/* prefer scamper data if it exists, otherwise try traceroute data */
if ( checkDataExistsForTest($src, $dst, SCAMPER_DATA) ) {
  $subtype = getScamperSubtype($src, $dst, "icmp", false);
  $traceData = scamper_get_ds($src, $dst, $subtype, $startTimeSec,86400,0);
} 

if ( $traceData ) {
  printTrace($traceData, true);
} else {
  $traceData = trace_get_ds($src, $dst, 0, $startTimeSec, 86400, 0);
  if ( $traceData )
    printTrace($traceData, false);
}

echo "</div>\n";

endPage();

// Emacs Control
// Local Variables:
// eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
