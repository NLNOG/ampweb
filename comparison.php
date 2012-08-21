<?php
/*
 * AMP Data Display Interface 
 *
 * Comparisons
 *
 * Author:      Matt Brown <matt@crc.net.nz.
 * Version:     $Id: comparison.php 1850 2010-08-19 23:58:18Z brendonj $
 *
 * Generates Comparison Graphs
 *
 */
require("amplib.php");

$pageClass = PREF_LONGTERM;
$xAxisType = "week";
$secOfData = 604800;

/* Setup the AMP system */
initialise();

/* If comparisons are disabled, return to index */
if (get_preference(PREF_GLOBAL, GP_COMPARISONS, PREF_GLOBAL)==PREF_FALSE) {
  goPage("src.php"); 
  return;
}
$timeZone = get_preference(PREF_GLOBAL, GP_TIMEZONE, PREF_GLOBAL);
putenv("TZ=$timeZone");
$binSize =  get_preference(PREF_COMPARISON, PREF_BINSIZE, $pageClass);
$binSecs = $binSize * 60;

/* Get comparisons */
$cgraphs = array();
$objects = array();
$dgraphs = comp_common_dests(-1, $secOfData, $binSize);
$cgraphs = array_merge($cgraphs, $dgraphs);

/* Initialise the HTML */
templateTop();

/* Page Heading & Instructions */
echo "<h2>$system_name - Comparative Graphs</h2>\n";


if ( $cgraphs == array() ) {
  echo "No graphs selected<br>\n";
} else {
  
  foreach ( $cgraphs as $name=>$graph ) {
    
    $object = $graph->displayObject;
    $dataSets = $graph->dataSets;
    $graphDate = strftime("%Y-%m-%d", $graph->time);
    
    /* Determine the xAxis label */
    if ( $object->xLabel == "" ) {
      $nData = count($dataSets[0]["data"])-1;
      $lastTime = $dataSets[0]["data"][$nData]["time"];
      $xLabel = xLabelByTime($dataSets[0]["info"], $lastTime, PREF_LONGTERM);
    } else {
      $xLabel = $object->xLabel;
    }
    
    /* Fill in other options if not set already */
    if ( $object->lineGraph == "" ) {
      $lineGraph = get_preference($object->prefModule, PREF_LINES, $pageClass);
    } else {
      $lineGraph = $object->lineGraph;
    }
    
    /* Display Title */
    $title = $graph->category . " - " . $graph->title;
    echo '<h2 class="graphtitle">'.htmlspecialchars($title).' - Week Starting '.$graphDate.'</h2>';
    
    /* Display the actual graph */
    if ( $dataSets == array() ) {
      graphError("No Data Available for $title!");
      return;
    } else {
      $df = $object->displayFunc;
      if ( ! function_exists($df) ) {
        graphError("Display function does not exist for $title! - $df");
        return;
      }
      /* massage datasets into the format expected by the graphing function */
      $dataSets = array("comparison" => 
          array($graph->dataSets[0]["info"]->dst => $graph->dataSets) );

      $df("comparison", $graph->cdst, $graphDate, "", $graph->name,
        $lineGraph, $xAxisType, FALSE, $xLabel, $object->yLabel, 
        $object->xMax, $object->yMax, $dataSets,
        $object->extraOpts, $scope);				
    }
    /* Keep track of which objects have been displayed */
    $objects = array_merge($objects, $graph->objects);
  }

	/* Check for objects that weren't part of a comparison */
	foreach( $_SESSION["comparison_items"] as $idx=>$item ) {
    
    $object = get_display_object($item["object"]);
    if ( in_array($object->name, $objects) ) {
      continue;
    }

    /* Display it as a normal graph */
    $src = $item["src"];
    $dst = $item["dst"];
    $title = $object->category . " - " . $object->title;
    $startTimeSec = start_of_week($item["time"]);		
    $graphDate = strftime("%Y-%m-%d", $startTimeSec);
    echo '<h2>'.htmlspecialchars($title).' - Week Starting '.$graphDate.' ('.htmlspecialchars($src).' to '.htmlspecialchars($dst).')</h2>';
    
    $dsf = $object->dataSetFunc;
    $dataSet = $dsf($item["src"], $item["dst"], $item["subType"],
      $startTimeSec, $secOfData, $binSize);

    /* Determine the xAxis label */
    if ($object->xLabel == "") {
      $nData = count($dataSet[0]["data"])-1;
      $lastTime = $dataSet[0]["data"][$nData]["time"];
      $xLabel = xLabelByTime($dataSet[0]["info"], $lastTime, PREF_LONGTERM);
    } else {
      $xLabel = $object->xLabel;
    }
    
    /* Fill in other options if not set already */
    if ($object->lineGraph == "") {
      $lineGraph = get_preference($object->prefModule, PREF_LINES, $pageClass);
    } else {
      $lineGraph = $object->lineGraph;
    }
    
    if ($dataSet == array()) {
      graphError("No Data Available for $title!");
      return;
    } else {
      $df = $object->displayFunc;
      if (!function_exists($df)) {
        graphError("Display function does not exist for $title! - $df");
        return;
      }
      $df($item["src"], $item["dst"], $graphDate, "", $object->name,
        $lineGraph, $xAxisType, FALSE, $xLabel, $object->yLabel, 
        $object->xMax, $object->yMax, $dataSet, $object->extraOpts, $scope);
    }
  }		

}

/* Finish off the page */
endPage();

// vim:set sw=2 ts=2 sts=2 et:
?>
