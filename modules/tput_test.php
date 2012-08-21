<?php
/*
 * AMP Data Display Interface
 *
 * Throughput (tput) Test Display Module
 *
 * If TPUT_PREF_MERGED_GRAPHS is set, The data collection functions do some
 * funky massaging to try and generate graphs with upstream / downstream
 * throughput on the same graph if they are available.
 *
 * Author:      Matt Brown <matt@crc.net.nz.
 * Version:     $Id: tput_test.php 2103 2011-04-12 23:59:22Z brendonj $
 *
 * This module provides the throughput graphs
 *
 */

/* Define Preference Stuff */
define('PREF_TPUT', "tput");

define('TPUT_PREF_DISPLAY_TCP', "display-tcp");
define('TPUT_PREF_MERGED_GRAPHS', "merge-graphs");

/* Register Preferences */
register_module(PREF_TPUT, "Throughput Graphs", "These preferences " .
                "control the display of graphs relating to the throughput " .
                "tests.");

register_preference(array(PREF_TPUT), PREF_YMAX, array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Maximum y-axis Value", "", PREF_TYPE_INPUT, array(), 3);

/* Register Display Preferences */
register_preference(array(PREF_GLOBAL), TPUT_PREF_DISPLAY_TCP,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display TCP Tput Graph", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                        PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_TPUT), TPUT_PREF_MERGED_GRAPHS, array(PREF_GLOBAL),
                    "Merge Upstream / Downstream Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_GLOBAL=>PREF_TRUE));

/* Register Available Display Objects */
register_display_object("tput", "Throughput", "TCP Tput", 11, TPUT_DATA, "*",
                        "tput_avail", "drawGraph", "tput_get_ds",
                        "tput_get_base_ds", TPUT_PREF_DISPLAY_TCP, PREF_TPUT,
                        array("summary"=>array("Summary",PREF_DISPLAY_SUMMARY)),
                        array("ymax"=>array("y-axis max.",3,PREF_YMAX)), -1,
                        -1, "", "Kbit/s", "", "y", TRUE,
                        "POINT_TYPE cross\nPOINT_SIZE 3\nWITH_GAP true\n" .
                        "X_GAP_THRESHOLD 30\n", TRUE);

/* Register Test Helper Functions */
$test_names[TPUT_DATA] = "Throughput";
$subtype_name_funcs[TPUT_DATA] = "tput_subtype_name";
$raw_data_funcs[TPUT_DATA] = "tput_format_raw";

/** Data Available Function
 *
 * Return appropriate display item(s) if Tput data is available
 */
function tput_avail($object_name, $src, $dst, $startTimeSec)
{
  if ( !checkDataExistsForTest($src, $dst, TPUT_DATA) )
    return array();

  $bytes = array();
  $durations = array();
  $items = array();
  $merge = get_preference(PREF_TPUT, TPUT_PREF_MERGED_GRAPHS, PREF_GLOBAL);
  $merge = ($merge == PREF_TRUE);

  $object = get_display_object($object_name);

  /* Tput data exists - get subtypes */
  $subtypes = ampsubtypelist(TPUT_DATA, $src, $dst);

  foreach ( $subtypes->subtypes as $idx=>$subtype ) {
    /* Parse the subtype */
    $tt = substr($subtype, 0, 1);
    $param = substr($subtype, 1);
    if ( strtolower($tt) == "t" ) {
      /* Check for merging */
      if ( in_array($param, $durations) && $merge ) {
        continue;
      }
      $durations[] = $param;
      $title = $param/1000 . "sec ";
    } else if ( strtolower($tt) == "s" ) {
      /* Check for merging */
      if ( in_array($param, $bytes) && $merge ) {
        continue;
      }
      $bytes[] = $param;
      $title = $param . "byte ";
    } else {
      continue;
    }
    if ( ! $merge ) {
      if ($tt=='T' || $tt=='S') {
        $title = "Outgoing $title";
      } else {
        $title = "Incoming $title";
      }
    }

    /* Create the display item */
    $item = new display_item_t();
    $item->category = $object->category;
    $item->name = $object->name . "-$tt-$param";
    $item->title = $title. " TCP";
    $item->displayObject = $object->name;
    $item->subType = $subtype;
    $items[$item->name] = $item;
  }

  return $items;

}

/** Data Retrieval Functions **/

/* Tput Base Dataset */
function tput_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  global $timeZone;
  $plotStats{'total'} = 0;
  $plotStats{'loss'} = 0;
  $plotStats{'squaresSum'} = 0;
  $plotStats{'max'} = 0;
  $plotStats{'min'} = 999999999;
  $plotStats{'count'} = 0;
  $rates = Array();

  /* Open a connection to the amp database */
  $res = ampOpenDb(TPUT_DATA, $subType, $src, $dst, $startTimeSec, 0,
  $timeZone);
  if (!$res) {
    return array();
  }
  $info = ampInfoObj($res);

  /* Extract Data */
  $sample = 0;
  while (($obj = ampNextObj($res)) && $obj->secInPeriod<$secOfData) {
    $plotData[$sample]->secInPeriod  = $obj->secInPeriod;
    $plotData[$sample]->bytes   = $obj->data;
    $plotData[$sample]->fetchTime = $obj->data2;
    $sample++;
  
    // skip measurements that aren't valid (loss)
    if($obj->data > 0 && $obj->data2 > 0) {
      // data is stored as total bytes over a time period
      // so to get a rate in kbps we need to do some maths
      $value = (($obj->data*8)/1000)/($obj->data2/1000);
      if($plotStats{'max'} < $value)
        $plotStats{'max'} = $value;
      if($plotStats{'min'} > $value)
        $plotStats{'min'} = $value;
      $plotStats{'total'} += $value;
      $plotStats{'squaresSum'} += ($value * $value);
      $plotStats{'count'}++;
      $rates[] = $value;
    } else {
      $plotStats{'loss'}++;
    }
  }
  // total samples good + bad
  $numSamples = $sample;

  if ($plotStats{'count'} == 0) {
    // if there are no samples, the median is zero
    $plotStats{'median'} = 0;

  } else if ($plotStats{'count'} == 1) {
    // if there is one sample, it is the median
    $plotStats{'median'} = $rates[0];

  } else if (($plotStats{'count'} % 2) == 0) {
    // if there is an even number of samples, then we want the average
    // of the middle two samples
    sort($rates);
    $v1 = $rates[$plotStats{'count'}/2];
    $v2 = $rates[($plotStats{'count'}/2)+1];
    $plotStats{'median'} = ($v1 + $v2) / 2;

  } else {
    // otherwise there is an odd number, so we can just take the 
    // very middle value
    sort($rates);
    $plotStats{'median'} = $rates[ ((int)($plotStats{'count'}/2)) + 1 ];
  }

  /* Return Data */
  return array("plotData"=>$plotData,"numSamples"=>$numSamples,
               "info"=>$info, "plotStats"=>$plotStats);

}

/* Tput Dataset */
function tput_get_ds($src, $dst, $subType, $startTimeSec, $secOfData,
$binSize, $recurse=1)
{

  $merge = get_preference(PREF_TPUT, TPUT_PREF_MERGED_GRAPHS, PREF_GLOBAL);
  $merge = ($merge == PREF_TRUE);

  /* Get the base dataset for the subtype */
  $ds = tput_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
      $binSize);

  if ( $ds == array() || $ds["plotData"] == NULL) {
    return array();
  }

  $plotData = $ds["plotData"];
  $numSamples = $ds["numSamples"];
  $info = $ds["info"];
  $plotStats = $ds["plotStats"];

  /* Setup the dataset parameter */
  $tt = substr($info->dataSubtype, 0, 1);
  $param = substr($info->dataSubtype, 1);
  $ds = array();
  if ( $tt == 'T' || $tt == 'S' ) {
    $ds["color"] = "blue";
    $ds["key"] = "outgoing";
    $ds["info"] = $info;
    $ds["plotStats"] = $plotStats;
    $ot = strtolower($tt);
  } else if ( $tt == 't' || $tt == 's' ) {
    $ds["color"] = "red";
    $ds["key"] = "incoming";
    $ds["info"] = $info;
    $ds["plotStats_reverse"] = $plotStats;
    $ot = strtoupper($tt);
  }
  /* Get the data */
  $data = array();
  for ( $sample = 0; $sample<$numSamples; ++$sample ) {
    $sec = $plotData[$sample]->secInPeriod;
    $bytes  = $plotData[$sample]->bytes;
    $fetchTime = $plotData[$sample]->fetchTime;
    if ( $bytes != 0 && $fetchTime != 0 ) {
      $data[$sample]["x"] = $sec;
      $data[$sample]["y"] = (($bytes*8)/1000)/($fetchTime/1000);
    }
  }
  $ds["data"] = $data;

  $dataSets = array($ds);

  /* Handle a merged graph */
  if  ($merge && $recurse ) {
    $ds2 = tput_get_ds($src, $dst, "$ot$param", $startTimeSec, $secOfData,
      $binSize, 0);
    $dataSets = array_merge($dataSets, $ds2);
  }
  return $dataSets;

}

/* Parse an tput subtype and return a legible name for it */
function tput_subtype_name($subtype)
{

  $tt = substr($subtype, 0, 1);
  $param = substr($subtype, 1);

  if ($tt == 'T' || $tt == 'S') {
    $dir = "Outgoing";
  } else {
    $dir = "Incoming";
  }

  if ($tt == 'T'  || $tt == 't') {
    $sec = $param/1000;
    return "${sec}sec $dir";
  } else if ($tt == 'S' || $tt == 's') {
    return "${param}bytes $dir";
  }

  return $subtype;

}

/* Formats a line of raw TPUT data */
function tput_format_raw($subType, $obj)
{

  /* Handle request for a header line */
  if ($obj == NULL) {
    return "bytes,transfer_time_ms";
  }

  return sprintf("%s,%s", $obj->data, $obj->data2);

}


// Emacs control
// Local Variables:
// eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
