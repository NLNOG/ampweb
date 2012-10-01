<?php
/*
 * AMP Matrix Display Library
 *
 * Author:	Brad Cowie <bmc26@waikato.ac.nz>
 * Author:      Brendon Jones <bcj3@cs.waikato.ac.nz>
 * Author:      Yu Wei (Alex) Wang <yww4@cs.waikato.ac.nz>
 * Version:     $Id: matrix_lib.php 1590 2010-01-21 00:31:22Z bmc26 $
 *
 */

require("amplib.php");
ini_set('arg_separator.output','&amp;');

$globalSubtypeList = array();

      
function roundRTT($rtt) {
  if ( $rtt == "" || $rtt < 0 ) 
    return $rtt;
  if ( $rtt < 1 )
    return round($rtt, 1);
  return round($rtt, 0);
}

function stripSiteSuffixes($name) {
    if ( strpos($name, HOST_SEPARATOR) === false )
	return $name;
    return substr($name, 0, strpos($name, HOST_SEPARATOR));
}

function filterSitesByString($string, $sites) {
    $filteredSites = array();
    $string = HOST_SEPARATOR . $string;

    foreach ( $sites as $key => $value ) {
	/* 
	 * string exists in the name, and it is either the last part of
	 * the string, or it is followed by another HOST_SEPARATOR
	 */
	$pos = strpos($key, $string);
	if ( $pos !== false && (($pos+strlen($string) == strlen($key)) ||
	    ($key + $pos + strlen($string) == HOST_SEPARATOR) ) ) {
	    $filteredSites[$key] = $value;
	}
    }
    return $filteredSites;
}


function filterSitesByProtocol($protocol, $sites) {
    $filteredSites = array();
    $suffix = "";

    /* for now, v4 addresses can have no suffix, or ":v4" */
    if ( $protocol == "ipv4" ) {
	foreach ( $sites as $key => $value ) {
	    if ( strpos($key, HOST_SEPARATOR) === false ) {
		$filteredSites[$key] = $value;
	    }
	}
    } 
    
    /* find all sites with the appropriate suffix */
    switch ( $protocol ) {
	case "ipv4": $suffix = "v4"; break;
	case "ipv6": $suffix = "v6"; break;
	case "ethernet": $suffix = "eth"; break;
    };
    return array_merge($filteredSites, filterSitesByString($suffix, $sites));
}

function getLatencyData($ampsource, $ampdest, $duration) {

    $result = array();
    $data = queryPingInfo($ampsource, $ampdest, $duration);

    if(is_object($data) && property_exists($data, "count")) {
	// data exists, lets slightly massage it for displaying purposes and
	// add to our array

	$mean   = '';
	$loss   = '';
	$stddev = '';

	/* if there is no actual data in that time period then leave
	 * the values as blank/missing so they show up purple
	 */
	if($data->count > 0 && $data->mean >= 0 && 
		$data->mean !== '' && !is_nan($data->mean)) {
	    $mean = $data->mean;
	}

	if($data->loss !== '' && !is_nan($data->loss)) {
	    $loss = round($data->loss, 2) * 100;
	}

	if($data->stddev !== '' && !is_nan($data->stddev)) {
	    $stddev = round($data->stddev, 2);
	}
	$result['latency'] = $mean;
	$result['latency-stddev'] = $stddev;
	$result['loss'] = $loss;
    } else {
	$result['latency'] = '';
	$result['latency-stddev'] = '';
	$result['loss'] = '';
    }
    return $result;
}

function getMtuData($ampsource, $ampdest, $duration) {
    $mtu = "";
    $hops = queryScamperMtuInfo($ampsource, $ampdest, $duration);

    /* scamper mtu test is different to the traceroute test in that it will
     * record the final hop in the trace - therefore any good data will have
     * at least one hop in it. Anything less is "no data".
     */
    if ( is_array($hops) && count($hops) > 0 ) {
	$lasthop = $hops[count($hops) - 1];
	/* if the last 5 addresses are 0.0.0.0 then we didn't reach it */
	if ( count($hops) > 5 && 
		($lasthop["ip"] == "0.0.0.0" || $lasthop["ip"] == "::") ) {
	    $complete = false;
	    for ( $i=2; $i <= 5; $i++ ) {
		if ( $hops[count($hops)-$i]["ip"] != "0.0.0.0" &&
			$hops[count($hops)-$i]["ip"] != "::" ) {
		    $complete = true;
		    break;
		}
	    }
	    /* couldn't reach the host at all */
	    if ( $complete == false )
		return "";
	}

	$mtu = $lasthop["mtu"];
	/* 
	 * -1 means an MTU could not be found, which is translated to zero 
	 * here as negative numbers are used to mean that PMTUD failed but a
	 * value for the MTU was still inferred.
	 */
	if ( $mtu < 0 )
	    $mtu = 0;
	if ( !$lasthop["pmtud"] )
	    $mtu = $mtu * -1;
    }

    return $mtu;
}


function getHopData($ampsource, $ampdest, $duration) {

    if ( isHostInfrequentlyTested($ampdest) && $duration < 10800 ) {
	$duration = 10800;
    }

    // get the number of hops
    $lasthopIncluded = false;
    $hops = queryScamperPathInfo($ampsource, $ampdest, $duration);
    if ( is_array($hops) ) {
	$lasthopIncluded = true;
    } else {
	$hops = queryPathInfo($ampsource, $ampdest, $duration);
	$lasthopIncluded = false;
    }

    $hopCount = '';

    if(is_array($hops)) {
	// find timeouts (5 unknown hops in a row) and remove them
	$reachedDestination = true;
	$unknowncount  = 0;
	$maxoffset     = count($hops) - 1;
	$unknownthresh = count($hops) - 5;
	for($i=$maxoffset; $i>=0; $i--) {
	    if($hops[$i]['ip'] == "0.0.0.0" || $hops[$i]['ip'] == "::")
		$unknowncount++;

	    if($unknowncount == 5) {
		$reachedDestination = false;
		for($x=$maxoffset; $x>=$unknownthresh; $x--) {
		    unset($hops[$x]);
		}
		break;
	    }
	}

	if ( $reachedDestination ) {
	    // if a destination is reachable then it is at least 1 
	    // hop away but the AMP test will only record intermediate 
	    // hops, so we need to boost the count by 1 to account for 
	    // the last hop
	    $hopCount = count($hops);
	    if ( !$lasthopIncluded )
		$hopCount++;
	} else {
	    // if its not reachable then count how many hops we did 
	    // see before we got timeouts, and mark it appropriately
	    // (make it negative, will be flipped again on display)
	    $hopCount = count($hops) * -1;
	}
    }

    return $hopCount;
}



/*
 * Query the average round trip time for the given time period, using a 
 * variety of test types. We hit this function a bunch of times with different
 * periods so cache the subtypes in between to save at least a little bit of
 * disk access. The results from here are cached at a higher level.
 */
function queryPingInfo($src, $dst, $offset) {
  global $globalSubtypeList;

  $timeZone = get_preference(PREF_GLOBAL, GP_TIMEZONE, PREF_GLOBAL);

  $subtype = "";
  $test = -1;

  /* check if we have looked this one up before in this run */
  if ( !isset($globalSubtypeList[$src]) ) {
    $globalSubtypeList[$src] = array();
  }
  if ( !isset($globalSubtypeList[$src][$dst]) ) {
    $globalSubtypeList[$src][$dst] = array();
  }

  /* 
   * figure out which test we should be using here, prefer dns2 because all
   * machines are the target of icmp tests but dns2 data is more useful for
   * those sites that do it - we would prefer to show dns latency to dns 
   * servers and icmp latency to other servers.
   */
  if ( isset($globalSubtypeList[$src][$dst][DNS2_DATA]) ) {
    $test = DNS2_DATA;
    $stList = $globalSubtypeList[$src][$dst][DNS2_DATA];
  } else if ( isset($globalSubtypeList[$src][$dst][ICMP_DATA]) ) {
    $test = ICMP_DATA;
    $stList = $globalSubtypeList[$src][$dst][ICMP_DATA];
  } else {
    /* if it's not cached then fetch the subtypes now */
    $stList = ampSubtypeList(DNS2_DATA, $src, $dst);
    if ( isset($stList->subtypes) && $stList->count > 0 ) {
      $test = DNS2_DATA;
      $globalSubtypeList[$src][$dst][DNS2_DATA] = $stList;
    } else {
      $stList = ampSubtypeList(ICMP_DATA, $src, $dst);
      if ( isset($stList->subtypes) && $stList->count > 0 ) {
        $test = ICMP_DATA;
        $globalSubtypeList[$src][$dst][ICMP_DATA] = $stList;
      }
    }
  }
if ( 0 ) {//XXX be smarter about icmp vs dns
  /* figure out which test we should be using here, prefer icmp */
  if ( isset($globalSubtypeList[$src][$dst][ICMP_DATA]) ) {
    $test = ICMP_DATA;
    $stList = $globalSubtypeList[$src][$dst][ICMP_DATA];
  } else if ( isset($globalSubtypeList[$src][$dst][DNS2_DATA]) ) {
    $test = DNS2_DATA;
    $stList = $globalSubtypeList[$src][$dst][DNS2_DATA];
  } else {
    /* if it's not cached then fetch the subtypes now */
    $stList = ampSubtypeList(ICMP_DATA, $src, $dst);
    if ( isset($stList->subtypes) && $stList->count > 0 ) {
      $test = ICMP_DATA;
      $globalSubtypeList[$src][$dst][ICMP_DATA] = $stList;
    } else {
      $stList = ampSubtypeList(DNS2_DATA, $src, $dst);
      if ( isset($stList->subtypes) && $stList->count > 0 ) {
        $test = DNS2_DATA;
        $globalSubtypeList[$src][$dst][DNS2_DATA] = $stList;
      }
    }
  }
}

  if ( $test < 0 || !isset($stList->subtypes) || $stList->count < 1 ) {
    return "";
  }

  if ( $test == ICMP_DATA ) {
    //find the smallest (non-random) packet size used to test between the sites
    foreach( $stList->subtypes as $idx=>$size ) {
      if($size == "rand") continue;
      $sizei = (int)$size;
      if($subtype == "" || $sizei<(int)$subtype) {
        $subtype = $size;
      }
    }

  } else if ( $test == DNS2_DATA ) {
    /* for now, just use the first (and only) one */
    $subtype = $stList->subtypes[0];
    /* 
     * XXX: existing dns2 tests are only running every hour, so thats the
     * shortest time period we can usefully look at
     */
    if ( $offset < 3600 )
      $offset = 3600;
  } else {
    return "";
  }

  // go back exactly the amount of time required
  $time = time() - $offset;
  $db = ampOpenDB($test, $subtype, $src, $dst, $time, 0, $timeZone);


  if ( $db ) {
    if ( $test == ICMP_DATA ) {
      ampgetsummarydataset($db, $offset, 0, &$data);
    } else {
      /* do this manually for now */
      $total = 0;
      $count = 0;
      $loss = 0;
      $squaresSum = 0;
      while ( ($obj = ampNextObj($db)) != NULL ) {
        if ( $obj->latency >= 0 ) {
          $total += $obj->latency;
          $count++;
          $squaresSum += ($obj->latency * $obj->latency);
        } else  {
          $loss++;
        }
      }

      $data["count"] = $count;
      if ( $count > 0 )
        $data["mean"] = $total/$count;
      else 
        $data["mean"] = "";

      if ( $count > 1 )
        $data["stddev"] = 
          sqrt(($squaresSum-(($total*$total)/$count))/($count-1));
      else if ( $count > 0 )
        $data["stddev"] = 0;
      else 
        $data["stddev"] = "";

      if ( $loss+$count > 0 )
        $data["loss"] = $loss/($loss+$count);
      else 
        $data["loss"] = "";

      $data = (object)$data;
    }
    return $data;
  }

  return "";
}


/*
 * query the path from the source to the destination for the given time period
 */
function queryPathInfo($src, $dst, $offset) {
  $timeZone = get_preference(PREF_GLOBAL, GP_TIMEZONE, PREF_GLOBAL);

  /* XXX temporary fix to not having traceroute data for these DNS servers
   * as they are tested to quite infrequently
   */
  if ( isHostInfrequentlyTested($dst) && $offset < 10800 ) {
    $offset = 10800;
  }

  // go back exactly the amount of time required
  $time = time() - $offset;
  $db = ampOpenDB(TRACE_DATA, 0, $src, $dst, $time, 0, $timeZone);

  if($db) {
    $path = getCommonPath($db, $offset);
    if ( is_array($path) )
      return $path;
  }

  return "";
}

function queryScamperPathInfo($src, $dst, $offset) {
  $subtype = getScamperSubtype($src, $dst, "icmp", false);
  if ( strlen($subtype) > 0 )
    return queryScamperInfo($src, $dst, $subtype, $offset);
  return "";
}

function queryScamperMtuInfo($src, $dst, $offset) {
  $subtype = getScamperSubtype($src, $dst, "udp", true);
  if ( strlen($subtype) > 0 )
    return queryScamperInfo($src, $dst, $subtype, $offset);
  return "";
}

function queryScamperInfo($src, $dst, $subtype, $offset) {
  $timeZone = get_preference(PREF_GLOBAL, GP_TIMEZONE, PREF_GLOBAL);

  // go back exactly the amount of time required
  $time = time() - $offset;

  $db = ampOpenDB(SCAMPER_DATA, $subtype, $src, $dst, $time, 0, $timeZone);

  if ( $db ) {
    $path = getCommonPath($db, $offset);
    if ( is_array($path) )
      return $path;
  }

  return "";
}



/* setup all the required stuff for the matrix */

initialise();

/* Get default preferences */
$mesh = get_preference(PREF_GLOBAL, GP_MESH, PREF_GLOBAL);

/* Allow preferences to be overriden by request variables */
if ( isset($options['mesh']) ) {
  $mesh = $options['mesh'];
} else {
  // by default show the NZ mesh
  $options['mesh'] = 'NZ';
  $mesh = "NZ";
}

$username = have_login();
$username = explode("|", $username);
$uid      = (sizeof($username) > 1)?$username[1]:"";
$username = $username[0];

/* Try and connect to the database */
$dbwarning = false;
$siteDb = dbConnect($GLOBALS['sitesDB']);
if ( ! $siteDb ) {
  $dbwarning = true;
}

/* Get list of available meshes */
$meshes = array();
$uidquery = "";
if ( $uid ) {
  $uidquery = " or uid='".pg_escape_string($uid)."'";
}
if ( $src == "" ) {
  $query = "select meshname from meshes where uid = '-1'$uidquery";
} else {
  $query = "select meshname from meshes natural join meshes_sites natural " .
    "join sites where ampname = '".pg_escape_string($src)."' and (uid = '-1'$uidquery)";
}
$query .= " order by meshname";
$resultMesh = queryAndCheckDB($siteDb, $query);
for ( $rowNum = 0; $rowNum < $resultMesh{'rows'}; ++$rowNum ) {
  $meshName = $resultMesh['results'][$rowNum]['meshname'];
  $meshes[$rowNum] = $meshName;
}
if (count($meshes)>0) {
  $haveMesh = true;
} else {
  $haveMesh = false;
}

/* Close DB */
dbCheckAndClose($siteDb);

// find all the sites listed in the database
$siteInfo = array();

getSites($siteInfo, "", $mesh);

// find all the sites that there are directories for
$ampsitelist = ampSiteList(null);

// only bother with those that exist in both places so we can be
// sure we know which mesh they are in and that there is the chance
// there is valid data to be shown
$srcs->srcNames = array_intersect(
  array_keys($siteInfo), array_values($ampsitelist->srcNames));
sort($srcs->srcNames);
$srcs->count = count($srcs->srcNames);

$srcList = array();
for ( $srcIndex = 0; $srcIndex < $srcs->count; ++$srcIndex ) {
  $srcName = $srcs->srcNames[$srcIndex];
  $srcList[$srcName] = 1;

  if ( !$siteInfo[$srcName] ) {
    $siteInfo[$srcName]['longname'] = $srcName;
  }
  if ( !$haveMesh ) {
    $siteInfo[$srcName]['mapx'] = -201;
    $siteInfo[$srcName]['mapy'] = -201;
  }
}

// remove everything from the mesh that isn't a source
foreach(array_keys($siteInfo) as $ampsrc){
    if (!isset($srcList[$ampsrc])){
        unset($siteInfo[$ampsrc]);
    }
}

// Emacs control
// Local Variables:
// eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
