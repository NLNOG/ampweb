<?php
/*
 * AMP Data Display Interface
 *
 * IPerf Test Display Module
 *
 * Author:      Jeremy Kallstrom <jkallstrom@nlanr.net>
 * Version:     $Id: java_data.php 1712 2010-03-10 22:19:05Z brendonj $
 *
 * This module provides data to the java part of the web interface.
 *
 */

require("amplib.php");

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

$data = $_REQUEST["data"];


if ( $data == "site_info" ) {
  $filename = "siteinfo.txt";

  $siteDB = dbconnect($GLOBALS[sitesDB]);
  if ( !$siteDB ) {
    errexit("Unable to connect to sitelist database\n");
  }

  $query = "SELECT ampname, longname, mapx, mapy from sites";
  $result = dbquery($siteDB, $query);
  $rows = $result{'rows'};

  dbclose($siteDB);

  $content = "ampname:longname:mapx:mapy\n";

  for ( $rowNum = 0; $rowNum < $rows; ++$rowNum ) {
    $content .= $result{'results'}[$rowNum][0] . ":" .
                $result{'results'}[$rowNum][1] . ":" .
                $result{'results'}[$rowNum][2] . ":" .
                $result{'results'}[$rowNum][3] . "\n";
  }
} else if ( $data == "map" ) {
  $filename = "mapfilename.txt";
  if ( $dir = opendir(".") ) {
    while ( $fileName = readdir($dir)) {
      if ( substr($fileName, -13) == "_map_info.php" ) {
        $map = substr($fileName, 0, strlen($fileName) - 13);
      }
    }
  }
  $content = $map . ".jpg";
} else if ( $data == "maps" ) {
  $filename = "mapinfo.txt";
  $content = "filename,left,right,top,bottom\n";
  if ( ($dir = opendir(".")) ) {
    while ( $fileName = readdir($dir)) {
      if ( substr($fileName, -13) == "_map_info.php" ) {
        $map = substr($fileName, 0, strlen($fileName) - 13);
        $left = $right = $top = $bottom = -10000;
        $mapFile = fopen($fileName, "r");
        while ( ($line = fgets($mapFile)) ) {
          if ( preg_match("/(map\w+)\s*=\s*([^;]+)/", $line, $matches) == 1 ) {
            if ( $matches[1] == "mapRight" ) {
              $right = $matches[2];
            } else if ( $matches[1] == "mapLeft" ) {
              $left = $matches[2];
            } else if ( $matches[1] == "mapTop" ) {
              $top = $matches[2];
            } else if ( $matches[1] == "mapBottom" ) {
              $bottom = $matches[2];
            }

            if ( $right != -10000 && $left != -10000 && $top != -10000 && $bottom != -10000 ) {
              break;
            }
          }
        }
        $content .= "$map.jpg,$left,$right,$top,$bottom\n";
      }
    }
  }
}


header("Content-Type: text/plain");
header("Content-Disposition: attachment; filename=$filename");
echo $content;
flush();

  // Emacs control
  // Local Variables:
  // eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
