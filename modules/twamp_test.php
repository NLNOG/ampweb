<?php
/*
 * AMP Data Display Interface
 *
 * TWAMP Test Display Module
 *
 * Author:      Brendon Jones <bcj3@cs.waikato.ac.nz>
 *
 * This module provides the TWAMP latency, jitter, loss and 
 * RTT Distribution graphs.
 * It is quite simplistic at the moment and lacking in the statistical
 * analysis that some of the other tests do.
 *
 */
$dataSets = array();

/* Define Preference Stuff */
define('PREF_TWAMP', "twamp");

define('TWAMP_PREF_DISPLAY_LATENCY', "display-latency");
define('TWAMP_PREF_DISPLAY_JITTER', "display-jitter");
define('TWAMP_PREF_DISPLAY_LOSS', "display-loss");
//define('TWAMP_PREF_DISPLAY_RTTSIZE', "display-rtt-packetsize");
//define('TWAMP_PREF_DISPLAY_LOSSCDF', "display-losscdf");
define('TWAMP_PREF_DISPLAY_RTTDIST', "display-rttdist");

/* Register Preference Module*/
register_module(PREF_TWAMP, "TWAMP Graphs", "These preferences control the " .
  "display of graphs relating to the TWAMP tests such as Latency, Jitter, " .
  "Loss, etc.");

/* Register Preferences Relating To Graph Options */
register_preference(array(PREF_TWAMP), PREF_BINSIZE,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Bin Size", "",
                    PREF_TYPE_INPUT,
                    array(PREF_LONGTERM=>35, PREF_SHORTTERM=>5), 3);
register_preference(array(PREF_TWAMP), PREF_YMAX, 
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Maximum y-axis Value", "", PREF_TYPE_INPUT,
                    array(PREF_LONGTERM=>-1,PREF_SHORTTERM=>-1), 3);
register_preference(array(PREF_TWAMP), PREF_LINES,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display as Line Graph", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));

register_preference(array(PREF_TWAMP), PREF_DISPLAY_MAX,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Maximum", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));
register_preference(array(PREF_TWAMP), PREF_DISPLAY_MIN,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Minimum", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));
register_preference(array(PREF_TWAMP), PREF_DISPLAY_MEAN,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Mean", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_TWAMP), PREF_DISPLAY_MEDIAN,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Median", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));
register_preference(array(PREF_TWAMP), PREF_DISPLAY_STDDEV,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Std. Dev.", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));
register_preference(array(PREF_TWAMP), PREF_DISPLAY_SUMMARY,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Summary", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));

/* Register Preferences Relating To Graph Display - one preference for
 * each type of graph that we register below... */
register_preference(array(PREF_GLOBAL), TWAMP_PREF_DISPLAY_LATENCY,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display TWAMP Latency Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_GLOBAL), TWAMP_PREF_DISPLAY_JITTER,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display TWAMP Jitter Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_GLOBAL), TWAMP_PREF_DISPLAY_LOSS,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display TWAMP Loss Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_TRUE));
/*                          
register_preference(array(PREF_GLOBAL), TWAMP_PREF_DISPLAY_RTTSIZE,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display TWAMP RTT vs Packet Size Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_TRUE));
                          */
register_preference(array(PREF_GLOBAL), TWAMP_PREF_DISPLAY_RTTDIST,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display TWAMP RTT Distribution CDF", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_TRUE));
                          /*
register_preference(array(PREF_GLOBAL), TWAMP_PREF_DISPLAY_LOSSCDF,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display TWAMP Loss CDF Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                    PREF_SHORTTERM=>PREF_TRUE));
*/

/** Register Display Objects **/
/* Latency */
register_display_object("twamp-latency", "UDP TWAMP Latency", 
                        "ping packets", 80,
                        TWAMP_DATA, "*", "twamp_size_avail", "drawGraph",
                        "twamp_get_latency_ds", "twamp_get_base_ds",
                        TWAMP_PREF_DISPLAY_LATENCY, PREF_TWAMP,
                        array("max"=>array("Max",PREF_DISPLAY_MAX),
                        "min"=>array("Min",PREF_DISPLAY_MIN),
                        "mean"=>array("Mean",PREF_DISPLAY_MEAN),
                        "median"=>array("Median",PREF_DISPLAY_MEDIAN),
                        "stddev"=>array("Std. Dev.",PREF_DISPLAY_STDDEV),
                        "summary"=>array("Summary",PREF_DISPLAY_SUMMARY)),
                        array("ymax"=>array("y-axis max.",3,PREF_YMAX)), -1,
                        -1, "", "Latency (ms)", "", "", TRUE, "", TRUE);

/* Jitter */
register_display_object("twamp-jitter", "UDP TWAMP Jitter", 
                        "ping packets", 81, TWAMP_DATA,
                        "*", "twamp_size_avail", "drawGraph",
                        "twamp_get_jitter_ds", "twamp_get_base_ds",
                        TWAMP_PREF_DISPLAY_JITTER, PREF_TWAMP,
                        array("summary"=>array("Summary",PREF_DISPLAY_SUMMARY)),
                        array("ymax"=>array("y-axis max.",3,PREF_YMAX)), -1,
                        -1, "", "Jitter (ms)", "", "", TRUE, "", TRUE);

/* Loss */
register_display_object("twamp-loss", "UDP TWAMP Loss", 
                        "ping packets", 82, TWAMP_DATA, "*",
                        "twamp_size_avail", "drawGraph", "twamp_get_loss_ds",
                        "twamp_get_base_ds", TWAMP_PREF_DISPLAY_LOSS, 
                        PREF_TWAMP, array("summary"=>
                              array("Summary", PREF_DISPLAY_SUMMARY)),
                        array(), -1, 100, "", "loss percent", "", "", TRUE,
                        "", TRUE);

/* RTT vs Packet Size */
/*
register_display_object("twamp-rttsize", "Latency", "Packet Size vs RTT", 4,
                        TWAMP_DATA, "rand", "twamp_rand_avail", "drawGraph",
                        "twamp_get_rttsize_ds", "twamp_get_rand_ds",
                        TWAMP_PREF_DISPLAY_RTTSIZE, PREF_TWAMP,
                        array("summary"=>
                              array("Summary", PREF_DISPLAY_SUMMARY)),
                        array("ymax"=>array("y-axis max.", 3, TWAMP_PREF_YMAX)),
                        1500, -1, "Packet Size", "Latency (ms)", "number",
                        "n", FALSE, "", TRUE);
*/
/* Packet Size Loss CDF */
/*
register_display_object("twamp-losscdf", "Loss", "Packet Size vs Loss CDF", 5,
                        TWAMP_DATA, "rand", "twamp_rand_avail", "drawGraph",
                        "twamp_get_losscdf_ds", "twamp_get_rand_ds",
                        TWAMP_PREF_DISPLAY_LOSSCDF, PREF_TWAMP,
                        array("summary"=>
                              array("Summary", PREF_DISPLAY_SUMMARY)),
                        array(), 1500, 100, "Packet Size",
                        "Percentage of Loss", "number", "y", FALSE, "", FALSE);
*/
/* RTT Distribution */
register_display_object("twamp-rttdist", "UDP TWAMP Latency", 
                        "RTT Distribution", 83,
                        TWAMP_DATA, "*", "twamp_size_avail", "drawGraph",
                        "twamp_get_rttdist_ds", "twamp_get_base_ds",
                        TWAMP_PREF_DISPLAY_RTTDIST, PREF_TWAMP,
                        array("summary"=>
                              array("Summary", PREF_DISPLAY_SUMMARY)),
                        array(), -1, 100, "RTT Time(ms)", "Percentage",
                        "number", "y", FALSE, "POINT_SIZE 1\n", FALSE);

/* Register Test Helper Functions */
$test_names[TWAMP_DATA] = "TWAMP";
$subtype_name_funcs[TWAMP_DATA] = "twamp_subtype_name";
$raw_data_funcs[TWAMP_DATA] = "twamp_format_raw";

/** Data Available Functions
 *
 * These functions check if the appropriate data is available for the
 * specified source and destination. And if so, return a list of display
 * items that can be displayed.
 *
 */
function twamp_size_avail($object_name, $src, $dst, $startTimeSec)
{
  if ( !checkDataExistsForTest($src, $dst, TWAMP_DATA) )
    return array();

  $stList = ampSubtypeList(TWAMP_DATA, $src, $dst);

  /* Loop through returned subtypes (packet sizes), create item for each */
  $items = array();
  foreach ( $stList->subtypes as $idx=>$size ) {
    $sized = rtrim($size, "B");
  
    /* register display item */
    $object = get_display_object($object_name);
    $item = new display_item_t();
    $item->category = $object->category;
    $item->name = $sized . "b-" . $object->name;
    $item->title = " $sized byte " . $object->title;
    $item->displayObject = $object->name;
    $item->subType = $size;

    $items[$item->name] = $item;
  }

  return $items;

}

/** Data Retrieval Functions **/

/* Size Dataset */
function twamp_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  global $timeZone, $dataSets;

  /* Checked for cached data */
  if (array_key_exists($subType, $dataSets)) {
    if ($dataSets[$subType]["startTimeSec"] == $startTimeSec &&
        $dataSets[$subType]["secOfData"] == $secOfData &&
        $dataSets[$subType]["src"] == $src &&
        $dataSets[$subType]["dst"] == $dst) {
      return $dataSets[$subType];
    } else {
      unset($dataSets[$subType]);
    }
  }

  $res = getBinnedDataSet($src, $dst, TWAMP_DATA, $subType, $startTimeSec,
    $timeZone, $secOfData, $binSize, "y", "y", "y", $bins, $numBins,
    $plotStats, $info);

  if ($res==-1|| $numBins<=0) {
    /* No data available for this test... */
    return array();
  }

  /* Cache the data to save extra lookups */
  if (!array_key_exists($subType, $dataSets)) {
    $dataSets[$subType] = array();
    $dataSets[$subType]["bins"] = $bins;
    $dataSets[$subType]["numBins"] = $numBins;
    $dataSets[$subType]["plotStats"] = $plotStats;
    $dataSets[$subType]["res"] = $res;
    $dataSets[$subType]["info"] = $info;
    $dataSets[$subType]["startTimeSec"] = $startTimeSec;
    $dataSets[$subType]["secOfData"] = $secOfData;
    $dataSets[$subType]["src"] = $src;
    $dataSets[$subType]["dst"] = $dst;
  }

  return $dataSets[$subType];

}

function twamp_get_rttdist_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{
  /* Get the base dataset for the subtype */
  $ds = twamp_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
          $binSize);
  if ( $ds == array() ) {
    return array();
  }

  $plotStats = $ds["plotStats"];

  /* Return an array of datasets suitable for passing to tsDrawGraph */
  $dataSets = array();
  $dataSet["color"] = "";
  $dataSet["key"] = "";
  $dataSet["info"] = $ds["info"];
  $gdata = array();
  $total = 0;
  $totPercent = 0;
  for ($count = 0; $count < $plotStats{'maxValue'}; ++$count) {
    if ( $totPercent > 99.5) {
      break;
    }
    if ( isset($plotStats{'ValueDist'}[$count]) ) {
      $rtt = $plotStats{'ValueDist'}[$count];
      $total = $plotStats{'packetTotal'};
      $percent = $rtt / $total;
      $totPercent += $percent * 100;
    }
    $gdata[$count]["x"] = $count;
    $gdata[$count]["y"] = $totPercent;
  }
  $dataSet["data"] = $gdata;
  $dataSets[] = $dataSet;

  return $dataSets;

}

function twamp_get_size_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize, $components)
{

  /* Get the base dataset for the subtype */
  $ds = twamp_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
          $binSize);

  if ( $ds == array() ) {
    return array();
  }

  $bins = $ds["bins"];
  $numBins = $ds["numBins"];

  /* Return an array of datasets suitable for passing to tsDrawGraph */
  $dataSets = array();
  $c=0;
  foreach ( $components as $dataSet ) {

    $dataSets[$c]["color"] = $GLOBALS["dataSetColour"][$dataSet];
    $dataSets[$c]["key"] = $dataSet;
    $dataSets[$c]["info"] = $ds["info"];
    $data = array();
    $cc=0;
    for ( $bin = 0; $bin<$numBins; ++$bin ) {
      $time = $bins[$bin]['time'];
      $val = $bins[$bin][$dataSet];
      $data[$cc]["x"] = $time;
      $data[$cc]["y"] = $val;
      $cc++;
    }
    $dataSets[$c]["data"] = $data;

    $c++;

  }

  return $dataSets;

}

/* Latency */
function twamp_get_latency_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize, $components=array())
{

  if ( $components == array() ) {
    $components = array('mean');
  }

  return twamp_get_size_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize, $components);

}

/* Jitter */
function twamp_get_jitter_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize, $components=array())
{
  if ( $components == array() ) {
    $components = array('jitter');
  }

  return twamp_get_size_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize, $components);

}

/* Loss */
function twamp_get_loss_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize, $components=array())
{

  if ( $components == array() ) {
    $components = array('loss');
  }

  return twamp_get_size_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize, $components);

}




/* Parse an TWAMP subtype and return a legible name for it */
function twamp_subtype_name($subtype)
{
    $sized = rtrim($subtype, "B");

    return $sized . " byte Packets";

}

/* Formats a line of raw TWAMP data */
function twamp_format_raw($subType, $obj)
{

  /* Handle request for a header line */
  if ($obj == NULL) {
    return "packet_size_bytes,rtt_ms";
  }

  $content = $obj->data2 . ",";
  if ($obj->data == -1 || $obj->error==1) {
    $content .= "loss";
  } else {
    $content .= $obj->data;
  }

  return $content;

}

  // Emacs control
  // Local Variables:
  // eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
