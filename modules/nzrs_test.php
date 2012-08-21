<?php
/*
 * AMP Data Display Interface
 *
 * DNS anycast instance test (nzrs) Test Display Module
 *
 *
 * Author:      Brendon Jones <bcj3@cs.waikato.ac.nz>
 *
 * This module provides the nzrs graphs and raw data interface.
 * It is quite simplistic at the moment and lacking in the statistical
 * analysis that some of the other tests do.
 *
 */

/* Define Preference Stuff */
define('PREF_NZRS', "nzrs");
define('NZRS_PREF_DISPLAY', "display-nzrs");

/* Register Preferences */
register_module(PREF_NZRS, "NZRS Graphs", "These preferences " .
                "control the display of graphs relating to the NZRS " .
                "tests.");

register_preference(array(PREF_NZRS), PREF_YMAX, array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Maximum y-axis Value", "", PREF_TYPE_INPUT, array(), 3);
register_preference(array(PREF_NZRS), PREF_LINES,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display as Line Graph", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_NZRS), PREF_BINSIZE,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Bin Size", "",
                    PREF_TYPE_INPUT,
                    array(PREF_LONGTERM=>35, PREF_SHORTTERM=>5), 3);

/* Register Display Preferences */
register_preference(array(PREF_GLOBAL), NZRS_PREF_DISPLAY,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display NZRS Graph", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,
                        PREF_SHORTTERM=>PREF_TRUE));


/* Register Available Display Objects */
register_display_object("nzrs", "NZRS", "NZRS Latency", 60, NZRS_DATA, "*",
                        "nzrs_avail", "drawNewGraphs", "nzrs_get_ds",
                        "nzrs_get_base_ds", NZRS_PREF_DISPLAY, PREF_NZRS,
                        array("max"=>array("Max",PREF_DISPLAY_MAX),
                        "min"=>array("Min",PREF_DISPLAY_MIN),
                        "mean"=>array("Mean",PREF_DISPLAY_MEAN),
                        "median"=>array("Median",PREF_DISPLAY_MEDIAN),
                        "stddev"=>array("Std. Dev.",PREF_DISPLAY_STDDEV),
                        "summary"=>array("Summary",PREF_DISPLAY_SUMMARY)),
                        array("ymax"=>array("y-axis max.",3,PREF_YMAX)),
                        -1, -1, "", "latency (ms)", "", "", TRUE,
                        //"POINT_TYPE dot\nPOINT_SIZE 3\nWITH_GAP true\n" .
                        //"X_GAP_THRESHOLD 30\n", TRUE);
                        "", TRUE);

/* Register Test Helper Functions */
$test_names[NZRS_DATA] = "NZRS Latency";
$subtype_name_funcs[NZRS_DATA] = "";
$raw_data_funcs[NZRS_DATA] = "nzrs_format_raw";

/** Data Available Function
 *
 * Return appropriate display item(s) if NZRS data is available
 */
function nzrs_avail($object_name, $src, $dst, $startTimeSec)
{
  if ( !checkDataExistsForTest($src, $dst, NZRS_DATA) )
    return array();

  $items = array();

  /* Create the display item */
  $object = get_display_object($object_name);
  $item = new display_item_t();
  $item->category = $object->category;
  $item->name = $object->name;
  $item->title = $object->title;
  $item->displayObject = $object->name;
  $item->subType = "";
  $items[$item->name] = $item;

  return $items;
}

/** Data Retrieval Functions **/

/* NZRS Base Dataset */

function nzrs_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  global $timeZone;
  $binStartTime = 0;
  $bin = 0;

  /* Open a connection to the amp database */
  $res = ampOpenDb(NZRS_DATA, $subType, $src, $dst, $startTimeSec, 0,
      $timeZone);
  if (!$res) {
    return array();
  }

  $info = ampInfoObj($res);

  $instances = array();
  /* Retrieve the data - Loop until bins are filled */
  do {
    /* Get a measurement */
    $obj = ampNextObj($res);
    $dataV = $obj->latency;
    $dataInstance = $obj->instance;
    
    /* initialise values for a new server instance */
    if ( strlen($dataInstance) > 0 && $dataInstance != "NULL" && 
        !isset($instances[$dataInstance]) ) {

        $instances[$dataInstance] = array();
        $instances[$dataInstance]["binTotal"] = 0;
        $instances[$dataInstance]["binCount"] = 0;
        $instances[$dataInstance]["bins"][0]["time"] = ((int)($binSize * 30));
        $instances[$dataInstance]["res"] = $res;
        $instances[$dataInstance]["info"] = $info;
        $instances[$dataInstance]["startTimeSec"] = $startTimeSec;
        $instances[$dataInstance]["secOfData"] = $secOfData;
        $instances[$dataInstance]["src"] = $src;
        $instances[$dataInstance]["dst"] = $dst;
        /*
        $plotStats['total'] = 0;
        $plotStats['loss'] = 0;
        $plotStats['squaresSum'] = 0;
        $plotStats['max'] = 0;
        $plotStats['min'] = 3000000;
        $plotStats['count'] = 0;
        */
    }


    /* if measurement is outside the bin, calculate the stats for the bin */
    if ( ! $obj || $obj->secInPeriod > $secOfData ||
        $obj->secInPeriod > ($binStartTime + ($binSize * 60)) ) {
      
      $binStartTime = $obj->secInPeriod - ($obj->secInPeriod % ($binSize * 60));

      /* need to update all instances we have data for, not just the one that
       * owns this particular measurement
       */
      foreach($instances as &$instance) {
        if ( $instance["binCount"] != 0 ) {
          /* calculate mean for bin */
          $instance["bins"][$bin]["mean"] = 
            $instance["binTotal"]/$instance["binCount"];
        }
        /* zero everything ready for the next bin */
        $instance["binTotal"] = 0;
        $instance["binCount"] = 0;
      }
      $bin++;
    }


    /* Check this measurement is still in requested time period */
    if ( ! $obj || $obj->secInPeriod>$secOfData ) {
      /* End loop if it's not */
      break;
    }

    /* ignore loss for now, might want to record them but against 
     * which instance?
     */
    if ( $dataV < 0 || strlen($dataInstance) < 1 )
      continue;

    /* measurement is good, record it */

    /* first measurement in this bin should also record the time */
    if ( !isset($instances[$dataInstance]["bins"][$bin]["time"]) )
      $instances[$dataInstance]["bins"][$bin]["time"] = 
        $binStartTime + ((int)($binSize * 30));

    $instances[$dataInstance]["binCount"]++;
    $instances[$dataInstance]["binTotal"] += $dataV;


  } while(TRUE);

  return $instances;
}


/* NZRS Dataset */
function nzrs_get_ds($src, $dst, $subType, $startTimeSec, $secOfData,
$binSize, $recurse=1)
{
  /* Get the base dataset for the subtype */
  $ds = nzrs_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
      $binSize);

  //check for no data
  if ( $ds == array() )
    return array();
  
  $dataSets = array();
  $colours = array("blue", "red", "black", "cyan", "magenta", "gray", 
      "green", "yellow");

  foreach ( $ds as $instance=>$data ) {
    $plotData = $data["bins"];
    $numSamples = $data["numBins"];
    $info = $data["info"];
        
    /* set up the graph stuff for new instances the first time we see them */
    /* if there are no colours left, don't create the new instance */
    $colour = array_shift($colours);
    if ( $colour == NULL )
      break;
    $dataSets[$instance] = array();
    $dataSets[$instance]["color"] = $colour;
    $dataSets[$instance]["key"] = $instance;
    $dataSets[$instance]["info"] = $info;
    $dataSets[$instance]["data"] = array();
  
    foreach ( $plotData as $sample ) {
      $sec = $sample["time"];
      $latency = $sample["mean"];

      $point = array("x" => $sec, "y" => $latency);
      $dataSets[$instance]["data"][] = $point;
    }
  }
  return array_values($dataSets);
}


/* Formats a line of raw NZRS data */
function nzrs_format_raw($subType, $obj)
{

  /* Handle request for a header line */
  if ($obj == NULL) {
    return "instance,latency";
  }

  return sprintf("%s,%d", $obj->instance, $obj->latency);

}


// Emacs control
// Local Variables:
// eval: (c++-mode)
