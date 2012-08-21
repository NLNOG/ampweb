<?php
/*
 * AMP Data Display Interface 
 *
 * Raw Data Extraction
 *
 * Author:      Matt Brown <matt@crc.net.nz.
 * Version:     $Id: extract_raw.php 1712 2010-03-10 22:19:05Z brendonj $
 *
 * Extract raw data for the specified time period / amplets / test from DB
 *
 * The data is returned in CSV to the browser and is presented as an attachment
 * in the hope that the browser will prompt the user to save it as a file. 
 *
 * Each testType that wishes to return raw data must add an entry to the 
 * raw_data_funcs array which contains the name of a function which when 
 * passed ($subType, $obj), where $subType is the testSubType and $obj is
 * an object returned from ampNextObj, should return a line of comma separated
 * values describing the data described by that object. If $obj is NULL, the
 * function should return a header line, describing each field. 
 *
 */
require("amplib.php");

/* Initialise AMP */
initialise();

/* Kill the page and exit with an error */
function errexit($error) {

  $filename = "amp-raw-download-error";
  header("Content-Type: text/plain");
  header("Content-Disposition: attachment; filename=$filename");
  echo 'ERROR: '.htmlspecialchars($error);
  flush();
  exit();

}

// we can't use any functions that deal in localtime unless we set
// ourselves to be in the correct timezone
$timeZone = get_preference(PREF_GLOBAL, GP_TIMEZONE, PREF_GLOBAL);
putenv("TZ=$timeZone");

/* Work out the start time and duration */
if ( isset($_REQUEST["date"]) ) {
  if ( isset($_REQUEST["time"]) ) {
    list($hour, $min, $sec) = split("[:]", $_REQUEST["time"]);
  } else {
    $hour = $min = $sec = 0;
  }
  list($year, $mon, $day) = split("[-]", $_REQUEST["date"]);
  $startTimeSec = mktime($hour, $min, $sec, $mon, $day, $year);
  
  if ( isset($_REQUEST["duration"]) ) {
    $secOfData = $_REQUEST["duration"];
  } else {
    $secOfData = (60*60); # One Hour if no duration specified
  }
} else {
  $startTimeSec = mktime($_POST["start_hour"], $_POST["start_min"],
                          $_POST["start_sec"], $_POST["start_mon"],
                          $_POST["start_day"], $_POST["start_year"]);
  $endTimeSec = mktime($_POST["end_hour"], $_POST["end_min"],
                          $_POST["end_sec"], $_POST["end_mon"],
                          $_POST["end_day"], $_POST["end_year"]);
                          
  $secOfData = $endTimeSec - $startTimeSec;
}
$dstart = gmdate("Y-m-d_H:i:s", $startTimeSec);

/* Check incoming data */
$error = "";
if ( strlen($_REQUEST["testType"]) <= 0 ) {
  $error = "Test Type not specified!";
}
if ( strlen($_REQUEST["src"]) <= 0 ) {
  $error = "Source Amplet not specified!";
}
if ( strlen($_REQUEST["dst"]) <= 0 ) {
  $error = "Destination Amplet not specified!";
}
if ( $startTimeSec <= 0 ) {
  $error = "Start time not specified!";
}
if ( $secOfData <= 0 ) {
  $error = "Duration not specified!";
}
if ( strlen($error)>0 ) {
	errexit($error);
}

/* Retrieve the data */
$src = $_REQUEST["src"];
$dst = $_REQUEST["dst"];
$testType = $_REQUEST["testType"];
$testSubType = $_REQUEST["testSubType"];

$content = "";

$func = $raw_data_funcs[$testType];
if (!function_exists($func)) {
  errexit("Raw data format function ($func) doesn't exist for " .
    "test $testType");
}

/* Open the Database */
$res = ampOpenDb($testType, $testSubType, $src, $dst, $startTimeSec, 0,
  get_preference(PREF_GLOBAL, GP_TIMEZONE, PREF_GLOBAL));
if ( $res ) {

  /* Retrieve the data - Loop until end of specified time period */
  do {
    /* Get a measurement */
    $obj = ampNextObj($res);
    
    /* Check this measurement is still in requested time period */
    if ( ! $obj || $obj->secInPeriod > $secOfData ) {
      /* End loop if it's not */
      break; 
    }

    /* Setup the generic start of line bits */
    $content .= sprintf("%d,%s,%s,%s,%s,", $obj->time, $src, $dst, 
      $testType, $testSubType);
      
    /* Get the raw data function for this test type */
    $content .= $func($testSubType, $obj);

    /* Finish the line */
    $content .= "\n";

  } while (1);

  $header = "timestamp,src,dst,testType,testSubType," . 
    $func($testSubType, NULL);
  $content = "$header\n$content";
  
} else {
  $content = "Failed to open AMP DB!";
}

/* Return it to the browser */
$testName = $test_names[$testType];
$filename = "${src}.$dst.$testName.$testSubType-$dstart+$secOfData.csv";
header("Content-Type: text/plain");
header("Content-Disposition: attachment; filename=\"$filename\"");

echo $content;
flush();

// vim:set sw=2 ts=2 sts=2 et:
?>
