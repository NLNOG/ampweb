<?php
/*
 * AMP Data Display Interface
 *
 * ICMP Traceroute Display Module
 *
 * Author:      Tony McGregor <tonym@cs.waikato.ac.nz>
 * Author:      Matt Brown <matt@crc.net.nz.
 * Author:      Jeremy Kallstrom <jkallstrom@nlanr.net>
 * Version:     $Id: scamper_test.php 1794 2010-06-16 01:28:37Z brendonj $
 *
 * This module provides the scamper graphs
 *
 */
define('PREF_SCAMPER', "scamper");
define('SCAMPER_PREF_DISPLAY_SCAMPER', "display-scamper");

/* Display Preference */
register_preference(array(PREF_GLOBAL), SCAMPER_PREF_DISPLAY_SCAMPER,
    array(PREF_LONGTERM,PREF_SHORTTERM), "Display Scamper Traceroute Graph",
    "", PREF_TYPE_BOOL,
    array(PREF_LONGTERM=>PREF_FALSE,PREF_SHORTTERM=>PREF_TRUE));

/* Register Scamper Graph */
register_display_object("scamper", "Path Analysis", "Scamper", 40,
        SCAMPER_DATA, "*", "scamper_avail", "drawScamperTraceroute", 
        "scamper_get_ds",
        "scamper_get_ds", SCAMPER_PREF_DISPLAY_SCAMPER, PREF_SCAMPER,
        array(), array(), -1, -1, "", "Number of Hops", "", "", FALSE, "", FALSE);

/* Register Test Helper Functions */
$test_names[SCAMPER_DATA] = "Scamper";
$subtype_name_funcs[SCAMPER_DATA] = "scamper_subtype_name";
$raw_data_funcs[SCAMPER_DATA] = "scamper_format_raw";

/** Data Available Function
 *
 * Return appropriate display item(s) if Traceroute data is available
 */
function scamper_avail($object_name, $src, $dst, $startTimeSec)
{
  if ( !checkDataExistsForTest($src, $dst, SCAMPER_DATA) )
    return array();
  
  $stList = ampSubtypeList(SCAMPER_DATA, $src, $dst);

  $items = array();
  foreach ( $stList->subtypes as $idx=>$subtype ) {
      /* Create the display item */
      $object = get_display_object($object_name);
      $item = new display_item_t();
      $item->category = $object->category;
      $item->name = $object->name . "-$subtype";
      $item->title = $object->title . " " .scamper_subtype_name($subtype);
      $item->displayObject = $object->name;
      $item->subType = $subtype;
      $items[$item->name] = $item;
  }
  return $items;
}

/** Data Retrieval Functions **/

/* Traceroute Dataset */
function scamper_get_ds($src, $dst, $subType, $startTimeSec, $secOfData,
                      $binSize)
{
  global $timeZone;
  $dataSets = array();

  //  $startingtime = microtime();

  /* Open database */
  $res = ampOpenDb(SCAMPER_DATA, $subType, $src, $dst, $startTimeSec, 0,
                   $timeZone);
  if(!$res) {
    return array();
  }

  /* Extract data */
  $dataSets = array();
  $dataSet["color"] = "";
  $dataSet["key"] = "";
  $dataSet["info"] = ampInfoObj($res);
  $data = array();
  $c=0;
  $lasttime = 0;
  $last_route = "";
  $lastMtus = 0;
  while ($obj = ampNextObj($res)) {
    if ($obj->secInPeriod > $secOfData) {
      break;
    }
    if ($obj->secInPeriod < 0) {
      continue;
    }
    if ( $obj->secInPeriod < $lasttime ) {
      continue;
    }
    /* TODO: should this have 5 null hops here instead of none? */
    if ( $obj->hops < 1 ) {
      continue;
    }

    $this_route = "";
    $pathMtus = array();
    $data[$c]["x"] = $obj->secInPeriod;
    $data[$c]["y"] = "";
    for ($hop = 0; $hop < $obj->hops; ++$hop) {
      $ip = $obj->tracepath->{$hop}->ip;
      $name = $obj->tracepath->{$hop}->hostname;
      $pathMtus[] = $obj->tracepath->{$hop}->mtu;
      //$this_route .= "$name($ip,mtu=$mtu) ";
      $this_route .= "$name($ip) ";
    }

    if ( $this_route == $last_route && $pathMtus == $lastMtus ) {
      $data[$c]["y"] = "same";
      $data[$c]["mtu"] = "same";
    } else {
      $data[$c]["y"] = $this_route;
      $data[$c]["mtu"] = $pathMtus;
      $last_route = $data[$c]["y"];
      $lastMtus = $data[$c]["mtu"];
    }

/*
    $data[$c]["x"] = $obj->secInPeriod;
    $data[$c]["y"] = "";
    for ($hop = 0; $hop < $obj->hops; ++$hop) {
      $ip = $obj->tracepath->{$hop}->ip;
      $name = $obj->tracepath->{$hop}->hostname;
      $mtu = $obj->tracepath->{$hop}->mtu;
      $this_route .= "$name($ip,mtu=$mtu) ";
    }

    if ( $this_route == $last_route ) {
      $data[$c]["y"] = "same";
    } else {
      $data[$c]["y"] = $this_route;
      $last_route = $data[$c]["y"];
    }
    */
    $lasttime = $data[$c]["x"];
    unset($this_route);
    $c++;
  }
  if ( !$data ) {
    return array();
  }

  $dataSet["data"] = $data;
  $dataSets[] = $dataSet;

//   list($endut, $endt) = explode(" ", microtime());
//   list($startut, $startt) = explode(" ", $startingtime);

//   print "Get data time: ";
//   print $endut - $startut - ($startt - $endt) . "<BR />\n";

  return $dataSets;
}

function drawScamperTraceroute($src, $dst, $date, $graphOptions, $item_name,
			$lineGraph, $xAxisType, $usemap, $xLabel, $yLabel, $xMax, $yMax,
			$dataSet,  $extraOpts, $scope)
{
  global $gapThreshold;

  foreach ( array_keys($dataSet) as $src ) {
    $destinations = array_keys($dataSet[array_pop(array_keys($dataSet))]);
    foreach ( $destinations as $dst ) {

      if ( $src == $dst )
        continue;

      $mapName = "";

      echo "<h4>$src to $dst</h4>\n";
      /* Build a map file name */
      $mapFile = cacheFileName("mapfile", $item_name, $src, $dst, $date,
          $graphOptions, $mCached);

      /* Build graph file name */
      $fileName = cacheFileName($xAxisType, $item_name, $src, $dst, $date,
          $graphOptions, $cached);
      if ($fileName == "")
        continue;

      if ($scope == PREF_LONGTERM) {
        $map = " usemap=\"#scamperweekmap-$date-$src-$dst\"";
      } else {
        $map = " usemap=\"#scamperhops-$date-$src-$dst\"";
      }

      /* Check for cached image / mapfile */
      if ($cached && ($mCached || $scope==PREF_LONGTERM)) {
        /* Return to browser */
        outputScamperMapFile($mapFile);
        echo "<img alt=\"scamper graph\" src=\"$fileName\"$map>\n";
        continue;
      }

      /* open a pipe to the graph tool */
      $dgraph = popen("$GLOBALS[dgraf] -i - -o $fileName -m $mapFile", "w");
      if (!$dgraph) {
        graphError("Could not open dgraf command");
        return -1;
      }

      /* Setup Axes and Labels */
      fputs($dgraph, "GRAPH_TYPE hops\n");
      fputs($dgraph, "X_AXIS_TYPE $xAxisType\n");
      fputs($dgraph, "X_HEADER $xLabel\n");
      fputs($dgraph, "Y_HEADER $yLabel\n");
      fputs($dgraph, "CREATE_HTML_MAP TRUE\n");
      fputs($dgraph, "MAP_NAME scamperhops-$date-$src-$dst\n");
      fputs($dgraph, "WITH_GAP TRUE\n");
      
      /* 
       * print this before the X_GAP_THRESHOLD because we want to override
       * the default setting (in extraOpts) with our own value - the most 
       * recent value is the one used so that works here.
       */
      foreach ($extraOpts as $option => $value) {
        switch($option) {
          case "GRAPH_NUMBER": break;
          default: fputs($dgraph, "$option $value\n"); break;
        };
      }

      /* 
       * If the host isn't tested often then the gap threshold needs to be
       * bigger to prevent erroneous gaps being displayed. 
       * Also start using the new continuous option which will prevent gaps
       * from occurring between consecutive but different measurements.
       * TODO: - ask the scheduler how often the test runs to set gap threshold
       *       - consider applying the continuous option to all scampers
       */
      fputs($dgraph, "X_GAP_THRESHOLD 11100\n"); // 60 seconds * 185 minutes
      fputs($dgraph, "CONTINUOUS_TRACE TRUE\n");

      /* Draw the datasets */
      foreach ($dataSet[$src][$dst] as $set) {


        /* Dataset initialisation */
        fputs($dgraph, "DATASET_START\n");

        /* Output the data */
        for ($i = 0; $i<count($set["data"]); $i++) {
          $x = $set["data"][$i]["x"];
          $y = $set["data"][$i]["y"];
          if ( $y != "" ) {
            if ( $y != "same" ) {
              fputs($dgraph, "url trace_detail.php?src=$src&dst=" .
                  "$dst&date=$date#". $x . "\n");
            }
            fputs($dgraph, "$x $y\n");
          }
          flush();
        }

      }

      /* Close dgraph connection */
      pclose($dgraph);

      /* Return to browser */
      if ($scope == PREF_SHORTTERM) {
        outputScamperMapFile($mapFile);
      }
      echo "<img alt=\"scamper graph\" src=\"$fileName\"$map>\n";

      //drawEvents($src, $dst, $date, $xAxisType, $scope);
    } 
  }
  //     list($endut, $endt) = explode(" ", microtime());
  //     list($startut, $startt) = explode(" ", $starttime);

  //     print "Draw Time: ";
  //     print $endut - $startut - ($startt - $endt) . "<BR />\n";
}

/*
function drawEvents($src, $dst, $date, $xAxisType, $scope)
{
  $fileName = cacheFileName($xAxisType, "event", $src, $dst, $date,
      $graphOptions, $cached);

  $mapFile = cacheFileName("mapfile", "event", $src, $dst, $date,
      $graphOptions, $mCached);

  if ($cached && ($mCached || $scope == PREF_LONGTERM)) {
    echo "<img alt=\"events graph\" src=\"$fileName\"usemap=#events-$date>\n";
    return;
  }

  if ( $scope == PREF_LONGTERM ) {
    $secOfData = 604800;
  } else if ( $scope = PREF_SHORTTERM) {
    $secOfData = 86400;
  }

  $eventsDB = dbconnect($GLOBALS[eventsDB]);
  if ( !$eventsDB ) {
    echo "<p>Could not connect to events database.  Please try again later.</p>\n";
    exit;
  }

  list($year, $month, $day) = split('[:/.-]', $date);

  $timeZone = get_preference(PREF_GLOBAL, GP_TIMEZONE, PREF_GLOBAL);

  $oldTZ = getenv("TZ");
  putenv("TZ=$timeZone");

  $startTime = mktime(0, 0, 0, $month, $day, $year, -1);
  putenv("TZ=$oldTZ");

  $endTime = $startTime + $secOfData;

  $query = "select event_type,time_start,time_end from events where";
  $query .= " (time_start < $startTime and time_end > $endTime";
  $query .= " or time_start > $startTime and time_start < $endTime";
  $query .= " or time_end > $startTime and time_end < $endTime)";
  $query .= " and ( (source = '$src' and dest = '')";
  $query .=    " or (source = '' and dest = '$dst')";
  $query .=    " or (source = '' and dest = '')";
  $query .=    " or (source = '$src' and dest = '$dst') )";

  $result = queryAndCheckDB($eventsDB, $query);

  dbclose($eventsDB);

  $rows = $result{'rows'};

  if ( $rows > 0 ) {
    $dgraph = popen("$GLOBALS[dgraf] -i - -o $fileName -m $mapFile", "w");
    if ( !$dgraph ) {
      graphError("Could not open dgraf command");
      return -1;
    }

    fputs($dgraph, "GRAPH_TYPE events\n");
    fputs($dgraph, "X_AXIS_TYPE $xAxisType\n");
    fputs($dgraph, "CREATE_HTML_MAP TRUE\n");
    fputs($dgraph, "MAP_NAME events-$date\n");
    fputs($dgraph, "DATASET_START\n");

    $color = "";

    for ( $rowNum = 0; $rowNum < $rows; ++$rowNum ) {
      $color = nextColor($color);

      $row = $result{'results'}[$rowNum];
      $start = $row[1];
      $end = $row[2];
      $start -= $startTime;
      $end -= $startTime;

      if ( $start < 0 ) {
        $start = 0;
      }

      if ( $end > $endTime - $startTime ) {
        $end = $endTime - $startTime;
      }

      $eventType = preg_replace("/\s/", "_", $row[0]);
      fputs($dgraph, "$start $end $color event_detail.php?src=$src&dst=");
      fputs($dgraph, "$dst&date=$date#$eventType$row[1]$row[2] $row[0]\n");
    }

    pclose($dgraph);

    outputMapFile($mapFile);

    echo "<img alt=\"events graph\" src=\"$fileName\"usemap=#events-$date>\n";
  }
}
*/

function nextScamperColor($color) {
  if ( $color == "" ) {
    $newColor = 500200;
  } else {
    list($red, $green, $blue) = explode(",", $color);

    $newColor = $red << 16;
    $newColor += $green << 8;
    $newColor += $blue;
    $newColor += 500200;

    while ( ($newColor % 256 < 100) && (($newColor >> 8) % 256 < 100) &&
            (($newColor >> 16) % 256 < 100) ) {
      $newColor += 65793;
    }
  }

  $red = ($newColor & 0xFF0000) >> 16;
  $green = ($newColor & 0xFF00) >> 8;
  $blue = ($newColor & 0xFF);

  return "$red,$green,$blue";
}

function outputScamperMapFile($mapFile) {
  $file = fopen($mapFile, "r");
  if ( !$file ) {
    graphError("Error opening mapfile: $mapFile");
  } else {
    while ( $line = fgets($file, 1024) ) {
      echo $line;
    }
  }
}


function scamper_subtype_name($subtype) {

  list($test, $method, $args) = explode("-", $subtype, 3);
  $method = substr($method, 1);

  list($dport, $sport, $firsthop, $gaplimit, $maxttl, $loop, $queries, $pmtud) =
    sscanf($args, "d%d-s%d-f%d-g%d-m%d-l%d-q%d-M%d");

  $name = "$method $test " . (($pmtud)?"with":"without") . " pmtud";

  return $name;
}

/* Formats raw SCAMPER data */
function scamper_format_raw($subType, $obj) {
  global $last_route;

  if ( $obj == NULL ) {
    return "scamper";
  }

  for ( $hop = 0; $hop < $obj->hops; ++$hop ) {
    $ip = $obj->tracepath->{$hop}->ip;
    $name = $obj->tracepath->{$hop}->hostname;
      $this_route .= "$name($ip) ";
  }
  if ( $this_route == $last_route ) {
    $content = "same";
  } else {
    $content = "$this_route";
    $last_route = $this_route;
  }

  return $content;
}

  // Emacs control
  // Local Variables:
  // eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
