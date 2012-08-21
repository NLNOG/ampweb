<?php 
/*
 * AMP Data Display Interface 
 *
 * Global Constants 
 *
 * Version:     $Id: const.php 1825 2010-07-27 05:08:25Z brendonj $
 *
 * This file contains global constants and defaults for the interface. Do
 * NOT put system specific information in this file as it will be overwritten!
 * Instead use const_local.php, which will not be overwritten!
 *
 */
 
$dbUser = "";
$dbPassword = "";
$dbHost = "";
$dbName ="webusers";
$webusersDB = 0;
$eventsDB = 3;
$sitesDB = 1;

global $dgraf;
$dgraf = getcwd() . "/dgraf";

global $dataSetColour;
$dataSetColour{'random'} = 'red';
$dataSetColour{'max'}    = 'purple';
$dataSetColour{'min'}    = 'green';
$dataSetColour{'mean'}   = 'red';
$dataSetColour{'median'} = 'blue';
$dataSetColour{'jitter'} = 'red';
$dataSetColour{'stddev'} = 'olive';
$dataSetColour{'loss'}   = 'red';

global $system_name;
$system_name = "AMP";

global $adminContact;
$adminContact = "webmaster@" . php_uname('n');

define('CACHE_DIR', "cache/");
define('HOST_SEPARATOR', ":");

/* Colour to use for comparison graphs */
//$comp_colors = array("0X803800", "0Xf0b848", "0X189050", "0Xd038c0", 
//	"0X182040", "0X70d0e0", "0X4050b0", "0X580044");
$comp_colors = array("red", "green", "blue", "magenta", "purple", "lime", "cyan",  "maroon", "navy", "olive", "teal", "silver", "gray", "yellow");

/* Default Gap Threshold */
$gapThreshold[TRACE_DATA] = 1201;

/* Time definitions */
define('SECONDS_1MIN', 60);
define('SECONDS_5MINS', 60*5);
define('SECONDS_10MINS', 60*10);
define('SECONDS_30MINS', 60*30);
define('SECONDS_1HOUR', 60*60);
define('SECONDS_1DAY', 24*SECONDS_1HOUR);
define('SECONDS_1WEEK', 7*SECONDS_1DAY);

/* Include local preferences to override defaults */
@include_once("const_local.php");
?>
