<?php
/*
 * AMP Data Display Interface
 *
 * AMPlet selection
 *
 * Author:      Tony McGregor <tonym@cs.waikato.ac.nz>
 * Author:      Matt Brown <matt@crc.net.nz>
 * Version:     $Id: src.php 1757 2010-05-26 05:41:58Z brendonj $
 *
 */
require("amplib.php");
ini_set('arg_separator.output','&amp;');

//---------------------------------------------------------------------------
// Start the AMP system and initialise page variables
initialise();

/* Get default preferences */
$sort = strtolower(get_preference(PREF_GLOBAL, GP_SORT, PREF_GLOBAL));
$mesh = get_preference(PREF_GLOBAL, GP_MESH, PREF_GLOBAL);

/* Allow preferences to be overriden by request variables */
if (isset($_REQUEST["sort"])) {
  $sort = $_REQUEST["sort"];
}
if (isset($_REQUEST["mesh"])) {
  $mesh = $_REQUEST["mesh"];
}



/*****************************************************************/


$meshes = array();
$siteInfo = array();
$sitesWithData = array();



/*****************************************************************/
/* populate $siteInfo with all sites that should be reached from $src */

/* find the sources we are using, be it none, a host or a mesh */
if ( isMesh($src) ) {
  /* if $src is a mesh then get all sites that are in that mesh */
  getSites($siteInfo, "", $src);

  /* save this because we will need this unmodified list later */
  $members = $siteInfo;
  /* find all the meshes that these sites belong to */
  $meshes = getMeshes(array_keys($siteInfo), true);

  /* get all the meshes that all sites in these meshes are in */
  foreach($meshes as $m) {
    getSites($siteInfo, "", $m);
  }
  $meshes = getMeshes(array_keys($siteInfo));

  if ( $mesh != "Any" ) {
    /* filter the list of sites by those that appear in the given $mesh */
    getSites($byMesh, "", $mesh);
    $siteInfo = array_intersect_key($siteInfo, $byMesh);
  }

} else {
  /* 
   * get the meshes that all sites here belong to so the filter box will always
   * list all available meshes rather than just those the currently displayed
   * sites belong to
   */
  if ( isset($src) && $src != "" ) {
    getSites($siteInfo, $src, "");
    $meshes = getMeshes(array_keys($siteInfo));
    unset($siteInfo);
  }
  /* now get the sites that we want to display based on the current mesh */
  getSites($siteInfo, $src, $mesh);
  /* save this because we will need this unmodified list later */
  $members = $siteInfo;
}



/*****************************************************************/
/* find which sites have data available on disk */
$sitesWithData = array();
if ( isMesh($src) ) {

  foreach(array_keys($members) as $site) {
    $available = ampSiteList($site);
    $sitesWithData = array_merge($sitesWithData, 
        array_diff($available->srcNames, $sitesWithData));
  }

} else {

  $available = ampSiteList($src);
  $sitesWithData = $available->srcNames;

}


/* 
 * we only want to look at sites that are both in the database as belonging 
 * to the appropriate meshes and actually have data available on disk.
 */
$siteInfo = array_intersect_key($siteInfo, array_flip($sitesWithData));

/*
 * If the source isn't set then now is the time to find what meshes we
 * should offer - we only want meshes that have _actual_ source sites in them, 
 * until this point our site list contains all sites in the DB (including sites
 * that are only ever destinations).
 */
if ( !isset($src) || $src == "" )
  $meshes = getMeshes(array_keys($siteInfo));

/*****************************************************************/
/* update the last seen time for all sites in the final list */
foreach(array_keys($siteInfo) as $site) {
  if ( strlen($src) > 0 ) {
    if ( isMesh($src) ) {
      $siteInfo[$site]["lastseen"] = 
        //updateLastSeenTime(array_keys($siteInfo), $site);
        updateLastSeenTime(expandSites($src), $site);
    } else {
      $siteInfo[$site]["lastseen"] = updateLastSeenTime($src, $site);
    }
  } else {
    $siteInfo[$site]["lastseen"] = updateLastSeenTime($site);
  }
}



/*****************************************************************/
/* find all the meshes that the machines in the list belong to */
/* Use something like this to present the full mesh list under "Name" */
/*
if ( isset($src) && !isMesh($src) )
  $destmeshes = $meshes;
else
*/
$destmeshes = getMeshes(array_keys($siteInfo), false);
  
/* 
 * update the last seen time for each mesh that the target machines 
 * belong to (NZ,KAREN,etc) 
 */
foreach($destmeshes as $m) {
  /* get all the machines that are in that mesh (waikato,auckland,etc) */
  $meshsites = array();
  getSites($meshsites, "", $m);

  /* test from all machines in $src to all machines in each mesh */
  $last = 0;
  foreach(array_keys($meshsites) as $ms) {
    if ( isset($src) ) {
      $thislast = updateLastSeenTime(expandSites(($src)), $ms);
    } else {
      $thislast = updateLastSeenTime(array_keys($members), $ms);
    }
    /* 1 is the best measurement, so we can break out early here */
    if ( $thislast == 1 ) {
      $last = 1;
      break;
    }
    /* otherwise update $last if the new value is better */
    if ( $thislast != 0 && ($last == 0 || $thislast < $last) )
      $last = $thislast;
  }
  $meshInfo[$m] = array("lastseen" => $last);
}



/*****************************************************************/




if ( $sort == "longname" ) {
  uasort($siteInfo, "longnamesort");
  $lsortclass = " class=\"sorted\"";
  $nsortclass = "";
  $tsortclass = "";
} else if($sort == "lastseen") {
  uasort($siteInfo, "timesort");
  $tsortclass = " class=\"sorted\"";
  $lsortclass = "";
  $nsortclass = "";
} else {
  ksort($siteInfo);
  $nsortclass = " class=\"sorted\"";
  $lsortclass = "";
  $tsortclass = "";
}

//---------------------------------------------------------------------------
// Begin HTML Output
templateTop();

/* Page header */
echo "<h2>$system_name - ";
if ( $src == "" ) {
  echo "Sources ";
} else {
  echo 'Destinations from '.htmlspecialchars($src).' ';
}
if ( count($meshes) > 0 ) {
  if($mesh == "Any" || $mesh == "None")
    echo "in any mesh";
  else
    echo 'in the '.htmlspecialchars($mesh).' mesh';
}
echo "</h2>";

//---------------------------------------------------------------------------
// Output Mesh Selection Form
if ( count($meshes) > 0 ) {

  echo "<div id=\"meshform\">Filter By Mesh:\n";
  echo "<form action=\"src.php\" method=\"GET\">\n";
  if ( isset($src) && $src != "" )
    echo '<input type="hidden" name="src" value="'.htmlspecialchars($src).'">';
  echo '<input type="hidden" name="sort" value="'.htmlspecialchars($sort).'" />'."\n";
  echo "<select name=\"mesh\">\n";
  echo "<option value=\"Any\"";
  if ( $mesh == "Any" )
    echo " selected";
  echo ">Any</option>\n";
  for ( $rowNum = 0; $rowNum < count($meshes); ++$rowNum ) {
    echo "<option value=\"" . htmlspecialchars($meshes[$rowNum]) . "\"";
    if ( $meshes[$rowNum] == $mesh ) {
      echo " selected";
    }
    echo ">" . htmlspecialchars($meshes[$rowNum]) . "</option>\n";
  }
  echo "</select>\n";
  echo "<input type=\"submit\" value=\"Change Mesh\">\n";
  echo "</form></div>\n";
}


//---------------------------------------------------------------------------
// Link back to source selection
if ( $src != "" ) {
  echo "<div>\n";
  echo "<a href=\"src.php\">other sources</a>\n";
  echo "</div>\n";
}


if(sizeof($siteInfo) < 1 && $_REQUEST['src'] != "") {
  echo '<font color="red">No valid destinations from '.htmlspecialchars($src).'</font>';
  endPage(); 
  exit;
}

/**********************************************************/
/* display super table of everything! */
$sortstring = '';
$meshstring = '&amp;mesh='.urlencode($mesh);

if ( $sort != strtolower(get_preference(PREF_GLOBAL, GP_SORT, PREF_GLOBAL)) )
  $sortstring = '&amp;sort='.urlencode($sort);
/*
if ( $mesh != get_preference(PREF_GLOBAL, GP_MESH, PREF_GLOBAL) )
  $meshstring = "&amp;mesh=$mesh";
*/
if ( $src == "" ) {
  $srclink = "";
} else {
  $srclink='&amp;src='.urlencode($src);
}

echo "<table>\n";
echo "<tr><th$nsortclass>\n";
echo "<a href=\"src.php?sort=ampname$meshstring$srclink\" title=\"" .
"Sort by site name\">Name</a>\n";
echo "</th><th$lsortclass>\n";
echo "<a href=\"src.php?sort=longname&amp;mesh=$mesh$srclink\" title=\"" .
"Sort by site description\">Description</a>\n";
echo "</th>";
echo "<th$tsortclass>\n";
echo "<a href=\"src.php?sort=lastseen&amp;mesh=$mesh$srclink\" title=\"" .
"Sort by last seen\">Last Seen</a>\n";
echo "</th>";

echo "</tr>\n";



/* display table of meshes */
if ( count($meshes) > 0 ) {

  foreach(array_keys($meshInfo) as $m) {
    echo "<tr>\n";
    echo "<td>\n";
    if ( $src == "" ) {
      echo "<a href='src.php?src=$m$meshstring$sortstring'>$m</a>\n";
    } else {
      echo '<a href="graph.php?src='.urlencode($src).'&amp;dst='.urlencode($m).'&amp;rge=1-week">'.htmlspecialchars($m).'</a>'."\n";
    } 
    echo "</td><td>\n";
    echo htmlspecialchars($m);
    echo "</td><td>\n";
    if ( $meshInfo[$m]["lastseen"] > 0 )
      echo htmlspecialchars($meshInfo[$m]["lastseen"]) . " minute(s) ago";
    else
      echo "no recent data";
    echo "</td></tr>\n";


  }

}

/**********************************************************/
//---------------------------------------------------------------------------
// Insert table of sites
//XXX
echo "<tr><td></td><td></td><td></td></tr>";

foreach ( array_keys($siteInfo) as $name ) {
  if ( $siteInfo{$name}{'mapx'} == -200 && 
      $siteInfo{$name}{'mapy'} == -200 ) {
    continue;
  }
  echo "<tr><td>\n";
  if ( $src == "" ) {
    echo '<a href="src.php?mesh='.urlencode($mesh).$sortstring.'&amp;src='.urlencode($name).'">'.htmlspecialchars($name).'</a>'."\n";
  } else {
    echo '<a href="graph.php?src='.urlencode($src).'&amp;dst='.urlencode($name).'&amp;rge=1-week">'.htmlspecialchars($name).'</a>'."\n";
  } 
  echo "</td><td>\n";
  echo htmlspecialchars($siteInfo{$name}{'longname'});
  echo "</td><td>\n";
  if ( $siteInfo[$name]["lastseen"] > 0 )
    echo htmlspecialchars($siteInfo[$name]["lastseen"]) . " minute(s) ago";
  else
    echo "no recent data";
  echo "</td></tr>\n";
}
echo "</table>\n";
endPage();
// Emacs control
// Local Variables:
// eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
