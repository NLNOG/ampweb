<?php
/*
 * AMP API
 *
 *
 * Author:      Brad Cowie <bmc26@waikato.ac.nz>
 * Version:     $Id: api.php 1596 2010-01-21 20:39:45Z bmc26 $
 *
 */

require_once('matrix_lib.php');

function getCachedLatencyData($ampsource, $ampdest, $period) {
	/* we get a few historical periods for latency */
	$intervals = array(
		'10mins' => SECONDS_10MINS,
		'1day'	=> SECONDS_1DAY,
		);
	/* and cache the longer periods for longer */
	$cachetimes = array(
		'10mins' => SECONDS_1MIN,
		'1day' => SECONDS_30MINS,
		);
    $cache_hit = false;
    $key = $ampsource . $ampdest . "latency" . $period;
    if ( $memcache_connected ) {
	if ( ($data = $memcache->get($key)) != false ) {
	    $cache_hit = true;
	}
    } 
    /* fetch and save it if it isn't already in cache */
    if ( $cache_hit == false ) {
	$data = getLatencyData($ampsource, $ampdest, $intervals[$period]);
	/* cache data according to length of period */
	if ( $memcache_connected )
	    $memcache->set($key, $data, 0, $cachetimes[$period]);
    }
    return $data;
}


// everything goes in here
$magicArray = array();

// allow user to change the destination mesh
$siteInfoDest = array();
/* even if source and dest are the same, some hosts can be in a mesh but not
 * act as sources so we still need to query the db for them
 */
if(!empty($options['dest']) && /*$options['dest'] != $options['mesh'] &&*/ strtolower($options['dest']) != 'none') {
    getSites($siteInfoDest, '', $options['dest']);
} else {
    $siteInfoDest = $siteInfo;
}

/* filter by protocol */
$siteInfoDest = filterSitesByProtocol($options["protocol"], $siteInfoDest);
//$siteInfoDest = filterSitesByString("v6", $siteInfoDest);

// sort the arrays by key (site name) alphabetically
ksort($siteInfo);
ksort($siteInfoDest);


/* some metrics fetch a bunch of stuff that gets displayed under different 
 * names, so massage the metric into what we expect
 */
switch ( $options["metric"]) {
    case "hops": $metric = "hops"; break;
    case "mtu": $metric = "mtu"; break;
    default: $metric = "latency"; /* latency, latency-stddev, loss */
};
if(class_exists('Memcache')) {
    $memcache           = new Memcache;
    $memcache_connected = $memcache->connect('localhost', 11211);
}

//$foo_starttime = microtime(true);
foreach(array_keys($siteInfo) as $ampsource) {
    list($ampprefix, $tmpname) = split("-", $ampsource, 2);
    $longname = $siteInfo[$ampsource]['longname'];

    $magicArray[$ampsource]['longname'] = $longname;
    $magicArray[$ampsource]['results']  = array();

    foreach (array_keys($siteInfoDest) as $ampdest) {
	if ( $ampsource == $ampdest )
	    continue;

	switch ( $metric ) {
	    case "hops":
		$cache_hit = false;
		$key = $ampsource . $ampdest . "hops";
		if ( $memcache_connected ) {
		    //echo "<br />$key";
		    if ( ($data = $memcache->get($key)) != false ) {
			$cache_hit = true;
		    }
		    //echo ": " . ($cache_hit?"true":"false");
		} 
		/* fetch and save it if it isn't already in cache */
		if ( $cache_hit == false ) {
		    $data = getHopData($ampsource, $ampdest, SECONDS_30MINS);
		    /* cache traceroutes for 10 mins, as often as it changes */
		    if ( $memcache_connected )
			$memcache->set($key, $data, 0, SECONDS_10MINS);
		}
		/* pass the data back to be displayed */
		$magicArray[$ampsource]['results'][$ampdest]["hops"] = $data;
		break;

	    case "mtu":
		$cache_hit = false;
		$key = $ampsource . $ampdest . "mtu";
		if ( $memcache_connected ) {
		    //echo "<br />$key";
		    if ( ($data = $memcache->get($key)) != false ) {
			$cache_hit = true;
		    }
		    //echo ": " . ($cache_hit?"true":"false");
		} 
		/* fetch and save it if it isn't already in cache */
		if ( $cache_hit == false ) {
		    $data = getMtuData($ampsource, $ampdest, SECONDS_1DAY);
		    /* MTU is updated every 3 hours currently, but only
		     * cache for one hour so we aren't caught out too badly
		     * if caching close to update time. It doesn't change
		     * often anyway, and will need multiple new measurements
		     * to become the "common" path new a new MTU.
		     */
		    if ( $memcache_connected )
			$memcache->set($key, $data, 0, SECONDS_1HOUR);
		}
		/* pass the data back to be displayed */
		$magicArray[$ampsource]['results'][$ampdest]["mtu"] = $data;
		break;

	    case "latency":
		$data = getCachedLatencyData($ampsource, $ampdest, "10mins");
		/* recent latency/loss data for display */
		$magicArray[$ampsource]['results'][$ampdest]['latency']['10mins'] = roundRTT($data['latency']);
		$magicArray[$ampsource]['results'][$ampdest]['loss']['10mins'] = $data['loss'];
	
		/* longer term standard deviation for comparison with recent */
		$data = getCachedLatencyData($ampsource, $ampdest, "1day");
		$magicArray[$ampsource]['results'][$ampdest]['latency']['1day'] = roundRTT($data['latency']);
		$magicArray[$ampsource]['results'][$ampdest]['latency-stddev']['1day'] = roundRTT($data['latency-stddev']);
		break;
	};

    }
}

/* fill in details about destination sites too, if they haven't already
 * appeared as source sites
 */
foreach(array_keys($siteInfoDest) as $ampdest) {
    if ( !isset($magicArray[$ampdest]) ) {
	$longname = $siteInfoDest[$ampdest]['longname'];
	$magicArray[$ampdest]['longname'] = $longname;
    }
}

if ( $memcache_connected )
    $memcache->close();
ksort($magicArray);

?>
