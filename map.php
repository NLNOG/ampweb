<?php
/*
 * AMP Data Display Interface
 *	
 * Map display
 *
 * Author:      Tony McGregor <tonym@cs.waikato.ac.nz>
 * Author:      Jeremy Kallstrom <jkallstrom@nlanr.net>
 * Version:     $Id: map.php 1712 2010-03-10 22:19:05Z brendonj $
 */
 
//---------------------------------------------------------------------------
function findFreeSpot(&$x, &$y)
{

  global $relocateX, $relocateY, $gridSize, $mapSize, $grid;

  $x = (int)($x / $gridSize);
  $y = (int)($y / $gridSize);

  $attempt = 0;
  $layer = 1;
  $tryX = $x;
  $tryY = $y;
  while ( $tryX < 0 || $tryX > $mapSize[0] ||
          $tryY < 0 || $tryY > $mapSize[0] ||
          $grid[$tryX][$tryY] ) {
    $tryX = $x + ($relocateX[$attempt] * $layer);
    $tryY = $y + ($relocateY[$attempt] * $layer);

    if ( $attempt < 7 ) {
      $attempt++;
    } else {
      $attempt = 0;
      $layer++;
    }
  }

  $grid[$tryX][$tryY] = TRUE;
  $x = $tryX * $gridSize;
  $y = $tryY * $gridSize;

}

//---------------------------------------------------------------------------
// Display the selected map
if ($map != "") {

  $relocateX = array(1, -1,  0, 0,  1, -1, -1, 1);
  $relocateY = array(0,  0, -1, 1, -1,  1, -1, 1);

  require $map . "_map_info.php";

  $fileName = cacheFileName("map", $map, $src, $dst, "", "", $cached);
  flush();
  if (!$cached) {
    $mapImage = imagecreatefromjpeg($map . ".jpg");
    if (!$mapImage) {
      graphError("Could not create map image");
    }

    $mapSize = getimagesize($map . ".jpg");
    $starColour = imagecolorallocate($mapImage, $starRGB[0], $starRGB[1],
      $starRGB[2]);
    $selectedStarColour = imagecolorallocate($mapImage, $selectedStarRGB[0],
      $selectedStarRGB[1], $selectedStarRGB[2]);

    $xScale = $mapSize[0] / ($mapRight - $mapLeft);
    $yScale = $mapSize[1] / ($mapBottom - $mapTop);

    for ($x = 0; $x < (int)($mapSize[0] / $gridSize); $x++) {
      for ($y = 0; $y < (int)($mapSize[0] / $gridSize); $y++) {
        $grid[$x][$y] = FALSE;
      }
    }

    $siteDb = dbconnect($GLOBALS[sitesDB]);

    if (!$siteDb) {
      page_error("Could not connect to webusers database. " .
          "Please try again later.");
    }

    echo "<map name=\"map\">\n";

    if ($src != "") {
      $srcs = ampSiteList($src);
      sort($srcs->srcNames);
    }

    if ( !$mesh || $mesh == "Any" ) {
      $query = 'SELECT ampname, longname, mapx, mapy FROM sites';
    } else {
      $query = "select ampname, longname, mapx, mapy from srclistview where meshname = '$mesh'";
    }
    $result = queryAndCheckDB($siteDb, $query);
    $rows = $result{'rows'};
    for ($rowNum = 0; $rowNum < $rows; ++$rowNum) {
      $row = $result{'results'}[$rowNum];

      if ( $row['mapx'] == -200 && $row['mapy'] == -200 ) {
        continue;
      }

      if ( $row['mapx'] == -201 && $row['mapy'] == -201 ) {
	continue;
      }

      $xOffset = ($row['mapx'] - $mapLeft) * $xScale;
      $yOffset = ($row['mapy']- $mapTop)  * $yScale;

      if ( $xOffset < 0 || $xOffset > $mapSize[0] ||
              $yOffset < 0 || $yOffset > $mapSize[1] ) {
        continue;
      }

      findFreeSpot($xOffset, $yOffset);

      for ($point = 0; $point < ($starPoints * 2); $point += 2) {
        $aStar[$point] = (int)(($star[$point  ] / $starSize) +0.5) +
            $xOffset;
        $aStar[$point+1]=(int)(($star[$point+1] / $starSize) +0.5) +
            $yOffset;
      }

      if ($row['ampname'] == $src) {
        imagefilledpolygon($mapImage, $aStar, $starPoints,
            $selectedStarColour);
      } else {
        imagefilledpolygon($mapImage, $aStar, $starPoints,
            $starColour);
      }

      $gridOffset = (int)($gridSize / 2);
      $link = "";
      if ($src == "") {
        $link = "src.php?src=" . urlencode($row['ampname']) . "&amp;map=".urlencode($map);
      } else {
        if (in_array($row['ampname'], $srcs->srcNames)) {
          $numWeeks = get_preference(PREF_GLOBAL, GP_LT_NUM_WEEKS, PREF_GLOBAL);
          $link = "graph.php?src=$src&amp;dst=" . urlencode($row['ampname']) .
            "&amp;rge=".urlencode($numWeeks)."-week";
        }
      }
      if ($link != "") {
	$current_site = htmlspecialchars($row["longname"]) . "(" . htmlspecialchars($row["ampname"]) . ")";
        echo '<area href="'.htmlspecialchars($link).'" shape="circle"';
        echo " coords=\"" . ($xOffset + $gridOffset) . ",";
        echo $yOffset + $gridOffset . ",$gridSize\" alt=\"";
        echo htmlspecialchars($row['ampname']) . "\" OnMouseOver=\"window.status='$current_site'; return true\" OnMouseOut=\"window.status=''; return true\" title=\"$current_site\">\n";
      }
    }

    echo "</map>\n";
    imagejpeg($mapImage, $fileName);

  } //if not cached

  if ($src=="") {
    $type = "source";
  } else {
    $type = "destination";
  }
  echo "Click on a star to select a $type<br>";
  echo "<img src=\"$fileName\" alt=\"map\" usemap=\"#map\"";
  if ( $mapXSize != "" ) {
    echo " width=$mapXSize";
  }
  if ( $mapYSize != "" ) {
    echo " height=$mapYSize";
  }
  echo "><br>\n";

} // if $map != ""

//---------------------------------------------------------------------------
// Display a list of available maps
$maps = array();
if ( $dir = opendir(".") ) {
  while ( $fileName = readdir($dir) ) {
    if ( substr($fileName, -13) != "_map_info.php" ) {
      continue;
    }
    $maps[] = substr($fileName, 0, strlen($fileName) - 13);
  }
  closedir($dir);
}
if ( count($maps) > 0 ) {
  echo "<div>Available Maps:&nbsp;&nbsp;";
  $get = str_replace("&", "&amp;", $get);
  $get = flatten($_GET);
  if ( strlen($get) > 0 ) {
    $get .= "&";
  }
  $get = str_replace("&", "&amp;", $get);
  for ( $i = 0; $i < count($maps) ; $i++ ) {
    $mapName = $maps[$i];
    if ($i > 1) {
      echo "|&nbsp;&nbsp;";
    }
    echo "<a href=\"src.php?${get}map=".htmlspecialchars($mapName)."\">".htmlspecialchars($mapName)."</a>&nbsp;&nbsp;\n";
  }
  echo "</div>";
}

// Emacs control
// Local Variables:
// eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
