<?php
/*
 * AMP Data Display Interface
 *
 * Display data using the map
 *
 * Author:   Jeremy Kallstrom <jkallstrom@nlanr.net>
 * Version:  $Id: performance_map.php 1712 2010-03-10 22:19:05Z brendonj $
 *
 * Displays data on a map 
 *
 */

require("amplib.php");

initialise();

templateTop();

//Opera insists on not reloading the image from the server, so this is needed
//to force it to do so
if ( strstr($HTTP_SERVER_VARS["HTTP_USER_AGENT"], "Opera") ) {
  if ( !isset($_GET["reload"]) ) {
    echo "<script type=\"text/javascript\">\n";
    if ( !empty($_GET) ) {
      $addText = "&reload=1";
    } else {
      $addText = "?reload=1";
    }
    
    echo "location.replace(location.href + \"$addText\")\n";
    echo "</script>";
  }
}



print "<h2 class=graph>$system_name - Performance Map</h2><Br />\n";

$siteDb = dbConnect($GLOBALS[sitesDB]);

$username = explode("|", have_login());
$uid = $username[1];

//Get the list of meshes, uid = '-1' are sitewide meshes
//If a user is logged in also get there defined meshes
$query = "select meshname from meshes where uid = '-1'";
if ( $uid ) {
  $query .= " or uid = '$uid'";
}

$query .= " order by meshname";

$map = $_REQUEST["map"];
$show = $_REQUEST["show"];
$selected = $_REQUEST["selected"];
$mesh = $_REQUEST["mesh"];

//Get the default mesh if none is selected
if ( !$mesh ) {
  $mesh = get_preference(PREF_GLOBAL, GP_MESH, PREF_GLOBAL);
}

//Does the user want ampnames or longnames in the listing
if ( $show == "Show ampnames" ) {
  $show = "amp";
} else if ( $show == "Show longnames" ) {
  $show = "long";
} else if ( !$show ) {
  $sort = get_preference(PREF_GLOBAL, GP_SORT, PREF_GLOBAL);
  if ( $sort == "longname" ) {
    $show = "long";
  } else {
    $show = "amp";
  }
}

//Get the list of currently selected sites
if ( $_REQUEST["sites"] != "" ) {
  $sites = explode(",", $_REQUEST["sites"]);

  $last = array_pop($sites);
  if ( $last != "" ) {
    array_push($sites, $last);
  }
} else {
  $sites = array();
}

if ( $selected ) {
  foreach ( $selected as $item ) {
    array_push($sites, $item);
  }
}

//New is the one which was clicked on
//So we need to either add or remove it from the list
$new = $_REQUEST["new"];

if ( $new ) {
  if ( in_array($new, $sites) ) {
    foreach ( array_keys($sites) as $key ) {
      if ( $sites[$key] == $new ) {
	unset($sites[$key]);
	break;
      }
    }
  } else {
    array_push($sites, $new);
  }
}

$result = queryAndCheckDB($siteDb, $query);
if ( $result{'rows'} != 0 ) {
  echo "<div style=\"text-align: left\">";
  echo "<form action=\"performance_map.php\" method=\"GET\">";
  echo '<input type="hidden" name="show" value="'.htmlspecialchars($show).'">';
  echo '<input type="hidden" name="map" value="'.htmlspecialchars($map).'">';
  
  echo "<input type=\"hidden\" name=\"sites\" value=\"";
  foreach ( $sites as $site ) {
    echo htmlspecialchars($site).',';
  }
  echo "\">\n";

  echo "<input type=\"submit\" value=\"Change Mesh:\">&nbsp;&nbsp;";

  echo "<select name=\"mesh\">";
  echo "<option value=\"Any\"";
  if ( $mesh == "Any" ) {
    echo " selected";
  }
  echo ">Any</option>\n";

  for ( $rowNum = 0; $rowNum < $result{'rows'}; ++$rowNum ) {
    $meshName = $result{'results'}[$rowNum]{'meshname'};
    echo '<option value="'.htmlspecialchars($meshName).'"';
    if ( $meshName == $mesh ) {
      echo " selected";
    }
    echo '>'.htmlspecialchars($meshName).'</option>'."\n";
  }
  echo "</select></form></div>";
}


//If the pseudo-mesh Any is selected act as if no mesh is selected
if ( $mesh == "Any" ) {
  unset($mesh);
}

echo "<form action=\"performance_map.php\" method=\"GET\">";

if ( !$map ) {
  echo "Available Maps:&nbsp;";
} else {
  echo "Change Map:&nbsp;";
}

if ( $dir = opendir(".") ) {
  
  echo '<input type="hidden" name="mesh" value="'.htmlspecialchars($mesh).'">'."\n";
  echo '<input type="hidden" name="show" value="'.htmlspecialchars($show).'">'."\n";
  echo "<input type=\"hidden\" name=\"sites\" value=\"";
  foreach ( $sites as $site ) {
    echo htmlspecialchars($site).',';
  }
  echo "\">";
  
  while ( $fileName = readdir($dir)) {
    if ( substr($fileName, -13) == "_map_info.php" ) {
      $mapName = substr($fileName, 0, strlen($fileName) - 13);
      echo "&nbsp;";
      echo '<input type="submit" class="srclist" value="'.htmlspecialchars($mapName).'" name="map">'."\n";
    }
  }
  closedir($dir);
  echo "</form>";
  echo "<br />\n";
}

if ( $map ) {
  
  //This line breaks html validity, but it works in most browsers
  echo "<meta http-equiv=\"refresh\" content=\"300\">";
  $siteList = array();

  //  $starttime = microtime();

  $timeZone = get_preference(PREF_GLOBAL, GP_TIMEZONE, PREF_GLOBAL);
  //Back off a bit ( 1 hours worth of data )
  $startTimeSec = timeinzone($timeZone);
  $startTimeSec = $startTimeSec->time - 3600;

  require $map . "_map_info.php";

  $relocateX = array(1, -1, 0, 0, 1, -1, -1, 1);
  $relocateY = array(0, 0, -1, 1, -1, 1, -1, 1);

  //Get the filename, I doubt that we'll ever really cache this image
  $fileName = cacheFileName("map", $map, "", "", session_id(), "", $cached);

  if ( !$cached ) {
    $graph = array();
    $mapImage = imagecreatefromjpeg($map . ".jpg");
    if ( !$mapImage ) {
      graphError("Could not create map image");
    }

    $mapSize = getimagesize($map . ".jpg");

    //The colors are defined in the _map_info.php files
    $starColor = imagecolorallocate($mapImage, $starRGB[0],
				    $starRGB[1], $starRGB[2]);

    $starSelectedColor = imagecolorallocate($mapImage, $selectedStarRGB[0],
					    $selectedStarRGB[1], 
					    $selectedStarRGB[2]);

    $xScale = $mapSize[0] / ($mapRight - $mapLeft);
    $yScale = $mapSize[1] / ($mapBottom - $mapTop);

    $siteDB = dbconnect($GLOBAL[sitesDB]);
    if ( !$siteDb ) {
      page_error("Could not connect to sites database. " .
		 "Please try again later.");
    }

    echo "<map name=\"map\">\n";

    //Get the list of sites in the selected mesh
    if ( !$mesh || $mesh == "Any" ) {
      $query = "SELECT ampname, longname, mapx, mapy FROM sites";
    } else {
      $query = "SELECT ampname, longname, mapx, mapy from srclistview where meshname = '$mesh'";
    }
    $result = queryAndCheckDB($siteDB, $query);

    dbCheckAndClose($siteDB);

    $rows = $result{'rows'};

    //Don't show sites that are outside of the map boundaries
    for ( $rowNum = 0; $rowNum < $rows; ++$rowNum) {
      $row = $result{'results'}[$rowNum];
      if ( $row["mapx"] > $mapRight || $row["mapx"] < $mapLeft ||
	   $row["mapy"] > $mapTop || $row["mapy"] < $mapBottom ) {
	continue;
      }
      $siteData{$row["ampname"]}{'mapx'} = $row["mapx"];
      $siteData{$row["ampname"]}{'mapy'} = $row["mapy"];
      $siteData{$row["ampname"]}{'longname'} = $row["longname"];
    }

    $numberLines = 0;
    $completed = array();
    for ( $rowNum = 0; $rowNum < $rows; ++$rowNum ) {
      $row = $result{'results'}[$rowNum];

      $xOffset = ($row["mapx"] - $mapLeft) * $xScale;
      $yOffset = ($row["mapy"] - $mapTop) * $yScale;

      if ( $xOffset < 0 || $xOffset > $mapSize[0] ||
	   $yOffset < 0 || $yOffset > $mapSize[1] ) {
	continue;
      }

      array_push($siteList, $row["ampname"]);

      findFreeSpot($xOffset, $yOffset);
      
      $site_info["x"] = $xOffset;
      $site_info["y"] = $yOffset;

      $nodeLocations[$row["ampname"]] = $site_info;

      for ( $point = 0; $point < ($starPoints * 2); $point += 2 ) {
	$aStar[$point] = (int)(($star[$point  ] / $starSize) + 0.5) + 
	  $xOffset;
	$aStar[$point+1] = (int)(($star[$point+1] / $starSize) + 0.5) + 
	  $yOffset;
      }

      if ( in_array($row["ampname"], $sites) ) {
	imagefilledpolygon($mapImage, $aStar, $starPoints,
			   $starSelectedColor);
	foreach ( $sites as $site ) {
	  
	  if ( $completed[$site][$row["ampname"]] || $site == $row["ampname"] ) {
	    continue;
	  }
	  
	  $stList = ampSubtypeList(0, $row["ampname"], $site);
	  $items = array();
	  
	  if ( $stList ) {

	    foreach ($stList->subtypes as $idx=>$size) {
	      if ( $size == "rand" ) {
		continue;
	      } else {
		$type = $size;
		break;
	      }
	    }

	    $ampDB = ampOpenDB(0, $type, $row["ampname"], $site, $startTimeSec,
			       0, $timeZone);
	    if ( $ampDB ) {
	      //Get most recent data, make sure that we're going past 
	      // the end of the data

	      $data = getPingInfo($ampDB, 86400, PING_RECENT);

	      if ( $siteData{$site} ) {
		$newxOffset = ($siteData{$site}["mapx"] - $mapLeft) * $xScale;
		$newyOffset = ($siteData{$site}["mapy"] - $mapTop) * $yScale;

		if ( ! ($newxOffset < 0 || newxOffset > $mapSize[0] ||
			$newyOffset < 0 || newyOffset > $mapSize[1]) ) {
		  $info["value"] = $data->PING_RECENT;

		  $info["src"] = $row["ampname"];
		  $info["dest"] = $site;

		  if ( $data && $data->PING_RECENT != -1 ) {
		    $numberLines++;
		    $completed[$row["ampname"]][$site] = 1;
		  }

		  array_push($graph, $info);
		}
	      }
	    }
	  }
	}
      } else {
	imagefilledpolygon($mapImage, $aStar, $starPoints,
			   $starColor);
      }

      $gridOffset = (int)($gridSize /2);

      $link = "performance_map.php?map=".urlencode($map);
      if ( count($sites) != 0 ) {
	$link .= "&amp;sites=";

	foreach ( array_values($sites) as $site ) {
	  $link .= urlencode($site).',';
	}

	$link = rtrim($link, ",");
      }

      $current_site = $siteData[$row["ampname"]]["longname"] . "(" . $row["ampname"] . ")";

      $link .= "&amp;new=" . urlencode($row["ampname"]);
      $link .= '&amp;mesh=' . urlencode($mesh);

      if ( $link != "" ) {
	echo "<area href=\"$link\" shape=\"circle\"";
	echo " coords=\"" . ($xOffset + $gridOffset) . ",";
	echo $yOffset + $gridOffset . ",$gridSize\" alt=\"";
	echo htmlspecialchars($row[0]) . "\" OnMouseOver=\"window.status='".htmlspecialchars($current_site)."'; return true\" OnMouseOut=\"window.status=''; return true\" title=\"".htmlspecialchars($current_site)."\">\n";
      }
    }
    echo "</map>\n";
    
    $top = $legendPos[1] * $yScale;
    $left = $legendPos[0] * $xScale;
    $bottom = $top + 110;
    $right = $left + 80;

    if ( $bottom > $mapSize[1] ) {
      $top -= ($bottom - $mapSize[1]) + 5;
      $bottom = $mapSize[1] - 5;
    }

    if ( $right > $mapSize[0] ) {
      $left -= ($right - $mapSize[0]) + 5;
      $right = $mapSize[0] - 5;
    }

    if ( $top < 0 ) {
      $top = 0;
      $bottom = $top + 110;
    }

    if ( $left < 0 ) {
      $left = 0;
      $right = $left + 90;
    }

    imagefilledrectangle($mapImage, $left, $top, $right, $bottom,
			 imagecolorallocate($mapImage, 245, 230, 230));

    $max = 0;
    $min = 100000;

    sort($graph);

    $ranges = array();

    if ( $numberLines > 5 ) {
      $number = round($numberLines / 5);
      $max = 4;
    } else {
      $number = 1;
      $max = $numberLines;
    }
    $counter = 0;
    foreach( $graph as $info ) {
      if ( $info["value"] == -1 ) {
	continue;
      }
      if ( ++$counter == $number ) {
	$counter = 0;
	array_push($ranges, $info["value"]+1);
	if ( count($ranges) == $max ) {
	  break;
	}
      }
    }

    $newleft = $left + 2;
    $newtop = $top;

    imagestring($mapImage, 5, $newleft, $newtop, "RTT", 0);

    $newtop += 20;
    $newleft += 2;
    
    $colors = array();

    //Create the legend based on the number of selected sites
    if ( $numberLines == 1 ) {
      $color = imagecolorallocate($mapImage, 0, 255, 0);
      $ranges[0]-=2;
      
      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 16, $newtop + 16,
			   $color);
      
      imagestring($mapImage, 2, $newleft + 24, $newtop, $ranges[0]+1, $color);

      array_push($colors, $color);
    } else if ( $numberLines == 2 ) {

      $ranges[0]-=2;
      $ranges[1]-=2;

      $color = imagecolorallocate($mapImage, 0, 255, 0);
      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);

      imageString($mapImage, 2, $newleft + 24, $newtop-1, $ranges[0]+1, 0);

      array_unshift($colors, $color);

      $newtop += 15;

      $color = imagecolorallocate($mapImage, 255, 255, 0);
      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);

      imageString($mapImage, 2, $newleft + 24, $newtop-1, $ranges[1]+1, 0);

      array_unshift($colors, $color);

    } else if ( $numberLines == 3 ) {

      $ranges[0]-=2;
      $ranges[1]-=2;
      $ranges[2]-=2;

      $color = imagecolorallocate($mapImage, 0, 255, 0);
      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);

      imageString($mapImage, 2, $newleft + 24, $newtop-1, $ranges[0]+1, 0);

      array_unshift($colors, $color);

      $newtop += 15;

      $color = imagecolorallocate($mapImage, 255, 255, 0);

      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);

      imageString($mapImage, 2, $newleft + 24, $newtop-1, $ranges[1]+1, 0);

      array_unshift($colors, $color);

      $newtop += 15;

      $color = imagecolorallocate($mapImage, 204, 150, 255);

      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);

      imageString($mapImage, 2, $newleft + 24, $newtop-1, $ranges[2]+1, 0);

      array_unshift($colors, $color);

      $ranges = array_unique($ranges);

    } else if ( $numberLines == 4 ) {

      $ranges[0]-=2;
      $ranges[1]-=2;
      $ranges[2]-=2;
      $ranges[3]-=2;

      $color = imagecolorallocate($mapImage, 0, 255, 0);
      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);

      imageString($mapImage, 2, $newleft + 24, $newtop-1, $ranges[0]+1, 0);

      array_unshift($colors, $color);

      $newtop += 15;

      $color = imagecolorallocate($mapImage, 255, 255, 0);

      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);

      imageString($mapImage, 2, $newleft + 24, $newtop-1, $ranges[1]+1, 0);

      array_unshift($colors, $color);

      $newtop += 15;

      $color = imagecolorallocate($mapImage, 204, 150, 255);

      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);

      imageString($mapImage, 2, $newleft + 24, $newtop-1, $ranges[2]+1, 0);

      array_unshift($colors, $color);

      $newtop += 15;

      $color = imagecolorallocate($mapImage, 0, 0, 255);

      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);

      imageString($mapImage, 2, $newleft + 24, $newtop-1, $ranges[3]+1, 0);

      array_unshift($colors, $color);
    } else if ( $numberLines == 5 ) {

      $ranges[0]-=2;
      $ranges[1]-=2;
      $ranges[2]-=2;
      $ranges[3]-=2;
      $ranges[4]-=2;


      $color = imagecolorallocate($mapImage, 0, 255, 0);
      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);

      imageString($mapImage, 2, $newleft + 24, $newtop-1, $ranges[0]+1, 0);

      array_unshift($colors, $color);

      $newtop += 15;

      $color = imagecolorallocate($mapImage, 255, 255, 0);

      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);

      imageString($mapImage, 2, $newleft + 24, $newtop-1, $ranges[1]+1, 0);

      array_unshift($colors, $color);

      $newtop += 15;

      $color = imagecolorallocate($mapImage, 204, 150, 255);

      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);

      imageString($mapImage, 2, $newleft + 24, $newtop-1, $ranges[2]+1, 0);

      array_unshift($colors, $color);

      $newtop += 15;

      $color = imagecolorallocate($mapImage, 0, 0, 255);

      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);

      imageString($mapImage, 2, $newleft + 24, $newtop-1, $ranges[3]+1, 0);

      array_unshift($colors, $color);

      $newtop += 15;

      $color = imagecolorallocate($mapImage, 255, 102, 0);

      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);

      imageString($mapImage, 2, $newleft + 24, $newtop-1, $ranges[4]+1, 0);

      array_unshift($colors, $color);
    } else {

      $color = imagecolorallocate($mapImage, 0, 255, 0);

      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);
      
      imagestring($mapImage, 2, $newleft + 24, $newtop, "> 0", 0);
      array_push($colors, $color);

      $newtop += 15;

      $color = imagecolorallocate($mapImage, 255, 255, 0);      
      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);
      imagestring($mapImage, 2, $newleft + 24, $newtop, "> " . $ranges[0], 0);
      array_push($colors, $color);      

      $newtop += 15;

      $color = imagecolorallocate($mapImage, 204, 150, 255);
      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);
      imagestring($mapImage, 2, $newleft + 24, $newtop, "> " . $ranges[1] , 0);  
      array_push($colors, $color);      

      $newtop += 15;

      $color = imagecolorallocate($mapImage, 0, 0, 255);
      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);
      imagestring($mapImage, 2, $newleft + 24, $newtop, "> " . $ranges[2], 0);  
      array_push($colors, $color);      

      $newtop += 15;
      
      $color = imagecolorallocate($mapImage, 255, 102, 0);
      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   $color);
      imagestring($mapImage, 2, $newleft + 24, $newtop, "> " . $ranges[3], 0);  
      array_push($colors, $color);

      $colors = array_reverse($colors);

      array_unshift($ranges, 0);

      $newtop += 15;
      imagefilledrectangle($mapImage, $newleft, $newtop, $newleft + 12, $newtop + 12,
			   0);
      imagestring($mapImage, 2, $newleft + 24, $newtop, "Loss", 0);  
      
    }

    $ranges = array_reverse($ranges);

    //Draw the lines
    foreach ( $graph as $info ) {
      $pos = 0;

      if ( $info["value"] == -1 ) {
	$src = $info["src"];
	$dest = $info["dest"];
	
	$x1 = $nodeLocations[$src]["x"];
	$y1 = $nodeLocations[$src]["y"];
	$x2 = $nodeLocations[$dest]["x"];
	$y2 = $nodeLocations[$dest]["y"];
	
	imagelinethick($mapImage, $x1, $y1, $x2, $y2, 0, 1);
	continue;
      }

      foreach ( $ranges as $range ) {
	if ( $info["value"] > $range ) {
	  $src = $info["src"];
	  $dest = $info["dest"];

	  $x1 = $nodeLocations[$src]["x"];
	  $y1 = $nodeLocations[$src]["y"];
	  $x2 = $nodeLocations[$dest]["x"];
	  $y2 = $nodeLocations[$dest]["y"];
	  imagelinethick($mapImage, $x1, $y1, $x2, $y2, $colors[$pos], 1);
	  
	  break;
	}
	++$pos;
      }
    }

    imagejpeg($mapImage, $fileName);
  }

  $size = $mapSize[1]/19;


  echo "<form action=\"performance_map.php\" method=\"get\">";
  echo '<input type="hidden" name="map" value="'.htmlspecialchars($map).'">';
  echo '<input type="hidden" name="show" value="'.htmlspecialchars($show).'">';
  echo '<input type="hidden" name="mesh" value="'.htmlspecialchars($mesh).'">';
  echo "<table>";
  echo "<tr><td align=center>";
  if ( $show == "amp" ) {
    echo "<input type=\"submit\" name=\"show\" class=\"srclist\" value=\"Show longnames\">";
  } else {
    echo "<input type=\"submit\" class=\"srclist\" name=\"show\" value=\"Show ampnames\">";
  }
  echo '</td><td rowspan="3"><img id="testing" src="'.$fileName.'" alt="map" usemap="#map" /></tr>';
  echo "<tr><td align=center valign=\"top\">";
  $size = floor($size);
  echo "<select name=\"selected[]\" multiple size=$size>";


  if ( $show == "amp" ) {
    sort($siteList);
    foreach ( $siteList as $site ) {
      if ( in_array($site, $sites) ) {
	echo '<option value="'.htmlspecialchars($site).'" selected>'.htmlspecialchars($site).'</option>';
      } else {
	echo '<option value="'.htmlspecialchars($site).'">'.htmlspecialchars($site).'</option>';
      }
    }
  } else {
    uasort($siteData, listSort);
    foreach ( array_keys($siteData) as $site ) {
      if ( in_array($site, $sites) ) {
	echo '<option value="'.htmlspecialchars($site).'" selected>'.htmlspecialchars($siteData[$site]['longname']).'</option>';
      } else {
	echo '<option value="'.htmlspecialchars($site).'">'.htmlspecialchars($siteData[$site]['longname']).'</option>';
      }
    }
  }

?>
</select>
</tr><tr><td align="center">
<input type="submit" value="Update">
</td></tr></table>

<?
  echo '<input type="hidden" name="mesh" value="'.htmlspecialchars($mesh).'">';
  echo "</form>";

//   list($endut, $endt) = explode(" ", microtime());
//   list($startut, $startt) = explode(" ", $starttime);

//   print "Draw Time: ";
//   print $endut - $startut - ($startt - $endt) . "<BR />\n";



}
endPage();

function listSort($a, $b) {
  if ( $a["longname"] == $b["longname"] ) {
    return 0;
  }

  return ( $a["longname"] < $b["longname"]) ? -1 : 1;
}

//---------------------------------------------------------------------------
//Used to draw a line which is hopefully of
function imagelinethick($image, $x1, $y1, $x2, $y2, $color, $thick = 1) {
  if ( $thick == 1 ) {
    return imageline($image, $x1, $y1, $x2, $y2, $color);
  }

  $t = $thick /2 - 0.5;
  if ( $x1 == $x2 || $y1 == $y2 ) {
    return imagefilledrectangle($image, round(min($x1, $x2) - $t),
				round(min($y1, $y2) - $t),
				round(max($x1, $x2) + $t),
				round(max($y1, $y2) + $t),
				$color);
  }
  
  $k = ($y2 - $y1) / ($x2 - $x1);
  $a = $t / sqrt(1 + (2 << $k));

  $points = array(round($x1 - (1 + $k) * $a), round($y1 + (1 - $k) * $a),
		  round($x1 - (1 - $k) * $a), round($y1 - (1 + $k) * $a),
		  round($x2 + (1 + $k) * $a), round($y2 - (1 - $k) * $a),
		  round($x2 + (1 - $k) * $a), round($y2 + (1 + $k) * $a));

  imagefilledpolygon($image, $points, 4, $color);
  
  return imagepolygon($image, $points, 4, $color);

}

//---------------------------------------------------------------------------
function findFreeSpot(&$x, &$y){
	
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

// Emacs control
// Local Variables:
// eval: (c++-mode)

?>
