<?php
/*
 * AMP Data Display Interface
 *
 * HTTP2 Test Display Module
 *
 * Author:      Brendon Jones <bcj3@cs.waikato.ac.nz>
 *
 * This module provides the HTTP2 duration, bandwidth and server/object 
 * count graphs. These are essentially a small update to the older HTTP
 * graphs but there is heaps more information available that should be
 * graphed.
 *
 */
$dataSets = array();

/* Define Preference Stuff */
define('PREF_HTTP2', "http2");

define('HTTP2_PREF_DISPLAY_DURATION', "display-duration");
define('HTTP2_PREF_DISPLAY_BYTES', "display-bytes");
define('HTTP2_PREF_DISPLAY_BANDWIDTH', "display-bandwidth");
define('HTTP2_PREF_DISPLAY_OBJECTS', "display-objects");
define('HTTP2_PREF_DISPLAY_OBJECT_DURATION', "display-object-duration");

/* Register Preferences */
register_module(PREF_HTTP2, "HTTP2 Graphs", "These preferences control the " .
                "display of graphs relating to Web Browsing (HTTP2) tests");

register_preference(array(PREF_HTTP2), PREF_YMAX, array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Maximum y-axis value", "", PREF_TYPE_INPUT, array(), 3);

/* Register Display Preferences */
register_preference(array(PREF_GLOBAL), HTTP2_PREF_DISPLAY_DURATION,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display HTTP2 Duration Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_GLOBAL), HTTP2_PREF_DISPLAY_BYTES,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display HTTP2 Byte Count Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_GLOBAL), HTTP2_PREF_DISPLAY_BANDWIDTH,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display HTTP2 Bandwidth Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_GLOBAL), HTTP2_PREF_DISPLAY_OBJECTS,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display HTTP2 Object Count Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_GLOBAL), HTTP2_PREF_DISPLAY_OBJECT_DURATION,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display HTTP2 Object Duration Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_HTTP2), PREF_DISPLAY_SUMMARY,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Summary", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,
                          PREF_SHORTTERM=>PREF_TRUE));

/* Register Available Display Objects */
register_display_object("http2-duration", "Web Browsing (HTTP2)",
                        "Page Fetch Time", 20, HTTP2_DATA, "*", "http2_avail",
                        "drawGraph", "http2_get_duration_ds", "http2_get_base_ds",
                        HTTP2_PREF_DISPLAY_DURATION, PREF_HTTP2, array(),
                        array("ymax"=>array("y-axis max.",3,PREF_YMAX)), -1,
                        -1, "",  "ms", "", "y", TRUE,
                        "POINT_TYPE cross\nPOINT_SIZE 2\nWITH_GAP true\n" .
                        "X_GAP_THRESHOLD 30\n", TRUE);
register_display_object("http2-bytes", "Web Browsing (HTTP2)",
                        "Page Total Bytes Fetched", 21, HTTP2_DATA, "*",
                        "http2_avail", "drawGraph", "http2_get_bytes_ds",
                        "http2_get_base_ds", HTTP2_PREF_DISPLAY_BYTES,
                        PREF_HTTP2, array(),
                        array("ymax"=>array("y-axis max.",3,PREF_YMAX)), -1,
                        -1, "", "Kilobytes", "", "y", TRUE,
                        "POINT_TYPE cross\nPOINT_SIZE 2\nWITH_GAP true\n" .
                        "X_GAP_THRESHOLD 30\n", TRUE);
register_display_object("http2-bandwidth", "Web Browsing (HTTP2)",
                        "Page Fetch Bandwidth", 22, HTTP2_DATA, "*",
                        "http2_avail", "drawGraph", "http2_get_bandwidth_ds",
                        "http2_get_base_ds", HTTP2_PREF_DISPLAY_BANDWIDTH,
                        PREF_HTTP2, array(),
                        array("ymax"=>array("y-axis max.",3,PREF_YMAX)), -1,
                        -1, "", "KByte/s", "", "y", TRUE,
                        "POINT_TYPE cross\nPOINT_SIZE 2\nWITH_GAP true\n" .
                        "X_GAP_THRESHOLD 30\n", TRUE);
register_display_object("http2-objects", "Web Browsing (HTTP2)",
                        "Page Object Count", 23, HTTP2_DATA, "*",
                        "http2_avail", "drawGraph", "http2_get_objects_ds",
                        "http2_get_base_ds", HTTP2_PREF_DISPLAY_OBJECTS,
                        PREF_HTTP2, array(),
                        array("ymax"=>array("y-axis max.",3,PREF_YMAX)), -1,
                        -1, "", "Count", "", "y", TRUE,
                        "POINT_TYPE cross\nPOINT_SIZE 2\nWITH_GAP true\n" .
                        "X_GAP_THRESHOLD 30\n", TRUE);
register_display_object("http2-object-duration", "Web Browsing (HTTP2)",
                        "Combined Object Fetch Time", 24, HTTP2_DATA, "*", 
                        "http2_avail",
                        "drawUdpstream", "http2_get_object_duration_ds", 
                        "http2_get_base_ds",
                        HTTP2_PREF_DISPLAY_OBJECT_DURATION, PREF_HTTP2,
                        array("summary"=>array("Summary",PREF_DISPLAY_SUMMARY)),
                        array("ymax"=>array("y-axis max.",3,PREF_YMAX)), -1,
                        -1, "",  "ms", "", "y", TRUE,
                        "POINT_TYPE cross\nPOINT_SIZE 2\nWITH_GAP true\n" .
                        "X_GAP_THRESHOLD 30\n", TRUE);

/* Register Test Helper Functions */
$test_names[HTTP2_DATA] = "HTTP2 (Web Browsing)";
$subtype_name_funcs[HTTP2_DATA] = "http2_subtype_name";
$raw_data_funcs[HTTP2_DATA] = "http2_format_raw";

/** Data Available Function
 *
 * Return appropriate display item(s) if HTTP2 data is available
 */
function http2_avail($object_name, $src, $dst, $startTimeSec)
{
  if ( !checkDataExistsForTest($src, $dst, HTTP2_DATA) )
    return array();
    
  $stList = ampSubtypeList(HTTP2_DATA, $src, $dst);

  $items = array();
  foreach ( $stList->subtypes as $idx=>$subtype ) {
    /* register display item */
    $object = get_display_object($object_name);
    $item = new display_item_t();
    $item->category = $object->category;
    $item->name = $object->name . "-$subtype";
    $item->title = $object->title."(".http2_subtype_name($subtype).")";
    $item->displayObject = $object->name;
    $item->subType = $subtype;

    $items[$item->name] = $item;
  }
  return $items;
}

/** Data Retrieval Functions **/

/* HTTP2 Base Dataset */
function http2_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  global $timeZone, $dataSets;

  //echo number_format(memory_get_usage()) . "\\n"; flush();


  /* Checked for cached data */
  if ( $dataSets != array() ) {
    if ( isset($dataSets["$src-$dst-$subType-$startTimeSec-$secOfData"]) ) {
      return $dataSets["$src-$dst-$subType-$startTimeSec-$secOfData"];
    }
  }

  /* Open Database Connection */
  $res = ampOpenDb(HTTP2_DATA, $subType, $src, $dst, $startTimeSec, 0, $timeZone);
  if ( ! $res ) {
    return array();
  }
  $info = ampInfoObj($res);

  /* Fetch Data */
  $sample = 0;
  while ( ($obj = ampNextObj($res)) && $obj->secInPeriod<$secOfData ) {
    $plotData[$sample]->secInPeriod  = $obj->secInPeriod;
    $plotData[$sample]->duration = $obj->duration;
    $plotData[$sample]->size    = $obj->size;
    $plotData[$sample]->serverCount = $obj->serverCount;
    $plotData[$sample]->objectCount = $obj->objectCount;
    /* only give objects if a week or less of data selected, it's just too
     * much memory otherwise 
     */
    if ( $secOfData <= SECONDS_1WEEK )
      $plotData[$sample]->servers = $obj->servers;
    $sample++;
  }
  $numSamples = $sample;

  /* Cache data in case of further look ups */
  $data = array("plotData" => $plotData, "numSamples" => $numSamples,
      "info" => $info);
  $dataSets["$src-$dst-$subType-$startTimeSec-$secOfData"] = $data;
  return $data;

}

function http2_get_duration_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  /* Get the base dataset for the subtype */
  $ds = http2_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize);
  if ( $ds == array() ) {
    return array();
  }

  $plotData = $ds["plotData"];
  $numSamples = $ds["numSamples"];

  /* Setup the dataset parameter */
  $dataSets = array();
  $dataSet["color"] = "red";
  $dataSet["key"] = "http2 duration";
  $dataSet["info"] = $ds["info"];
  $gdata = array();
  $total = 0;
  for ( $sample=0; $sample<$numSamples; ++$sample ) {
    $gdata[$sample]["x"] = $plotData[$sample]->secInPeriod;
    $gdata[$sample]["y"] = $plotData[$sample]->duration;
  }
  $dataSet["data"] = $gdata;
  $dataSets[] = $dataSet;

  return $dataSets;

}


function http2_get_bytes_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  /* Get the base dataset for the subtype */
  $ds = http2_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize);
  if ( $ds == array() ) {
    return array();
  }

  $plotData = $ds["plotData"];
  $numSamples = $ds["numSamples"];

  /* Setup the dataset parameter */
  $dataSets = array();
  $dataSet["color"] = "red";
  $dataSet["key"] = "http2 kilobytes";
  $dataSet["info"] = $ds["info"];
  $gdata = array();
  $total = 0;
  for ( $sample=0; $sample<$numSamples; ++$sample ) {
    $gdata[$sample]["x"] = $plotData[$sample]->secInPeriod;
    $gdata[$sample]["y"] = $plotData[$sample]->size/1024.0;
  }
  $dataSet["data"] = $gdata;
  $dataSets[] = $dataSet;

  return $dataSets;

}


function http2_get_bandwidth_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  /* Get the base dataset for the subtype */
  $ds = http2_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize);
  if ( $ds == array() ) {
    return array();
  }

  $plotData = $ds["plotData"];
  $numSamples = $ds["numSamples"];

  /* Setup the dataset parameter */
  $dataSets = array();
  $dataSet["color"] = "blue";
  $dataSet["key"] = "http2 bandwidth";
  $dataSet["info"] = $ds["info"];
  $gdata = array();
  for ($sample=0; $sample<$numSamples; ++$sample) {
    if ($plotData[$sample]->duration == 0) {
      continue;
    }
    $gdata[$sample]["x"] = $plotData[$sample]->secInPeriod;
    $bw = (int)(($plotData[$sample]->size /
      $plotData[$sample]->duration * 1000)/1024);
    $gdata[$sample]["y"] = $bw;
  }
  $dataSet["data"] = $gdata;
  $dataSets[] = $dataSet;

  return $dataSets;

}

function http2_get_objects_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  /* Get the base dataset for the subtype */
  $ds = http2_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize);
  if ( $ds == array() ) {
    return array();
  }


  $plotData = $ds["plotData"];
  $numSamples = $ds["numSamples"];
  $info = $ds["info"];

  /* Setup the dataset parameter */
  $ds1 = array();
  $ds1["color"] = "blue";
  $ds1["key"] = "servers";
  $ds1["info"] = $info;

  $ds2 = array();
  $ds2["color"] = "red";
  $ds2["key"] = "objects";
  $ds2["info"] = $info;

  /* Get the data */
  $data1 = array();
  $data2 = array();
  for ( $sample = 0; $sample<$numSamples; ++$sample ) {
    $sec = $plotData[$sample]->secInPeriod;
    $servers  = $plotData[$sample]->serverCount;
    $objects = $plotData[$sample]->objectCount;
      
    $data1[$sample]["x"] = $sec;
    $data1[$sample]["y"] = $servers;
    $data2[$sample]["x"] = $sec;
    $data2[$sample]["y"] = $objects;
  }
  $ds1["data"] = $data1;
  $ds2["data"] = $data2;

  $dataSets = array($ds1, $ds2);

  return $dataSets;

}

/* this should maybe be made more generic and put in amplib? */
function calculateMedian($data) {
  if (sizeof($data) == 0) {
    return 0;
  } else if (sizeof($data) == 1) {
    return $data[0];
  } else if ((sizeof($data) % 2) == 0) {
    sort($data);
    $v1 = (int)(sizeof($data)/2);
    $v2 = ((int)(sizeof($data)/2))+1;
    return ($data[$v1] + $data[$v2]) / 2;
  } else {
    sort($data);
    return $data[((int)sizeof($data)/2)+1];
  }
}
define('LOOKUP', 0);
define('CONNECT', 1);
define('SETUP', 2);
define('TOTAL', 3);

function http2_get_object_duration_ds($src, $dst, $subType, $startTimeSec, 
    $secOfData, $binSize)
{

  /* Get the base dataset for the subtype */
  $ds = http2_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize);
  
  if ( $ds == array() ) {
    return array();
  }

  $plotData = $ds["plotData"];
  $numSamples = $ds["numSamples"];

  /* Setup the dataset parameter */
  $dataSet = array();
  $dataSet["color"] = "black";
  $dataSet["key"] = "total object time";
  $dataSet["info"] = $ds["info"];

  $totalData = array();
  for ( $sample=0; $sample<$numSamples; ++$sample ) {
    $total = 0;
    $connect = 0;
    $lookup = 0;
    $setup = 0;
    $download = 0;
    foreach ( $plotData[$sample]->servers as $server ) {
      foreach ( $server->objects as $object ) {
        $lookup += $object->lookuptime;
        $connect += $object->connecttime;
        $setup += $object->starttransfertime;
        $total += $object->totaltime;
      }
    }
    /* interarrival/rainbow graph expects differences, not absolute values */
    $total = $total - $setup;
    $setup = $setup - $connect;
    $connect = $connect - $lookup;

    /* the way the udpstream/arrival graph was originally set up requires
     * these values in microseconds!
     */
    $totalData[$sample]->interarrival[LOOKUP] = $lookup * 1000000;
    $totalData[$sample]->interarrival[CONNECT] = $connect * 1000000;
    $totalData[$sample]->interarrival[SETUP] = $setup * 1000000;
    $totalData[$sample]->interarrival[TOTAL] = $total * 1000000;
    $totalData[$sample]->secInPeriod = $plotData[$sample]->secInPeriod;
    $totalData[$sample]->packets = TOTAL + 1;
  }


  $tmpData = array(LOOKUP => array(), CONNECT => array(), 
      SETUP => array(), TOTAL => array());
  foreach ( $tmpData as $key => $value ) {
    $summary[$key] = array("total" => 0, "squaresSum" => 0, "count" => 0, 
        "max" => 0, "min" => PHP_INT_MAX);
  }

  foreach ( $totalData as $point ) {
    foreach ( $point->interarrival as $key => $value ) {
      /* convert the microsecond values to millisecond values */
      $value = $value / 1000.0;
      $tmpData[$key][] = $value;
      $summary[$key]["total"] += $value;
      $summary[$key]["squaresSum"] += $value * $value;
      $summary[$key]["count"]++;
      if ( $value > $summary[$key]["max"] )
        $summary[$key]["max"] = $value;
      if ( $value < $summary[$key]["min"] ) {
        $summary[$key]["min"] = $value;
      }

    }
  }


  $summary[LOOKUP]["median"] = calculateMedian($tmpData[LOOKUP]);
  $summary[CONNECT]["median"] = calculateMedian($tmpData[CONNECT]);
  $summary[SETUP]["median"] = calculateMedian($tmpData[SETUP]);
  $summary[TOTAL]["median"] = calculateMedian($tmpData[TOTAL]);


  $dataSet["data"] = $totalData;
  $dataSet["summary"]["lookup"] = $summary[LOOKUP];
  $dataSet["summary"]["connect"] = $summary[CONNECT];
  $dataSet["summary"]["setup"] = $summary[SETUP];
  $dataSet["summary"]["total"] = $summary[TOTAL];
  $dataSets = array($dataSet);

  return $dataSets;

}

function http2_subtype_name($subtype) {

  list($keep_alive, 
      $max_connections, 
      $max_connections_per_server,
      $max_persistent_connections_per_server, 
      $pipelining, 
      $pipelining_maxrequests, 
      $caching, $url) = sscanf($subtype, "k%dm%ds%dx%dp%dr%dc%d-%s");

    /* XXX this is going to mess up as soon as anyone actually has an 
     * underscore as part of the url, what to use instead?
     */
    $url = str_replace("_", "/", $url);

    $name = "persist:$keep_alive " . 
      "conn:$max_connections " . 
      "server:$max_connections_per_server " . 
      "pserver:$max_persistent_connections_per_server " . 
      "pipe:$pipelining " . 
      "pipemax:$pipelining_maxrequests " . 
      "cache:$caching " .
      "url:$url";

    return $name;
}

/* Formats a line of raw HTTP2 data */
function http2_format_raw($subType, $obj)
{

  /* Handle request for a header line */
  if ($obj == NULL) {
    return "duration_ms,size_bytes,total_servers,total_objects";
  }

  return sprintf("%d,%s,%d,%d", 
      $obj->duration, $obj->size, $obj->serverCount, $obj->objectCount);

}

// Emacs control
// Local Variables:
// eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
