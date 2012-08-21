<?php
/*
 * AMP Data Display Interface
 *
 * Author:      Matt Brown <matt@crc.net.nz.
 * Version:     $Id: amp_display.php 2164 2012-03-02 03:16:53Z brendonj $
 *
 * Display Objects:
 * Each test module (located in the modules directory) should register a
 * number of display objects. These represent distinct graphs that the module
 * is able to display. Display objects can be registered by calling the
 * register_display_object function. Modules should call this function to
 * register all available display_objects as soon as they are loaded, which
 * is guaranteed to be before any page components are processed.
 * Each display object specifies a set of parameters that are used to display
 * and process it. These parameters are described in the display object
 * definition below.
 * Each display object should not process data for more than one type of test
 * however it may process data for many subtypes of a single test.
 *
 * Display Items:
 * Display items are specific instances of a display object that are available
 * to be displayed given the currently selected src, dst and time. The list of
 * display items is created by enumerating through the list of display_objects
 * and calling the function specified in the data_available_func parameter.
 *
 * Display object code should live inside files in the modules subdirectory.
 * When amplib.php is included in a page, it includes all files in the modules
 * subdirectory that end in _test.php, which gives those files the opportunity
 * to register the appropriate display modules.
 *
 * See the icmp_test.php module for a working example of how display objects
 * operate. Or see example_test.php for a commented example of display objects.
 *
 * Display Objects must provide a callback function that is used to display the
 * graph. This function must accept the following parameters
 *
 * item_name:           The name of the display item that is being displayed
 * startTimeSec:    Unix timestamp (in localtime) of the time to graph from
 * secOfData:       How many seconds of data to include in the graph
 * graphDate:       Date of the graph
 * xAxisType:       Type of axis... (day, week, etc)
 * scope:           The scope of the graph, PREF_LONGTERM or PREF_SHORTTERM
 *                  used to determine which preferences to look at
 *
 * Helper Functions:
 * Each test module (located in the modules directory) should register a
 * number of helper functions and strings, that assist with raw data output,
 * and the user friendly display of the test names and test sub type names.
 * Currently there are three arrays that each module should put an entry
 * into:
 *
 * test_names:          Array of test name strings indexed by test type number
 * subtype_name_funcs:  Array of function name strings indexed by test type
 *                      number describing a function that will take a subtype
 *                      string for that type and return a user friendly name.
 *                      Prototype: subtype_name($subtype)
 * raw_data_funcs:      Array of function name strings indexed by test type
 *                      number describing a function that when given a raw
 *                      data obj (as returned by ampNextObj), will return a
 *                      comma seperated list of values suitable for returning
 *                      to the user. See export_raw.php for details.
 *                      Prototype: raw_data_func($subtype, $obj)
 *
 * Test type number for the above arrays should never be hard coded and should
 * always use the constants provided (ie. ICMP_DATA, HTTP_DATA, etc.).
 *
 */

/**** Data Types ****/

class display_object_t {
  /*
  * This class does not have any member functions, but is simply a
  * placeholder for display information. Typically it would be used to
  * describe each graph that is available in the interface, however it is
  * generic enough that it can be used for other types of display objects
  * as well.
  *
  * Category and title are arbitrary fields that are displayed to the user,
  * however graphs with identical categories are grouped together.
  *
  * Parameters:
  * category          Arbitary field used to group similar graphs
  * title             Abritary field used to described the graph
  * name              Unique key for the graph, a-z and 0-9 characters only
  * validType                       The test type that this object processes
  * validSubTypes                 The test sub types that this object processes
  * dataAvailableFunc   Function to be called to ascertain if data is currently
  *                   available for the display object. Parameters below:
        *                   ($object_name, $src, $dst, $startTimeSec)
  * displayFunc       Function to be called to display the object
  * dataSetFunc       Function to be called to retrieve a processed dataset
  *                   for the object. The dataset returned should be in a
  *                   format suitable for passing to drawGraph.
  *                   Parameters Below.
  *                       ($src, $dst, $subType, $startTimeSec, $secOfData,
  *                   $binSize)
  * baseDataSetFunc   Function to be called to retrieve an unprocessed
  *                       dataset for the object. The dataset returned will have
  *                   minimal processing performed on it and will typically be
  *                   the output of getBinnedDataSet or getRawDataSet.
  *                   Parameters Below.
  *                       ($src, $dst, $subType, $startTimeSec, $secOfData,
  *                   $binSize)
  * sortOrder         Display sortOrder
  * displayPref       Name of a preference in the PREF_GLOBAL module that
  *                   determines when this object is displayed.
  * prefModule        Preference module that display preferences for this
  *                   object are registered in.
  * copts             Array of checkbox options for the graph
  *                       array(name=>array("caption"=>PREF_NAME),...)
  *                   If the array has multiple entries that are not named
  *                   "summary" an array of their names is built and is passed
  *                   to the dataSetFunc function.
  * topts             Array of textbox options for the graph
  *                   array(name=>array("caption"=>PREF_NAME),...)
  * xMax              Maximum x-axis value for graph, -1 for unspecified
  * yMax              Maximum y-axis value for graph, -1 for unspecified
  * xLabel            x-axis-label, or "" for autogenerated label
  * yLabel            y-axis label.
  * xAxisType         The xAxisType for the graph, or "" for default
  * lineGraph         'y', 'n'or "", whether to join poits with lines.
  *                   If lineGraph is "" the preference PREF_LINES is used.
  * useMap            TRUE / FALSE - Whether to allow image map linking
  * extraOpts         Extra options to pass straight to dgraf
  * comparable        TRUE / FALSE - Whether object can be compared to other
  *                   objects.
  *
  * See the top of modules/icmp_test.php for an example of how display
  * objects are registered.
  *
  */

  var $category;
  var $name;
  var $title;
  var $validType;
  var $validSubTypes;
  var $dataAvailableFunc;
  var $dataSetFunc;
  var $baseDataSetFunc;
  var $displayFunc;
  var $sortOrder;
  var $displayPref;
  var $prefModule;
  var $cOpts;
  var $tOpts;
  var $xMax;
  var $yMax;
  var $xLabel;
  var $yLabel;
  var $xAxisType;
  var $lineGraph;
  var $useMap;
  var $extraOpts;
  var $comparable;

}

class display_item_t {
  /*
  * This class does not have any member functions, but is simply a
  * placeholder for display information.
  *
  * Category and title are arbitrary fields that are displayed to the user,
  * however graphs with identical categories are grouped together.
  *
  * Parameters:
  * category        Arbitary field used to group similar graphs
  * title           Abritary field used to described the graph
  * name            Unique key for the graph, a-z and 0-9 characters only.
  *                 Should incorporate the subtype name and the name of the
  *                 display_object that this item derives from.
  * displayObject               Display object this item derives from
  * subType             The test sub type this item will display
  *
  */

  var $category;
  var $name;
  var $title;
  var $displayObject;
  var $subType;

}

/**** Global Variables ****/
$display_objects = array();
$display_cats = array();
$display_items = array();

/* Array of test names, test modules insert their name into this array */
$test_names = array();

/* Array of functions to convert subtypes to legible names */
$subtype_name_funcs = array();

/* Array of function to format a line of raw data for a test type */
$raw_data_funcs = array();

/**** Function ****/

/*
 * Called by display modules to register a new display object
 *
 * See documentation above for an explanation of each field.
 *
 */
function register_display_object($name, $category, $title, $sortOrder,
  $validType, $validSubTypes, $dataAvailableFunc, $displayFunc,
  $dataSetFunc, $baseDataSetFunc, $displayPref, $prefModule, $copts,
  $topts, $xMax, $yMax, $xLabel, $yLabel, $xAxisType, $lineGraph,
  $useMap, $extraOpts, $comparable)
{

  global $display_objects;

  $info = new display_object_t();
  $info->name = $name;
  $info->category = $category;
  $info->title = $title;
  $info->sortOrder = $sortOrder;
  $info->validType = $validType;
  $info->validSubTypes = $validSubTypes;
  $info->dataAvailableFunc = $dataAvailableFunc;
  $info->displayFunc = $displayFunc;
  $info->dataSetFunc = $dataSetFunc;
  $info->baseDataSetFunc = $baseDataSetFunc;
  $info->displayPref = $displayPref;
  $info->prefModule = $prefModule;
  $info->cOpts = $copts;
  $info->tOpts = $topts;
  $info->xMax = $xMax;
  $info->yMax = $yMax;
  $info->xLabel = $xLabel;
  $info->yLabel = $yLabel;
  $info->xAxisType = $xAxisType;
  $info->lineGraph = $lineGraph;
  $info->useMap = $useMap;
  $info->comparable = $comparable;
  
  $info->extraOpts = array();

  /*
   * TODO:
   * Extra options have become an array to make it easier to work with the
   * new graphing functions, but all the register functions pass extra 
   * options in as a string. This fix should only be considered temporary
   * until someone goes through all the modules to make the options into
   * an array.
   */
  if ( strlen($extraOpts) > 0 ) {
    $options = explode("\n", $extraOpts, -1);
    foreach ( $options as $line ) {
      $opt = explode(" ", $line);
      $info->extraOpts[$opt[0]] = $opt[1];
    }
  }

  /* Insert an entry into the display objects array for this graph */
  $display_objects[$name] = $info;

}


/*
 * Return the specified display_object
 */
function get_display_object($object_name) {

  global $display_objects;

  if ( array_key_exists($object_name, $display_objects) ) {
    return $display_objects[$object_name];
  }

  return NULL;

}

/*
 * Intialises the list of display items for a page.
 */
function initialise_display_items($src, $dst, $startTimeSec)
{

  global $display_objects, $display_items, $display_cats;

  /* Loop through display objects, call data available func. Results are
   * the available display items.
   */
  foreach ( $display_objects as $name=>$object ) {
    $da = $object->dataAvailableFunc;
    $items = $da($name, $src, $dst, $startTimeSec);
    $display_items = array_merge($items, $display_items);
    if ( $items != array() ) {
      if ( ! in_array($object->category, $display_cats) ) {
        $display_cats[] = $object->category;
      }
    }
  }
  uasort($display_items, "cmp_display_items");
}

function cmp_display_items($ai, $bi)
{

        $a = get_display_object($ai->displayObject);
        $b = get_display_object($bi->displayObject);
        if ( $a->sortOrder == $b->sortOrder ) {
          /* if the object sort order is the same, use the name of the 
           * actual display item (not the display object!)
           */
          return (strcmp($ai->name, $bi->name));
        }

        return ($a->sortOrder > $b->sortOrder) ? 1 : -1;

}

/*
 * Displays a graph and associated headings / options for the specified
 * display item.
 *
 * Much of what happens in this function is governed by the settings of the
 * display object underlying the specified display item.
 *
 */
function item_display($item_name, $startTimeSec, $secOfData, $graphDate,
        $xAxisType, $scope, $imagemap, $graph_number=0)
{

  global $src, $dst;

  $graphOptions = "";
  $xLabel = "";
  $yLabel = "";
  $xMax = -1;
  $yMax = -1;
  $summary = PREF_FALSE;

  $item = get_display_item($item_name);
  if ($item == NULL) {
    graphError("icmp_display called with invalid display item! - $item_name");
    return;
  }
  $object = get_display_object($item->displayObject);

  /* Send the preceding part of the page to the browser */
  flush();

  /* Adjust the binsize (in minutes) depending on the length of the graph. 
   * 288 is a magic number that is how many data points are shown in the 
   * daily and weekly graphs with default bin sizes
   * TODO: confirm this is good, remove the bin size preferences, deal to
   * magic numbers
   */
  $binSize = $secOfData / 288.0 / 60.0;
  $binSecs = $binSize * 60;

  /* Determine which components are selected for the graph */
  $elements = array();
  $index = 0;

  foreach ( $object->cOpts as $name=>$parts ) {
    if ( $parts == array() ) {
      /* Not an option, add to elements list */
      $elements[] = $name;
    } else {
      /* Check if option is selected before adding to elements list */
      list($caption, $pref) = $parts;
      $value = get_option_value($item_name, $object->prefModule,
        $pref, $name, $scope, $index);

      if ( $value == PREF_TRUE && $name != "summary" ) {
        /* Option is selected */
        $elements[] = $name;
        if ( strlen($graphOptions)>0 ) {
          $graphOptions .= "-";
        }
        $graphOptions .= "$name";
      }
      else if($name == "summary") {
        // save the value of summary so we can use it later on
        $summary = $value;
      }
    } // ( $parts == array() )
    $index++;
  } // foreach ( $object->cOpts as $name=>$parts )

  /* Retrieve the dataSet */
  $dsf = $object->dataSetFunc;
  if ( ! function_exists($dsf) ) {
    graphError("Dataset function does not exist for $title! - $dsf");
    return;
  }

  /* work out where we are going from and to so we can build dataset array */
  $sources = expandSites($src);
  $destinations = expandSites($dst);

  /* regardless of how many sources and destinations there are, the individual
   * datasets will always be given inside $dataSets[$source][$destination],
   * (even if there is only a single one of each). The individual data sets
   * retain the same format they used to when only one-to-one graphs were used.
   */
  $dataSets = array();
  foreach($sources as $source) {
    foreach($destinations as $destination) {
      if ( $source == $destination )
        continue;
      if ( $elements != array() ) {
        $dataSet = $dsf($source, $destination, $item->subType, $startTimeSec,
            $secOfData, $binSize, $elements);
      } else {
        $dataSet = $dsf($source, $destination, $item->subType, $startTimeSec,
            $secOfData, $binSize);
      }
      if ( count($dataSet) > 0 )
        $dataSets[$source][$destination] = $dataSet;
    }
  }

  /* Setup y-axis maximum value */
  if ( $object->yMax != -1 ) {
    $yMax = $object->yMax;
  } else {
    $yMax = get_option_value($item_name, $object->prefModule, PREF_YMAX,
      "ymax", $scope);
    if ($yMax>0) {
      $graphOptions .= "-ymax$yMax";
    }
  }

  /* Determine the xAxis label */
  if ( $object->xLabel == "" && $dataSets != array() ) {
    $indexa = array_pop(array_keys($dataSets));
    $indexb = array_pop(array_keys($dataSets[$indexa]));
    if ( isset($dataSets[$indexa][$indexb][0]) )
      $info = $dataSets[$indexa][$indexb][0]["info"];
    else
      $info = $dataSets[$indexa][$indexb]["info"];
    $xLabel = xLabelByTime($info, $startTimeSec + $secOfData, $scope);
  } else {
    $xLabel = $object->xLabel;
  }

  /* Setup x Axis Type */
  if ( $object->xAxisType != "" ) {
    $xAxisType = $object->xAxisType;
  }

  /* Fill in other options if not set already */
  if ( $object->lineGraph == "" ) {
    $lineGraph = get_preference($object->prefModule, PREF_LINES, $scope);
  } else {
    $lineGraph = $object->lineGraph;
  }

  /* Display Graph Option Bar */
  // only show the options bar and title if we are looking at the 
  // first graph in a series of similar graphs
  if($graph_number < 1)
  {
    $title = $item->category . " - " . $item->title;
    echo "<h2 class=\"graphtitle\">";

    // if we arent displaying a specific graph, make the title a link to
    // the graph that is being displayed
    if(!isset($_REQUEST["graph"])) {
      global $date;
      $optstring = "";
      $ymaxstring = "";
      // unique id as we have many different graph types
      $id = str_replace(" ", "", $item->category . "_" . $item->title);
      if ( isset($_REQUEST["hidden_".$id]) )
        $optstring = "&opts=" . $_REQUEST["hidden_" . $id];
      if ( isset($_REQUEST["hidden_" . $id . "_ymax"]) )
        $ymaxstring = "&ymax=". $_REQUEST["hidden_" . $id . "_ymax"];

      echo "<a href='graph.php?" .
        "graph=" . $item->name . 
        "&src=" . $_REQUEST["src"] . 
        "&dst=" . $_REQUEST["dst"] . 
        "&date=". $date . 
        $optstring .
        (isset($_REQUEST["rge"])?("&rge=" . $_REQUEST["rge"]):"") .
        $ymaxstring . "' " .
        "id='" . $id . "'>";
    }
    else
      // only need the one id as there is only a single update button
      $id='updatelink';

    echo htmlspecialchars($title)."</a><br />\n";

    draw_options_bar($item_name, $object->cOpts, $object->tOpts, 
      $object->prefModule, $scope, $id);
    echo "</h2>\n";
  }

  /* display a heading above every graph with the start date in it*/
  echo "<br /><br />";
  echo "<div id=\"" . htmlspecialchars($item->displayObject . "-" . 
      $graph_number) . "\">";
  echo "<h3>";
  // need a nice way to check for time period here without having to
  // parse the rge option, or hardcoding numbers here?
  if($secOfData == 604800) {
    echo "Week starting ";
  } else if($secOfData >= 2419200) {
    echo "Month starting ";
    // set the number of divisions in the graph to match the number 
    // of days in the month being shown
    $object->extraOpts["X_BIG_DIVIS_COUNT"] = date("t", $startTimeSec);
  }
  // convert timestamp to localtime for display
  echo date("D M d Y", $startTimeSec) . "</h3>";
  echo "</div\n>";

  echo "<div class=\"graph\">\n";
  $object->extraOpts["X_GAP_THRESHOLD"] = $binSize * 60;
  $object->extraOpts["GRAPH_NUMBER"] = $graph_number;
  
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
    $df($src, $dst, $graphDate, $graphOptions, $item_name, $lineGraph,
      $xAxisType, $object->useMap, $xLabel, $object->yLabel, $object->xMax,
      $yMax, $dataSets, $object->extraOpts, $scope, $imagemap);
  }
  echo "</div>";

  unset($dataSets);

  /* Display Summary */
  if ( $summary == PREF_TRUE ) {
    /* Get base dataset */
    $bdsf = $object->baseDataSetFunc;
    if ( ! function_exists($bdsf) ) {
      graphError("Base dataSet function does not exist for $title " .
        "$bdsf");


    } else if(strncmp($item_name, "tput", 4) == 0) {
      // if this is a tput test we want to show summaries for both dirs
      // which means we have to use a different data function
      $bds = $dsf($src, $dst, $item->subType, $startTimeSec,
        $secOfData, $binSize);
      foreach($bds as $dir) {
        if ( $dir["plotStats"] ) {
          if(sizeof($bds) > 1)
            $displaystring = " <font color='blue'>outgoing</font> summary";
          else
            $displaystring = " summary";

          printSummaryStats($item->title . $displaystring,
            $dir["plotStats"], "Kbit/s");
        }
        if ( $dir["plotStats_reverse"] ) {
          if(sizeof($bds) > 1)
            $displaystring = " <font color='red'>incoming</font> summary";
          else
            $displaystring = " summary";

          printSummaryStats($item->title . $displaystring,
              $dir["plotStats_reverse"], "Kbit/s");
        }
      }

    } else if(strncmp($item_name, "http2", 5) == 0) {
      /* make this work properly in the case where $dst is a mesh rather than
       * an individual destination. Can't clobber $dst because it might be
       * needed later on.
       */
      $tmpdst = $dst;
      if ( isMesh($dst) ) {
        $parts = explode("-", $item_name, 5);
        $tmpdst = $parts[4];
      }
      // if this is an http2 test we want to show summaries for a few different
      // which means we have to use a different data function
      $bds = $dsf($src, $tmpdst, $item->subType, $startTimeSec,
        $secOfData, $binSize);

      /* these colours should match the ones used by the arrival graph */
      $label = " <font color='#07a1e8'>combined lookup time</font> summary";
      printSummaryStats($label, $bds[0]["summary"]["lookup"], "ms");
      
      $label = " <font color='#0f43d0'>combined connect time</font> summary";
      printSummaryStats($label, $bds[0]["summary"]["connect"], "ms");
      
      $label = " <font color='#16e5b8'>combined setup time</font> summary";
      printSummaryStats($label, $bds[0]["summary"]["setup"], "ms");
      
      $label = " <font color='#1e87a0'>combined download time</font> summary";
      printSummaryStats($label, $bds[0]["summary"]["total"], "ms");

    } else {
      // this is the normal case, display summary stats if available
      $bds = $bdsf($src, $dst, $item->subType, $startTimeSec,
        $secOfData, $binSize);
      if ( $bds["plotStats"] ) {
        printSummaryStats($item->title . " summary", $bds["plotStats"], "ms");
      }
    } // ( ! function_exists($bdsf) )
  } // ( $val == PREF_TRUE )

  /* Display Graph Links */
  drawGraphLinks($item_name, $src, $dst, $startTimeSec, $scope, $graphDate);
}

/*
 * Generate a caption for displaying on an x-axis describing a timescale
 */
function xLabelByTime($info, $periodEnd, $scope)
{
  $tzString = $info->timeZone;
  $offsetHour = (int)($info->zoneUtcOffset / 3600);
  $offsetMin = (int)(($info->zoneUtcOffset % 3600) / 60);
  if ( $info->zoneUtcOffset < 0 ) {
    $offsetSign = '-';
    $offsetHour *= -1;
  } else {
    $offsetSign = '+';
  }

  //The periodEnd < 1140000000 is for the NLANR AMP mesh as there is a 
  //problem with dst handling in the old data format which we've deemed 
  //not worth time to fix at the moment
  if ( $info->nextChangeTime > $periodEnd || $info->nextChangeTime == -1 ) {
    $dstNote = "";
  } else {
    if ( $scope == PREF_SHORTTERM ) {
      $dstNote = " Note: DST changes at " .
	gmstrftime("%l:%M%p", $info->nextChangeTime);
    } else if ($scope == PREF_LONGTERM ) {
      $dstNote = " Note: DST changes at " .
	gmstrftime("%l:%M%p, %a", $info->nextChangeTime);
    } else {
      $dstNote = "";
    }
  }
  $xLabel = sprintf("Time of day %s (UTC%s%02d:%02d)%s", $tzString,
    $offsetSign, $offsetHour, $offsetMin, $dstNote);

  return $xLabel;

}

/*
 * Return the specified display_item
 */
function get_display_item($item_name)
{

  global $display_items;

  if (array_key_exists($item_name, $display_items)) {
    return $display_items[$item_name];
  }

  return NULL;

}

/*
 * Checks whether a display item should be displayed.
 * Takes into account preferences as well as forms submitted on the page
 */
function is_item_displayed($item_name, $display_pref, $pageClass)
{

  global $page_settings, $display_items;

  /* Check preferences to see if this object is to be displayed */
  $dpref = get_preference(PREF_GLOBAL, $display_pref, $pageClass);

  /* Check for POST/GETed override */
  $oname = build_item_formname($item_name);
  
  if ( isset($page_settings["graph-selection"]) ) {

    /* if enabled by a POST/GET then display graph */
    if ( isset($page_settings["graph-selection"][$oname]) ) {
      return TRUE;

    } else {

      /* create an array of all the form names of valid graphs */
      $form_names = array_flip(array_map("build_item_formname", 
            array_keys($display_items)));

      /* If any enabled graph is valid, don't display this one. 
       * However, if nothing enabled by POST/GET is currently valid
       * then we want to fall through and display any graphs that are
       * enabled by preferences.
       */
      foreach ( $page_settings["graph-selection"] as $name=>$status ) {
        if ( array_key_exists($name, $form_names) && $status=="on" ) {
          return FALSE;
        }
      }
    }
  }

  /* the graph type is enabled by preferences and wasn't overridden */
  if ($dpref == PREF_TRUE) {
    return TRUE;
  }

  return FALSE;

}

/*
 * Determines the value of the requested option for the specified
 * graph. Taking into account preferences and page form submissions.
 */
function get_option_value($item_name, $pref_module, $option_name,
                          $short_name, $pageClass, $index=-1)
{
  global $page_settings;
  
  // make sure this is in the right order...
  if($short_name == "ymax") {
    if(isset($_REQUEST["ymax"]) && $_REQUEST["ymax"] > 0)
      return $_REQUEST["ymax"];
  }
  
  /* 
   * Check the options string first - if this is set its a fixed 
   * view of the graph that we don't want to change
   */
  if(isset($_REQUEST["opts"]) && $index >= 0) {
    $option_string = "";

    // build up a binary string so we can just do a lookup of index
    for($i=0; $i<strlen($_REQUEST["opts"]); $i++) {
      // each character is worth 4 bits of data, and is based from 'A'
      $tmp_string = decbin(ord($_REQUEST["opts"][$i])-65);
      $option_string .= str_pad($tmp_string, 4, "0", STR_PAD_LEFT);
    }

    if($option_string[$index] == 1)
      return TRUE;
    return FALSE;
  }
  

  /* Check preferences to get global value */
  $dpref = get_preference($pref_module, $option_name, $pageClass);

  /* Check for POST/GETed override */
  $oname = build_option_formname($item_name, $short_name);
  if ( isset($_REQUEST["do"]) ) {
    if ( isset($page_settings["$item_name-options"]) ) {
      if ( isset($page_settings["$item_name-options"][$oname]) ) {
        return $page_settings["$item_name-options"][$oname];
      } else {
        //echo "Option not set!<br>";
        return FALSE;
      }
    } // ( isset($page_settings["$item_name-options"]) )
  } // ( isset($_REQUEST["do"]) )

  if ($dpref == PREF_TRUE) {
    //echo "Preference is set #$dpref#<br>";
    return $dpref;
  }

  //echo "Preference not Set!<br>";
  return FALSE;
}

/*
 * Build a name to be used in HTML elements for the specified display item
 * option.
 */
function build_option_formname($item_name, $option_name)
{
  return "display-$item_name-$option_name";
}

/*
 * Build a name to be used in HTML elements for the specified display item.
 */
function build_item_formname($item_name)
{
  $item_name = str_replace(".", "_", $item_name);
  return "show-$item_name";
}

/*
 * Displays the graph options bar
 *
 * item_name:     The name parameter of the display item that this options bar
 *              controls
 * copts:       An array specifying the checkboxes to display. The format
 *              of this array is (display_name=>preference_name), where
 *              display_name is the text to be used as a caption, this is
 *              also passed through to amplib to determine which lines to
 *              display on the graph. preference_name is used to set the
 *              default state of the checkbox and must be a valid preference
 *              name.
 * topts:       An array specifying the textboxes to display. The format of
 *              this array is (display_name=>(box_size,preference_name), where
 *              display_name and preference_name are as per the description
 *              above, and box_size is how wide to make the input box.
 * prefClass:   The preference module to request preferences in.
 * scope:       The scope of the graph, PREF_LONGTERM or PREF_SHORTTERM
 *              used to determine which preferences to look at
 */
function draw_options_bar($item_name, $copts, $topts, $prefClass, $scope, $id)
{

  global $page_name;

  if ( $copts == array() && $topts == array() ) {
    return;
  }

  /* Form Setup */
  echo "<form action=\"$page_name\" method=\"post\" class=\"graphoptions\">\n";
  echo "<input type=\"hidden\" name=\"do\" value=\"yes\">\n";
  echo "<input type=\"hidden\" name=\"form\" value=\"" .
      "$item_name-options\">\n";
  echo "<input type=\"hidden\" name=\"form_prefix\" value=\"" .
          "display-\">\n";

  // the nicest way I could find to get the javascript options array
  // set up was to create all the values on the fly as the html page
  // is built. Unfortunately this means there are small patches of
  // javascript like this everywhere.
  echo "<script type='text/javascript' language='javascript'>\n";
  echo "options[\"$id\"] = new Array();\n";
  echo "</script>\n";

  /* The actual bar */
  echo "<span class=\"graphoptions\">Options:&nbsp;&nbsp;&nbsp;&nbsp;";
  $count = 0;
  /* Checkboxes */
  $index = 0;
  foreach ($copts as $name=>$parts) {
    list($caption, $pref) = $parts;
    $oname = build_option_formname($item_name, $name);
    $value = get_option_value($item_name, $prefClass, $pref, 
      $name, $scope, $count);


    if ($value == PREF_TRUE) {
      /* Option is selected */
      $checked = " checked";
    } else {
      /* Option is not selected */
      $checked = "";
    }

    /* Display the actual checkbox */
    echo "<label>\n";
    echo "<input type=\"checkbox\" class=\"graphoptions\" name=\"" .
        "$oname\" value=\"" . PREF_TRUE . "\" " .
        "onclick='updateOptions(\"$id\", $count, this.checked)'" .
        "$checked>&nbsp;$caption&nbsp;&nbsp;&nbsp;\n";
    echo "</label>\n";

    // assign the value of this option to the appropriate place in
    // the javascript array we created just above
    echo "<script type='text/javascript' language='javascript'>\n";
    echo "options[\"$id\"][$count] = " . ($checked?"true":"false");
    echo "</script>\n";
    $count++;
  }
  /* Text boxes */
  foreach ($topts as $name=>$parts) {
    list($caption, $size, $pref) = $parts;
    $oname = build_option_formname($item_name, $name);
    $value = get_option_value($item_name, $prefClass, $pref, $name,
        $scope, $count);
    /* Display text box */
    echo "<input type=\"text\" size=$size class=\"graphoptions\" name=\"" .
        htmlspecialchars($oname)."\" " .
        "onchange='updateTextfields(\"".htmlspecialchars($id)."\", \"".htmlspecialchars($oname)."\", this.value)' " .
        "value=\"".htmlspecialchars($value)."\">&nbsp;".htmlspecialchars($caption)."&nbsp;&nbsp;";
    
  }

  // if we just have a single graph we have a regular link
  if(isset($_REQUEST["graph"])) {
    global $date;
    $ymaxstring = (isset($_REQUEST["ymax"]))?"&ymax=".$_REQUEST["ymax"]:"";
    $optstring = (isset($_REQUEST["opts"]))?"&opts=".$_REQUEST["opts"]:"";
    echo "<a href='graph.php?" .
      "graph=". urlencode($_REQUEST["graph"]) .
      "&src=" . urlencode($_REQUEST["src"]) .
      "&dst=" . urlencode($_REQUEST["dst"]) .
      "&date=". urlencode($date) .
      $optstring .  $ymaxstring .
      (isset($_REQUEST["rge"])?("&rge=". $_REQUEST["rge"]):"&rge=1-day") .
      "' id='".htmlspecialchars($id)."'>Update Graph &gt;&gt;</a>\n";
    } else {

        // if we have lots of graphs, then for now we use the old
        // method that involves a form and submit button...
        echo "<input type='hidden' name='hidden_".htmlspecialchars($id)."' " .
          "value='' id=hidden_".htmlspecialchars($id)." />\n";
        if ( isset($_REQUEST["hidden_" . $id . "_ymax"]) ) {
          echo "<input type='hidden' " . 
            "name='hidden_" . htmlspecialchars($id) . "_ymax' " .
            "value='".htmlspecialchars($_REQUEST["hidden_".$id."_ymax"])."' " .
            "id='hidden_" . htmlspecialchars($id) . "_ymax' />\n";
        }
        
        // all elements like this have the same name so i can
        // iterate over them all and set the range on all just
        // from the one select box
        echo "<input type='hidden' name='rge' " .
          "value='".(isset($_REQUEST["rge"])?(htmlspecialchars($_REQUEST["rge"])):"1-day") .
          "' id='hidden_" . htmlspecialchars($id) . "_rge' />\n";
  
        /* Submit Button */
        echo "<input type=\"submit\" class=\"graphoptionssubmit\" " .
          "value=\"Refresh Graph &gt;&gt;\">\n";
    }

  /* Finish Up */
  echo "</span></form>\n";

}

/* Displays a link to the reverse set of graphs if it exists */
function displayReverseLink($src, $dst, $date="", $gstr="", $optstr="")
{

  $sites = ampSiteList("");
  if (in_array($dst, $sites->srcNames)) {
    $dests = ampSiteList($dst);
    if (in_array($src, $dests->srcNames)) {
      echo "&nbsp;&nbsp;|&nbsp;&nbsp;<a href=\"" .
        $_SERVER['PHP_SELF'].'?src='.urlencode($dst).'&amp;dst='.urlencode($src).$date.$gstr.$optstr.'">reverse</a>';
    }
  }

}

// vim:set sw=2 ts=2 sts=2 et:
?>
