<?php
/*
 * AMP Data Display Interface 
 *
 * Example Test Display Module
 *
 * Author:      Matt Brown <matt@crc.net.nz.
 * Version:     $Id: example_test.php 505 2005-02-13 22:04:59Z mglb1 $
 *
 * This module provides an skeleton setup for a display module. See 
 * icmp_test.php for an example of a working module.
 *
 * Lines starting with a # must be uncommented (remove the #) to give the
 * module any effect!
 *
 */

/* Define Preference Stuff 
 * EXAMPLE/example should be replaced with the name of the module in all of the
 * following definitions.
 */
#define(PREF_EXAMPLE, "example");

/* SAMPLEPREF/samplepref shuold be replaced with the name of the preference */
#define(EXAMPLE_PREF_SAMPLEPREF, "samplepref");

/* GRAPHNAME/graphname should be replaced with the name of the graph */
#define(EXAMPLE_PREF_DISPLAY_GRAPHNAME, "display-graphname");

/* Register Preference Module*/
#register_module(PREF_EXAMPLE, "Example Module", "These preferences " .
#    "relate to the example module!");

/* Register Preferences Relating To Graph Options */
#register_preference(PREF_EXAMPLE, EXAMPLE_PREF_SAMPLEPREF, 
#    array(PREF_LONGTERM,PREF_SHORTTERM), "Sample Preference", PREF_TYPE_INPUT,
#    array(PREF_LONGTERM=>35, PREF_SHORTTERM=>5), 3);

/* Register Preferences Relating To Graph Display - one preference for
 * each type of graph that we register below... */
#register_preference(PREF_GLOBAL, EXAMPLE_PREF_DISPLAY_GRAPHNAME, 
#    array(PREF_LONGTERM,PREF_SHORTTERM), "Display Example Graph Name",
#    PREF_TYPE_BOOL, 
#    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));

/* Register Available Display Objects */
#register_display_object("example", "Example", "Example Test", 100, 
#	EXAMPLE_DATA, "*", "example_avail", "drawGraph", "example_get_ds", 
#	"example_get_base_ds", EXAMPLE_PREF_DISPLAY_GRAPHNAME, PREF_EXAMPLE,
#	array(),
#	array("ymax"=>array("y-axis max.",3,PREF_YMAX)), -1, -1, "", 
#	"", "", "y", TRUE, "", TRUE);

/* Register Test Helper Functions */
#$test_names[EXAMPLE_DATA] = "Example";
#$subtype_name_funcs[EXAMPLE_DATA] = "example_subtype_name";
#$raw_data_funcs[EXAMPLE_DATA] = "example_format_raw";

/** Data Available Function 
 *
 * Return appropriate display item(s) if example data is available 
 */
 function example_avail($object_name, $src, $dst, $startTimeSec)
 {
 	
	global $timeZone;
	
	$object = get_display_object($object_name);
	
	/* Check for example in the test list */
	$tests = amptestlist($src, $dst);
	if (in_array(EXAMPLE_DATA, $tests->types)) {
		/* example data exists - check for subtypes if required... */

		/* Create the display item */
		$item = new display_item_t();
		$item->category = $object->category;
		$item->name = $object->name;
		$item->title = "Example Graph";
		$item->displayObject = $object->name;
		$item->subType = $subtype;
		$items[$item->name] = $item;
			
		return $items;
    }

	/* No Example Data Available */
	return array();
	
}

/** Data Retrieval Functions **/

/* Example Base Dataset */
function example_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData, 
	$binSize)
{
	
	global $timeZone;
	
    /* Open a connection to the amp database */
    $res = ampOpenDb(EXAMPLE_DATA, $subType, $src, $dst, $startTimeSec, 0, 
		$timeZone);    
    if (!$res) {
		return array();
		
    }
	$info = ampInfoObj($res);
  
	/* Extract Data */
	$sample = 0;
	while (($obj = ampNextObj($res)) && $obj->secInPeriod<$secOfData) {
		// Store data here
		$sample++;
	}
	$numSamples = $sample;

	/* Return Data */
	return array("plotData"=>$plotData,"numSamples"=>$numSamples,
		"info"=>$info);

}

/* Example Dataset */
function example_get_ds($src, $dst, $subType, $startTimeSec, $secOfData, 
	$binSize, $recurse=1)
{
		
	/* Get the base dataset for the subtype */
	$ds = example_get_base_ds($src, $dst, $subType, $startTimeSec, $secOfData,
		$binSize);
	if ($ds == array())
		return array();
	
	$plotData = $ds["plotData"];
	$numSamples = $ds["numSamples"];
	$info = $ds["info"];

	/* Setup the dataset parameter */
	$ds = array();
	$ds["color"] = "blue";
	$ds["key"] = "example data";
	$ds["info"] = $info;
	/* Get the data */
	$data = array();
	for ($sample = 0; $sample<$numSamples; ++$sample) {
		/* Store data in array here  */
		#$data[$sample]["x"] = XVALUE;
		#$data[$sample]["y"] = YVALUE;
	}
	$ds["data"] = $data;

	$dataSets = array($ds);
	
	return $dataSets;

}

/* Parse an example subtype and return a legible name for it */
function example_subtype_name($subtype)
{
	
	return $subtype;
	
}

/* Formats a line of raw example data */
function example_format_raw($subType, $obj)
{
	
	/* Handle request for a header line */
	if ($obj == NULL) {
		return "data,data2";	
	}

	return sprintf("%s,%s", $obj->data, $obj->data2);

}


