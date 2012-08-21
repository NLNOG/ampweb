<?php
/*
 * AMP Data Display Interface
 *
 * UDP stream (udpstream) Test Display Module
 *
 * Author:      Brendon Jones <bcj3@cs.waikato.ac.nz>
 *
 * This module provides the udpstream graphs.
 * It is quite simplistic at the moment and lacking in the statistical
 * analysis that some of the other tests do.
 *
 * TODO: number of packets is hardcoded at 5, should be extracted from subtype
 */

/* Define Preference Stuff */
define('PREF_UDPSTREAM', "udpstream");

/* Register Preferences */
register_module(PREF_UDPSTREAM, "UDP Stream Graphs", "These preferences " .
                "control the display of graphs relating to the UDP stream " .
                "tests.");

/* Register Display Preferences */
define('UDPSTREAM_PREF_DISPLAY_UDPSTREAM', "display-udpstream");

/* Display Preference */
register_preference(array(PREF_GLOBAL), UDPSTREAM_PREF_DISPLAY_UDPSTREAM,
    array(PREF_LONGTERM,PREF_SHORTTERM), "Display UDP Stream Graph",
    "", PREF_TYPE_BOOL,
    array(PREF_LONGTERM=>PREF_FALSE,PREF_SHORTTERM=>PREF_TRUE));

/* Register Available Display Objects */
register_display_object("udpstream", "UDP Stream", "UDP Stream", 50, UDPSTREAM_DATA, "*",
                        "udpstream_avail", "drawUdpstream", "udpstream_get_base_ds",// was not base before
                        "udpstream_get_base_ds", UDPSTREAM_PREF_DISPLAY_UDPSTREAM, PREF_UDPSTREAM,
                        array("summary"=>array("Summary",PREF_DISPLAY_SUMMARY)),
                        array(), -1, -1, "", "ms", "", "", FALSE, "", FALSE);


/* Register Test Helper Functions */
$test_names[UDPSTREAM_DATA] = "UDP Stream";
$subtype_name_funcs[UDPSTREAM_DATA] = "udpstream_subtype_name";
$raw_data_funcs[UDPSTREAM_DATA] = "udpstream_format_raw";

/** Data Available Function
 *
 * Return appropriate display item(s) if udpstream data is available
 */
function udpstream_avail($object_name, $src, $dst, $startTimeSec)
{
  if ( !checkDataExistsForTest($src, $dst, UDPSTREAM_DATA) )
    return array();
  
  $items = array();
  $object = get_display_object($object_name);

  /* UDP stream data exists - get subtypes */
  $subtypes = ampsubtypelist(UDPSTREAM_DATA, $src, $dst);

  foreach ( $subtypes->subtypes as $idx=>$subtype ) {

    // XXX I think the number of packets should be part of the subtype 

    /* Create the display item */
    $item = new display_item_t();
    $item->category = $object->category;
    $item->name = $object->name . "-$subtype";
    $item->title = udpstream_subtype_name($subtype);
    $item->displayObject = $object->name;
    $item->subType = $subtype;
    $items[$item->name] = $item;
  }
  return $items;
}

/** Data Retrieval Functions **/

/* Tput Base Dataset */
function udpstream_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{
  global $timeZone;
  $dataSets = array();

  /* Open database */
  $res = ampOpenDb(UDPSTREAM_DATA, $subType, $src, $dst, $startTimeSec, 0,
                   $timeZone);
  if(!$res) {
    return array();
  }

  $info = ampInfoObj($res);

  $dataSet["color"] = "";
  $dataSet["key"] = "";
  $dataSet["info"] = ampInfoObj($res);
  $data = array();
  
  /* Extract Data */
  $sample = 0;
  while (($obj = ampNextObj($res)) && $obj->secInPeriod<$secOfData) {
    $previous = 0;

    $data[$sample]->secInPeriod  = $obj->secInPeriod;
    $data[$sample]->packets   = $obj->packets;
    
    for($packet=0; $packet<$obj->packets; $packet++) {
      /* dont save the previous value if it is a loss (-1) */
      if ( $obj->interarrival[$packet] >= 0 ) {
        $data[$sample]->interarrival[$packet] = 
          $obj->interarrival[$packet] - $previous;
        $previous = $obj->interarrival[$packet];
      } else {
        $data[$sample]->interarrival[$packet] = -1;
      }
    }
    
    $sample++;
  }
  $dataSet["data"] = $data;
  $dataSets[] = $dataSet;

  return $dataSets;
}


/* Parse an udpstream subtype and return a legible name for it */
function udpstream_subtype_name($subtype)
{
  
  list($size, $interval, $packets) = sscanf($subtype, "%dB%dus%dP");
  $suffix = "us";

  if( ($interval / 1000 > 0) && ($interval % 1000 == 0) ) {
    $interval = $interval / 1000;
    $suffix = "ms";
  }

  return "$packets * $size byte packets at $interval$suffix spacing";
  
}



/* Formats a line of raw UDPSTREAM data */
function udpstream_format_raw($subType, $obj)
{
  list($packets) = sscanf($subType, "%*dB%*dus%dP");

  if ( $obj == NULL ) {
    $string = "";
    for ( $i=1; $i<=$packets; $i++ ) {
      if ( $i > 1 )
        $string .= ",";
      $string .= "p" . $i . "_ms";
    }
    return $string;
  }

  for($packet=0; $packet<$obj->packets; $packet++) {
    if($obj->interarrival[$packet] < 0) {
      $content .= "-1";
    } else {
      /* round the values to the nearest millisecond */
      $content .= (int)(($obj->interarrival[$packet]/1000.0) + 0.5);
    }

    if($packet+1 < $obj->packets)
      $content .= ",";
  }

  return $content;
}




function drawUdpstream($src, $dst, $date, $graphOptions, $item_name,
			$lineGraph, $xAxisType, $usemap, $xLabel, $yLabel, $xMax, $yMax,
			$dataSet,  $extraOpts, $scope)
{
  global $gapThreshold;
  
  foreach ( array_keys($dataSet) as $src ) {
    $destinations = array_keys($dataSet[array_pop(array_keys($dataSet))]);
    foreach ( $destinations as $dst ) {

      if ( $src == $dst )
        continue;
      
      echo "<h4>$src to $dst</h4>\n";

      // XXX might want to add a map later so can look at the specific data
      // recorded for the tests?
      $mapName = "";

      /* Build a map file name */
      /*
         $mapFile = cacheFileName("mapfile", $item_name, $src, $dst, $date,
         $graphOptions, $mCached);
       */
      /* Build graph file name */
      $fileName = cacheFileName($xAxisType, $item_name, $src, $dst, $date,
          $graphOptions, $cached);
      if ($fileName == "")
        return;

      if ( $xAxisType == "week" ) {
        $map = " usemap=\"#weekmap-$date\"";
      } else if($xAxisType == "month") {
        $map = " usemap=\"#monthmap-$date\"";
      } else {
        // not doing a map at this level currently
        //$map = " usemap=\"#hops-$date\"";
      }


      /* Check for cached image / mapfile */
      //if ($cached && ($mCached || $scope==PREF_LONGTERM)) {
      if ($cached ) { /* cache all scopes, it's only for 5 minutes */
        /* Return to browser */
        //outputMapFile($mapFile);
        echo "<img alt=\"udpstream graph\" src=\"$fileName\"$map>\n";
        //echo "<img alt=\"udpstream graph\" src=\"$fileName\">\n";
        return;
      }
    
      /* open a pipe to the graph tool */
      //$dgraph = popen("$GLOBALS[dgraf] -i - -o $fileName -m $mapFile", "w");
      $dgraph = popen("$GLOBALS[dgraf] -i - -o $fileName", "w");
      //$dgraph = fopen("/tmp/dgraf.log", "w");
      if (!$dgraph) {
        graphError("Could not open dgraf command");
        return -1;
      }

      /* Setup Axes and Labels */
      fputs($dgraph, "GRAPH_TYPE arrival\n");
      fputs($dgraph, "X_AXIS_TYPE $xAxisType\n");
      fputs($dgraph, "X_HEADER $xLabel\n");
      fputs($dgraph, "Y_HEADER $yLabel\n");
    
      if ( $yMax > 0 ) {
        fputs($dgraph, "Y_MAX $yMax\n");
      }

      //fputs($dgraph, "CREATE_HTML_MAP TRUE\n");
      fputs($dgraph, "CREATE_HTML_MAP FALSE\n");
      //fputs($dgraph, "MAP_NAME hops-$date\n");
      fputs($dgraph, "WITH_GAP TRUE\n");
      //fputs($dgraph, "WITH_GAP FALSE\n");
      fputs($dgraph, "CONTINUOUS_TRACE TRUE\n");

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
      
      /* to modify the gap threshold then print it here after extra options */
      /* XXX the check on subtype is just to deal with the test that runs
       * more frequently than others, need to know how long the gap should be
       */
      if ( $dataSet[$src][$dst][0]["info"]->dataSubtype == "0100B20000us100P" )
        fputs($dgraph, "X_GAP_THRESHOLD " . $gapThreshold[TRACE_DATA] . "\n");
      else
        fputs($dgraph, "X_GAP_THRESHOLD 14460\n");

      /* Draw the datasets */
      foreach ($dataSet[$src][$dst] as $set) {

        /* Dataset initialisation */
        fputs($dgraph, "DATASET_START\n");

        /* Output the data */
        for ($i = 0; $i<count($set["data"]); $i++) {
          $x = $set["data"][$i]->secInPeriod;
          $y = "";

          foreach($set["data"][$i]->interarrival as $time)
            $y .= " $time";

          if ( $y != "" ) {
            fputs($dgraph, "$x $y\n");
          }
          flush();
        }

      }

      /* Close dgraph connection */
      pclose($dgraph);

      /* Return to browser */
      if ($scope == PREF_SHORTTERM) {
        //outputMapFile($mapFile);
      }
      echo "<img alt=\"udpstream graph\" src=\"$fileName\"$map>\n";
      //echo "<img alt=\"udpstream graph\" src=\"$fileName\">\n";
    }
  }
  
}

// Emacs control
// Local Variables:
// eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
