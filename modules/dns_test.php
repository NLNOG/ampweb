<?php
/*
 * AMP Data Display Interface
 *
 * DNS Test Display Module
 *
 * Author:      Brendon Jones <bcj3@cs.waikato.ac.nz>
 *
 * This module provides the DNS latency, jitter and loss graphs for the DNS
 * test. 
 *
 */
$dataSets = array();

/* Define Preference Stuff */
define('PREF_DNS', "dns");

define('DNS_PREF_DISPLAY_LATENCY', "display-latency");
define('DNS_PREF_DISPLAY_JITTER', "display-jitter");
define('DNS_PREF_DISPLAY_LOSS', "display-loss");

/* Register Preference Module*/
register_module(PREF_DNS, "DNS Graphs", "These preferences control the " .
  "display of graphs relating to the DNS tests such as Latency, Jitter, " .
  "Loss, etc.");

/* Register Preferences Relating To Graph Options */
register_preference(array(PREF_DNS), PREF_BINSIZE,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Bin Size", "",
                    PREF_TYPE_INPUT,
                    array(PREF_LONGTERM=>35, PREF_SHORTTERM=>5), 3);
register_preference(array(PREF_DNS), PREF_YMAX, array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Maximum y-axis Value", "", PREF_TYPE_INPUT,
                    array(PREF_LONGTERM=>-1,PREF_SHORTTERM=>-1), 3);
register_preference(array(PREF_DNS), PREF_LINES,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display as Line Graph", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));

register_preference(array(PREF_DNS), PREF_DISPLAY_MAX,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Maximum", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));
register_preference(array(PREF_DNS), PREF_DISPLAY_MIN,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Minimum", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));
register_preference(array(PREF_DNS), PREF_DISPLAY_MEAN,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Mean", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_DNS), PREF_DISPLAY_MEDIAN,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Median", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));
register_preference(array(PREF_DNS), PREF_DISPLAY_STDDEV,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Std. Dev.", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));
register_preference(array(PREF_DNS), PREF_DISPLAY_SUMMARY,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Summary", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));

/* Register Preferences Relating To Graph Display - one preference for
 * each type of graph that we register below... */
register_preference(array(PREF_GLOBAL), DNS_PREF_DISPLAY_LATENCY,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display DNS Latency Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_GLOBAL), DNS_PREF_DISPLAY_JITTER,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display DNS Jitter Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_GLOBAL), DNS_PREF_DISPLAY_LOSS,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display DNS Loss Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_TRUE));


/** Register Display Objects **/
/* Latency */
register_display_object("dns-latency", "Latency", "DNS latency", 1,
                        DNS_DATA, "*", "dns_avail", "drawNewGraphs",
                        "dns_get_latency_ds", "dns_get_base_ds",
                        DNS_PREF_DISPLAY_LATENCY, PREF_DNS,
                        array(
                        //"max"=>array("Max",PREF_DISPLAY_MAX),
                        //"min"=>array("Min",PREF_DISPLAY_MIN),
                        //"mean"=>array("Mean",PREF_DISPLAY_MEAN),
                        //"median"=>array("Median",PREF_DISPLAY_MEDIAN),
                        //"stddev"=>array("Std. Dev.",PREF_DISPLAY_STDDEV),
                        "summary"=>array("Summary",PREF_DISPLAY_SUMMARY)),
                        array(), -1,
                        -1, "", "Latency (ms)", "", "", TRUE, "", TRUE);

/* Jitter */
register_display_object("dns-jitter", "Jitter", "DNS latency", 2, DNS_DATA,
                        "*", "dns_avail", "drawNewGraphs",
                        "dns_get_jitter_ds", "dns_get_base_ds",
                        DNS_PREF_DISPLAY_JITTER, PREF_DNS,
                        array("summary"=>array("Summary",PREF_DISPLAY_SUMMARY)),
                        array("ymax"=>array("y-axis max.",3,PREF_YMAX)), -1,
                        -1, "", "Jitter (ms)", "", "", TRUE, "", TRUE);

/* Loss */
register_display_object("dns-loss", "Loss", "DNS latency", 3, DNS_DATA, "*",
                        "dns_avail", "drawNewGraphs", "dns_get_loss_ds",
                        "dns_get_base_ds", DNS_PREF_DISPLAY_LOSS, PREF_DNS,
                        array("summary"=>
                              array("Summary", PREF_DISPLAY_SUMMARY)),
                        array(), -1, 100, "", "loss percent", "", "", TRUE,
                        "", TRUE);


/* Register Test Helper Functions */
$test_names[DNS_DATA] = "DNS";
$subtype_name_funcs[DNS_DATA] = "dns_subtype_name";
$raw_data_funcs[DNS_DATA] = "dns_format_raw";


function cmp($a, $b) {
  //echo "$a vs $b<br />\n";
  $name1 = substr($a, 3);
  $name2 = substr($b, 3);

  if(strcmp($name1, $name2) != 0)
    return strcmp($name1, $name2);

  $count1 = substr($a, 0, 2);
  $count2 = substr($b, 0, 2);

  return strcmp($count1, $count2);
}

/** Data Available Functions
 *
 * These functions check if the appropriate data is available for the
 * specified source and destination. And if so, return a list of display
 * items that can be displayed.
 *
 */
function dns_avail($object_name, $src, $dst, $startTimeSec)
{
  if ( !checkDataExistsForTest($src, $dst, DNS_DATA) )
    return array();

  $stList = ampSubtypeList(DNS_DATA, $src, $dst);
  usort($stList->subtypes, "cmp");

  /* Loop through returned subtypes (destination hosts), create item for each */
  $items = array();
  foreach ( $stList->subtypes as $idx=>$type ) {
    /* register display item */
    $object = get_display_object($object_name);
    $item = new display_item_t();
    $item->category = $object->category;
    $item->name = "$type-" . $object->name;
    $item->title = " $type " . $object->title;
    $item->displayObject = $object->name;
    $item->subType = $type;

    $items[$item->name] = $item;
  }

  return $items;

}

/** Data Retrieval Functions **/

/* Size Dataset */
function dns_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
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

  $res = getBinnedDataSet($src, $dst, DNS_DATA, $subType, $startTimeSec,
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


function dns_get_size_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize, $components)
{

  /* Get the base dataset for the subtype */
  $ds = dns_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
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
function dns_get_latency_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize, $components=array())
{

  if ( $components == array() ) {
    $components = array('mean');
  }

  return dns_get_size_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize, $components);

}

/* Jitter */
function dns_get_jitter_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize, $components=array())
{
  if ( $components == array() ) {
    $components = array('jitter');
  }

  return dns_get_size_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize, $components);

}

/* Loss */
function dns_get_loss_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize, $components=array())
{

  if ( $components == array() ) {
    $components = array('loss');
  }

  return dns_get_size_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize, $components);

}


/* Parse a DNS subtype and return a legible name for it */
function dns_subtype_name($subtype)
{

  return substr($subtype, 0, -4);

}

/* Formats a line of raw DNS data */
function dns_format_raw($subType, $obj)
{

  /* Handle request for a header line */
  if ($obj == NULL) {
    return "latency_ms";
  }

  //if($obj->error) {
  switch($obj->data) {
    case -1: $content = "loss"; break;
    case -2: $content = "invalid"; break;
    case -3: $content = "notfound"; break;
    
    default: $content = $obj->data; break;
  };

  return $content;
}

  // Emacs control
  // Local Variables:
  // eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
