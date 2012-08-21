<?php
/*
 * AMP Data Display Interface
 *
 * ICMP Test Display Module
 *
 * Author:      Tony McGregor <tonym@cs.waikato.ac.nz>
 * Author:      Matt Brown <matt@crc.net.nz.
 * Version:     $Id: icmp_test.php 2159 2012-02-21 22:47:12Z brendonj $
 *
 * This module provides the ICMP latency, jitter, loss, RTT vs packet size,
 * loss CDF, and RTT Distribution graphs
 *
 */
$dataSets = array();

/* Define Preference Stuff */
define('PREF_ICMP', "icmp");

define('ICMP_PREF_DISPLAY_LATENCY', "display-latency");
define('ICMP_PREF_DISPLAY_JITTER', "display-jitter");
define('ICMP_PREF_DISPLAY_LOSS', "display-loss");
define('ICMP_PREF_DISPLAY_RTTSIZE', "display-rtt-packetsize");
define('ICMP_PREF_DISPLAY_LOSSCDF', "display-losscdf");
define('ICMP_PREF_DISPLAY_RTTDIST', "display-rttdist");

/* Register Preference Module*/
register_module(PREF_ICMP, "ICMP Graphs", "These preferences control the " .
  "display of graphs relating to the ICMP tests such as Latency, Jitter, " .
  "Loss, etc.");

/* Register Preferences Relating To Graph Options */
register_preference(array(PREF_ICMP), PREF_BINSIZE,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Bin Size", "",
                    PREF_TYPE_INPUT,
                    array(PREF_LONGTERM=>35, PREF_SHORTTERM=>5), 3);
register_preference(array(PREF_ICMP), PREF_YMAX, array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Maximum y-axis Value", "", PREF_TYPE_INPUT,
                    array(PREF_LONGTERM=>-1,PREF_SHORTTERM=>-1), 3);
register_preference(array(PREF_ICMP), PREF_LINES,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display as Line Graph", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));

register_preference(array(PREF_ICMP), PREF_DISPLAY_MAX,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Maximum", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));
register_preference(array(PREF_ICMP), PREF_DISPLAY_MIN,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Minimum", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));
register_preference(array(PREF_ICMP), PREF_DISPLAY_MEAN,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Mean", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_ICMP), PREF_DISPLAY_MEDIAN,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Median", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));
register_preference(array(PREF_ICMP), PREF_DISPLAY_STDDEV,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Std. Dev.", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));
register_preference(array(PREF_ICMP), PREF_DISPLAY_SUMMARY,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Summary", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));

/* Register Preferences Relating To Graph Display - one preference for
 * each type of graph that we register below... */
register_preference(array(PREF_GLOBAL), ICMP_PREF_DISPLAY_LATENCY,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display ICMP Latency Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_GLOBAL), ICMP_PREF_DISPLAY_JITTER,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display ICMP Jitter Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_GLOBAL), ICMP_PREF_DISPLAY_LOSS,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display ICMP Loss Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_GLOBAL), ICMP_PREF_DISPLAY_RTTSIZE,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display ICMP RTT vs Packet Size Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_GLOBAL), ICMP_PREF_DISPLAY_RTTDIST,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display ICMP RTT Distribution CDF", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_GLOBAL), ICMP_PREF_DISPLAY_LOSSCDF,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display ICMP Loss CDF Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                    PREF_SHORTTERM=>PREF_TRUE));


/** Register Display Objects **/
/* Latency */
register_display_object("icmp-latency", "Latency", "ping packets", 1,
                        ICMP_DATA, "*", "icmp_size_avail", "drawNewGraphs",
                        "icmp_get_latency_ds", "icmp_get_base_ds",
                        ICMP_PREF_DISPLAY_LATENCY, PREF_ICMP,
                        array(//"max"=>array("Max",PREF_DISPLAY_MAX),
                        //"min"=>array("Min",PREF_DISPLAY_MIN),
                        //"mean"=>array("Mean",PREF_DISPLAY_MEAN),
                        //"median"=>array("Median",PREF_DISPLAY_MEDIAN),
                        //"stddev"=>array("Std. Dev.",PREF_DISPLAY_STDDEV),
                        "summary"=>array("Summary",PREF_DISPLAY_SUMMARY)),
                        array(/*"ymax"=>array("y-axis max.",3,PREF_YMAX)*/), -1,
                        -1, "", "Latency (ms)", "", "", TRUE, "", TRUE);

/* Jitter */
register_display_object("icmp-jitter", "Jitter", "ping packets", 2, ICMP_DATA,
                        "*", "icmp_size_avail", "drawNewGraphs",
                        "icmp_get_jitter_ds", "icmp_get_base_ds",
                        ICMP_PREF_DISPLAY_JITTER, PREF_ICMP,
                        array("summary"=>array("Summary",PREF_DISPLAY_SUMMARY)),
                        array("ymax"=>array("y-axis max.",3,PREF_YMAX)), -1,
                        -1, "", "Jitter (ms)", "", "", TRUE, "", TRUE);

/* Loss */
register_display_object("icmp-loss", "Loss", "ping packets", 3, ICMP_DATA, "*",
                        "icmp_size_avail", "drawNewGraphs", "icmp_get_loss_ds",
                        "icmp_get_base_ds", ICMP_PREF_DISPLAY_LOSS, PREF_ICMP,
                        array("summary"=>
                              array("Summary", PREF_DISPLAY_SUMMARY)),
                        array(), -1, 100, "", "loss percent", "", "", TRUE,
                        "", TRUE);

/* RTT vs Packet Size */
register_display_object("icmp-rttsize", "Latency", "Packet Size vs RTT", 4,
                        ICMP_DATA, "rand", "icmp_rand_avail", "drawNewGraphs",
                        "icmp_get_rttsize_ds", "icmp_get_rand_ds",
                        ICMP_PREF_DISPLAY_RTTSIZE, PREF_ICMP,
                        array("summary"=>
                              array("Summary", PREF_DISPLAY_SUMMARY)),
                        array("ymax"=>array("y-axis max.", 3, PREF_YMAX)),
                        1500, -1, "Packet Size", "Latency (ms)", "number",
                        "n", FALSE, "", TRUE);

/* Packet Size Loss CDF */
register_display_object("icmp-losscdf", "Loss", "Packet Size vs Loss CDF", 5,
                        ICMP_DATA, "rand", "icmp_rand_avail", "drawNewGraphs",
                        "icmp_get_losscdf_ds", "icmp_get_rand_ds",
                        ICMP_PREF_DISPLAY_LOSSCDF, PREF_ICMP,
                        array("summary"=>
                              array("Summary", PREF_DISPLAY_SUMMARY)),
                        array(), 1500, 100, "Packet Size",
                        "Percentage of Loss", "number", "y", FALSE, "", FALSE);

/* RTT Distribution */
register_display_object("icmp-rttdist", "Latency", "RTT Distribution", 6,
                        ICMP_DATA, "*", "icmp_size_avail", "drawNewGraphs",
                        "icmp_get_rttdist_ds", "icmp_get_base_ds",
                        ICMP_PREF_DISPLAY_RTTDIST, PREF_ICMP,
                        array("summary"=>
                              array("Summary", PREF_DISPLAY_SUMMARY)),
                        array(), -1, 100, "RTT Time(ms)", "Percentage",
                        "number", "y", FALSE, "POINT_SIZE 1\n", FALSE);

/* Register Test Helper Functions */
$test_names[ICMP_DATA] = "ICMP";
$subtype_name_funcs[ICMP_DATA] = "icmp_subtype_name";
$raw_data_funcs[ICMP_DATA] = "icmp_format_raw";

/** Data Available Functions
 *
 * These functions check if the appropriate data is available for the
 * specified source and destination. And if so, return a list of display
 * items that can be displayed.
 *
 */
function icmp_rand_avail($object_name, $src, $dst, $startTimeSec)
{
  if ( !checkDataExistsForTest($src, $dst, ICMP_DATA) )
    return array();

  $stList = ampSubtypeList(ICMP_DATA, $src, $dst);
  if ( is_object($stList) && property_exists($stList, "subtypes") && 
        in_array("rand", $stList->subtypes) ) {
    /* Random ICMP data exists - register display item */
    $object = get_display_object($object_name);
    $item = new display_item_t();
    $item->category = $object->category;
    $item->name = $object->name;
    $item->title = $object->title;
    $item->displayObject = $object->name;
    $item->subType = "rand";

    return array($item->name=>$item);
  }

  return array();

}
function icmp_size_avail($object_name, $src, $dst, $startTimeSec)
{
  if ( !checkDataExistsForTest($src, $dst, ICMP_DATA) )
    return array();

  $stList = ampSubtypeList(ICMP_DATA, $src, $dst);

  /* Loop through returned subtypes (packet sizes), create item for each */
  $items = array();
  foreach ( $stList->subtypes as $idx=>$size ) {
    /* Skip random data */
    if ( $size == "rand" ) {
      continue;
    }
    /* register display item */
    $sized = ltrim($size, " 0");
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
function icmp_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
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

  $res = getBinnedDataSet($src, $dst, ICMP_DATA, $subType, $startTimeSec,
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

function icmp_get_rttdist_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{
  /* Get the base dataset for the subtype */
  $ds = icmp_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
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

function icmp_get_size_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize, $components)
{

  /* Get the base dataset for the subtype */
  $ds = icmp_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
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
function icmp_get_latency_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize, $components=array())
{

  if ( $components == array() ) {
    $components = array('mean');
  }

  return icmp_get_size_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize, $components);

}

/* Jitter */
function icmp_get_jitter_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize, $components=array())
{
  if ( $components == array() ) {
    $components = array('jitter');
  }

  return icmp_get_size_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize, $components);

}

/* Loss */
function icmp_get_loss_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize, $components=array())
{

  if ( $components == array() ) {
    $components = array('loss');
  }

  return icmp_get_size_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize, $components);

}

/* Random Size */
function icmp_get_rand_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  global $timeZone, $dataSets;

  $maxLossPacketSize = 0;

  /* Checked for cached data */
  if ( array_key_exists($subType, $dataSets)) {
    if ( $dataSets[$subType]["startTimeSec"] == $startTimeSec &&
        $dataSets[$subType]["secOfData"] == $secOfData &&
        $dataSets[$subType]["src"] == $src &&
        $dataSets[$subType]["dst"] == $dst ) {
      return $dataSets[$subType];
    } else {
      unset($dataSets[$subType]);
    }
  }

  /* Retrieve a raw dataset containing the desired information */
  $res = getRawDataSet($src, $dst, ICMP_DATA, $subType, $startTimeSec,
  $timeZone, $secOfData, "y", $data, $numSamples, $plotStats);
  if ( $res == -1 || $numSamples <= 0 ) {
    /* No data available for this test... */
    return array();
  }
  $info = ampInfoObj($res);

  /* Loop through returned dataset */
  for ( $i=0; $i<$numSamples; $i++ ) {
    $rtt = $data[$i]["data"];
    /* Skip non loss samples */
    if ( $rtt != -1 ) {
      continue;
    }
    /* Calculate loss dist and max lost packet size */
    $lossDist[$data[$i]["data2"]]++;
    if ( $data[$i]["data2"] > $maxLossPacketSize ) {
      $maxLossPacketSize = $data[$i]["data2"];
    }
  }

  /* Cache the data to save extra lookups */
  if  (! array_key_exists($subType, $dataSets) ) {
    $dataSets[$subType] = array();
    $dataSets[$subType]["data"] = $data;
    $dataSets[$subType]["numSamples"] = $numSamples;
    $dataSets[$subType]["plotStats"] = $plotStats;
    $dataSets[$subType]["res"] = $res;
    $dataSets[$subType]["info"] = $info;
    $dataSets[$subType]["maxLossPacketSize"] = $maxLossPacketSize;
    $dataSets[$subType]["lossDist"] = $lossDist;
    $dataSets[$subType]["startTimeSec"] = $startTimeSec;
    $dataSets[$subType]["secOfData"] = $secOfData;
    $dataSets[$subType]["src"] = $src;
    $dataSets[$subType]["dst"] = $dst;
  }

  return $dataSets[$subType];

}

/*
 * Packet Size vs. RTT
 */

function icmp_get_rttsize_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{
  global $timeZone;

  $res = ampOpenDb(ICMP_DATA, $subType, $src, $dst, $startTimeSec, 0,
                   $timeZone);

  if ( ! $res ) {
    return array();
  }

  do {
    $obj = ampNextObj($res);

    if ( ! $obj || $obj->secInPeriod > $secOfData ) {
      break;
    }

    if ( $obj->data < 0 ) {
      continue;
    }

    $data[$obj->data][$obj->data2] = 1;
  } while ( TRUE );

  $numSamples = 0;

  $dataSets = array();

  $dataSet["color"] = $GLOBALS["dataSetColour"]["random"];
  $dataSet["key"] = "";
  $dataSet["info"] = ampInfoObj($res);

  $gdata = array();

  if ( $data ) {
    foreach ( array_keys($data) as $size ) {
      foreach ( array_keys($data[$size]) as $time) {
        $dataSet["data"][$numSamples]["x"] = $time;
        $dataSet["data"][$numSamples]["y"] = $size;
        ++$numSamples;
      }
    }
  } else {
    return array();
  }

  $dataSets[] = $dataSet;

  return $dataSets;
}

function icmp_get_losscdf_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{
  global $timeZone;
  $totalLosses = 0;

  $res = ampOpenDb(ICMP_DATA, $subType, $src, $dst, $startTimeSec, 0,
                   $timeZone);

  if ( ! $res ) {
    return array();
  }

  do {
    $obj = ampNextObj($res);
    if ( ! $obj || $obj->secInPeriod > $secOfData ) {
      break;
    }
    if ( $obj->data != -1 ) {
      continue;
    }

    $totalLosses++;
    $lossDist[$obj->data2]++;
    if ( $obj->data2 > $maxLossPacketSize ) {
      $maxLossPacketSize = $obj->data2;
    }
  } while ( TRUE );

  if ( $totalLosses == 0 ) {
    return array();
  }

  $dataSets = array();
  $dataSet["color"] = $GLOBALS["dataSetColour"]["random"];
  $dataSet["key"] = "";
  $dataSet["info"] = ampInfoObj($res);
  $total = 0;

  for ( $size = 0; $size <= $maxLossPacketSize; ++$size ) {
    if ( isset($lossDist[$size]) )
      $total += $lossDist[$size];
    $gdata[$size]["x"] = $size;
    $gdata[$size]["y"] = round($total / $totalLosses * 100);
  }

  $dataSet["data"] = $gdata;
  $dataSets[] = $dataSet;

  return $dataSets;
}

/* Parse an ICMP subtype and return a legible name for it */
function icmp_subtype_name($subtype)
{

  if ($subtype == "rand") {
    return "Random Sized Packets";
  } else {
    return ltrim($subtype, " 0") . "byte Packets";
  }

  return $subtype;

}

/* Formats a line of raw ICMP data */
function icmp_format_raw($subType, $obj)
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
