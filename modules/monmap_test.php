<?php
/*
 * AMP Data Display Interface
 *
 * Monitor Map Display Module
 *
 * This module provides a map of the various monitors
 *
 */


define('PREF_MONMAP', "monmap");

define('PREF_MONMAP_RTTAVG', "display-rttavg");
define('PREF_MONMAP_RECENT', "display-rttrecent");
define('PREF_MONMAP_ALLNODES', "display-monmap-allnodes");
define('PREF_MONMAP_OTHERPATHS', "display-monmap-otherpaths");

/* Define Preference Stuff */
define('MONMAP_PREF_DISPLAY_MAP', "display-map");

register_preference(array(PREF_GLOBAL), MONMAP_PREF_DISPLAY_MAP,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display Monitor Map Graph", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,PREF_SHORTTERM=>PREF_TRUE));

register_preference(array(PREF_MONMAP), PREF_MONMAP_ALLNODES,
                    array(PREF_LONGTERM, PREF_SHORTTERM),
                    "Show all other nodes", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE, PREF_SHORTTERM=>PREF_FALSE));

register_preference(array(PREF_MONMAP), PREF_MONMAP_OTHERPATHS,
                    array(PREF_LONGTERM, PREF_SHORTTERM),
                    "Show other uncommon paths", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE, PREF_SHORTTERM=>PREF_FALSE));

register_preference(array(PREF_MONMAP), PREF_MONMAP_RTTAVG,
                    array(PREF_LONGTERM, PREF_SHORTTERM), "Show RTT Average",
                    "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE, PREF_SHORTTERM=>PREF_TRUE));

register_preference(array(PREF_MONMAP), PREF_MONMAP_RECENT,
                    array(PREF_LONGTERM, PREF_SHORTTERM), "Show most recent RTT",
                    "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE, PREF_SHORTTERM=>PREF_TRUE));

register_module(PREF_MONMAP, "Monitor Map", "These preferences control the " .
                "display of the monitor map graph.");

/* Register Monitor Map Graph */
register_display_object("monmap", "Path Analysis", "Monitor Map", 30,
        TRACE_DATA, "*", "monmap_avail", "drawMonmap", "monmap_get_ds",
        "monmap_get_ds", MONMAP_PREF_DISPLAY_MAP, PREF_MONMAP,
        array("allnodes"=>array("Show all nodes", PREF_MONMAP_ALLNODES),
              "rttavg"=>array("RTT Average", PREF_MONMAP_RTTAVG),
              "rttrecent"=>array("Most recent RTT", PREF_MONMAP_RECENT)),
        array(), -1, -1, "", "", "", "", FALSE, "", FALSE);

/* Register Test Helper Functions */
$test_names[TRACE_DATA] = "Traceroute";
$subtype_name_funcs[TRACE_DATA] = "";

/** Data Available Function
 *
 * Return appropriate display item(s) if Traceroute data is available
 */
function monmap_avail($object_name, $src, $dst, $startTimeSec)
{
  if ( !checkDataExistsForTest($src, $dst, TRACE_DATA) ) 
    if ( !checkDataExistsForTest($src, $dst, SCAMPER_DATA) ) 
      return array();

  /* Traceroute data exists - register display item */
  $object = get_display_object($object_name);
  $item = new display_item_t();
  $item->category = $object->category;
  $item->name = $object->name;
  $item->title = $object->title;
  $item->displayObject = $object->name;
  $item->subType = "";

  return array($item->name=>$item);
}



/** Data Retrieval Functions **/

/* Traceroute Dataset */
function monmap_get_ds($src, $dst, $subType, $startTimeSec, $secOfData,
                       $binSize)
{

  global $timeZone;
  $dataSets = array();

  /* Open database */
  $dataType = SCAMPER_DATA;
  $subtype = getScamperSubtype($src, $dst, "icmp", false, false);
  $res = ampOpenDb(SCAMPER_DATA, $subtype, $src, $dst, $startTimeSec, 0, 
      $timeZone);
  if( !$res ) {
    $dataType = TRACE_DATA;
    $res = ampOpenDb(TRACE_DATA, 0, $src, $dst, $startTimeSec, 0, $timeZone);
    if( !$res ) {
      return array();
    }
  }
  $dataSets["info"] = ampInfoObj($res);
  $dataSets["secOfData"] = $secOfData;
  $dataSets["startTimeSec"] = $startTimeSec;

  /* Extract the main path */
  $paths = processPaths($res);
  $dataSets["origPath"] = $paths["mostcommon"]["trace"];
  $dataSets["paths"] = $paths["paths"];

  /* 
   * scamper data includes the final destination in the path list, remove it
   * for this graph to make it the same as the normal traceroute test
   */
  $lastItem = sizeof($dataSets["origPath"]) - 1;
  if ( $dataType == SCAMPER_DATA && 
      $dataSets["origPath"][$lastItem]["hostname"] == $dst ) {
    unset($dataSets["origPath"][$lastItem]);
  }

  if ( $dataSets["origPath"] == false )
    return array();

  return $dataSets;
}


function getPaths($src, $dst, $startTime, &$dataSets, $from) {

  global $timeZone;
  $dstCur;

  /* check direction of the path */
  if (!$from)
    $sites = ampSiteList("");
  else
    $sites = ampSiteList($from);

  for ($siteIndex = 0; $siteIndex < $sites->count; ++$siteIndex) {
    $siteName = $sites->srcNames[$siteIndex];
    /* Skip main source / dest amplets */
    //if ($src == $siteName || $siteName == $dst || 
    if (is_numeric(substr($siteName, 0, 2)))
      continue;

    if ($from) {
      $dstCur = $siteName;
      $siteName = $src;
    } else {
      $dstCur = $dst;
    }

    $dataType = SCAMPER_DATA;
    $subtype = getScamperSubtype($src, $dst, "icmp", false, false);
    $test = ampOpenDb(SCAMPER_DATA, $subtype, $siteName, $dstCur, 
        $startTime, 0, $timeZone);

    /* Skip amplet if no traceroute data available */
    if ( !$test ) {
      $dataType = TRACE_DATA;
      $test = ampOpenDb(TRACE_DATA, 0, $siteName, $dstCur, $startTime, 0,
        $timeZone);
      if ( !$test ) {
        continue;
      }
    }

    /* Get the path */
    $paths = processPaths($test);

    /* an empty array here represents adjacent hosts, false is an error */
    if ( is_array($paths) ) {
      if (!$from) {
        $dataSets["paths"][$siteName] = $paths;
      } else {
        $dataSets["dstPaths"][$dstCur] = $paths;
      }
    }
  }
}


function print_path($dgraph, $start, $end, $path) {

  fputs($dgraph, $start);
  foreach ( $path as $host ) {
    fputs($dgraph, " " . $host["hostname"]);
  }
  fputs($dgraph, " $end\n");

}


function printLinkUrl($dgraph, $start, $end, $date, $range) {

  fputs($dgraph,
      "urls graph.php?src=$start&dst=$end&date=$date$range " .
      "trace_detail.php?src=$start&dst=$end&date=$date\n");
}


function printRttHeaders($dgraph, $wantedData, $subtype) {
  $sizecaption = ltrim($subtype, " 0");
  if ( $wantedData & PING_AVG )
    fputs($dgraph, "metric blue ${sizecaption}b rtt average\n");
  if ( $wantedData & PING_RECENT )
    fputs($dgraph, "metric red ${sizecaption}b rtt recent\n");
}


/*
 * Open dgraf and send it the commands to actually draw the monitor map.
 */
function drawOneMonmap($src, $dst, $date, $graphOptions, $item_name, $lineGraph,
        $xAxisType, $usemap, $xLabel, $yLabel, $xMax, $yMax, $dataSet,
        $extraOpts, $scope, $drawRight, $multi) 
{
  global $timeZone;

  $mapName = "";
  
  /* Build graph file name, check for cached image */
  $fileName = cacheFileName($xAxisType, $item_name, $src, $dst, $date,
      $graphOptions, $cached);
  if ( $fileName == "" ) {
    return;
  }
  
  $mapFile = cacheFileName("mapfile", $item_name, $src, $dst, $date,
      $graphOptions, $mCached);
  
  $map = " usemap=\"#pathmap-$date-$src-$dst\"";
  
  /* Return cached image if available */
  if ( $cached  && $mCached && $multi ) {
    outputMapFile($mapFile);
    echo "<img alt=\"monmap graph\" src=\"$fileName\"$map>\n";
    return;
  }
  
  /* get the icmp dataset function */
  foreach ($GLOBALS["display_items"] as $key=>$ditem) {
    if ( $ditem->displayObject == "icmp-latency" ) {
      $item = get_display_item($ditem->name);
      $object = get_display_object($item->displayObject);
      $dsf = $object->dataSetFunc;
    }
  }
  
  $startTimeSec = $dataSet[$src][$dst]["startTimeSec"];
  $secOfData = $dataSet[$src][$dst]["secOfData"];
  $range = (isset($_REQUEST["rge"]))?"&rge=" . $_REQUEST["rge"]:"";

  /* do we want to output rtt values?  */
  $wantavg = get_option_value(PREF_MONMAP, PREF_MONMAP, PREF_MONMAP_RTTAVG,
      "rttavg", $scope, 1);
  $wantrecent = get_option_value(PREF_MONMAP,PREF_MONMAP,PREF_MONMAP_RECENT,
      "rttrecent", $scope, 2);
  
  if ( $wantavg ) {
    $wantavg = PING_AVG;
  }
  if ( $wantrecent ) {
    $wantrecent = PING_RECENT;
  }
  
  $wantedData = $wantavg | $wantrecent;
  $wantedData = PING_AVG | PING_RECENT; // always give the new graph rtt's
  /* if there is no valid size for icmp test subtype then don't bother */
  if ( $wantedData ) {
    if ( ($subtype = getSmallestPacketSize($src, $dst)) < 1 )
      $wantedData = 0;
  }

  /* open a pipe to the graph tool */
  $dgraph = popen("$GLOBALS[dgraf] -i - -o $fileName -m $mapFile", "w");
  //$dgraph = fopen("/tmp/dgraf.log", "w");
  
  fputs($dgraph, "GRAPH_TYPE pathvis\n");
  fputs($dgraph, "CREATE_HTML_MAP TRUE\n");
  fputs($dgraph, "MAP_NAME pathmap-$date-$src-$dst\n");
  
  if ( !$multi && 
      !get_option_value(PREF_MONMAP, PREF_MONMAP, PREF_MONMAP_ALLNODES,
        "allnodes", $scope, 0) ) {
    fputs($dgraph, "NEAREST_ONLY TRUE\n");
  }
  
  fputs($dgraph, "DATASET_START\n");

  $doneHeaders = false;
  if ( $drawRight ) {
    /* one source on the left, multiple destinations on the right */
    foreach ( $dataSet[$src] as $destination => $path ) {
      if ( is_array($path) && is_array($path["origPath"]) ) {
        printLinkUrl($dgraph, $src, $destination, $date, $range);

        /* output RTT stats */
        if ( $wantedData ) {
          if ( !$doneHeaders ) {
            printRttHeaders($dgraph, $wantedData, $subtype);
            $doneHeaders = true;
          }
          printRecentData($dgraph, $src, $destination, $subtype, $wantedData, 
              $startTimeSec, $secOfData);
        }

        print_path($dgraph, $src, $destination, $path["origPath"]);
        
        // add the path into our path array
        addPath($src, $destination, $path["origPath"], &$paths);
 
        // put rtt data into the leaf data
        gatherRttData($src, $destination, $subtype, $startTimeSec, $secOfData, $timeZone, &$leafData);
        
        $leafData[$src][$destination]['allPaths'] = array("mostCommon" => $path['origPath'], "others" => $path['paths']);
      }
    }
    
    if (!$multi) drawJavascriptTree($paths, $src, $leafData, "right");

  } else {
    /* multiple sources on the left, one destination on the right */
    foreach ( $dataSet as $source => $path ) {
      if ( is_array($path[$dst]) && is_array($path[$dst]["origPath"]) ) {
        printLinkUrl($dgraph, $source, $dst, $date, $range);
        
        /* output RTT stats */
        if ( $wantedData ) {
          if ( !$doneHeaders ) {
            printRttHeaders($dgraph, $wantedData, $subtype);
            $doneHeaders = true;
          }
          printRecentData($dgraph, $source, $dst, $subtype, $wantedData, 
              $startTimeSec, $secOfData);
        }

        print_path($dgraph, $source, $dst, $path[$dst]["origPath"]);
        
        // add the path into our path array
        addPath($dst, $source, array_reverse($path[$dst]["origPath"]), &$paths);
        
        // put rtt data into the leaf data
        gatherRttData($dst, $source, $subtype, $startTimeSec, $secOfData, $timeZone, &$leafData);
        
        $leafData[$dst][$source]['allPaths'] = array("mostCommon" => $path[$dst]['origPath'], "others" => $path[$dst]['paths']);
      }
    }

    if (!$multi) drawJavascriptTree($paths, $dst, $leafData, "left");
  }


  /* Close connection to dgraf */
  pclose($dgraph);
  
  if ($multi) { 
    outputMapFile($mapFile);
  
    /* Return to browser */
    echo "<img alt=\"monmap graph\" src=\"$fileName\"$map>\n";
  }

}

/*
 * Gathers rtt data for use in the monitor map
 */
function gatherRttData($src, $dst, $subtype, $startTimeSec, $secOfData, $timeZone, &$rttData) {
   /* output RTT stats */
   $rttData[$src][$dst]['rtt'] = getRecentData($src, $dst, $subtype, 3, $startTimeSec,
      $secOfData);

      $test = ampOpenDb(ICMP_DATA, $subtype, $src, $dst, $startTimeSec, 0, $timeZone);
      if ($test) {
          ampGetSummaryDataset($test, $secOfData, false, &$summary);
          
          foreach($summary as $key => $stat) {
              $stats[$key] = $stat;
          }

          $rttData[$src][$dst]['icmp'] = $stats;  
      }
}

/*
 * Adds a paths to a given array of paths.
 * This adds the source and destinations to the beginning and
 * end of the array respectively.
 */
function addPath($src, $dst, $path, &$paths) {
  $paths[] = array_merge(
    array(array("ip" => $src, "hostname" => $src)), 
    $path, 
    array(array("ip" => $dst, "hostname" => $dst)));   
}


/*
 * Draws a javascript tree of the traceroute data
 */
function drawJavascriptTree($paths, $src, $leafData, $treeDirection) {
    // process the full tree
    $tTree = new TracerouteTree($paths, $src, false);
    $tTree->setLeafValues($leafData[$src]);
    $tree = $tTree->treeToJson();
    $id = str_replace(array(":", " ", "."), "-", $src);

    $trees['treeFull'] = $tree;

    // process the pruned tree
    $tTree = new TracerouteTree($paths, $src, true);
    $tTree->setLeafValues($leafData[$src]);
    $tree = $tTree->treeToJson();

    $trees['treePruned'] = $tree;

    // output html and javascript
    echo "<div id='jsmap-$id'></div>";
    echo "<script type='text/javascript' src='js/traceroute/traceroute.map.js'></script>"; 
    echo "<script type='text/javascript' src='js/traceroute/traceroute.view.js'></script>"; 
    echo "<script type='text/javascript' src='js/raphael.js'></script>";
    echo "<script type='text/javascript'>";

    // create a new traceroute map view
    echo "var tracerouteView = $.amptraceview($('#jsmap-$id'), { treeFull: " . 
      $trees['treeFull'] . ", treePruned: " . $trees['treePruned'] . "}, \"" . $treeDirection . "\")";
   
    echo "</script>";
}

/*
 * Get the smallest packet size used between two sites for the ICMP test,
 * to use for the recent/average latency display.
 */
function getSmallestPacketSize($src, $dst) {
  
  $subtype = "";
  $stList = ampSubtypeList(ICMP_DATA, $src, $dst);

  if ( count($stList->subtypes) < 1 ) {
    return 0;
  }

  foreach ( $stList->subtypes as $idx=>$size ) {
    /* Skip random data */
    if ( $size == "rand" ) {
      continue;
    }
    $sizei = (int)$size;
    if ($subtype == "" || $sizei<(int)$subtype) {
      $subtype = $size;
    }
  }
  return $subtype;
}


/*
 * Print average and recent RTT measurements for a path, if required.
 */
function printRecentData($dgraph, $src, $dst, $subtype, $wantedData, 
    $startTimeSec, $secOfData) {

  /* output RTT stats */
  $data = getRecentData($src, $dst, $subtype, $wantedData, $startTimeSec, 
      $secOfData);

  if ( $wantedData & PING_AVG ) {
    /* dgraf will only deal with integers here? */
    fputs($dgraph, round($data["average"]) . " ");
  }

  if ( $wantedData & PING_RECENT ) {
    fputs($dgraph, $data["recent"] . " ");
  }
}


/*
 * Get average and recent RTT measurements for a path, if required.
 */
function getRecentData($src, $dst, $subtype, $wantedData, 
    $startTimeSec, $secOfData) {

  global $timeZone;

  $result = array("average" => -1, "recent" => -1);

  $ampDB = ampOpenDB(ICMP_DATA, $subtype, $src, $dst, $startTimeSec, 0, 
      $timeZone);

  if ( $ampDB ) {
    $data = getPingInfo($ampDB, $secOfData, $wantedData);
    if ( !$data )
      return $result;

    if ( ($wantedData & PING_AVG) && is_numeric($data->PING_AVG) ) {
      $result["average"] = $data->PING_AVG;
    } 

    if ( ($wantedData & PING_RECENT) && is_numeric($data->PING_RECENT) ) {
      $result["recent"] = $data->PING_RECENT;
    }
  }

  return $result;
}



function drawMonmap($src, $dst, $date, $graphOptions, $item_name, $lineGraph,
        $xAxisType, $usemap, $xLabel, $yLabel, $xMax, $yMax, $dataSet,
        $extraOpts, $scope)
{
  $sources = expandSites($src);
  $destinations = expandSites($dst);
  $drawRight = false;
  $multi = false;

  if ( sizeof($sources) > 1 && sizeof($destinations) > 1 ) {
    $multi = true;
  }

  if ( sizeof($destinations) > 1 ) {
    $drawRight = true;
  }

   /* XXX
    * Go behind the back of the dataset fetching functions and get some extra
    * data to make the graph more interesting in the case of 1 source and 1
    * destination.
    */
  if ( sizeof($sources) == 1 && sizeof($destinations) == 1 ) {
    /* get all paths from the given source */
    $extraPaths = array();
    getPaths($src, $dst, $dataSet[$src][$dst]["startTimeSec"], 
        $extraPaths, $src);

    /* add the paths to the working dataset */
    foreach($extraPaths["dstPaths"] as $destination => $path) {
      $dataSet[$src][$destination]["origPath"] = $path["mostcommon"]["trace"];
      $dataSet[$src][$destination]["paths"] = $path["paths"];
    }
    $drawRight = true;
  }


  foreach($sources as $theSrc) {
    
    if ( !isset($dataSet[$theSrc]) ) {
      continue;
    }
  
    /* Make sure the "main" source and "main" destination are different */
    if ( count($destinations) > 1 ) {
      /* this is just to update the graph label */
      $src = $theSrc;

      /* check all possible destinations, stop on the first legitimate one */
      foreach ( $destinations as $destination ) {
        if ( $theSrc == $destination )
          continue;

        if ( !isset($dataSet[$theSrc][$destination]) )
          continue;

        $theDst = $destination;
        break;
      }
    } else {
      /* only one destination, use the one we were originally given */
      $theDst = $dst;
    }

    echo "<h4>$src to $dst</h4>\n";

    drawOneMonmap($theSrc, $theDst, $date, $graphOptions, $item_name, 
        $lineGraph,
        $xAxisType, $usemap, $xLabel, $yLabel, $xMax, $yMax, $dataSet,
        $extraOpts, $scope, $drawRight, $multi);

    /* Many-to-one graphs only need to draw one graph, not one per source */
    if ( count($destinations) == 1 )
      break;
  }
}

/*
 * Converts an object into an array
 */
function object2array($object) {
    if (is_object($object)) {
        foreach ($object as $key => $value) {
            $array[$key] = $value;
        }
    }
    else {
        $array = $object;
    }
    return $array;
}

/*
 * Use with 'usort' to sort traces based on the number of times they occur
 */
function compareTraces($a, $b) {
    if ($a['count'] == $b['count']) return 0;
    return ($a['count'] < $b['count']) ? -1 : 1;
}

/*
 * Loop through a dataset referrence and extract the most common paths,
 * and all other paths that occur.
 *
 * An associative array is returned with the following fields
 * result["mostcommon"]     - The most commonly found path
 * result["paths"]          - All other paths excluding the most common
 * 
 * Each path is an associative array containing
 * path["trace"]            - An array of the path hops
 * path["count"]            - The number of times this path was used
 */
function processPaths($test) {
    $paths = array();
    $stripFinal = false;
    if ( ampInfoObj($test)->dataType == SCAMPER_DATA ) {
      $stripFinal = true;
    }

    do {
        $obj = ampNextObj($test);
        
        if (!$obj) break;

        if ($obj->time > ampInfoObj($test)->requestStartSecUtc + 7200) {
            break;
        }

        if (!$obj->tracepath || $obj->hops < 2) continue;

        // convert the object into an array to make manipulation easier
        $trace = object2array($obj->tracepath);
        for ($i = 0; $i < count($trace); $i++) {
          /* 
           * scamper data includes the final destination in the path list, remove it
           * for this graph to make it the same as the normal traceroute test
           */
          if ( $stripFinal && $i == count($trace)-1 )
            unset($trace[$i]);
          else
            $trace[$i] = object2array($trace[$i]);
        }
        
        if (count($paths) < 1) {
            $paths[] = array("trace" => $trace, "count" => 1);
        }


        $found = false;
        for ($i = 0; $i < count($paths); $i++) {
            $path = $paths[$i];

            $equal = true;
            for ($j = 0; $j < count($path["trace"]); $j++) {
                $hop = $path["trace"][$j];
                
                if ($hop['ip'] != $trace[$j]['ip']) {
                    $equal = false;
                    break;
                }
            }

            if ($equal) {
                $found = true;
                $paths[$i]["count"] += 1;
                break;
            }
        }
        
        if (!$found) {
            $paths[] = array("trace" => $trace, "count" => 1);
        }
    
    } while ( TRUE );

    if ($paths) {
        usort($paths, "compareTraces");
        return array("mostcommon" => array_pop($paths), "paths" => $paths);
    } else {
        return array("mostcommon" => array(), "paths" => array());
    }
}

/**
 * A class to store the data relating to an individual traceroute hop.
 *
 * @author Joel Oughton
 */
class Node {
    public $name = "default";
    public $width = 0;
    public $branches = array();
    public $height = 0;
    public $above = 0;
    public $below = 0;
    public $direction = 0;
    public $isLeaf = false;
    public $isMainHop = false;
    public $collapseStart = false;
    public $collapsing = false;
    public $collapseEnd = false;
    public $data = array("ip" => "unknown", "latency" => -1, "mtu" => -1, "pmtud" => "unknown");

    public static function nodeComparator($a, $b) {
        if ($a === $b)
            return 0;

        if ($a->width == $b->width)
            return 0;

        return ($a->width < $b->width) ? -1 : 1;
    }
}

/**
 * A class to processes traceroute data and get it into a form suitable for
 * traceroute visualisation tasks.
 *
 * @author Joel Oughton
 */
class TracerouteTree {
    private $paths;
    private $rootName;
    private $tree;
    private $mainLine;

    public function __construct($paths, $rootName, $prune) {
        $this->rootName = $rootName;
        $this->paths = $paths;

        if ($prune) 
            $this->paths = $this->pruneTree($paths);
        
        $this->tree = $this->buildTree($this->paths, $rootName);
        $this->buildMainLines($this->tree['root']);
        $this->aboveAndBelow($this->tree['root'], 1);
       
        if ($prune) $this->tree['pruned'] = true;
        else $this->tree['pruned'] = false;

        $this->tree['height'] = 0;
        $this->treeHeight($this->tree['root']);

        $this->collapse($this->tree['root'], $this->tree['root'], false);
    }

    /**
     * Returns the tree data structure as a json encoded string
     *
     * @return a json encoded string of the tree structure
     */
    public function treeToJson() {
        return json_encode($this->tree);
    } 

    /**
     * Fills in the tree leaf arrays with the given data
     * The data should be in the form $data[destination][property-name]
     * 
     * This method makes changes to the tree
     *
     * @param $data
     *          the data to be inserted into the leaf data
     */
    public function setLeafValues($data) {
        if (!$data) {echo "return"; return;}
        foreach ($data as $key => $val) {
            $this->tree['leaves'][$key] = $val;
        }
    }

    /**
     * Fills in those nodes that are in the main line with appropriate properties.
     *
     * This method makes changes to the tree
     *
     * @param $node
     *          the node to start the algorithm from
     */
    private function buildMainLines($node) {
        $root = $node;
        $dir = -1;
        $altDir = -1;

        for ($i = 0; $i < count($root->branches); $i++) {
            $node = $root->branches[$i];
            
            while (count($node->branches) > 0) {
                $node->isMainHop = true;

                // fill in the main directions
                if (count($root->branches) == 1) {
                    if (count($node->branches) > 1)
                        $altDir *= -1;

                    $node->direction = $altDir;
                } else {
                    if ($i == 0) {
                        $node->direction = -1;
                    } else if ($i == count($root->branches) - 1) {
                        $node->direction = 1;
                    } else {
                        $node->direction = $dir;
                    }
                }

                $node = $node->branches[0];
            }
            $dir *= -1;
        }
    }
    
    /**
     * Finds the last position that a path and another path
     * are the same
     *
     * @param $mainPath
     *          the main path to compare with
     * @param $path
     *          the path that has the difference
     *
     * @return the last identical hop position
     */
    private function findBreakPos($mainPath, $path) {
        for ($i = 0; $i < count($mainPath); $i++) {             
            if ($mainPath[$i]['ip'] != $path[$i]['ip']) {
                return $i - 1;
            }
        }
    }

    /**
     * Runs over an array of paths and prunes off any branches 
     * from the main line that are not the shortest on each hop
     *
     * @param $paths
     *          an array of paths to be pruned
     *
     * @return the pruned path array
     */
    private function pruneTree($paths) {
        $mainPath = $paths[0];
        $paths = array_slice($paths, 1);
        $lengths = array();

        // find minimum lengths of each hop off the main path    
        foreach ($paths as $path) {
            $breakPos = $this->findBreakPos($mainPath, $path);
            
            if (array_key_exists($breakPos, $lengths)) {
                $lengths[$breakPos] = min($lengths[$breakPos], count($path));
            } else {
                $lengths[$breakPos] = count($path);
            }
        }
        
        // only take the paths that are short enough
        foreach ($paths as $path) {
            $breakPos = $this->findBreakPos($mainPath, $path);

            if (count($path) <= $lengths[$breakPos]) {
                $prunedPaths[] = $path;
            }
        }
    
        return array_merge(array($mainPath), $prunedPaths);
    }

    private function isHopInTree($root, $hop, $path, $place) {
        $current = $root;

        for ($i = 1; $i < count($path); $i++) {
            $found = false;
            $testHop = $path[$i]['hostname'];
            
            for ($j = 0; $j < count($current->branches); $j++) {
                $branch = $current->branches[$j];
                
                if ($branch->name == $testHop) {
                    // have we found the hop yet
                    if ($testHop == $hop && $place == $i) {
                        return $branch;
                    } else {
                        $current = $branch;
                        $found = true;
                        break;
                    }
                }
            }
            
            // check if the hop was found in the branches
            if (!$found)
                return NULL;
        }
    }
    
    private function findLastCommonHop($root, $path) {
        $current = $root;

        for ($i = 0; $i < count($path); $i++) {
            $newHop = $path[$i];
            $found = false;

            for ($j = 0; $j < count($current->$branches); $j++) {
                if ($newHop == $current->branches[$j]->name) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return $path[$i - 1];
            }
        }
    }

    /**
     * Extracts data from a hop if available and adds to a given node.
     *
     * @param $node
     *          the node to fill in
     * @param $hop
     *          an associative array containing hop data
     */
    private function fillInHopData($node, $hop) {
        if (array_key_exists("ip", $hop)) {
            $node->data["ip"] = $hop["ip"];
        }
        if (array_key_exists("latency", $hop)) {
            if ( $hop["latency"] < 0 )
              $node->data["latency"] = "unknown";
            else
              $node->data["latency"] = $hop["latency"];
        }
        if (array_key_exists("mtu", $hop)) {
            if ( $hop["mtu"] < 0 )
              $node->data["mtu"] = "unknown";
            else
              $node->data["mtu"] = $hop["mtu"];
        }
        if (array_key_exists("pmtud", $hop)) {
            if ( $hop["pmtud"] == 1 )
              $node->data["pmtud"] = "success";
            else if ( array_key_exists("mtu", $hop) && $hop["mtu"] > 0 )
              $node->data["pmtud"] = "inferred";
            else
              $node->data["pmtud"] = "failed";
        } 
    }

    /**
     * Takes an array of paths and builds a tree data structure from it
     *
     * @param $paths
     *          an array of traceroute paths
     * @param $rootName
     *          the name of the root node as a string
     */
    private function buildTree($paths, $rootName) {
        $root = new Node();
        $root->name = $rootName;
        $leaves = array();

        foreach($paths as $hops) {

            // check for the same source
            if ($hops[0]['hostname'] != $rootName) {
                continue;
            }

            // check for ipv6 timeout
            if ($hops[1]['hostname'] == "::") {
                continue;
            }

            if (in_array(end($hops), $leaves)) {
                return;
            } else {
                $w = 1;
                $end = end($hops);
                $leaves[$end['hostname']] = array();
            }
            
            $parent = $root;
            $root->width += $w;
            
            for ($i = 1; $i < count($hops); $i++) {
                $hop = $hops[$i];
                $node = $this->isHopInTree($root, $hop['hostname'], $hops, $i);

                // check if the node was in the tree
                if (is_null($node)) {
                    $node = new Node();
                    $node->name = $hop['hostname'];
                    $node->height = $parent->height + 1;

                    if ($i == count($hops) - 1)
                        $node->isLeaf = true;

                    $parent->branches[] = $node;
                }
                
                $this->fillInHopData($node, $hop);
                
                $node->width += $w;
                $parent = $node;
            }
        }    
        
        return array("leaves" => $leaves, "root" => $root);
    }
    
    /**
     * Traverses a tree and calculates branch directions and lengths
     *
     * This method makes changes to the tree
     *
     * @param $node
     *          the node to start the algorithm from
     * @param $direction
     *          the first branch direction to take
     */
    private function aboveAndBelow($node, $direction) {
        // only set the direction of non main hops 
        if (!$node->isMainHop)
            $node->direction = $direction;

        if (count($node->branches) == 0) {
            $node->above = 0;
            $node->below = 0;
        } else if (count($node->branches) == 1) {
            $result = $this->aboveAndBelow($node->branches[0], $direction);
            $node->above = $result['above'];
            $node->below = $result['below'];
        } else {
            $side = 0;
           
            // take the direction that the main hop tells us
            if ($node->isMainHop) $direction = $node->direction;

            for ($i = 1; $i < count($node->branches); $i++) {
                $branch = $node->branches[$i];

                $side += $branch->width;
                $this->aboveAndBelow($branch, $direction);
            }

            if ($direction == 1)
                $node->above += $side;
            else
                $node->below += $side;

            $result = $this->aboveAndBelow($node->branches[0], $direction);
            $node->above += $result['above'];
            $node->below += $result['below'];
        }

        return array("above" => $node->above, "below" => $node->below);
    }

    /**
     * Traverses the tree and sets its overall height and finds the fathest leaf node.
     *
     * This method makes changes to the tree
     *
     * @param $node
     *          the node to travers from
     */
    private function treeHeight($node) {
        $deepestNode = $node;
        if ( isset($this->tree['deepestNode']) )
          $deepestNode = $this->tree['deepestNode'];

        if ($node->height > $this->tree['height']
            || ($node->height == $deepestNode->height && strlen($node->name) > strlen($deepestNode->name))) {
            $this->tree['height'] = $node->height;
            $this->tree['deepestNode'] = $node;
        }

        foreach ($node->branches as $branch) {
            $this->treeHeight($branch);
        }
    }

    /**
     * Traverses a tree and sets it up to be collapsable
     *
     * This method makes changes to the tree
     *
     * @param $node
     *          the node to start the algorithm from
     * @param $prevNode
     *          should initially be set to $node
     * @param $collapsing
     *          should initially be set to array()
     */
    private function collapse($node, $prevNode, $collapsing) {
        if (count($node->branches) == 0) {
            if ($collapsing) {
                $node->collapseEnd = true;
                $node->collapsing = true;
            }
        } else if (count($node->branches) == 1) {
            // do not try collapse the root node
            if($node->height != 0) {
                // only set collapse start, at the collapse beginning
                if (!$collapsing )
                    $node->collapseStart = true;
                $collapsing = true;
                $node->collapsing = true;
            }

            $this->collapse($node->branches[0], $node, $collapsing);
        } else {
            if ($collapsing)
                $prevNode->collapseEnd = true;
            $collapsing = false;

            // remove 1 node collapses
            if ($prevNode->collapseStart && $prevNode->collapseEnd) {
                $prevNode->collapseStart = false;
                $prevNode->collapseEnd = false;
                $prevNode->collapsing = false;
            }

            foreach ($node->branches as $branch) {
               $this->collapse($branch, $node, $collapsing); 
            }
        }
    }

    /**
     * Traverse over a tree and print it out in html format for debugging
     *
     * @param $node
     *          the node to start printing from
     */
    public function printTree($node) {
        echo "<b>Node: " . $node->name . "</b>, above: " . $node->above . ", below: " . $node->below  . "<br/>";

        foreach ($node->branches as $branch)
            echo $branch->name . ":" . $branch->width . "," . $branch->height . "<br/>";

        echo "<br/>";

        foreach ($node->branches as $branch)
            printTree($branch);
    }

    /**
     * Output the tree data structure in html
     *
     * @param $tree
     *          the tree to output
     */
    public function debugOutput($tree) {
        print_r($tree['leaves']);
        echo "<br/>";
        echo "<pre>";
        print_r($tree['tree']);
        echo "</pre>";
    }
}
// Emacs control
// Local Variables:
// eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
