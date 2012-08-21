<?php
/*
 * AMP Data Display Interface
 *
 * One-way delay (owamp) Test Display Module
 *
 *
 * Author:      Brendon Jones <bcj3@cs.waikato.ac.nz>
 *
 * This module provides the owamp graphs and raw data interface.
 * It is quite simplistic at the moment and lacking in the statistical
 * analysis that some of the other tests do.
 *
 */

/* Define Preference Stuff */
define('PREF_OWAMP', "owamp");
define('OWAMP_PREF_DISPLAY_UDP', "display-owamp");

/* Register Preferences */
register_module(PREF_OWAMP, "OWAMP Graphs", "These preferences " .
                "control the display of graphs relating to the OWAMP " .
                "tests.");

register_preference(array(PREF_OWAMP), PREF_YMAX, array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Maximum y-axis Value", "", PREF_TYPE_INPUT, array(), 3);

/* Register Display Preferences */
register_preference(array(PREF_GLOBAL), OWAMP_PREF_DISPLAY_UDP,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display UDP OWAMP Graph", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                        PREF_SHORTTERM=>PREF_TRUE));

/* Register Available Display Objects */
register_display_object("owamp", "OWAMP", "OWAMP Latency", 60, OWAMP_DATA, "*",
                        "owamp_avail", "drawGraph", "owamp_get_ds",
                        "owamp_get_base_ds", OWAMP_PREF_DISPLAY_UDP, PREF_OWAMP,
                        array("summary"=>array("Summary",PREF_DISPLAY_SUMMARY)),
                        array("ymax"=>array("y-axis max.",3,PREF_YMAX)),
                        -1, -1, "", "latency (ms)", "", "y", TRUE,
                        //"POINT_TYPE dot\nPOINT_SIZE 3\nWITH_GAP true\n" .
                        //"X_GAP_THRESHOLD 30\n", TRUE);
                        "", TRUE);

/* Register Test Helper Functions */
$test_names[OWAMP_DATA] = "OWAMP Latency";
$subtype_name_funcs[OWAMP_DATA] = "owamp_subtype_name";
$raw_data_funcs[OWAMP_DATA] = "owamp_format_raw";

/** Data Available Function
 *
 * Return appropriate display item(s) if OWAMP data is available
 */
function owamp_avail($object_name, $src, $dst, $startTimeSec)
{
  if ( !checkDataExistsForTest($src, $dst, OWAMP_DATA) )
    return array();

  $items = array();
  $object = get_display_object($object_name);

  $subtypes = ampsubtypelist(OWAMP_DATA, $src, $dst);

  foreach ( $subtypes->subtypes as $idx=>$subtype ) {
    $sized = rtrim($subtype, "B");

    /* Create the display item */
    $item = new display_item_t();
    $item->category = $object->category;
    $item->name = $sized . "b-" . $object->name;
    $item->title = $sized . "byte OWAMP";
    $item->displayObject = $object->name;
    $item->subType = $subtype;
    $items[$item->name] = $item;
  }

  return $items;
}

/** Data Retrieval Functions **/

/* OWAMP Base Dataset */
function owamp_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  global $timeZone;

  /* Open a connection to the amp database */
  $res = ampOpenDb(OWAMP_DATA, $subType, $src, $dst, $startTimeSec, 0,
  $timeZone);
  if (!$res) {
    return array();
  }
  $info = ampInfoObj($res);

  /* Extract Data */
  $sample = 0;
  while (($obj = ampNextObj($res)) && $obj->secInPeriod<$secOfData) {
    $plotData[$sample]->secInPeriod  = $obj->secInPeriod;
    $plotData[$sample]->s2r = $obj->data;
    $plotData[$sample]->r2s = $obj->data2;
    $sample++;

  }
  // total samples good + bad
  $numSamples = $sample;

  /* Return Data */
  return array("plotData"=>$plotData,"numSamples"=>$numSamples,
               "info"=>$info, "plotStats1"=>$plotStats1, "plotStats2"=>$plotStats2);

}

/* OWAMP Dataset */
function owamp_get_ds($src, $dst, $subType, $startTimeSec, $secOfData,
$binSize, $recurse=1)
{
  /* Get the base dataset for the subtype */
  $ds = owamp_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
      $binSize);

  if ( $ds == array() || $ds["plotData"] == NULL) {
    return array();
  }

  $plotData = $ds["plotData"];
  $numSamples = $ds["numSamples"];
  $info = $ds["info"];

  /* Setup the dataset parameter */
  $ds1 = array();
  $ds1["color"] = "blue";
  $ds1["key"] = "outgoing";
  $ds1["info"] = $info;

  $ds2 = array();
  $ds2["color"] = "red";
  $ds2["key"] = "incoming";
  $ds2["info"] = $info;

  /* Get the data */
  $data1 = array();
  $data2 = array();
  for ( $sample = 0; $sample<$numSamples; ++$sample ) {
    $sec = $plotData[$sample]->secInPeriod;
    $s2r  = $plotData[$sample]->s2r;
    $r2s = $plotData[$sample]->r2s;
    if ($s2r > 0) {
      $data1[$sample]["x"] = $sec;
      $data1[$sample]["y"] = $s2r;
    }
    
    if ($r2s > 0) { 
      $data2[$sample]["x"] = $sec;
      $data2[$sample]["y"] = $r2s;
    }
  }
  $ds1["data"] = $data1;
  $ds2["data"] = $data2;

  $dataSets = array($ds1, $ds2);

  return $dataSets;

}

/* Parse an owamp subtype and return a legible name for it */
function owamp_subtype_name($subtype)
{

  $sized = rtrim($subtype, "B");
  return $sized . "byte OWAMP";

}

/* Formats a line of raw OWAMP data */
function owamp_format_raw($subType, $obj)
{

  /* Handle request for a header line */
  if ($obj == NULL) {
    return "sender2receiver,receiver2sender";
  }

  return sprintf("%d,%d", $obj->data, $obj->data2);

}


// Emacs control
// Local Variables:
// eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
