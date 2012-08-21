<?php
/*
 * AMP Data Display Interface
 *
 * GridSched Test Display Module
 *
 * Author:      Brendon Jones <bcj3@cs.waikato.ac.nz>
 *
 *
 */
$dataSets = array();

/* Define Preference Stuff */
define('PREF_GRID', "grid");

define('GRID_PREF_DISPLAY_SCHED', "display-sched");

/* Register Preference Module*/
register_module(PREF_GRID, "Grid Graphs", "These preferences control the " .
  "display of graphs relating to the grid tests such as scheduling time. ");

/* Register Preferences Relating To Graph Options */
register_preference(array(PREF_GRID), PREF_BINSIZE,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Bin Size", "",
                    PREF_TYPE_INPUT,
                    array(PREF_LONGTERM=>60, PREF_SHORTTERM=>10), 3);
register_preference(array(PREF_GRID), PREF_YMAX, array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Maximum y-axis Value", "", PREF_TYPE_INPUT,
                    array(PREF_LONGTERM=>-1,PREF_SHORTTERM=>-1), 3);
register_preference(array(PREF_GRID), PREF_LINES,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display as Line Graph", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));

register_preference(array(PREF_GRID), PREF_DISPLAY_MAX,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Maximum", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));
register_preference(array(PREF_GRID), PREF_DISPLAY_MIN,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Minimum", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));
register_preference(array(PREF_GRID), PREF_DISPLAY_MEAN,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Mean", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));
register_preference(array(PREF_GRID), PREF_DISPLAY_MEDIAN,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Median", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));
register_preference(array(PREF_GRID), PREF_DISPLAY_STDDEV,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Std. Dev.", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));
register_preference(array(PREF_GRID), PREF_DISPLAY_SUMMARY,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Show Summary", "",
                    PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_FALSE,
                          PREF_SHORTTERM=>PREF_FALSE));

/* Register Preferences Relating To Graph Display - one preference for
 * each type of graph that we register below... */
register_preference(array(PREF_GLOBAL), GRID_PREF_DISPLAY_SCHED,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display Grid Scheduling Graphs", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));


/** Register Display Objects **/
/* Scheduling delay */
register_display_object("grid-scheduling", "Grid Tests", "Scheduling Delay", 1,
                        GRID_DATA, "*", "grid_sched_avail", "drawGraph",
                        "grid_get_sched_ds", "grid_get_base_ds",
                        GRID_PREF_DISPLAY_SCHED, PREF_GRID,
                        array("max"=>array("Max",PREF_DISPLAY_MAX),
                        "min"=>array("Min",PREF_DISPLAY_MIN),
                        "mean"=>array("Mean",PREF_DISPLAY_MEAN),
                        "median"=>array("Median",PREF_DISPLAY_MEDIAN),
                        "stddev"=>array("Std. Dev.",PREF_DISPLAY_STDDEV),
                        "summary"=>array("Summary",PREF_DISPLAY_SUMMARY)),
                        array("ymax"=>array("y-axis max.",3,PREF_YMAX)), -1,
                        -1, "", "Scheduling delay (ms)", "", "y", TRUE, "", TRUE);


/* Register Test Helper Functions */
$test_names[GRID_DATA] = "GRID";
$subtype_name_funcs[GRID_DATA] = "grid_subtype_name";
$raw_data_funcs[GRID_DATA] = "grid_format_raw";

/** Data Available Functions
 *
 * These functions check if the appropriate data is available for the
 * specified source and destination. And if so, return a list of display
 * items that can be displayed.
 *
 */
function grid_sched_avail($object_name, $src, $dst, $startTimeSec)
{
  if ( !checkDataExistsForTest($src, $dst, GRID_DATA) )
    return array();
  
  /* grid data exists - register display item */
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


/* Grid Base Dataset */
function grid_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  global $timeZone;

  /* Open a connection to the amp database */
  $res = ampOpenDb(GRID_DATA, 0, $src, $dst, $startTimeSec, 0, $timeZone);
  if (!$res) {
    return array();
  }
  $info = ampInfoObj($res);

  /* Extract Data */
  $sample = 0;
  $offset = 0;//XXX
  while (($obj = ampNextObj($res)) && $obj->secInPeriod<$secOfData) {
    $plotData[$sample]->data  = $obj->data;

    //XXX this is needed to fix up some data that has been captured 
    // in the wrong time zone. its a nasty hack
    if($obj->secInPeriod < 0 && $offset < 1)
      $offset = $obj->secInPeriod * -1;

    $plotData[$sample]->secInPeriod  = $obj->secInPeriod + $offset;
    $sample++;
  }
  $numSamples = $sample;

  /* Return Data */
  return array("plotData"=>$plotData,"numSamples"=>$numSamples, "info"=>$info);

}

/* Grid Dataset */
function grid_get_sched_ds($src, $dst, $subType, $startTimeSec, $secOfData,
        $binSize)
{

  /* Get the base dataset for the subtype */
  $ds = grid_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
    $binSize);
  if ( $ds == array() ) {
    return array();
  }

  $plotData = $ds["plotData"];
  $numSamples = $ds["numSamples"];


  $dataSets[0]["color"] = "red";
  $dataSets[0]["key"] = "scheduling delay";
  $dataSets[0]["info"] = $ds["info"];
  $data = array();
  
  for ( $sample=0; $sample<$numSamples; ++$sample ) {
    $data[$sample]["x"] = $plotData[$sample]->secInPeriod;
    $data[$sample]["y"] = $plotData[$sample]->data;
  }
  $dataSets[0]["data"] = $data;


  return $dataSets;

}

  // Emacs control
  // Local Variables:
  // eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
