<?php
/*
 * AMP Graph Generation
 *
 * Author:      Matt Brown <matt@crc.net.nz.
 * Version:     $Id: amp_graphs.php 2159 2012-02-21 22:47:12Z brendonj $
 *
 */

/*
 * Draws a graph.
 *
 * This function can be used to draw any sort of graph that plots datasets
 * with X and Y values... If you need to draw a graph with more sophisticated
 * input such as a traceroute / monmap graph, then you can simply create a
 * function with identical parameters to this one, to implement your graph
 * and pass at as the displayFunc parameter of the required display_item
 *
 * src              Source amplet
 * dst              Destination amplet
 * date             YYYY-MM-DD of start of graph
 * graphOptions     textual string describing graph options present
 * itemName             Name of the display item that this graph is for
 * lineGraph        Whether to draw lines connecting dots 'y' or 'n'
 * xAxisType        Passed directly to dgraf's X_AXIS_TYPE option
 * usemap           Whether to output HTML map options in the img tag
 * xLabel                 Textual label describing X Axis
 * yLabel           Textual label describing Y Axis
 * xMax             Cutoff value for X scale, -1 if not required
 * yMax             Cutoff value for Y scale, -1 if not required
 * dataSet              Multidimensional array containing the dataset to
 *                            be graphed. The format of this array is as follows:
 *
 *              $dataSet[idx][color] = "color to display first line as"
 *              $dataSet[idx][key] = "textual key for the first dataset"
 *              $dataSet[idx][data][idx][x] = X-axis value
 *              $dataSet[idx][data][idx][y] = Y-Axis value
 *                        ... [x]
 *                            [y]
 *             ... [color] = "color to display second line as"
 *                 [key] = "textual key for the second dataset"
 *                 [data]
 *
 *              idx represents numeric indices
 *
 * extraOpts                Extra options to be passed directly to dgraf
 * scope                              Preference scope of the current page
 */
function drawGraph($src, $dst, $date, $graphOptions, $item_name, $lineGraph,
  $xAxisType, $usemap, $xLabel, $yLabel, $xMax, $yMax, $dataSet,
  $extraOpts, $scope, $mapname="")
{
  foreach ( array_keys($dataSet) as $src) {
    $destinations = array_keys($dataSet[array_pop(array_keys($dataSet))]);
    foreach ( $destinations as $dst ) {

    if ( $src == $dst )
      continue;

    echo '<h4>' . htmlspecialchars($src) . ' to ' .
      htmlspecialchars($dst) . '</h4>' . "\n";
    
    /* make sure there is actually data */
    if ( !isset($dataSet[$src][$dst]) ) {
      graphError("No data available for " . 
        htmlspecialchars($src . " to " . $dst . "."));
      continue;
    }
    
    /* Build graph file name, check for cached image */
    $fileName = cacheFileName($xAxisType, $item_name, $src, $dst, $date,
        $graphOptions, $cached);
    if ( $fileName == "" ) {
      continue;
    }

    /* If longterm graph - use imagemap */
    if ( $scope == PREF_LONGTERM && $usemap ) {
      if ( $mapname != "" ) {
        $map = " usemap=\"#" . htmlspecialchars($mapname) . "\"";
      } else {
        $map = " usemap=\"#weekmap-$date\"";
      }
    } else {
      $map = "";
    }

    /* Return cached image if available */
    if ( $cached ) {
      echo "<img src=\"$fileName\"$map>";
      continue;
    }

    /* Setup graph parameters */
    /*if ( $forceYMax ) {
      $yMax = $forceYMax;
    } else */if ( $yMax != 0 ) {
      $yMax = $yMax;
    } else {
      $yMax = -1;
    }

    /* open a pipe to the graph tool */
    $dgraph = popen("$GLOBALS[dgraf] -i - -o $fileName", "w");
    if ( ! $dgraph ) {
      graphError("Could not open dgraf command");
      return -1;
    }

    /* Setup Graph Config */
    fputs($dgraph, "WITH_GAP true\n");
    fputs($dgraph, "X_GAP_THRESHOLD 20\n");
    if ( $lineGraph == 'y' ) {
      fputs($dgraph, "PLOT_TYPE line\n");
    } else {
      fputs($dgraph, "PLOT_TYPE point\n");
    }

    foreach ($extraOpts as $option => $value) {
      /* some options end up here that aren't for dgraph, strip them */
      switch($option) {
        case "GRAPH_NUMBER": break;
        default: fputs($dgraph, "$option $value\n"); break;
      };
    }

    /* Setup Axes and Labels */
    fputs($dgraph, "X_AXIS_TYPE $xAxisType\n");
    fputs($dgraph, "Y_HEADER $yLabel\n");
    fputs($dgraph, "X_HEADER $xLabel\n");
    if ( $xMax != -1 ) {
      fputs($dgraph, "X_MAX $xMax\n");
    }
    if ( $yMax != -1 ) {
      fputs($dgraph, "Y_MAX $yMax\n");
    }

    /* Draw the datasets */
    foreach ( $dataSet[$src][$dst] as $set ) {

      /* Dataset initialisation */
      fputs($dgraph, "\nDATASET_START\n");
      if ( $set["color"] != "" ) {
        fputs($dgraph, "PEN_COLOR " . $set["color"] . "\n");
      }

      /* Display key if more than one dataset */
      if ( count($dataSet[$src][$dst]) > 1 || 
          $item_name == "nzrs" || $item_name == "dns2" ) {
        fputs($dgraph, "KEY " . $set["key"] . "\n");
      }

      /* Output the data */
      for ( $i = 0; $i<count($set["data"]); $i++ ) {
        if ( isset($set["data"][$i]["y"]) ) {
          $x = $set["data"][$i]["x"];
          $y = $set["data"][$i]["y"];
          fputs($dgraph, "$x $y\n");
        }
      }

    }

    /* Close dgraph connection */
    pclose($dgraph);

    /* Return to browser */
    echo "<img alt=\"graph\" src=\"$fileName\"$map>\n";
  }
  }
}

/* Draws the a time series graph using the flot API */
function drawFlotTimeSeries($src, $dst, $date, $graphOptions, $item_name, $lineGraph,
  $xAxisType, $usemap, $xLabel, $yLabel, $xMax, $yMax, $dataSet,
  $extraOpts, $scope, $mapname="", $statName)
{
  // need to use these later:
  $srcOrig = $src;
  $dstOrig = $dst;

  // get the timezone offset
  $offset = get_timezone_offset('UTC', $timeZone) * 1000;
  
  // get time range
  $xMin = strtotime($date . " UTC") * 1000;
  $xMin -= $offset;
  
  // TODO: find a better way to get the test name
  // get the test's name
  $item = get_display_item($item_name);
  $test = explode("-" , $item->displayObject);
  $gType = $test[0];
  $destinations = array_keys($dataSet[array_pop(array_keys($dataSet))]);
  $srcList = array();
  $dstList = array();

    foreach ( array_keys($dataSet) as $src) {

      foreach ( $destinations as $dst ) {
         
      if ( $src == $dst )
        continue;
      
      if (isset($dataSet[$src][$dst])) {
        if (!in_array($src, $srcList)) $srcList[] = $src;
        if (!in_array($dst, $dstList)) $dstList[] = $dst;

        if (count(array_keys($dataSet)) >= 1 && count($destinations) > 1) 
          $label = $dst;
        else 
          $label = $src;

        // get test's subtype details
        $obj = $dataSet[$src][$dst][0]["info"];
        $gSubtype = $obj->dataSubtype;
        
        // nzrs tests don't have a subtype 
        if ($gType == "nzrs")
          $gSubtype = "nzrs";
        
        $scheme = (isset($_SERVER["HTTPS"]) && 
            $_SERVER["HTTPS"] == "on") ? "https" : "http";

        $ajax[$src][$dst] = array("url" => "$scheme://" . 
          htmlspecialchars($_SERVER["SERVER_NAME"]) . "/data/json/" . 
          htmlspecialchars($src) . "/" . 
          htmlspecialchars($dst) . "/" .
          htmlspecialchars($gType) . "/" . 
          htmlspecialchars($gSubtype) . "/"
          , "src" => $src
          , "dst" => $dst
          , "label" => $label);
        }
      }
    }
      
      ?>
        <link rel="stylesheet" href="js/flot/flot.css" type="text/css">
        <?php //echo "<script type=\"text/javascript\" src=\"modules/" . htmlspecialchars($gType) . "_test.js\"></script>"; ?>  
        <!--<script language="javascript" type="text/javascript" src="http://code.jquery.com/jquery-1.8.0.min.js"></script>-->
        <script language="javascript" type="text/javascript" src="http://code.jquery.com/jquery-1.4.min.js"></script>
        <script type="text/javascript" src="modules/<?php echo htmlspecialchars($gType); ?>_test.js"></script>
        <!--[if IE]><script language="javascript" type="text/javascript" src="js/flot/excanvas.min.js"></script><![endif]-->    
        <script language="javascript" type="text/javascript" src="js/flot/jquery.flot.js"></script>
        <script language="javascript" type="text/javascript" src="js/flot/jquery.flot.plugins.js"></script>
        <script language="javascript" type="text/javascript" src="js/flot/AmpGraph.js"></script>

       <!-- graph page element -->
      <div id="<?php echo 
        htmlspecialchars($item->displayObject) . 
        "-" . htmlspecialchars(str_replace(array(":", " ", "."), "-", $srcOrig)) . 
        "-" . htmlspecialchars(str_replace(array(":", " ", "."), "-", $dstOrig)) . 
        "-graph" . htmlspecialchars($extraOpts["GRAPH_NUMBER"]); ?>"></div> 

      <!-- graph script begins -->
      <script type="text/javascript">
        $(function () {
        var type = "<?php echo $xAxisType ?>";
        var container = $("#<?php echo 
        htmlspecialchars($item->displayObject) . 
        "-" . htmlspecialchars(str_replace(array(":", " ", "."), "-", $srcOrig)) . 
        "-" . htmlspecialchars(str_replace(array(":", " ", "."), "-", $dstOrig)) . 
        "-graph" . htmlspecialchars($extraOpts["GRAPH_NUMBER"]); ?>");
        var ajax = <?php echo json_encode($ajax); ?>;
        var srcList = <?php echo json_encode($srcList); ?>;
        var dstList = <?php echo json_encode($dstList); ?>;
        var startMinX, startMaxX;
        var statNames = ["<?php echo htmlspecialchars($statName); ?>"];
        startMinX = <?php echo htmlspecialchars($xMin); ?>;
        switch (type) {
          case "day":
            startMaxX = startMinX + MS_1DAY;
            break;
          case "week":
            startMaxX = startMinX + MS_1WEEK;
            break;
          case "month":
            startMaxX = startMinX + MS_1MONTH;
            break;
        }
        var offset = <?php echo htmlspecialchars($offset); ?>;
        var endTime = new Date().setTimezone("GMT").getTime();
        var startTime = Math.round(( (new Date().getTime() - MS_1YEAR * 10)));
      
        <?php
          // TODO: uncomment this when we switch to the new database
          //$bounds = AEI::get()->GetBoundaries($src, $dst, $obj->dataType, $obj->dataSubtype);
          //echo "startTime = " . $bounds['startTime'] . ";\n";
          //echo "endTime = " . $bounds['endTime'] . ";\n";
        ?>
        
        <?php
          // see if this is a link to a specific graph
          if (!empty($_REQUEST["use"])) {
            
            echo " var linked = true;";
            
            // go through each option and see if it is available
            
            if (!empty($_REQUEST["xmin"]))
              echo "startMinX = " . htmlspecialchars($_REQUEST["xmin"]) . ";";
              
            if (!empty($_REQUEST["xmax"]))
              echo "startMaxX = " . htmlspecialchars($_REQUEST["xmax"]) . ";";
              
            if (!empty($_REQUEST["ymax"]))
              echo "gyMax = " . htmlspecialchars($_REQUEST["ymax"]) . ";";
              
            if (!empty($_REQUEST["ymin"]))
              echo "gyMin = " . htmlspecialchars($_REQUEST["ymin"]) . ";";
              
            if (!empty($_REQUEST["boxes"])) {
              // get all the check boxes that are ticked
              echo "statNames = [";        
              $boxurl = htmlspecialchars($_REQUEST["boxes"]);
              $boxes = explode("_", $boxurl);
              for ($i = 0; $i < count($boxes); $i++) {          
                echo "\"" . htmlspecialchars($boxes[$i]) . "\"";
                if ($i < count($boxes) - 1)
                  echo ", ";
              }
              
              echo "];";
            }      
          } else {
            echo "var linked = false;";
          }
        ?>
        
        $("<?php echo "#" . htmlspecialchars($item->displayObject) . 
            "-" . htmlspecialchars($extraOpts["GRAPH_NUMBER"]); ?>").remove();

        var drawGraph = function(ajaxUrls, heatmap, title, yLabel) {
          var pageUrl = "<?php 
              $scheme = (isset($_SERVER["HTTPS"]) && 
                $_SERVER["HTTPS"] == "on") ? "https" : "http";
              
              echo 
              "$scheme://" . htmlspecialchars($_SERVER["SERVER_NAME"]) . 
              "/graph.php?" . 
              "graph=" . htmlspecialchars($item->name) . 
              "&src=" . htmlspecialchars($srcOrig) . 
              "&dst=" . htmlspecialchars($dstOrig) . 
              "&date=". htmlspecialchars($date) . 
              (isset($_REQUEST["rge"])?(
                "&rge=" . htmlspecialchars($_REQUEST["rge"])):"")
              ?>";

          var module = new <?php echo htmlspecialchars($gType); ?>Module(statNames, ajaxUrls, offset);
          // build up the options
          var options = {
            statName: statNames[0],
            ajaxUrl: ajaxUrls,
            module: module,
            pageUrl: pageUrl,
            heatmap: { legend: { label: "<?php echo htmlspecialchars($yLabel); ?>" } },
            title: { text: title },
            yaxis: { label: yLabel },
            xaxis: { tzOffset: offset, min: startMinX + offset, max: startMaxX + offset, label: "<?php echo htmlspecialchars($xLabel); ?>"},
            boundaries: { startTime: startTime, endTime: endTime, startingRange: type }
          };
          
          // draw the graph
          $.ampgraph(container, options, undefined, type, heatmap);
        };
    
        // decide how many graphs we need to draw
        var mtom = false, a = [];
        
        // one heat map - single to multi
        if (srcList.length == 1 && dstList.length > 1) {
          $.each(dstList, function(index, dst) {
            a.push(ajax[srcList[0]][dst]);
          });
          drawGraph(a, true, srcList[0] + " to <?php echo $dstOrig; ?>", srcList[0] + " to");

        // one heat map - multi to single
        } else if (srcList.length > 1 && dstList.length == 1) {
          $.each(srcList, function(index, src) {
            a.push(ajax[src][dstList[0]]);
          });
          drawGraph(a, true, dstList[0] + " from <?php echo $srcOrig; ?>", dstList[0] + " from");
        
        // multiple heat maps - multi to multi
        } else if (srcList.length > 1 && dstList.length > 1) {
          mtom = true;
          $.each(srcList, function(index, src) {
            a = [];

            $.each(dstList, function(index, dst) {
              if (ajax[src][dst]) a.push(ajax[src][dst]);
            });
            drawGraph(a, true, src + " from <?php echo $srcOrig; ?>", src + " from");
            container.append("<br/>");
          });

        // single to single
        } else {
          drawGraph(ajax[srcList[0]][dstList[0]], false, "", "<?php 
            if (strcmp($yLabel, "Jitter (ms)") == 0) {
              echo "Latency (ms)";
            } else {
              echo htmlspecialchars($yLabel);
            }
          ?>");
        }

      });
      </script>
  <?php

}

/* Draws the graph using the flot API */
function drawFlotNormal($src, $dst, $date, $graphOptions, $item_name, $lineGraph,
  $xAxisType, $usemap, $xLabel, $yLabel, $xMax, $yMax, $dataSet,
  $extraOpts, $scope, $mapname="", $statName)
{
  $item = get_display_item($item_name);
  $test = explode("-" , $item->displayObject);
  $gType = $test[0];
      ?>
        <link rel="stylesheet" href="js/flot/flot.css" type="text/css">
        <?php //echo "<script type=\"text/javascript\" src=\"modules/" . htmlspecialchars($gType) . "_test.js\"></script>"; ?>  
        <script language="javascript" type="text/javascript" src="http://code.jquery.com/jquery-1.4.min.js"></script>
        <!--<script language="javascript" type="text/javascript" src="http://code.jquery.com/jquery-1.8.0.min.js"></script>-->
        <script type="text/javascript" src="modules/<?php echo htmlspecialchars($gType); ?>_test.js"></script>
        <!--[if IE]><script language="javascript" type="text/javascript" src="js/flot/excanvas.min.js"></script><![endif]-->    
        <script language="javascript" type="text/javascript" src="js/flot/jquery.flot.js"></script>
        <script language="javascript" type="text/javascript" src="js/flot/jquery.flot.plugins.js"></script>
        <script language="javascript" type="text/javascript" src="js/flot/AmpGraph.js"></script>

       <!-- graph page element -->
      <div id="<?php echo 
        htmlspecialchars($item->displayObject) . 
        "-" . htmlspecialchars(str_replace(array(":", " ", "."), "-", $src)) . 
        "-" . htmlspecialchars(str_replace(array(":", " ", "."), "-", $dst)) . 
        "-graph-$m-" . htmlspecialchars($extraOpts["GRAPH_NUMBER"]); ?>"></div> 

      <!-- graph script begins -->
      <script type="text/javascript">
        $(function () {
        var dataset;
        var type = "<?php echo $xAxisType ?>";
        var container = $("#<?php echo 
        htmlspecialchars($item->displayObject) . 
        "-" . htmlspecialchars(str_replace(array(":", " ", "."), "-", $src)) . 
        "-" . htmlspecialchars(str_replace(array(":", " ", "."), "-", $dst)) . 
        "-graph-$m-" . htmlspecialchars($extraOpts["GRAPH_NUMBER"]); ?>");

        // get the data
        dataset = <?php echo json_encode($dataSet); ?>;
        
        // build the options
        var options = {
          xaxis: { label: "<?php echo $xLabel; ?>" },
          yaxis: { label: "<?php echo $yLabel; ?>" },
          lines: { show: <?php echo ($lineGraph == "y") ? "true": "false"; ?> },
          points: { show: <?php echo ($lineGraph != "y") ? "true": "false"; ?> }
        };
            
        // draw the graphs
        $.each(dataset, function(src, val) {
          $.each(dataset[src], function(dst, val) {
            $("<h4>" + src + " to " + dst + "</h4>").appendTo(container);

            /* make sure there is actually data */
            if (!dataset[src][dst] || !dataset[src][dst][0]) {
              $("No data available for " + src + " to " + dst).appendTo(container);
            }
            
            $.ampgraph(container, options, dataset[src][dst][0], type, <?php if (count($dataSet) > 1 || count($allDestinations) > 1) echo "true"; else echo "false"; ?>);
            
          });
        });

      });
      </script>

    <?php
}

/* Draws the graph using gnuplot - with colors */
function drawGnuplotHeat($src, $dst, $date, $graphOptions, $item_name, $lineGraph,
  $xAxisType, $usemap, $xLabel, $yLabel, $xMax, $yMax, $dataSet,
  $extraOpts, $scope, $mapname="", $colors)
{

  global $timeZone;
  $barWidth = 0.4;
  $MINSIZE = 400;
  $threshold = $extraOpts["X_GAP_THRESHOLD"];

  /* open a pipe to the graph tool */
  $gnuplot = popen("`which gnuplot`", "w");
  if ( ! $gnuplot ) {
    graphError("Could not open gnuplot command");
    return -1;
  }

  $multi = 1; 
  $mtom = false;
  $allDestinations = array_keys($dataSet[array_pop(array_keys($dataSet))]);

  if (count($dataSet) > 1 && count($allDestinations) > 1) {
    $multi = count($allDestinations);
    $mtom = true;
  }

  /* loop through multi to multi graphs */
  for ($m = 0; $m < $multi; $m++) {

    $graphID = rand();
    $data = array();
    $prevX = 0;
    $xStore = array();  
    $yStore = array();
    $serverNo = 0;
    $rowLabels = array(); 
    $bc = 0;
    $blockA = array();
    $blockB = array();
    
    /* Build graph file name, check for cached image */
    $fileName = cacheFileName($xAxisType, $item_name, $src, $dst, $date,
    $graphOptions, $cached);
    if ( $fileName == "" ) {
      continue;
    }
  
    /* Return cached image if available */
    if ( $cached ) {
      echo "<img src=\"$fileName\">";
      continue;
    }    

    /* if multi to multi - flatten into multi to single */
    if ($mtom)
      $destinations = array($allDestinations[$m]);
    else
      $destinations = $allDestinations;

    /* work out dataset type */
    if (count($dataSet) == 1) {
      $rows = count($destinations);
      $singleToMulti = true;
      $title = array_shift(array_keys($dataSet)) . " to:";
    } else {
      $rows = count($dataSet); 
      $singleToMulti = false;
      $title = $destinations[0] . " from:";
    }

    /* get the data series */
    foreach ( array_keys($dataSet) as $src) {

      /* loop through destinations */
      foreach ( $destinations as $dst ) {  

        /* check for pointless plotting */
        if ( $src == $dst || !isset($dataSet[$src][$dst])) {
          $rows--;
          continue;
        }

        $instance = 0;

        /* loop through the datasets */
        foreach ( $dataSet[$src][$dst] as $set ) {
  
          if ( $item_name == "nzrs" || $item_name == "dns2" ) {
            if ($singleToMulti) {
              $rowLabels["$serverNo"][] = $set["key"];
            } else {
              if (count($dataSet[$src][$dst]) != 1) {
    
                if (isset($rowLabels["$serverNo"]))
                  $rowLabels["$serverNo"][] = $src . " to " . substr($set["key"], 0, strpos($set["key"], "."));
                else
                  $rowLabels["$serverNo"][] = substr($set["key"], 0, strpos($set["key"], "."));
    
              } else {
                $rowLabels["$serverNo"][] = $src . " to " . substr($set["key"], 0, strpos($set["key"], "."));
              }
    
            }
          } else {
            if ($singleToMulti)
              array_push($rowLabels, $dst);
            else
              array_push($rowLabels, $src);
          } 

            $prevX = -1;
            /* loop through the data points */
            for ( $i = 0; $i<count($set["data"]); $i++ ) {
              
              if ( isset($set["data"][$i]["y"]) ) {
        
                  $x = $set["data"][$i]["x"];
                  $y = $set["data"][$i]["y"];
  
                  $xc = strtotime($date . " UTC") + $x;

                  if ($x - $prevX > $threshold && $prevX != -1) {
                    $bc++;                                     
                  } 

                  $blockA[$bc][] = ($serverNo + $barWidth * $instance) . " " . $xc . " " . $y . "\n";
                  $blockB[$bc][] = ($serverNo + $barWidth + $barWidth * $instance) . " " . $xc . " " . $y . "\n";
                  
                  /* store previous x value for dot joining threashold */
                  $prevX = $x;
              }

              // stop a hole in data from appearing
              if ($i + 1 >= count($set["data"]) && $prevX == -1)
                $bc--;
          
            }
            $bc++;
            $instance++;
        }
        $serverNo++;
      }
    }
  
    /* begin piping graph properties */
    
    if ($item_name == "nzrs" || $item_name == "dns2" ) {
      $i = 0;    
      foreach ($rowLabels as $server => $instances) {
      $j = 0;
        foreach ($instances as $instance) {
          fputs($gnuplot, "set label " . (1 . $i . $j) . " \"" . $instance . " -" . "\" at graph +0.0025, second " . ($i + $barWidth / 2 + $j * $barWidth) ." right \n");
          $j++;
        }
        $i++;
      }
    } else {
      $i = 0;
      foreach ($rowLabels as $server) {
        fputs($gnuplot, "set label " . ($i + 1) . " \"" . $server . " -" . "\" at graph +0.0025, second " . ($i + $barWidth / 2) ." right \n");
        $i++;
      }
    }
  
    /* calculate graph size */
    $size = $MINSIZE;
    if ($rows * 40 > $MINSIZE) { 
        $size = $rows * 40;
    }
    fputs($gnuplot, "set terminal png nocrop enhanced size 800," . $size . "\n");  
    fputs($gnuplot,
    "set lmargin 11
    set rmargin 8
    unset key
    set cbrange [0:100]
    unset ytic
    set xdata time
    set timefmt \"%s\"
    set view map
    set pm3d corners2color c1
    set cblabel \"$yLabel\"
    ");    

    $maxRange = 0;
    $colorMap = "";
    foreach ( $colors as $key => $color) {
      $colorMap .= $key . " '" . $color . "', ";
      if ($key > $maxRange)
        $maxRange = $key;
    }

    $colorMap = substr($colorMap, 0, strlen($colorMap) - 2);

    fputs($gnuplot, "set cbrange [0:" . $maxRange . "]\n");
    fputs($gnuplot, "set palette defined (" . $colorMap . ")\n");    
    fputs($gnuplot, "set xlabel \"" . $xLabel . "\" 0,-0.5\n");
    fputs($gnuplot, "set title \"" . $title . "\"\n");    
    fputs($gnuplot, "set yr [-0.5:" . $rows . "]\n");
    fputs($gnuplot, "set output \"" . $fileName . "\"\n");
    fputs($gnuplot, "set xrange [\"" . strtotime($date . " UTC") . "\":\"" . strtotime($date . "+1 " . $xAxisType . " UTC") . "\"]\n");
    fputs($gnuplot, "set format x \"" . $xfmt . "\"\n");
    
    /* decide on a x axis format */
    switch ($xAxisType) {
      case "day":
        $xfmt = "%R";
        break;
      case "week": 
        $xfmt = "%a";
        break;
      case "month":
        $xfmt = "%d";
        fputs($gnuplot, "set autoscale xfix\n");
        fputs($gnuplot, "set mxtics 2\n");
        fputs($gnuplot, "set xtics " . (SECONDS_1DAY * 2) . "\n");
        break;
    }
    
    /* all settings have been processed, time to pipe the data */
    
    fputs($gnuplot, "splot '-' u 2:1:3 w pm3d\n");

    for ($i = 0; $i < count($blockA); $i++) {
      
      $bA = $blockA[$i];
      $bB = $blockB[$i];
      
      foreach ($bA as $lineA)
        fputs($gnuplot, $lineA);

      fputs($gnuplot, "\n");  

      foreach ($bB as $lineB)
        fputs($gnuplot, $lineB);

      if ($i + 1 < count($blockA))
        fputs($gnuplot, "\n\n");
    }

    fputs($gnuplot, "e\nset output\n");    
    fputs($gnuplot, "reset\n");
  
    echo "<img alt=\"Graph\" src=\"$fileName\"></img>\n";
  }
  
  pclose($gnuplot);

}

function drawNewGraphs ($src, $dst, $date, $graphOptions, $item_name, $lineGraph,
  $xAxisType, $usemap, $xLabel, $yLabel, $xMax, $yMax, $dataSet,
  $extraOpts, $scope, $mapname="")
{

  $multi = 1; 
  $mtom = false;
  $allDestinations = array_keys($dataSet[array_pop(array_keys($dataSet))]);

  if (count($dataSet) > 1 || count($allDestinations) > 1) {
    $multi = count($allDestinations);

  }
  
  /* XXX DNS2 test has hyphens in the subtype so this wont work very well.
   * It does work well enough at the moment because there is only one type
   * of DNS2 graph (latency) - there aren't enough measurements being made
   * to make other graphs useful.
   */
  $test = explode("-", $item_name, 2);
  $statName = explode("-", $item_name);
  $statName = $statName[count($statName) -1];
  if ($statName == "latency") $statName = "mean";
  if ($test[0] == "dns2") $statName = null;
  if ($statName == "nzrs") $statName = null;

  if ($xAxisType == "number") {
    drawFlotNormal($src, $dst, $date, $graphOptions, $item_name, $lineGraph,
      $xAxisType, $usemap, $xLabel, $yLabel, $xMax, $yMax, $dataSet,
      $extraOpts, $scope, $mapname="", $statName);
  } else {
    drawFlotTimeSeries($src, $dst, $date, $graphOptions, $item_name, $lineGraph,
      $xAxisType, $usemap, $xLabel, $yLabel, $xMax, $yMax, $dataSet,
      $extraOpts, $scope, $mapname="", $statName);
  }

}

  /**    Returns the offset from the origin timezone to the remote timezone, in seconds.
  *    @param $remote_tz;
  *    @param $origin_tz; If null the servers current timezone is used as the origin.
  *    @return int;
  */
  function get_timezone_offset($remote_tz, $origin_tz = null) {
      if($origin_tz === null) {
          if(!is_string($origin_tz = date_default_timezone_get())) {
              return false; // A UTC timestamp was returned -- bail out!
          }
      }
      $origin_dtz = new DateTimeZone($origin_tz);
      $remote_dtz = new DateTimeZone($remote_tz);
      $origin_dt = new DateTime("now", $origin_dtz);
      $remote_dt = new DateTime("now", $remote_dtz);
      $offset = $origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt);
      return $offset;
  }

  // Emacs control
  // Local Variables:
  // eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
