<?php
/*
 * AMP Data Display Interface
 *
 * Graph Comparisons
 *
 * Author:  Matt Brown <matt@crc.net.nz>
 * Version: $Id: amp_comparison.php 2103 2011-04-12 23:59:22Z brendonj $
 *
 * Functions and definitions to facilitate the generation of comparision
 * graphs.
 *
 * The session is used to keep track of the graphs to be compared and the
 * parameters for it.
 *
 */

/**** Variables ****/

/* Define Preference Stuff */
define('PREF_COMPARISON', "comparison");

/**** Initialisation ****/
register_preference(array(PREF_GLOBAL), GP_COMPARISONS, array(PREF_GLOBAL),
                    "Allow comparisons between sites", "", PREF_TYPE_BOOL,
                    array(PREF_GLOBAL=>PREF_TRUE));

/* Register Preferences Relating To Graph Options */
register_preference(array(PREF_COMPARISON), PREF_BINSIZE,
                    array(PREF_LONGTERM,PREF_SHORTTERM), "Bin Size", "",
                    PREF_TYPE_INPUT,
                    array(PREF_LONGTERM=>35, PREF_SHORTTERM=>5), 3);
register_preference(array(PREF_COMPARISON), PREF_YMAX,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Maximum y-axis Value", "", PREF_TYPE_INPUT,
                    array(PREF_LONGTERM=>-1,PREF_SHORTTERM=>-1), 3);
register_preference(array(PREF_COMPARISON), PREF_LINES,
                    array(PREF_LONGTERM,PREF_SHORTTERM),
                    "Display as Line Graph", "", PREF_TYPE_BOOL,
                    array(PREF_LONGTERM=>PREF_TRUE,PREF_SHORTTERM=>PREF_TRUE));

/**** Functions ****/

/*
 * Process a page submission to check for comparison stuff
 */
function process_comparison()
{


  /* Check for a posted form */
  if ( ! isset($_REQUEST["comparison"]) )
    return;

  if ( $_REQUEST["comparison"] == "add" ) {
    /* Initialise items array if it's not setup already */
    if ( ! isset($_SESSION["comparison_items"]) ) {
      $_SESSION["comparison_items"] = array();
    }
    /* Check if object is already in list */
    if ( find_comparison_item($_REQUEST["cobject"], $_REQUEST["src"],
                                                  $_REQUEST["dst"]) != -1 ) {
      /* Already in list, don't add again */
      return;
    }
    /* Create new object */
    $item = array();
    $item["object"] = $_REQUEST["cobject"];
    $item["subType"] = $_REQUEST["csubType"];
    $item["src"] = $_REQUEST["src"];
    $item["dst"] = $_REQUEST["dst"];
    $item["time"] = $_REQUEST["ctime"];
    $item["scope"] = $_REQUEST["cscope"];
    /* Add it to the list */
    $_SESSION["comparison_items"][] = $item;

  } else if ( $_REQUEST["comparison"] == "del" ) {
    $idx = $_REQUEST["idx"];
    unset($_SESSION["comparison_items"][$idx]);

  } else if ( $_REQUEST["comparison"] == "reset" ) {
    reset_comparison();
  }

}

/*
 * Find an item in the comparison list
 *
 * Returns the index of the item in the list, or -1 if not found.
 */
function find_comparison_item($object, $src, $dst)
{

  foreach ($_SESSION["comparison_items"] as $idx=>$item) {
    /* Check required parameters */
    if ( $item["object"] != $object ) {
      continue;
    }
    if ( $item["src"] != $src ) {
      continue;
    }
    if ( $item["dst"] != $dst ) {
      continue;
    }

    /* Found it */
    return $idx;
  }

  return -1;

}

/*
 * Reset the comparison
 */
function reset_comparison()
{

  /* Clear all session variables */
  $_SESSION["comparison_items"] = array();
  unset($_SESSION["comparison_items"]);

}

function build_comparison_graph($dir, $list, $startTimeSec, 
    $secOfData, $binSize) {
  
  global $comp_colors;

  $cgraphs = array();

  /* For any src/dst with more than 1 display object selected, build
   * a graph to show the comparison
   */
  foreach ( $list as $host=>$count ) {
    /* Skip dests with single object */
    if ( $count <= 1 ) {
      continue;
    }

    /* Build graphs if more than 1 object */
    $graph = new display_item_t();
    if($dir == "dst") {
      $graph->name = "comparison-dst-$host-src";
      $graph->title = "Comparison to $host";
    } else {
      $graph->name = "comparison-src-$host-dst";
      $graph->title = "Comparison from $host";
    }
    $graph->cdst = $host;
    $graph->dataSets = array();
    $graph->objects = array();
    $c=0;
    
    foreach ( $_SESSION["comparison_items"] as $idx=>$item ) {
      
      /* Skip other objects */
      if ( $item["$dir"] != $host ) {
        continue;
      }
      $object = get_display_object($item["object"]);
      /* Skip non comparable objects */
      if ( $object->comparable == FALSE ) {
        continue;
      }
      /* Add to destination comparison */
      if ( $graph->name != "" ) {
        $graph->name .= "-";
      }
      /* get the other end of the link */
      if($dir == "dst")
        $graph->name .= $item["src"];
      else
        $graph->name .= $item["dst"];
      $graph->objects[] = $object->name;
      $graph->category = $object->category;
      $graph->displayObject = $object;
      $graph->subType = $item["subType"];
      if ( $startTimeSec > 0 ) {
        $graph->time = $startTimeSec;

      } else {
        $graph->time = start_of_week($item["time"]);
      }

      /* Retrieve data sets */
      $dsf = $object->dataSetFunc;
      $dataSet = $dsf($item["src"], $item["dst"], $item["subType"], 
          $graph->time, $secOfData, $binSize);

      /* Loop through returned data sets */
      for ( $i=0; $i<count($dataSet); $i++ ) {
        $dataSet[$i]["color"] = $comp_colors[$c];
          $dataSet[$i]["key"] = $item["src"] . " to " . $item["dst"] . " " .
            $dataSet[$i]["key"];
        $c++;
      }
      $graph->dataSets = array_merge($dataSet, $graph->dataSets);
    } // foreach ( $_SESSION["comparison_items"] as $idx=>$item )

    /* Add to graph list */
    $cgraphs["$dir-$host"] = $graph;
  } // foreach ( $dests as $dest=>$count )
  
  return $cgraphs;

}

/* Look for display objects with common destinations in the comparison */
function comp_common_dests($startTimeSec, $secOfData, $binSize)
{

  /* Initialisation */
  $cgraphs = array();

  /* Get list of destinations in selected objects */
  $dests = comp_get_dest_list();
  $sources = comp_get_source_list();

  $cgraphs = build_comparison_graph("dst", $dests, $startTimeSec, 
      $secOfData, $binSize);

  $cgraphs = array_merge($cgraphs, 
      build_comparison_graph("src", $sources, $startTimeSec, 
        $secOfData, $binSize));

  return $cgraphs;
}


/* Return a list of all destinations in comparison list */
function comp_get_dest_list()
{

  /* Initialisation */
  $list = array();

  if ( ! isset($_SESSION["comparison_items"]) ) {
    return $list;
  }

  /* Loop through comparison items and add to list */
  foreach( $_SESSION["comparison_items"] as $idx=>$item ) {
    if ( array_key_exists($item["dst"], $list) ) {
      $list[$item["dst"]]++;
    } else {
      $list[$item["dst"]] = 1;
    }
  }

  return $list;

}

/* Return a list of all destinations in comparison list */
function comp_get_source_list()
{

  /* Initialisation */
  $list = array();

  if ( ! isset($_SESSION["comparison_items"]) ) {
    return $list;
  }

  /* Loop through comparison items and add to list */
  foreach( $_SESSION["comparison_items"] as $idx=>$item ) {
    if ( array_key_exists($item["src"], $list) ) {
      $list[$item["src"]]++;
    } else {
      $list[$item["src"]] = 1;
    }
  }

  return $list;

}


/* Return the timestamp for the start of the week the specified time is in */
function start_of_week($time) {

  $parts = getdate($time);
  $daystart = mktime(0, 0, 0, $parts["mon"], $parts["mday"],
                       $parts["year"], -1);

  /* If time is already start of week, just return midnight */
  if ( $parts["wday"] == 0 ) {
    return $daystart;
  }

  /* Return start of week */
  $ntime = $daystart - (86400 * $parts["wday"]);
  return $ntime;

}

// vim:set sw=2 ts=2 sts=2 et:
?>
