<?php
/*
 * AMP Web Interface
 *
 * Author:      Tony McGregor <tonym@cs.waikato.ac.nz>
 * Author:      Matt Brown <matt@crc.net.nz.
 * Author:      Jeremy Kallstrom <jkallstrom@nlanr.net>
 * Version:     $Id: amplib.php 2138 2012-02-09 01:09:23Z brendonj $
 *
 */
require_once("amp_prefs.php");
require_once("amp_users.php");
require_once("amp_comparison.php");
require_once("amp_display.php");
require_once("amp_graphs.php");
require_once("const.php");
require_once("template.php");

/**** Data Types ****/

/**** Global Variables ****/
$template_displayed = FALSE;

/* Import get or post variables that we permit on every page */
$src = (isset($_REQUEST["src"]))?$_REQUEST["src"]:"";
$dst = (isset($_REQUEST["dst"]))?$_REQUEST["dst"]:"";
$date= (isset($_REQUEST["date"]))?$_REQUEST["date"]:"";

/* Load Display Modules */
$dirpath = dirname($_SERVER["SCRIPT_FILENAME"]) . "/modules";
if ($dirp = opendir($dirpath)) {
  while ($fileName = readdir($dirp)) {
    if (substr($fileName, -9) == "_test.php") {
      /* Load module */
      include_once($dirpath . "/" . $fileName);
    }
  }
  closedir($dirp);
}

$page_name = htmlspecialchars($_SERVER['REQUEST_URI']);

unset($dirpath);
unset($dirp);
unset($fileName);

/* This function must be called at the top of every page to setup the AMP
 * system.
 */
function initialise()
{

  /* Inititate the Session */
  session_start();

  /* Check for cookie login */
  if ( ! have_login() ) {
    do_cookie_logon();
  }

  /* Initialiase preferences */
  init_preferences();

  /* Check for comparison form */
  if ( isset($_REQUEST["comparison"]) ) {
    process_comparison();
  }

}

/*
 * Nicely redirect the browser in an HTTP compliant way!
 *
 * Only redirects to pages in the current directory.
 *
 */
function goPage($page)
{
  header("Location: $page");
  exit();
}

/* This function must be called at the end of every page so that any resources
 * can be freed.
 */
function endPage($username="", $prefFormHtml="", $userMessages="")
{
  templateBottom();
  //phpinfo();
//echo "<pre>";
//print_r($_SESSION);
//echo "</pre>";
}

//---------------------------------------------------------------------------
// Query database and check for errors
function queryAndCheckDB($db, $query)
{

  global $page_error;

  if ( !$db ) {
    return NULL;
  }

  $res = dbquery($db, $query);
  if ( ! $res ) {
    return NULL;
  }

  return $res;

}

//---------------------------------------------------------------------------
// Closes a DB if it has been opened
function dbCheckAndClose($db) {
  if ( !$db ) {
    return;
  }

  dbClose($db);
}

//---------------------------------------------------------------------------
/*
 * Check if there is data for the given test
 */
function checkDataExistsForTest($source, $destination, $test) {

  $list = ampTestList($source, $destination);
  if ( is_object($list) && property_exists($list, "types") && 
      in_array($test, $list->types) ) {
    return true;
  }

  return false;
}

//---------------------------------------------------------------------------
/*
 * Expand the given source name into an array of sites
 */
function expandSites($site) {
  if ( isMesh($site) ) {
    getSites($siteInfo, "", $site);
    return array_keys($siteInfo);
  }
  return array($site);
}
//---------------------------------------------------------------------------
/*
 * Check if the given name is a mesh
 */
function isMesh($name) {
  if ( strlen($name) < 1 )
	  return false;

  $siteDb = dbConnect($GLOBALS["sitesDB"]);
  $query = "SELECT count(*) FROM meshes WHERE meshname='" . 
    pg_escape_string($name) . "'";
  $res = queryAndCheckDB($siteDb, $query);
  dbCheckAndClose($siteDb);

  if ( $res["rows"] > 0 && $res["results"][0]["count"] == 1 )
    return true;

  return false;
}

//---------------------------------------------------------------------------
// Returns a list of sites and their details 
function getSites(&$siteInfo, $source, $mesh) {

  /* Attempt to connect to the database */
  $siteDb = dbConnect($GLOBALS["sitesDB"]);
  if ( !$siteDb ) {
    log_error("Could not connect to sites database. Please try again later.\n");
    return;
  }

  $where = "";
  if($source != "")
    $where = " AND ampname != '".pg_escape_string($source)."' ";

  /* Try and retrieve a list of sites for the mesh */
  $query = "select ampname, longname, mapx, mapy from srclistview where " .
    "meshname = '".pg_escape_string($mesh)."' $where order by longname";
  $result = dbQuery($siteDb, $query);
  if ($result["rows"] <= 0 ) {

    // if mesh isnt set but source is we still want to have some restrictions
    if($source != "") {

      // if we are showing all hosts, but source is set then limit it to
      // hosts that share a mesh with the source
      $query = "select meshname from srclistview where ampname= '".pg_escape_string($source)."'";
      $result = dbQuery($siteDb, $query);

      if ($result["rows"] > 0 ) {
        $query = "SELECT ampname, longname, mapx, mapy from srclistview " .
          "WHERE (";
        $first = true;
        foreach($result['results'] as $row) {
          if($first)
            $first = false;
          else
            $query .= " or ";

          $query .= " meshname='" . pg_escape_string($row['meshname']) . "'";
        }
        $query .= ") $where";
        $result = dbQuery($siteDb, $query);
      }

    } else {
      
      /* If that fails then return a list of all sites */
      $query = "select ampname, longname, mapx, mapy from srclistview order by " .
        "longname";
      $result = dbQuery($siteDb, $query);
    }

  }
  
  /* Collect the details and insert into the array */
  for ( $rowNum = 0; $rowNum < $result['rows']; ++$rowNum ) {
    $row = $result["results"][$rowNum];
    $ampname = $row["ampname"];	
    $siteInfo[$ampname]["longname"] = $row["longname"];
    if ( $siteInfo[$ampname]['longname'] == "" ) {
      $siteInfo[$ampname]['longname'] = $ampname;
    }
    $siteInfo[$ampname]['mapx'] = $row["mapx"];
    $siteInfo[$ampname]['mapy'] = $row["mapy"];
  }

  /* Clean up and exit */
  dbClose($siteDb);

}

//---------------------------------------------------------------------------
// Get the sitename if available
function getLongSiteName($db, $site) {
  if ( !$db ) {
    return $site;
  }
  $query = "select longname,ampname from sites where ampname = '".pg_escape_string($site)."'";
  $result = queryAndCheckDB($db, $query);
  if ( $result['rows'] > 0 ) {
    if ( $result["results"][0]["longname"] != "" ) {
      return $result["results"][0]["longname"];
    }
  }

  return $site;
  
}

/* Retrieve the specified dataSet with the data placed into the appropriate
 * bins.
 *
 * Input Parameters:
 * src              Src Node ID
 * dst              Dst Node ID
 * testType         Test Type to fetch data for
 * testSubType      Test Sub Type (if applicable) to fetch data for
 * startTimeSecs    Unix Timestamp (!!in timezone specified by timeZone!!)
 *                  of start of time period to retrieve data from
 * timeZone         Time Zone to retrieve data in
 * secsOfData       How many seconds of data to retreive
 * binSize          Requested size of each bin
 * wantMedian       Return the median for each bin
 * wantSummary      Return the summary for the data
 * wantValueDist        Return the distribution of values in the dataset
 *
 * Output Parameters:
 * bins             Array of data
 * numBins          Size of bins array
 * plotStats        Various statistics about the dataset
 *
 * Return Value:
 * Returns a valid DB resource if data was successfully retrieved.
 * Returns -1 if an error occured
 *
 */
function getBinnedDataSet($src, $dst, $testType, $testSubType, $startTimeSec,
                          $timeZone, $secsOfData, $binSize, $wantMedian,
                          $wantSummary, $wantValueDist, &$bins, &$numBins,
                          &$plotStats, &$info)
{

  $dataV=0;

  /* Initialise variables */
  $binStartTime = 0;
  $bin = 0;
  $binMax = 0;
  $binMin = 3000000;
  $binCount = 0;
  $binTotal = 0;
  $binLoss = 0;
  $binSum = 0;
  $binSquaresSum = 0;
  $bins[0]{'time'} = ((int)($binSize * 30));
  $plotStats{'total'} = 0;
  $plotStats{'loss'} = 0;
  $plotStats{'squaresSum'} = 0;
  $plotStats{'max'} = 0;
  $plotStats{'min'} = 3000000;
  $plotStats{'count'} = 0;
  $plotStats{'maxValue'} = 0;
  $plotStats{'packetTotal'} = 0;

  /* Open the Database */
  $res = ampOpenDb($testType, $testSubType, $src, $dst, $startTimeSec,
    0, $timeZone);
  if ( ! $res ) {
      return -1;
  }

  $info = ampInfoObj($res);

  /* Retrieve the data - Loop until bins are filled*/
  do {
    /* Get a measurement */
    $obj = ampNextObj($res);

    if ( !$obj ) {
      break;
    }

    $dataV = $obj->data;

    /* Check it is within the current bin */
    if ( ! $obj || $obj->secInPeriod > $secsOfData ||
                    $obj->secInPeriod > ($binStartTime + ($binSize * 60)) ) {
      /* It is not - check bin is not empty*/
      if ( $binCount != 0 || $binLoss != 0 ) {

        /* Calculate loss percentage */
        $bins[$bin]{'loss'} = (int)($binLoss/($binLoss+$binCount)*100);

        /* If there is non-loss data - transfer into bin */
        if ( $binCount != 0 ) {
          $bins[$bin]{'mean'}   = $binTotal/$binCount;
          $bins[$bin]{'max'}    = $binMax;
          $bins[$bin]{'min'}    = $binMin;
          $bins[$bin]{'jitter'} = $binMax - $binMin;
          $bins[$bin]{'stddev'} =
              sqrt((($binCount*$binSquaresSum) - ($binSum*$binSum)) /
              ($binCount*$binCount));
  
          /* Retrieve median if requested */
          if ( $wantMedian ) {
            if ( $binCount == 0 ) {
              $bins[$bin]{'median'} = 0;
            } else if ( $binCount == 1 ) {
              $bins[$bin]{'median'} = $binData[0];
            } else if ( $binCount % 2 == 0 ) {
              sort($binData);
              $v1 = (int)($binCount/2);
              $v2 = ((int)($binCount/2))+1;
              $bins[$bin]{'median'} = ($binData[$v1] + $binData[$v2]) / 2;
            } else {
              sort($binData);
              $bins[$bin]{'median'} = $binData[((int)$binCount/2)+1];
            }
          } // ( $binCount == 0 )
        } // ( $wantMedian)

        /* Retrieve Summary if requested */
        if ( $wantSummary ) {
          $plotStats{'total'} += $binTotal;
          $plotStats{'loss'} += $binLoss;
          $plotStats{'squaresSum'} += $binSquaresSum;
          if ( $plotStats{'max'} < $binMax ) {
            $plotStats{'max'} = $binMax;
          }
          if ( $plotStats{'min'} > $binMin ) {
            $plotStats{'min'} = $binMin;
          }
          for ( $index = 0; $index < $binCount; $index++ ) {
            $plotStats{'data'}[$plotStats{'count'}] =
            $binData[$index];
            $plotStats{'count'}++;
          }
        }

        /* Clear bin temporary variables and start again */
        unset($binData);
        $binMax = 0;
        $binMin = 300000;
        $binCount = 0;
        $binTotal = 0;
        $binLoss  = 0;
        $binSum   = 0;
        $binSquaresSum = 0;

        $bin++;
        $binStartTime = $obj->secInPeriod -
                        ($obj->secInPeriod % ($binSize * 60));
        $bins[$bin]{'time'} = $binStartTime + ((int)($binSize * 30));

      }// not empty bin
    } // End of bin

    /* Check this measurement is still in requested time period */
    if ( ! $obj || $obj->secInPeriod>$secsOfData ) {
      /* End loop if it's not */
      break;
    }

    /* Add the measurement to the current bin */
    if ( $dataV == -1 && $obj->error == "loss" ) {
      $binLoss++;
    } else if ( $dataV != -1 ) {
      /* Calculate Value Distribution */
      if ( $wantValueDist ) {
        if ( $dataV > $plotStats{'maxValue'} ) {
          $plotStats{'maxValue'} = $dataV;
        }
        if ( isset($plotStats{'ValueDist'}[$dataV]) ) {
          $plotStats{'ValueDist'}[$dataV]++;
        } else {
          $plotStats{'ValueDist'}[$dataV] = 1;
        }
        $plotStats{'packetTotal'}++;
      }

      /* Bin the data */
      $binData[$binCount] = $dataV;
      $binCount++;
      $binTotal += $dataV;
      $binSum += $dataV;
      $binSquaresSum += $dataV * $dataV;
      if ($dataV > $binMax) {
          $binMax = $dataV;
      }
      if ($dataV < $binMin) {
          $binMin = $dataV;
      }
    } //not loss

  } while ( TRUE );  // retrieve data
  $numBins = $bin;

  /* Calculate Median if summary requested */
  if ( $wantSummary ) {
    /* work out median of all data */
    if ( $plotStats{'count'} == 0 ) {
      $plotStats{'median'} = 0;
    } else if ( $plotStats{'count'} == 1 ) {
      $plotStats{'median'} = $plotStats{'data'}[0];
    } else if ( ($plotStats{'count'} % 2) == 0 ) {
      sort($plotStats{'data'});
      $v1 = (int)($plotStats{'count'}/2);
      $v2 = ((int)($plotStats{'count'}/2))+1;
      $plotStats{'median'} = ($plotStats{'data'}[$v1] + 
        $plotStats{'data'}[$v2]) / 2;

    } else {
      sort($plotStats{'data'});
      $plotStats{'median'} = $plotStats{'data'}[((int)$plotStats{'count'}/2)+1];

    }

    /* Free all the memory used */
    unset($plotStats{'data'});

  }

  return $res;

}

/* Retrieve the specified dataSet - returning the raw measurements.
 *
 * This function can use a lot of memory as no summarisation is performed
 *  so don't use this function unless you absolutely need to.  This function
 *  should be considered deprecated.
 *
 * Input Parameters:
 * src              Src Node ID
 * dst              Dst Node ID
 * testType         Test Type to fetch data for
 * testSubType      Test Sub Type (if applicable) to fetch data for
 * startTimeSec     Unix Timestamp (!!in timezone specified by timeZone!!)
 *                  of start of time period to retrieve data from
 * timeZone         Time Zone to retrieve data in
 * secsOfData       How many seconds of data to retreive
 * wantSummary      Return the summary for the data
 *
 * Output Parameters:
 * data             Array of data, empty or negative entries signify loss
 * numMeasurements  Size of data array
 * plotStats        Various statistics about the dataset
 *
 * Return Value:
 * Returns the db resource if successful or -1 if an error occured.
 *
 */
function getRawDataSet($src, $dst, $testType, $testSubType, $startTimeSec,
                       $timeZone, $secsOfData, $wantSummary,
                       &$data, &$numSamples, &$plotStats)
{

  $dataV=0;
  $numSamples=0;

  /* Initialise variables */
  $plotStats{'total'} = 0;
  $plotStats{'loss'} = 0;
  $plotStats{'squaresSum'} = 0;
  $plotStats{'max'} = 0;
  $plotStats{'min'} = 3000000;
  $plotStats{'count'} = 0;

  /* Open the Database */
  $res = ampOpenDb($testType, $testSubType, $src, $dst, $startTimeSec,
    0, $timeZone);
  if ( ! $res ) {
    return -1;
  }

  /* Retrieve the data - Loop until end of specified time period */
  do {
    /* Get a measurement */
    $obj = ampNextObj($res);
    $dataV = $obj->data;

    /* Check this measurement is still in requested time period */
    if ( ! $obj || $obj->secInPeriod > $secsOfData ) {
      /* End loop if it's not */
      break;
    }

    /* Add the measurement to the current bin */
    $data[$numSamples]["secInPeriod"] = $obj->secInPeriod;
    if ( $dataV == -1 && $obj->error == "loss" ) {
        $data[$numSamples]["data"] = -1;
    } else if ( $dataV != -1 ) {
        $data[$numSamples]["data"] = $dataV;
    } //not loss
    $data[$numSamples]["data2"] = $obj->data2;

    /* Calculate summary if requested */
    if ( $wantSummary ) {
      $plotStats{'total'} ++;
      if ( $data[$numSamples]["data"]==-1 ) {
        $plotStats{'loss'} ++;
      } else {
        $plotStats{'squaresSum'} += ($dataV * $dataV);
        if ($plotStats{'max'} < $dataV) {
          $plotStats{'max'} = $dataV;
        }
        if ($plotStats{'min'} > $dataV) {
          $plotStats{'min'} = $dataV;
        }
      } // ( $data[$numSamples]["data"]==-1 )
    } // ( $wantSummary )

    /* Increment Number of Samples */
    $numSamples++;

  } while ( TRUE );  // retrieve data

  /* Calculate Median if summary requested */
  if ( $wantSummary ) {
    $plotStats{'count'} = $numSamples;
    /* work out median of all data */
    if ($numSamples == 0) {
      $plotStats{'median'} = 0;
    } else if ($numSamples == 1) {
      $plotStats{'median'} = $data[0]["data"];
    } else if (($numSamples % 2) == 0) {
      sort($data);
      $v1 = (int)($numSamples/2);
      $v2 = ((int)($numSamples/2))+1;
      $plotStats{'median'} = ($data[$v1]["data"] +
                              $data[$v2]["data"]) / 2;
    } else {
      sort($data);
      $plotStats{'median'} = $data[((int)$numSamples/2)+1]["data"];
    }
  } // ( $wantSummary )

  return $res;

}

/*
 * Handle errors nicely
 */
function graphError($errString)
{
  echo '<div class="errMsg">'.htmlspecialchars($errString).'</div>'."\n";
}

function page_error($errString)
{

  global $template_displayed;

  if ( ! $template_displayed ) {
      templateTop();
  }

  graphError($errString);

  endPage();

  exit();
}

function log_error($errString)
{
  error_log($errString);
}

/*---------------------------------------------------------------------------
 * Outputs HTML code to display links for the specified graph
 *
 */
function drawGraphLinks($item_name, $src, $dst, $startTimeSec, $scope, $date)
{
  global $page_name;
  $item = get_display_item($item_name);
  if ($item == NULL) {
    return;
  }
  $object = get_display_object($item->displayObject);
  $object_name = $object->name;
  $subType = $item->subType;

  /* Graph Links */
  if (get_preference(PREF_GLOBAL, GP_COMPARISONS, PREF_GLOBAL)==PREF_TRUE 
    && !isset($_REQUEST["graph"])) {
  echo "<div class=\"graph\">\n";
    echo "<form action=\"$page_name\" method=\"post\">\n";
    echo '<input type="hidden" name="cobject" value="'.htmlspecialchars($object_name).'">'."\n";
    echo '<input type="hidden" name="csubType" value="'.htmlspecialchars($subType).'">'."\n";
    echo '<input type="hidden" name="ctime" value="'.htmlspecialchars($startTimeSec).'">'."\n";
    echo '<input type="hidden" name="cscope" value="'.htmlspecialchars($scope).'">'."\n";
    echo "<input type=\"hidden\" name=\"do\" value=\"yes\">\n";
    echo "<input type=\"hidden\" name=\"comparison\" value=\"add\">\n";
    echo "<input type=\"submit\" name=\"csubmit\" value=\"" .
            "[Add to Comparison List]\" class=\"graphoptionssubmit\">\n";
    echo "</form>\n";
  echo "</div>\n";
  }
}

//--------------------------------------------------------------------------
function printWeeklyMap($date, $links){
  $width = 75;

  print '<map name="weekmap-' . $date . '">' . "\n";
  for ($day=0; $day<7; ++$day){
    print '<area href="';
    print $links[$day] . '"';
    print ' shape="rect" coords="';
    print 70 + $day * $width;
    print ',35,';
    print 70 + ($day + 1) * $width;
    print ",220\" alt=\"day$day\">";
    print "\n";
  }
  print '</map>' . "\n";
  return "weekmap-$date";
}

//--------------------------------------------------------------------------
function printMonthlyMap($date, $links){
  $days = date("t", strtotime($date) );
  $width = 17; // empirically tested width value

  print '<map name="monthmap-' . $date . '">' . "\n";
  for ($day=0; $day<$days; ++$day){
    print '<area href="';
    print $links[$day] . '"';
    print ' shape="rect" coords="';
    print 70 + $day * $width;
    print ',35,';
    print 70 + ($day + 1) * $width;
    print ",220\" alt=\"day$day\">";
    print "\n";
  }
  print '</map>' . "\n";
  return "monthmap-$date";
}

//---------------------------------------------------------------------------
function printSummaryStats($message, $plotStats, $units){


  if ( $plotStats{'count'} == 0 ) { return; }

  $stddev = sqrt( (($plotStats{'count'}*$plotStats{'squaresSum'}) -
                   ($plotStats{'total'}*$plotStats{'total'})) /
                  ($plotStats{'count'}*$plotStats{'count'}) );

  $space2 = "&nbsp&nbsp ";

  printf("<div class=\"summarystats\">\n");
  printf("%s<br>", $message);
  printf("%d samples + %d losses, $space2", $plotStats{'count'},
         $plotStats{'loss'});
  printf("max: %d$units, $space2", $plotStats{'max'});
  printf("min: %d$units, $space2", $plotStats{'min'});
  printf("median: %d$units, $space2", $plotStats{'median'});
  printf("mean: %5.1f$units, $space2",$plotStats{'total'}/$plotStats{'count'});
  printf("stddev: %5.1f$units $space2", $stddev);
  printf("</div>\n");
}


//---------------------------------------------------------------------------
function flatten($array){

  $flat = "";
  foreach( array_keys($array) as $key ) {
    if ( $flat != "" ) { $flat .= "&"; }
    $flat .= urlencode($key) . "=" . urlencode($array{$key});
  }

  return $flat;
}

/*
 * Generate a filename for the graph, this filename is important as it must
 * be unique to avoid browser caching.
 *
 * Format:
 * <xAxisType>_<displayItemName>_<date>_<graphOptions>.<ext>
 *
 * Each file is stored in the cache dir under a hierarchy based on the
 * source and destination amplet, Making the full filename look like.
 *
 * CACHE_DIR/<src>/<dst>/<filename>
 *
 * Some components of the filename have internal formatting as well.
 *
 * Valid xAxisTypes are:
 * map (with source = "" for src selection)
 * day
 * week (date is for first day of the week)
 * mapfile
 */
 function cacheFileName($type, $itemName, $src, $dst, $date, $graphOptions,
        &$cached)
{

  /* Determine suffix */
  switch ( $type ) {
    case "map"  : $suffix = "jpg"; break;
    case "day"  : $suffix = "png"; break;
    case "week" : $suffix = "png"; break;
    case "month" : $suffix = "png"; break;
    case "mapfile" : $suffix = "map"; break;
    default:      $suffix = "";
  }

  /* Create directory portions of the name, check they exist */
  $fileName = CACHE_DIR;
  if ($src != "") {
    $fileName .= "$src/";
    if (!is_dir($fileName)) {
      mkdir($fileName, 0777);
    }
  }
  if ($dst != "") {
    $fileName .= "$dst/";
    if (!is_dir($fileName)) {
      mkdir($fileName, 0777);
    }
  }

  /* Create renaming portions of the name */
  $fileName = sprintf("%s%s_%s_%s", $fileName, $type, $itemName,
                      $date);
  if (strlen($graphOptions)>0) {
    $fileName .= "_$graphOptions";
  }
  $fileName .= ".$suffix";

  /* caching is now implemented! */

  /* check the file is ok and is fresh enough, otherwise regenerate it */
  if ( is_file($fileName) ) {
    if ( is_writeable($fileName) ) {
      /* lets make the freshness timeout an arbitrary 5 minutes */
      if ( filesize($fileName) > 0 && (time() - filemtime($fileName)) < 300) {
        chmod($fileName, 0664);
        $cached = TRUE;
        return $fileName;
      }
    } else {
      graphError("Could not create cache file: $fileName");
      $cached = FALSE;
      return "";
    }
  }

  /* Return filename */
  $cached = FALSE;
  return $fileName;
}


//---------------------------------------------------------------------------
// Sorts a list of sites based on their descriptions
function longnamesort($a, $b) {
  return strcasecmp($a['longname'], $b['longname']);
}


//---------------------------------------------------------------------------
// Sorts a list of sites based on when data was last seen
function timesort($a, $b) {
  if($a['lastseen'] != $b['lastseen'])
    return strcasecmp($a['lastseen'], $b['lastseen']);
  return longnamesort($a, $b);
}


//---------------------------------------------------------------------------
/*
 * Find the time in minutes since the given site last reported data to
 * any other site.
 */
function lastSeenAll($src){

  $destinations = ampSiteList($src);
  $recent = 0;

  /*
   * just check the last 2 hours to see if there is data...checking too
   * far back can be quite a bit of work and causes the page to be slow
   */
  foreach($destinations->srcNames as $dst) {
    $last = lastSeenSite($src, $dst, 2);

    /* if it's 1, it isn't going to get any better than this so return now */
    if($last == 1)
      return 1;

    /* otherwise update last if this value is lower and try the next site */
    if($last != 0 && ($recent == 0 || $last < $recent))
      $recent = $last;

  }
  return $recent;
}


//---------------------------------------------------------------------------
/*
 * Find the time in minutes since $src reported data about $dst. Uses the
 * filesystem to find the most recent file rather than querying the AMPDB
 * to find the most recent data as we would have to check too many different
 * test types.
 */
$lastseen = array();
function lastSeenSite($src, $dst, $hours) {
  global $lastseen;

  /* return the cached value if there is one for this pair */
  if ( isset($lastseen[$src]) && isset($lastseen[$src][$dst]) ) {
    return $lastseen[$src][$dst];
  }

  if ( !isset($lastseen[$src]) )
    $lastseen[$src] = array();

  /* check for files from today, most likely */
  $now = time();
  $basepath = "/amp-data/$src/$dst";
  $datestring = date("1y-m-d", $now);
  $files = glob("$basepath/$datestring*");

  if ( $files == false || $files == array() ) {
    /* if there is nothing for today, then check yesterday, might be early */
    $datestring = date("1y-m-d", strtotime("-1day", $now));
    $files = glob("$basepath/$datestring*");

    /* don't care how long it was if it was more than a day ago */
    if ( $files == false || $files == array() ) {
      $lastseen[$src][$dst] = 0;
      return 0;
    }
  }

  /* find the most recently modified file today/yesterday */
  $recent = 0;
  foreach ( $files as $file ) {
    $last = filemtime("$file");
    if ( $last > $recent )
      $recent = $last;
  }

  /* report how long ago that actually was */
  $recent = ($now - $recent) / 60 + 1;
  $lastseen[$src][$dst] = (int)$recent;
  return (int)$recent;
}


//---------------------------------------------------------------------------
/*
 * Get all the meshes that a site belongs to
 */
function getMeshesBySite($site, $showhidden=false) {

  /* Attempt to connect to the database */
  $siteDb = dbConnect($GLOBALS["sitesDB"]);
  if ( !$siteDb ) {
    log_error("Could not connect to sites database. Please try again later.\n");
    return array();
  }

  $username = have_login();
  if(!empty($username)) {
	  $username = explode("|", $username);
	  $uid = $username[1];
	  $username = $username[0];
  }

  /* if the user is logged in they might have custom meshes we need to show */
  $uidquery = "";
  $hiddenquery = "";

  if ( isset($uid) ) {
    $uidquery = " or uid='".pg_escape_string($uid)."'";
  }

  if ( $showhidden ) {
    $hiddenquery = " or uid='-2'";
  }

  if ( strlen($site) < 1 ) {
    /* no source host to work with, so select all meshes */
    $query = "SELECT meshname FROM meshes WHERE meshname != 'Any' " .
      "AND uid = '-1'$uidquery$hiddenquery";
  } else {
    /* single source host, select all meshes it is part of */
    $query = "SELECT meshname FROM meshes NATURAL JOIN meshes_sites " .
      "NATURAL JOIN sites where ampname='" .
      pg_escape_string($site) . "' AND (uid = '-1'$uidquery$hiddenquery)";
  }

  $res = dbQuery($siteDb, $query);

  $meshes = array();
  foreach($res["results"] as $m) {
    $meshes[] = $m["meshname"];
  }

  dbCheckAndClose($siteDb);
  return $meshes;
}


//---------------------------------------------------------------------------
/*
 * Get all the meshes that the sites in the members array belong to
 */
function getMeshes($members, $showhidden=false) {
  $meshes = array();

  /* if it's an array, then find the union of all mesh memberships */
  if ( is_array($members) ) {
    foreach($members as $member) {
      $sitemeshes = getMeshesBySite($member, $showhidden);
      if ( count($sitemeshes) < 1 )
        continue;
      $meshes = array_merge($meshes, array_diff($sitemeshes, $meshes));
    }
  } else {
    /* otherwise just find the meshes the single site belongs to */
    $meshes = getMeshesBySite($members, $showhidden);
  }

  return $meshes;
}


//---------------------------------------------------------------------------
/*
 * Get all the meshes that the sites in the members array have in common
 */
function getCommonMeshes($members) {
  $meshes = array();

  /* if it's an array, then find the intersection of all mesh memberships */
  if ( is_array($members) ) {
    $membermeshes = array();
    foreach($members as $member) {
	$membermeshes[] = getMeshesBySite($member);
    }

    $meshes = call_user_func_array('array_intersect', $membermeshes);
  } else {
    /* otherwise just find the meshes the single site belongs to */
    $meshes = getMeshesBySite($members);
  }

  return $meshes;
}


/*
 * Find the last time data was reported for a source (or group of sources)
 * about a particular destination. Giving group of sources will return the
 * most recent time any of them have seen data, giving a blank destination
 * will check for all destinations.
 */
function updateLastSeenTime($source, $destination="") {
  $last = 0;

  if ( is_array($source) ) {
    /* multiple sources, find the best one */
    foreach($source as $s) {
      if ( strlen($destination) > 0 )
        $thislast = lastSeenSite($s, $destination, 2);
      else
        $thislast = lastSeenAll($s);
      /* no response can be better than 1 minute, so stop looking now */
      if ( $thislast == 1 )
        return 1;
      /* update if this response is newer */
      if ( $thislast != 0 && ($last == 0 || $thislast < $last) )
        $last = $thislast;
    }

  } else {
    /* single source */
    if ( strlen($destination) > 0 )
      $last = lastSeenSite($source, $destination, 2);
    else
      $last = lastSeenAll($source);
  }

  return $last;

}

/*
 * XXX Nasty function to keep all these hardcoded names in one place.
 * What would be awesome is if this code knew how frequently each 
 * destination is tested to so we can set things based on that rather than 
 * some random hostnames.
 */
function isHostInfrequentlyTested($host) {
  if ( (strpos($host, "gtld-servers.net") !== false ||
        strpos($host, "root-servers.net") !== false ||
        strpos($host, "apnic.net") !== false ||
        strpos($host, "arin.net") !== false ||
        strpos($host, "ripe.net") !== false ||
        strpos($host, "lacnic.net") !== false ||
        strpos($host, "org.afilias-nst") !== false) ) {
    return true;
  }
  return false;
}


/*
 * Try to find a scamper test subtype that will provide the information
 * required. Asking for PMTUD will limit responses to only data with MTU
 * information, but asking for a specific method can be treated more as a
 * suggestion than a requirement.
 */
function getScamperSubtype($src, $dst, $method, $pmtud, $strictMethod=false) {

  $stList = ampSubtypeList(SCAMPER_DATA, $src, $dst);
  
  if ( isset($stList->subtypes) && $stList->count > 0 ) {
    foreach($stList->subtypes as $st) {
      /* check if pmtud is required, doesn't matter if it isn't */
      if ( $pmtud && strpos($st, "-M1") === false )
        continue;

      /* 
       * Take the first traceroute of the right method. Don't differentiate
       * between udp/udp-paris and icmp/icmp-paris if just the protocol is
       * asked for - expected parameters are "udp" and "icmp" but the more
       * specific algorithms can be asked for.
       */
      if ( strncmp($st, "trace-P".$method, strlen($method)+7) == 0 ) {
        return $st;
      }
    }

    /* 
     * if we haven't found the correct method we may be able to find something
     * that will give some useful data still
     */
    if ( !$strictMethod && !$pmtud ) {
      /* can we be smarter about this? */
      return $stList->subtypes[0];
    }
  }

  return "";
}

// Emacs control
// Local Variables:
// eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
