<?php
/*
 * AMP Data Display Interface
 *
 * HTTP Test Display Module
 *
 * Author:      Tony McGregor <tonym@cs.waikato.ac.nz>
 * Author:      Matt Brown <matt@crc.net.nz.
 * Version:     $Id: http_test.php 2103 2011-04-12 23:59:22Z brendonj $
 *
 * This module provides the HTTP latency and bandwidth graphs
 *
 */
$datasets = array();

/* Define Preference Stuff */
define('PREF_HTTP', "http");

define('HTTP_PREF_DISPLAY_LATENCY', "display-latency");
define('HTTP_PREF_DISPLAY_BANDWIDTH', "display-bandwidth");

/* Register Preferences */
register_module(PREF_HTTP, "HTTP Graphs", "These preferences control the " .
                "display of graphs relating to Web Browsing (HTTP) tests");

register_preference(array(PREF_HTTP), PREF_YMAX, array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Maximum y-axis value", "", PREF_TYPE_INPUT, array(), 3);

/* Register Display Preferences */
register_preference(array(PREF_GLOBAL), HTTP_PREF_DISPLAY_LATENCY,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display HTTP Latency Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_GLOBAL), HTTP_PREF_DISPLAY_BANDWIDTH,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display HTTP Bandwidth Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));

/* Register Available Display Objects */
register_display_object("http-latency", "Web Browsing (HTTP)",
                        "Page Fetch Time", 20, HTTP_DATA, "*", "http_avail",
                        "drawGraph", "http_get_latency_ds", "http_get_base_ds",
                        HTTP_PREF_DISPLAY_LATENCY, PREF_HTTP, array(),
                        array("ymax"=>array("y-axis max.",3,PREF_YMAX)), -1,
                        -1, "",  "ms", "", "y", TRUE,
                        "POINT_TYPE cross\nPOINT_SIZE 2\nWITH_GAP true\n" .
                        "X_GAP_THRESHOLD 30\n", TRUE);
register_display_object("http-bandwidth", "Web Browsing (HTTP)",
                        "Page Fetch Bandwidth", 21, HTTP_DATA, "*",
                        "http_avail", "drawGraph", "http_get_bandwidth_ds",
                        "http_get_base_ds", HTTP_PREF_DISPLAY_BANDWIDTH,
                        PREF_HTTP, array(),
                        array("ymax"=>array("y-axis max.",3,PREF_YMAX)), -1,
                        -1, "", "KByte/s", "", "y", TRUE,
                        "POINT_TYPE cross\nPOINT_SIZE 2\nWITH_GAP true\n" .
                        "X_GAP_THRESHOLD 30\n", TRUE);

/* Register Test Helper Functions */
$test_names[HTTP_DATA] = "HTTP (Web Browsing)";
$subtype_name_funcs[HTTP_DATA] = "";
$raw_data_funcs[HTTP_DATA] = "http_format_raw";

/** Data Available Function
 *
 * Return appropriate display item(s) if HTTP data is available
 */
function http_avail($object_name, $src, $dst, $startTimeSec)
{
  if ( !checkDataExistsForTest($src, $dst, HTTP_DATA) )
    return array();

  /* HTTP data exists - register display item */
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

/* HTTP Base Dataset */
function http_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  global $timeZone, $dataSets;

  /* Checked for cached data */
  if ( $dataSets != array() ) {
    if ( $dataSets["startTimeSec"] == $startTimeSec &&
        $dataSets["secOfData"] == $secOfData ) {
      return $dataSets;
    } else {
      $dataSets = array();
    }
  }

  /* Open Database Connection */
  $res = ampOpenDb(HTTP_DATA, 0, $src, $dst, $startTimeSec, 0, $timeZone);
  if ( ! $res ) {
    return array();
  }
  $info = ampInfoObj($res);

  /* Fetch Data */
  $sample = 0;
  while ( ($obj = ampNextObj($res)) && $obj->secInPeriod<$secOfData ) {
    $plotData[$sample]->secInPeriod  = $obj->secInPeriod;
    $plotData[$sample]->latency = $obj->latency;
    $plotData[$sample]->size    = $obj->size;
    $sample++;
  }
  $numSamples = $sample;

  /* Cache data in case of further look ups */
  $dataSets["plotData"] = $plotData;
  $dataSets["numSamples"] = $numSamples;
  $dataSets["info"] = $info;

  return $dataSets;

}

function http_get_latency_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  /* Get the base dataset for the subtype */
  $ds = http_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize);
  if ( $ds == array() ) {
    return array();
  }

  $plotData = $ds["plotData"];
  $numSamples = $ds["numSamples"];

  /* Setup the dataset parameter */
  $dataSets = array();
  $dataSet["color"] = "red";
  $dataSet["key"] = "http latency";
  $dataSet["info"] = $ds["info"];
  $gdata = array();
  $total = 0;
  for ( $sample=0; $sample<$numSamples; ++$sample ) {
    $gdata[$sample]["x"] = $plotData[$sample]->secInPeriod;
    $gdata[$sample]["y"] = $plotData[$sample]->latency;
  }
  $dataSet["data"] = $gdata;
  $dataSets[] = $dataSet;

  return $dataSets;

}

function http_get_bandwidth_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  /* Get the base dataset for the subtype */
  $ds = http_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize);
  if ( $ds == array() ) {
    return array();
  }

  $plotData = $ds["plotData"];
  $numSamples = $ds["numSamples"];

  /* Setup the dataset parameter */
  $dataSets = array();
  $dataSet["color"] = "blue";
  $dataSet["key"] = "http bandwidth";
  $dataSet["info"] = $ds["info"];
  $gdata = array();
  for ($sample=0; $sample<$numSamples; ++$sample) {
    if ($plotData[$sample]->latency == 0) {
      continue;
    }
    $gdata[$sample]["x"] = $plotData[$sample]->secInPeriod;
    $bw = (int)(($plotData[$sample]->size /
      $plotData[$sample]->latency * 1000)/1024);
    $gdata[$sample]["y"] = $bw;
  }
  $dataSet["data"] = $gdata;
  $dataSets[] = $dataSet;

  return $dataSets;

}

/* Formats a line of raw HTTP data */
function http_format_raw($subType, $obj)
{

  /* Handle request for a header line */
  if ($obj == NULL) {
    return "latency_ms,size_bytes";
  }

  return sprintf("%d,%s", $obj->latency, $obj->size);

}

// Emacs control
// Local Variables:
// eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
