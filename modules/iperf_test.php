<?php
/*
 * AMP Data Display Interface
 *
 * IPerf Test Display Module
 *
 * Author:      Tony McGregor <tonym@cs.waikato.ac.nz>
 * Author:      Matt Brown <matt@crc.net.nz.
 * Version:     $Id: iperf_test.php 2103 2011-04-12 23:59:22Z brendonj $
 *
 * This module provides the throughput graphs
 *
 */

/* Define Preference Stuff */
define('PREF_IPERF', "iperf");

define('IPERF_PREF_DISPLAY_TCP', "display-tcp");

/* Register Preferences */
register_module(PREF_IPERF, "Throughput Graphs", "These preferences " .
                "control the display of graphs relating to the throughput " .
                "tests.");

register_preference(array(PREF_IPERF), PREF_YMAX, array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Maximum y-axis Value", "", PREF_TYPE_INPUT, array(), 3);

/* Register Display Preferences */
register_preference(array(PREF_GLOBAL), IPERF_PREF_DISPLAY_TCP,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display TCP Iperf Graph", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_TRUE));

/* Register Available Display Objects */
register_display_object("iperf", "Throughput", "TCP Iperf", 10, IPERF_DATA,
                        "*", "iperf_avail", "drawGraph", "iperf_get_ds",
                        "iperf_get_base_ds", IPERF_PREF_DISPLAY_TCP,
                        PREF_IPERF, array(),
                        array("ymax"=>array("y-axis max.",3,PREF_YMAX)), -1,
                        -1, "",  "Kbit/s", "", "y", TRUE,
                        "POINT_TYPE cross\nPOINT_SIZE 3\nWITH_GAP true\n" .
                        "X_GAP_THRESHOLD 30\n", TRUE);

/* Register Test Helper Functions */
$test_names[IPERF_DATA] = "Iperf Throughput";
$subtype_name_funcs[IPERF_DATA] = "";
$raw_data_funcs[IPERF_DATA] = "iperf_format_raw";

/** Data Available Function
 *
 * Return appropriate display item(s) if Iperf data is available
 */
function iperf_avail($object_name, $src, $dst, $startTimeSec)
{
  if ( !checkDataExistsForTest($src, $dst, IPERF_DATA) )
    return array();

  /* Iperf data exists - register display item */
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

/* Iperf Base Dataset */
function iperf_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  global $timeZone;

  /* Open a connection to the amp database */
  $res = ampOpenDb(IPERF_DATA, 0, $src, $dst, $startTimeSec, 0, $timeZone);
  if (!$res) {
    return array();
  }
  $info = ampInfoObj($res);

  /* Extract Data */
  $sample = 0;
  while (($obj = ampNextObj($res)) && $obj->secInPeriod<$secOfData) {
    $plotData[$sample]->secInPeriod  = $obj->secInPeriod;
    $plotData[$sample]->latencyOut   = $obj->latencyOut;
    $plotData[$sample]->bandwidthOut = $obj->bandwidthOut;
    $plotData[$sample]->latencyIn    = $obj->latencyIn;
    $plotData[$sample]->bandwidthIn  = $obj->bandwidthIn;
    $sample++;
  }
  $numSamples = $sample;

  /* Return Data */
  return array("plotData"=>$plotData,"numSamples"=>$numSamples, "info"=>$info);

}

/* Iperf Dataset */
function iperf_get_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  /* Get the base dataset for the subtype */
  $ds = iperf_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize);
  if ( $ds == array() ) {
    return array();
  }

  $plotData = $ds["plotData"];
  $numSamples = $ds["numSamples"];

  /* Setup the dataset parameter */
  $dataSets = array();
  $incoming["color"] = "red";
  $incoming["key"] = "incoming";
  $incoming["info"] = $ds["info"];
  $outgoing["color"] = "blue";
  $outgoing["key"] = "outgoing";
  $outgoing["info"] = $ds["info"];
  $idata = array();
  $odata = array();
  for ( $sample = 0; $sample < $numSamples; ++$sample ) {
    $sec = $plotData[$sample]->secInPeriod;
    $bw  = $plotData[$sample]->bandwidthIn;
    $lat = $plotData[$sample]->latencyIn;
    if ( $lat > 80 && $bw != 0 ) {
      $idata[$sample]["x"] = $sec;
      $idata[$sample]["y"] = $bw;
    }
    $bw = $plotData[$sample]->bandwidthOut;
    $lat = $plotData[$sample]->latencyOut;
    if ( $lat > 80 && $bw != 0 ) {
      $odata[$sample]["x"] = $sec;
      $odata[$sample]["y"] = $bw;
    }
  }
  $incoming["data"] = $idata;
  $outgoing["data"] = $odata;
  $dataSets[] = $incoming;
  $dataSets[] = $outgoing;

  return $dataSets;

}

/* Formats a line of raw Iperf data */
function iperf_format_raw($subType, $obj)
{

  /* Handle request for a header line */
  if ( $obj == NULL ) {
    return "latency_out_ms,bandwidth_out_kpbs,latency_in_ms," .
      "bandwidth_in_kbps";
  }

  return sprintf("%d,%d,%d,%d", $obj->latencyOut, $obj->bandwidthOut,
    $obj->latencyIn, $obj->bandwidthIn);

}

// Emacs control
// Local Variables:
// eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
