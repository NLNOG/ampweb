<?
require("amplib.php");
//require("/home/httpd/erg.wand.net.nz/html/amp/testing/bmc26/profiler.php");

/* XXX amplib.php actually sets these, though it uses the empty string if there
 * is no useful value in $_REQUEST. Lets unset them for now so we can set them
 * to what we expect later on. This used to work, did PHP change the behaviour
 * of isset() to be true for the empty string? Or did we change amplib.php to
 * add those lines?
 */
unset($src);
unset($dst);


/*
 * Print an error using the appropriate formatting and terminate.
 */
function printError($format, $code, $brief, $details) {
    /* actually do want to return 200 so error details get through? */
    //header("HTTP/1.1 $code $brief");

    switch ( $format ) {
	case "xml":
	    $error = new SimpleXMLElement("<error/>");
	    $error->addChild("code", $code);
	    $error->addChild("message", $details);
	    echo $error->asXML();
	    break;

	case "json":
	    $error = json_encode(array("error" => 
			array("code" => $code,"message" => $details)));
	    echo $error;
	    break;

	case "csv":
	case "text": echo $details; break;
    };

    exit;
}


/* 
 * Print a list of sites reachable.
 */
function printSiteList($format, $siteList, $src=null) {
    switch ( $format ) {
	case "xml":
	    $response = new SimpleXMLElement("<response/>");
	    $sites = $response->addChild("sites");
	    if ( $src != null )
		$sites->addAttribute("src", $src);
	    foreach ( $siteList as $site ) {
		$sites->addChild("site", $site);
	    }
	    echo $response->asXML();
	    break;

	case "json":
	    echo json_encode(array("response" => array("sites" => $siteList)));
	    break;

	case "csv":
	case "text":
	    if ( $src != null )
		echo "# src='$src'\n";
	    foreach ( $siteList as $site ) {
		echo "$site\n";	
	    }
	    break;
    };
}

/*
 * Print a list of tests available
 */
function printTestList($format, $testList, $src, $dst) {
    if ( $testList == NULL )
	$testList = array();
    switch ( $format ) {
	case "xml":
	    $response = new SimpleXMLElement("<response/>");
	    $tests = $response->addChild("tests");
	    $tests->addAttribute("src", $src);
	    $tests->addAttribute("dst", $dst);
	    foreach ( $testList as $test ) {
		$node = $tests->addChild("test");
		$node->addChild("id", $test);
		$node->addChild("name", getTestName($test));
	    }
	    echo $response->asXML();
	    break;

	case "json":
	    $tests = array();
	    foreach ( $testList as $test )
		$tests[$test] = getTestName($test);
	    /* XXX if the test ids are consecutive this makes an array, 
	     * otherwise it makes an object. The version of PHP being used
	     * is too old to use JSON_FORCE_OBJECT, though it appears you
	     * can probably always set $assoc=true when decoding to make it 
	     * convert everything to an array so it is consistent.
	     */
	    echo json_encode(array("response" => array("tests" => $tests)));
	    break;

	case "csv":
	case "text": 
	    echo "# src='$src' dst='$dst'\n";
	    foreach ( $testList as $test ) {
		echo "$test " . getTestName($test) . "\n";	
	    }
	    break;
    };
}

/*
 * Print a list of subtypes available for a test
 */
function printSubtypeList($format, $subtypeList, $test, $src, $dst) {

    if ( $subtypeList == array() )
	$subtypeList = array($test);

    switch ( $format ) {
	case "xml":
	    $response = new SimpleXMLElement("<response/>");
	    $subtypes = $response->addChild("subtypes");
	    $subtypes->addAttribute("src", $src);
	    $subtypes->addAttribute("dst", $dst);
	    $subtypes->addAttribute("test", $test);
	    foreach ( $subtypeList as $subtype ) {
		$node = $subtypes->addChild("subtype", $subtype);
	    }
	    echo $response->asXML();
	    break;

	case "json":
	    echo json_encode(array("response" => 
			array("subtypes" => $subtypeList)));
	    break;

	case "csv":
	case "text": 
	    echo "# src='$src' dst='$dst' test='$test'\n";
	    foreach ( $subtypeList as $subtype ) {
		echo "$subtype\n";
	    }
	    break;
    };
}


/*
 * Data objects have some properties that we don't really care about when
 * binning data, so remove them.
 */
function filterObjectVars($var) {
    $illegal = array("isNumeric", "error", "time", "secInPeriod", "instance");

    if ( in_array($var, $illegal) )
	return false;

    return true;
}


/*
 * Zero all the current bin counters for everything reported by this test type
 */
function initialiseThisBin($test, $statnames) {
    $thisBin = array();

    foreach ( $statnames as $stat ) {
	$thisBin[$stat]["binTotal"] = 0;
	$thisBin[$stat]["binCount"] = 0;
	$thisBin[$stat]["max"] = 0;
	$thisBin[$stat]["min"] = PHP_INT_MAX;
	$thisBin[$stat]["missing"] = 0;
    }

    return $thisBin;
}

/*
 * Build a new set of instance stats with their own summary and bins to use
 * for tracking data.
 */
function initialiseInstanceStats($test, $statnames, $summary) {

    $result = array (
	    "thisBin" => array(),
	    "bins" => array(),
	    );
    
    $result["thisBin"] = initialiseThisBin($test, $statnames);

    if ( $summary ) {
	$result["summary"] = array();
	foreach ( $statnames as $stat ) {
	    $result["summary"][$stat]["mean"] = 0;
	    $result["summary"][$stat]["count"] = 0;
	    $result["summary"][$stat]["max"] = 0;
	    $result["summary"][$stat]["min"] = PHP_INT_MAX;
	    $result["summary"][$stat]["missing"] = 0;
	}
    }
    return $result;
}


/*
 * Update the current bin with the stats from the most recent object.
 * Passing thisBin by reference gives a slight speed increase...
 */
function updateThisBin($obj, &$thisBin, $test, $statnames) {

    foreach ( $statnames as $stat=>$statname ) {
	/* XXX this wont work with an array like udpstream test uses */
	$value = $obj->$stat;

	if ( $value >= 0 ) {
	    $thisBin[$statname]["binTotal"] += $value;
	    $thisBin[$statname]["binCount"]++;

	    if ( $value > $thisBin[$statname]["max"] )
		$thisBin[$statname]["max"] = $value;

	    if ( $value < $thisBin[$statname]["min"] )
		$thisBin[$statname]["min"] = $value;
	} else {
	    $thisBin[$statname]["missing"]++;
	}
    }
    return $thisBin;
}

//XXX stats and outputstat need better names!
function updateFinishedBin($time, $test, &$thisBin, $statnames, $outputstat) {

    $binned = array("time"=>$time);

    foreach ( $statnames as $stat ) {

	foreach ( $outputstat as $output ) {

	    $thisBinStat = $thisBin[$stat];
	    $finalStat = &$binned[$stat];
	    $finalStat["missing"] = $thisBinStat["missing"];
	    $finalStat["count"] = $thisBinStat["binCount"];

	    if ( $thisBinStat["binCount"] > 0 ) {

		/* XXX loss and count here or up a level? */


		switch ( $output ) {
		    case "mean":
			/* calculate mean for all stats in the bin*/
			$finalStat["mean"] =  
			$thisBinStat["binTotal"] /
			$thisBinStat["binCount"];
		    break;

		    case "max":
			$finalStat["max"] = $thisBinStat["max"];
		    break;

		    case "min":
			$finalStat["min"] = $thisBinStat["min"];
		    break;

		    case "jitter":
			$finalStat["jitter"] = 
			$thisBinStat["max"] - 
			$thisBinStat["min"];
		    break;

		    case "loss":
			$finalStat["loss"] = 
			$thisBinStat["missing"] /
			($thisBinStat["missing"]+
			 $thisBinStat["binCount"]);
		    break;
		};
	    } else if ( $thisBinStat["missing"] > 0 ) {
		/* time is set but no count, therefore loss */
		switch ( $output ) {
		    case "loss": $finalStat["loss"] = 1;
			break;

		    case "missing": break;
		    case "count": break;

		    default: $finalStat[$output] = -1;
			     break;
		};
	    }
	}
    }
    return $binned;
}
	    

/*
 * Update the most recent bin for all instances once we have a new datapoint
 * that falls outside of the bin. Now is the time to update global summary
 * stats, copy thisBin data into its proper location and then reinitialise it.
 * Passing $instances by reference here makes this function much faster!
 */
function updateInstanceBins(&$instances, $bin, $test, $statnames, 
	$outputstat, $summary) {

    foreach($instances as $name => &$instance) {

	if ( !empty($instance["bins"][$bin]["time"]) ) {
	//if ( strlen($instance["bins"][$bin]["time"]) > 0 ) {
	    $instance["bins"][$bin] = updateFinishedBin(
		    $instance["bins"][$bin]["time"], $test,
		    $instance["thisBin"], $statnames, $outputstat);
	}

	if ( $summary ) {
	    $instance["summary"] = updateSummary($instance["summary"], 
		    $test, $instance["thisBin"], $statnames);
	}

	/* zero everything ready for the next bin */
	$instance["thisBin"] = initialiseThisBin($test, $statnames);
    }

    return $instances;
}


/*
 * Update global summary stats based on the most recently completed bin
 */
function updateSummary($summary, $test, $thisBin, $statnames) {

    foreach ( $statnames as $stat ) {

	/* if there were no legitimate measurements we can skip everything
	 * that doesn't involve counting loss
	 */
	$count = $thisBin[$stat]["binCount"];
	if ( $count > 0 ) {

	    /* 
	     * update the running mean of all values by treating every 
	     * measurement in the bin as if it were the bin mean.
	     */
	    for($i=0; $i<$count; $i++) {
		$summary[$stat]["count"]++;
		$value = $thisBin[$stat]["binTotal"]/$count;
		$delta = $value - $summary[$stat]["mean"];
		$summary[$stat]["mean"] += ($delta / $summary[$stat]["count"]);
	    }

	    if ( $thisBin[$stat]["max"] > $summary[$stat]["max"] )
		$summary[$stat]["max"] = $thisBin[$stat]["max"];

	    if ( $thisBin[$stat]["min"] < $summary[$stat]["min"] )
		$summary[$stat]["min"] = $thisBin[$stat]["min"];
	}

	//$summary[$stat]["missing"] += $thisBin[$stat]["lossCount"];
	$summary[$stat]["missing"] += $thisBin[$stat]["missing"];

    }

    return $summary;
}


/*
 * 
 */
function getDataInstanceFromObject($obj) {
    //if ( $obj && property_exists($obj, "instance") ) // really slow
    if ( $obj && isset($obj->instance) )
	return $obj->instance;
    else
	return "default";
}


/*
 * To do "binning" of traceroutes it is simply taking the most common
 * path observed during that period, in much the same way as the graphs
 * only show the most common path.
 *
 * The getCommonPath() function also appears to return at least one path
 * for any time period
 */
function getBinnedTraceValues($dataset, $start, $secOfData, $binsize) {

    $paths = array("default" => array("bins" => array()));
    $bin = 0;
    $binStartTime = 0;
    $binsize = (int)$binsize;
    if ( $binsize < 1 )
	$binsize = 1;

    /* $info here is used as an easy way to get information about the dataset
     * that was handed to us so we can open the same one, just one binsize
     * into the future.
     *
     * getCommonPath seems to leave the dataset in a bad state when it
     * completes (it is pointing to a file 2 days in the future when it
     * should only have consumed a few minutes of data). Ideally we should
     * just be able to loop around getCommonPath without having to reopen
     * the dataset every time.
     */
    $info = ampInfoObj($dataset);

    while ( $binStartTime < $secOfData ) {
	
	$dataset = ampOpenDb($info->dataType, $info->dataSubtype, 
		$info->src, $info->dst, $start+$binStartTime, 0, "UTC");
	
	$paths["default"]["bins"][$bin]["time"] = $start+$binStartTime;
	$paths["default"]["bins"][$bin]["path"] = 
	    getCommonPath($dataset, $binsize);

	$bin++;
	$binStartTime += $binsize;
    }

    return $paths;
}

/*
 * XXX TODO: make it so things like packetsize aren't calculated for loss!
 * exclude them from all things and just have them as attributes? or just do
 * it for things that make sense like max, mean etc? see what other test
 * types suggest perhaps
 *
 * binsize is now in seconds!
 */
function getBinnedValues($dataset, $test, $start, $secOfData, $outputstat, 
	$binsize, $summary) {

    $bin = 0;
    $binStartTime = 0;
    $binsize = (int)$binsize;
    if ( $binsize < 0 )
	$binsize = 0;

    $statnames = array();
    $instances = array();

/*
    $prof_ampnextobj = new Profile();
    $prof_is_object = new Profile();
    $prof_getdatainstance = new Profile();
    $prof_buildinstance = new Profile();
    $prof_finishedbin = new Profile();
    $prof_finishedbincheck = new Profile();
    $prof_currentbin_pre = new Profile();
    $prof_currentbin = new Profile();
    $prof_updatethisbin = new Profile();
    $prof_updateinstancebins = new Profile();
    $prof_finishedA = new Profile();
    $prof_finishedC = new Profile();
    */
    /* Retrieve the data - Loop until bins are filled */
    do {
	//$prof_ampnextobj->start('ampnextobj');

	/* Get a measurement */
	$obj = ampNextObj($dataset);
	//$prof_ampnextobj->stop();

	//$prof_is_object->start('is_object');
	if ( $obj == FALSE || !is_object($obj) )
	    break;
	
	//$prof_is_object->stop();

	/* only NZRS tests will have instances, but we need to track them
	 * separately. Other tests may benefit from this in the future,
	 * (maybe the tput test?)
	 */
	//$prof_getdatainstance->start('getdatainstance');
	$dataInstance = getDataInstanceFromObject($obj);
	$secInPeriod = $obj->secInPeriod;

	//$prof_getdatainstance->stop();

	//$prof_buildinstance->start('buildinstance');
	/* initialise values for a new server instance */
	if ( !isset($instances[$dataInstance]) && 
		strlen($dataInstance) > 0/* && $dataInstance != "NULL"*/ ) {
	    /* 
	     * remove the elements that every object has to find the ones
	     * that are specific to this test
	     */
	    $stats = array_filter(array_keys(get_object_vars($obj)), 
		    "filterObjectVars");
	    //XXX Why? why not just leave everything in the obj name as is
	    // until we go to print it out and then give the pretty name?
	    if ( $statnames == array() ) {
		//$statnames = getStatName($test);
		foreach($stats as $stat)
		    $statnames[$stat] = getStatName($test, $stat);
	    }
	    $instances[$dataInstance] = 
		initialiseInstanceStats($test, $statnames, $summary);
	}

	//$prof_buildinstance->stop();

	//$prof_finishedbin->start('finishedbin');
	//$prof_finishedbincheck->start('finishedbincheck');
	/* if measurement is outside the bin, calculate the stats for the bin */
	if ( $secInPeriod > $secOfData || $binsize == 0 ||
		$secInPeriod > ($binStartTime + $binsize) ) {
	    //$prof_finishedbincheck->stop();

	    //$prof_finishedA->start("updateinstancebinsA");
	    if ( $binsize > 0 ) {
		$binStartTime = $secInPeriod - ($secInPeriod%$binsize);
	    }
	    //$prof_finishedA->stop();

	    //$prof_updateinstancebins->start("updateinstancebinsB");
	    $instances = updateInstanceBins($instances, $bin, $test, 
		    $statnames, $outputstat, $summary);
	    //$prof_updateinstancebins->stop();

	    //$prof_finishedC->start("updateinstancebinsC");
	    /* TODO bin can be incremented even if nothing is saved, care? */
	    $bin++;
	
	    /* Check this measurement is still in requested time period */
	    if ( $secInPeriod > $secOfData ) {
		/* End loop if it's not */
		break;
	    }
	    //$prof_finishedC->stop();
	} //else $prof_finishedbincheck->stop();

	//$prof_finishedbin->stop();


	/* ignore loss for now, might want to record them but against 
	 * which instance?
	 * XXX: maybe only ignore loss if part of the bin is lost, if the
	 * whole bin is lost then report it?
	 * XXX no instance to associate loss with in NZRS test
	 */
	//$prof_currentbin_pre->start('currentbin_pre');
	if ( (empty($dataInstance) && strlen($dataInstance) < 1)/* || 
		$dataInstance == "NULL"*/ )
	    continue;

	/* measurement is good, record it */
	$base = &$instances[$dataInstance]["bins"][$bin];
	//$prof_currentbin_pre->stop();

	//$prof_currentbin->start('currentbin');
	/* first measurement in this bin should also record the time */
	if ( !isset($base["time"]) ) {
	    if ( $binsize == 0 )
		$base["time"] = $obj->time;
	    else
		$base["time"] = $start + $binStartTime + ((int)($binsize/2));
	}
	//$prof_currentbin->stop();

	//$prof_updatethisbin->start("updatethisbin");
	$instances[$dataInstance]["thisBin"] = updateThisBin($obj, 
		$instances[$dataInstance]["thisBin"], $test, $statnames);
	//$prof_updatethisbin->stop();

    } while(TRUE);
	
    /* if we get here because the object is wrong, it means we haven't
     * put the last bin together properly, do so now.
     */
    if ( $obj == FALSE || !is_object($obj) ) {
	$instances = updateInstanceBins($instances, $bin, $test, 
		$statnames, $outputstat, $summary);
    }

    /*
    echo $prof_ampnextobj->getSummary();
    echo $prof_is_object->getSummary();
    echo $prof_getdatainstance->getSummary();
    echo $prof_buildinstance->getSummary();
    echo $prof_finishedbin->getSummary();
    echo $prof_finishedbincheck->getSummary();
    echo $prof_updateinstancebins->getSummary();
    echo $prof_finishedA->getSummary();
    echo $prof_finishedC->getSummary();
    echo $prof_currentbin_pre->getSummary();
    echo $prof_updatethisbin->getSummary();
    echo $prof_currentbin->getSummary();
    */
    return $instances;

}



/*
 * XXX this whole function is hax because i want instances inside summaries
 * while the data has summaries inside instances
 */
function printSummary($format, $info, $start, $end, $binsize, $summary) {

    $src = $info["src"];
    $dst = $info["dst"];
    $test = $info["testType"];
    $subtype = $info["testSubType"];

    switch ( $format ) {
	case "xml":
	    echo "<summary src='$src' dst='$dst' " . 
	    "start='$start' end='$end' test='" . getTestName($test) . 
	    "' subtype='$subtype' binsize='$binsize'>";
	/*
	   $response = new SimpleXMLElement("<response/>");
	   $node = $response->addChild("summary");
	   $node->addAttribute("src", $src);
	   $node->addAttribute("dst", $dst);
	   $node->addAttribute("start", $start);
	   $node->addAttribute("end", $end);
	   $node->addAttribute("test", getTestName($test));
	   $node->addAttribute("subtype", $subtype);
	   $node->addAttribute("binsize", $binsize);
	 */

	    foreach($summary as $instance => $data) {
		$response = new SimpleXMLElement("<response/>");

		foreach($data["summary"] as $stat => $values) {
		    $node = $response->addChild($stat);
		    foreach($values as $type => $value)
			$node->addChild($type, $value);

		    printInstanceHeaders($format, $test, $instance);
		    echo $node->asXML();
		    printInstanceFooters($format, $test, $instance);
		}
	    }
	    echo "</summary>";
	    break;

	case "json":
	/*
	    $local = array();
	    foreach($summary as $instance => $data) {
		$local[$instance] = $data["summary"];
	    }
	    echo json_encode(array("summary" => $local));
	    echo ",";
	*/
	    foreach($summary as $instance => $data) {
		printInstanceHeaders($format, $test, $instance);
		//echo json_encode(array("summary" => $data["summary"]));
		/* print this separately so we don't get an ending set of
		 * parentheses... we want to put the dataset after it
		 */
		echo "\"summary\":";
		echo json_encode($data["summary"]);
		printInstanceFooters($format, $test, $instance);
		echo ",";
	    }
	    break;

	case "csv":
	case "text":
	    echo "# SUMMARY:\n";

	    foreach($summary as $instance => $data) {
		if ( $instance != "default" )
		    echo "# instance: $instance\n";
		foreach($data["summary"] as $stat => $values) {
		    echo "# $stat";
		    foreach($values as $type => $value) {
			echo " $type:$value";
		    }
		    echo "\n";
		}
	    }
	    break;
    };
}
    

function printResponseStart($format, $info, $start, $end, $binsize) {
    $src = $info["src"];
    $dst = $info["dst"];
    $test = $info["testType"];
    $subtype = $info["testSubType"];

    switch ( $format ) {
	case "xml":
	    /* cant use proper xml printing here because we want to leave
	     * the tag open
	     */
	    echo "<?xml version=\"1.0\"?>";
	    echo "<response>";
	    break;
	
	case "json":
	    echo "{\"response\":{";
	    break;

	case "csv":
	case "text": 
	    echo "# src='$src' dst='$dst' start='$start' end='$end' " . 
		"test='" . getTestName($test) . "' subtype='$subtype' " . 
		"binsize='$binsize'\n";
	    break;
    };
}

/* 
 * do initialisation stuff that only needs to be done once - headers etc 
 *
 * REQUIRES: 
 * xml - actual src, dst, test, subtype, start, end, binsize
 * json - null
 * csv - column headings (time, src, dst, test, subtype, exta...)
 */
function printDataStart($format, $info, $start, $end, $binsize, $outputstats) {

    global $raw_data_funcs;

    $src = $info["src"];
    $dst = $info["dst"];
    $test = $info["testType"];
    $subtype = $info["testSubType"];
    
    switch ( $format ) {
	case "xml":
	    echo "<dataset src='$src' dst='$dst' " . 
		"start='$start' end='$end' test='" . getTestName($test) . 
		"' subtype='$subtype' binsize='$binsize'>";
	    break;

	case "json":
	    echo "\"dataset\":[";
	    break;

	case "csv":
	case "text": 
	    /* use custom names if we've got them */
	    $otherKeys = getStatName($test);
	    /* XXX can remove the raw data func bit? because all tests
	     * now should have a nice lookup/ordering info block?
	     */
	    if ( $otherKeys == array() ) {
		/* check the appropriate data function exists for the test */
		$func = $raw_data_funcs[getTestId($test)];
		if (!function_exists($func)) {
		    printError($format, 501, 
			    "No raw data format function available", 
			    "raw data format function doesn't exist for " .
			    "test $test (" . getTestName($test) . ")");
		}
		$otherKeys = explode(",", $func($subtype, NULL));
	    }
	    /* merge generic headings with test specific ones */
	    $keys = array_merge(array_keys($info), $otherKeys);

	    /* print the keys that are always present */
	    $first = true;
	    foreach ( array_keys($info) as $header ) {
		if ( !$first )
		    echo ",";
		echo $header;
		$first = false;
	    }

	    /* prefix each of the results with the type of measurement */
	    $keys = array_diff($keys, array_keys($info));
	    foreach($keys as $header) {
		if($header == "instance" || $header == "traceroute")
		    echo ",$header";
		else
		    foreach($outputstats as $stat) {
			echo ",";
			echo $stat . "_" . $header;
		    }
	    }
	    echo "\n";
	    break;
    };
}

/* 
 * do any final end things, printing those that require it 
 */
function printDataEnd($format) {
    switch ( $format ) {
	case "xml": echo "</dataset></response>"; break;
	case "json": echo "]}}"; break;
	case "csv":
	case "text": break;
    };
}
	
function printInstanceHeaders($format, $test, $instance) {
    $testId = getTestId($test);
    if ( $testId == NZRS_DATA || $testId == DNS2_DATA ) {
	switch($format) {
	    case "xml": echo "<instance name='$instance'>"; break;
	    case "json": echo "{\"$instance\":["; break;
	};
    }
}
function printInstanceFooters($format, $test) {
    $testId = getTestId($test);
    if ( $testId == NZRS_DATA || $testId == DNS2_DATA ) {
	switch($format) {
	    case "xml": echo "</instance>"; break;
	    case "json": echo "]}"; break;
	};
    }
}
	
function printData($format, $info, $test, &$data) {

    $first = true;
    foreach ( $data as $bin ) {

	if ( getTestId($test) == TRACE_DATA /*|| 
		getTestId($test) == SCAMPER_DATA*/ ) {
	    printTraceDataValue($format, $info, $bin, $first);

	} else {
	    printDataValue($format, $info, $bin, $first);
	}
	$first = false;
    }
}

function printTraceDataValue($format, $info, $data, $first) {

    switch ( $format ) {
	case "xml":
	    // not printed, just used as something to make subtrees under
	    // should be able to use LIBXML_NOXMLDECL but it's broken
	    $response = new SimpleXMLElement("<response/>");
	    $node = $response->addChild("data");
	    $node->addChild("time", $data["time"]);
	    $path = $node->addChild("path", "");
	    $path->addAttribute("count", sizeof($data["path"]));
	    foreach($data["path"] as $hopinfo) {
		$hop = $path->addChild("hop", "");
		$hop->addChild("ip", $hopinfo["ip"]);
		$hop->addChild("hostname", $hopinfo["hostname"]);
	    }
	    
	    echo $node->asXML();
	    break;

	case "json":
	    $node = array("time" => $data["time"]);
	    foreach($data as $key => $value)
		$node[$key] = $value;

	    if ( !$first )
		echo ",";
	    echo json_encode(array("data" => $node));
	    break;

	case "csv":
	case "text":
	    echo $data["time"];
	    //XXX hax
	    unset($data["time"]);
	    unset($info["timestamp"]);
	    foreach($info as $stat)
		echo ",$stat";
	    foreach($data["path"] as $hop) {
		$ip = $hop["ip"];
		$name = $hop["hostname"];
		$this_route .= "$name($ip) ";
	    }
	    echo ",";
	    echo $this_route;
	    echo "\n";
	    break;
    };
}

/*
 * Print an individual data value. Ideally this could all be done at the end
 * by printing a whole XML tree or something, but we may not have enough
 * memory to hold such large strings this could produce so we are only ever
 * printing a single data point at a time.
 */
function printDataValue($format, $info, &$data, $first) {
    switch ( $format ) {
	case "xml":
	    // not printed, just used as something to make subtrees under
	    // should be able to use LIBXML_NOXMLDECL but it's broken
	    $response = new SimpleXMLElement("<response/>");
	    $node = $response->addChild("data");
	    foreach($data as $key => $value) {
		if ( is_array($value) ) {
		    $child = $node->addChild($key, "");
		    foreach($value as $subkey => $subvalue)
			if ( $subkey == "missing" || $subkey == "count")
			    $child->addAttribute($subkey, $subvalue);
			else
			    $child->addChild($subkey, $subvalue);
		} else {
		    $node->addChild($key, $value);
		}
	    }
	    echo $node->asXML();
	    break;

	case "json":
	    $node = array("time" => $data["time"]);
	    foreach($data as $key => $value)
		$node[$key] = $value;

	    if ( !$first )
		echo ",";
	    echo json_encode(array("data" => $node));
	    break;

	case "csv":
	case "text":
	    echo $data["time"];
	    //XXX hax
	    unset($data["time"]);
	    unset($info["timestamp"]);
	    foreach($info as $stat)
		echo ",$stat";
	    foreach($data as $stat) {
		if(is_array($stat)) {
		    foreach($stat as $key=>$value) {
			echo ",$value";
		    }
		} else {
		    echo ",$stat";
		}
	    }
	    echo "\n";
	    break;
    };
}


/*
 * 
 */
function fetchData($format, $test, $subtype, $src, $dst, $start, $end, $stat,
	$binsize, $summary) {
    
    $secOfData = $end - $start;

    /* 
     * little bit more sanity checking before we go off and do potentionally 
     * long duration investigations
     */
    if ( $secOfData < 0 ) {
	printError($format, 400, "Negative duration", 
		"End time was earlier than start time - negative duration");
    }
    if ( $secOfData > (60*60*24*365) ) {
	printError($format, 400, "Duration too long", 
		"Maximum duration is currently capped at one year for all " . 
		"users. Let us know if this will be a problem for you.");
    }

    /* Open the Database */
    $dataset = ampOpenDb($test, $subtype, $src, $dst, $start, 0, "UTC");
    if ( !$dataset ) {
	printError($format, 400, "Failed to open database", 
		"Failed to open database, probably because the host/test " . 
		"combination doesn't exist, or you gave it a timeframe it " .
		"wasn't happy with. Check arguments are correct.");
    }
	
    /* add all the keys that this test uses */
    $keys = array("timestamp", "src", "dst", "testType", "testSubType");
    $info = array_combine($keys, array(0, $src, $dst, getTestName($test), 
		$subtype));
    
    if ( getTestId($test) == TRACE_DATA /*|| getTestId($test) == SCAMPER_DATA*/ )
	$results = getBinnedTraceValues($dataset,$start,$secOfData,$binsize);
    else
	$results = getBinnedValues($dataset, $test, $start, $secOfData,
		$stat, $binsize, $summary);

    return $results;
}

/*
 * Cause all the data available between $src and $dst for test type 
 * $test/$subtype to be processed and displayed
 */
function process($format, $test, $subtype, $src, $dst, $start, $end, $stat,
	$binsize, $summary) {

    $results = array();
    $cache_hit = false;
    $memcache = false;
    $memcache_key = "$src$dst$test$subtype$start$end" . implode($stat) . 
	"$binsize$summary";

    //XXX lets be smarter and fetch partially cached stuff

    /* if we have memcache then use it to try to save touching disk */
    if ( class_exists('Memcache') ) {
	$memcache = new Memcache;

	if ( $memcache->connect('localhost', 11211) ) {
	    $results = $memcache->get($memcache_key);
	    if ( is_array($results) && count($results) > 0 ) {
		$cache_hit = true;
	    }
	}
    }

    /* 
     * if we don't have memcache, or couldn't find what we were after then
     * we'll have to fetch it ourselves and save it for later.
     */
    if ( !$cache_hit ) {
	$results = fetchData($format, $test, $subtype, $src, $dst, $start, 
		$end, $stat, $binsize, $summary);
	/* if this succeeds or fails there isn't a lot we can do about it */
	if ( $memcache ) {
	    /* 
	     * would like to cache it forever, but the end timestamp is often
	     * in the future, which would mess up later queries
	     */
	    $memcache->set($memcache_key, $results, 0, 300);
	}
    }


    /* add all the keys that this test uses */
    $keys = array("timestamp", "src", "dst", "testType", "testSubType");
    $info = array_combine($keys, array(0, $src, $dst, getTestName($test), 
		$subtype));

    printResponseStart($format, $info, $start, $end, $binsize);

    if ( $summary ) {
	printSummary($format, $info, $start, $end, $binsize, $results);
    }

    printDataStart($format, $info, $start, $end, $binsize, $stat);
    
    $first = true;
    foreach ( $results as $instance => $result ) {
	$testId = getTestId($test);
	if ( ($format == "text" || $format == "csv") && 
		($testId == NZRS_DATA || $testId == DNS2_DATA) ) {
	    $info["instance"] = $instance;
	}

	//TODO nicer way to do this? i dont like $first variables...
	// and also this is json specific, can it be moved elsewhere
	if ( $format == "json" && !$first )
	    echo ",";
	printInstanceHeaders($format, $test, $instance);
	printData($format, $info, $test, $result["bins"]);
	printInstanceFooters($format, $test);
	$first = false;
    }

    printDataEnd($format);
}



/****************************************/
$testNames = array( 
	ICMP_DATA => "icmp",
	TRACE_DATA => "trace",
	DNS_DATA => "dns",
	NZRS_DATA => "nzrs",
	HTTP_DATA => "http",
	HTTP2_DATA => "http2",
	OWAMP_DATA => "owamp",
	TWAMP_DATA => "twamp",
	UDPSTREAM_DATA => "udpstream",
	TPUT_DATA => "tput",
	DNS2_DATA => "dns2",
	//SCAMPER_DATA => "scamper",
	);
$testIds = array(
	"icmp" => ICMP_DATA,
	"trace" => TRACE_DATA,
	"dns" => DNS_DATA,
	"nzrs" => NZRS_DATA,
	"http" => HTTP_DATA,
	"http2" => HTTP2_DATA,
	"owamp" => OWAMP_DATA,
	"twamp" => TWAMP_DATA,
	"udpstream" => UDPSTREAM_DATA,
	"tput" => TPUT_DATA,
	"dns2" => DNS2_DATA,
	//"scamper" => SCAMPER_DATA,
	);

/*
 * Return the test id for any test name or test id 
 */
function getTestId($test) {
    global $testIds, $testNames;
    
    if ( isset($testIds[$test]) ) {
	return $testIds[$test];
    }

    if ( is_numeric($test) ) {
	if( isset($testNames[(int)$test]) )
	    return $test;
	return -1;
    }

    return -1;
}

/*
 * Return the test name for any test name or test id 
 */
function getTestName($test) {
    global $testNames, $testIds;

    if ( is_numeric($test) ) {
	if ( isset($testNames[$test]) )
	    return $testNames[$test];
	return "UNKNOWN TEST";
    }

    if ( isset($testIds[$test]) ) {
	return $test;
    }

    return "UNKNOWN TEST";
}

/* this is needed for all tests that use data/data2, or where the names
 * of the values given by the raw data function don't match the order
 * they are in the structure
 */
$testResultNames = array(
	ICMP_DATA => array("data"=>"rtt_ms", "data2"=>"packetsize_bytes"),
	DNS_DATA => array("data" => "rtt_ms"),
	NZRS_DATA => array("instance" => "instance", "latency" => "rtt_ms"),
	OWAMP_DATA => array("data" => "sender2receiver", 
	    "data2" => "receiver2sender"),
	TWAMP_DATA => array("data"=>"rtt_ms", "data2"=>"packetsize_bytes"),
	TPUT_DATA => array("data" => "bytes", 
	    "data2" => "transfer_time_ms"),
	HTTP2_DATA => array("servers" => "servers", "objects" => "objects",
	    "duration" => "duration_ms", "size" => "size_bytes"),
	TRACE_DATA => array("traceroute" => "traceroute"),
	DNS2_DATA => array("instance" => "instance", "latency" => "rtt_ms", 
	    "response_code" => "response_code",
	    "query_len" => "query_len", "response_len" => "response_len",
	    "total_answer" => "total_answer", 
	    "total_authority" => "total_authority", 
	    "total_additional" => "total_additional",
	    "receive_flags" => "receive_flags", "dnssec" => "dnssec"),
	//SCAMPER_DATA => array("traceroute" => "traceroute"),
	);
/*
 * Get the useful display name of a statistic (or all stats for a test)
 */
function getStatName($test, $stat=null) {
    global $testResultNames;

    $test = getTestId($test);

    if ( $stat == null ) {
	if ( isset($testResultNames[$test]) ) {
	    return $testResultNames[$test];
	} else {
	    return array();
	}
    }

    if ( isset($testResultNames[$test][$stat]) ) {
	return $testResultNames[$test][$stat];
    }
    return $stat;
}



function updateBucket($db, $info, $data, $duration) {
    if ( $data > $info["data_bucket"] )
	return false;

    if ( $duration > $info["duration_bucket"] )
	return false;

    $query = "UPDATE api_users SET " . 
	"data_bucket=data_bucket-'$data', " . 
	"duration_bucket=duration_bucket-'$duration' WHERE " . 
	"api_key_id='" . $info["api_key_id"] . "' AND " . 
	"address='" . $info["address"] . "'";

    //echo $query . ";\n";
    if ( queryAndCheckDB($db, $query) == NULL )
	return false;

    return true;
}

function buildTooManyQueriesString($api_key_info, $api_user_info, $data, $dur) {

    return "Require $data data points (have " .
	$api_user_info["data_bucket"] . 
	"/" . $api_key_info["max_data_bucket"] . 
	") and $dur duration points (have " .
	$api_user_info["duration_bucket"] .
	"/" . $api_key_info["max_duration_bucket"] .
	")";
}



/******************************************/

/*
 * Try to get our results cached, at least a little bit.
 * For now use a timeout of 60 seconds, which is enough to deal with quick
 * refreshes (or multiple loads of the same data in a page...) but makes
 * sure the data doesn't get too stale.
 */
$now = time();
header("Expires: " . date("r", $now+60));
header("Last-Modified: " . date("r", $now));
header("Cache-Control: s-maxage=60, maxage=60, public, must-revalidate");
if ( isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && 
	$now - strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) < 60 ) {
    header("HTTP/1.1 304 Not Modified");
    exit;
}

$format = $_REQUEST["format"];

switch ( $format ) {
    case "json": header("Content-Type: text/plain"); break;
    case "xml": header("Content-Type: text/xml"); break;
    case "csv":
    case "text": header("Content-Type: text/plain"); break;
    default: 
	/* the rewrites are limiting format to xml/json/text/csv, so we
	 * can probably safely put some documentation here. Might need to
	 * be moved or reorganised at some point?
	 */
    //printError("csv", 400, "Unknown format", 
    //		     "Unknown format '$format'"); break;
    echo "<h1>Quick API documentation</h1>";
    echo "<p>URLs are built as follows:</p>";
    echo "<code>";
    echo "http://erg.wand.net.nz/amp/data/";
    echo "&lt;format&gt;";
    echo "/";
    echo "&lt;source&gt;";
    echo "/";
    echo "&lt;dest&gt;";
    echo "/";
    echo "&lt;test&gt;";
    echo "/";
    echo "&lt;subtype&gt;";
    echo "/";
    echo "&lt;start&gt;";
    echo "/";
    echo "&lt;end&gt;";
    echo ";&lt;optionstring&gt;";
    echo "</code>";

    echo "<p>";
    echo "Items may be dropped after the <code>format</code>, starting from ";
    echo "the right hand side. Once enough items are dropped that data can ";
    echo "no longer be returned, it will report possible values that could be ";
    echo "used for that item. If anything seems wrong or missing let me know, ";
    echo "as most of this is just a direct exporting of what is currently ";
    echo "done by AMP, and so will need extra work on making data ";
    echo "available in the exact way you want it. ";
    echo "In fact, I'm 100% certain there will be lots of inconsistencies ";
    echo "between different formats and different test types, please point ";
    echo "them out!";
    echo "</p>";

    echo "<p>UPDATE: api_key, binsize and summary statistic type are now ";
    echo "optional parameters until I find a better way to do them. ";
    echo "If an api_key is not supplied then a very restricted guest key ";
    echo "will be used";
    echo "</p>";

    echo "<h2>Arguments</h2>";

    echo "<ul>";
    echo "<li><b>format</b> xml, json, csv, text</li>";
    echo "<li><b>source</b> an amp name, eg ampz-waikato, ampz-auckland</li>";
    echo "<li><b>dest</b> an amp name, eg ampz-waikato, ns4.dns.net.nz</li>";
    echo "<li><b>test</b> a test name or id, eg icmp, trace, nzrs</li>";
    echo "<li><b>subtype</b> a subtype for the given test, eg rand for icmp</li>";
    echo "<li><b>start</b> unix timestamp at the start of data</li>";
    echo "<li><b>end</b> unix timestamp at the end of data</li>";
    echo "</ul>";
    
    echo "<h2>Extra options</h2>";

    echo "Consists of key=value pairs, with ampersands between pairs, ";
    echo "eg <code>api_key=123&stat=jitter</code>";

    echo "<ul>";
    echo "<li><b>api_key</b> any string will work for now</li>";
    echo "<li><b>stat</b> mean (default), min, max, jitter, loss, all</li>";
    echo "<li><b>binsize</b> binsize to aggregate data, in seconds (0 == all data)</li>";
    echo "</ul>";


    echo "<h2>Examples:</h2>";
    echo "<p>";
    echo "See all destinations from ampz-waikato, as XML: ";
    echo "<a href='http://erg.wand.net.nz/amp/testing/brendonj/data/xml/ampz-waikato'>http://erg.wand.net.nz/amp/testing/brendonj/data/xml/ampz-waikato</a>";
    echo "</p>";
    
    echo "<p>";
    echo "See all tests between ampz-waikato and ampz-auckland, as JSON: ";
    echo "<a href='http://erg.wand.net.nz/amp/testing/brendonj/data/json/ampz-waikato/ampz-auckland'>http://erg.wand.net.nz/amp/testing/brendonj/data/json/ampz-waikato/ampz-auckland</a>";
    echo "</p>";
    
    echo "<p>";
    echo "See the data for 84b icmp tests from ampz-waikato and ampz-auckland between 2pm and 3pm, Monday Jan 11 2010, as csv: ";
    echo "<a href='http://erg.wand.net.nz/amp/testing/brendonj/data/csv/ampz-waikato/ampz-auckland/icmp/0084/1263171600/1263175200'>http://erg.wand.net.nz/amp/testing/brendonj/data/csv/ampz-waikato/ampz-auckland/icmp/0084/1263171600/1263175200</a>";
    echo "</p>";
    
    echo "<p>";
    echo "See the maximum value for each 10 minute period for 84b icmp tests from ampz-waikato and ampz-auckland between 2pm and 3pm, Monday Jan 11 2010, as csv: ";
    echo "<a href='http://erg.wand.net.nz/amp/testing/brendonj/data/csv/ampz-waikato/ampz-auckland/icmp/0084/1263171600/1263175200/;stat=max&binsize=600'>http://erg.wand.net.nz/amp/testing/brendonj/data/csv/ampz-waikato/ampz-auckland/icmp/0084/1263171600/1263175200/;stat=max&binsize=600</a>";
    echo "</p>";

    echo "Extra <a href='http://erg.wand.net.nz/amp/testing/brendonj/testQuery.php'>test query page</a> with more examples";
};

//header("Content-Disposition: attachment; filename=\"foo\"");



/* validate the API key and make sure it exists */


/* work out what the request wants, and if it is allowed */
$parameters = array("src", "dst", "test", "subtype", "start", "end", 
	"binsize", "api_key", "summary", "stat");

foreach ( $parameters as $param ) {
    if ( isset($_REQUEST[$param]) && strlen($_REQUEST[$param]) > 0 ) {
	$$param = $_REQUEST[$param];
    }
}



/***********************************************/


if ( strlen($api_key) < 1 ) {

    printError($format, 401, "No API key specified", 
	"A valid API key is required to access this data. You may get " . 
	"limited access using the key \"guest\", but you should contact " .
	"us if you want to do more than that key allows");
}

/* XXX FIXME - for now, lets use the referer to make sure that the www key is
 * only used by the website and not by other people. Need a way that is less
 * easy to spoof.
 */
if ( false ) {
if ( $api_key == "www" /* && other checks TODO */ ) {
    printError($format, 401, "Invalid API key", 
	"The API key given is invalid. Please check that you have entered " .
	"it correctly. If you don't have a key you may get " . 
	"limited access using the key \"guest\", but you should contact " .
	"us if you want to do more than that key allows");
}
}

$db = dbConnect($GLOBALS["sitesDB"]);
if ( !$db ) {
    printError($format, 503, "Database error", 
	"Unable to connect to database to validate API key");
}

/* clean input before using it in the database */
$api_key = pg_escape_string($api_key);
$host = $_SERVER["REMOTE_ADDR"];



/* find the api_key that we match */
$query = "SELECT * FROM api_keys WHERE " . 
    "api_key='$api_key' AND '$host' << network";
//echo $query . ";\n";
$api_key_result = queryAndCheckDB($db, $query);

if ( $api_key_result["rows"] < 1 ) {
    dbCheckAndClose($db);
    printError($format, 401, "Invalid API key", 
	"The API key given is invalid. Please check that you have entered " .
	"it correctly. If you don't have a key you may get " . 
	"limited access using the key \"guest\", but you should contact " .
	"us if you want to do more than that key allows");
}
$api_key_info = $api_key_result["results"][0];



/* 
 * check some of the easy things we can do without having to perform another
 * query to the database.
 */
if ( isset($binsize) && $binsize < $api_key_info["min_binsize"]) {
    dbCheckAndClose($db);
    printError($format, 400, "Binsize too small", 
	"The minimum bin size allowed by this API key is " . 
	$api_key_info["min_binsize"] . " seconds (requested $binsize)");
} else if ( !isset($binsize) ) {
    /* if binsize isn't set then default to the smallest legal value */
    $binsize = $api_key_info["min_binsize"];
}


if ( isset($start) && isset($end) && 
	($end - $start > $api_key_info["max_duration"]) ) {
    dbCheckAndClose($db);
    printError($format, 400, "Duration too long", 
	"The maximum duration allowed by this API key is " . 
	$api_key_info["max_duration"] . " seconds (requested " . 
	($end-$start) . ")");
}





/* see if this key/host pair has been used before */
//$query = "SELECT * FROM api_users NATURAL JOIN api_keys WHERE api_key_id='" . 
if ( queryAndCheckDB($db, "BEGIN") == NULL ) {
    printError($format, 500, "Database error", "Failed to begin transaction");
}




$query = "SELECT * FROM api_users WHERE api_key_id='" . 
    $api_key_info["api_key_id"] . "' AND address='$host' FOR UPDATE";

//echo $query . ";\n";
$api_users_result = queryAndCheckDB($db, $query);
$api_user_info = $api_users_result["results"][0];


/*
 * Need to make sure that the current bucket values for this host using this
 * key are correct. This means creating them and setting them to the full
 * bucket size if this is the first time we have seen them, or updating their
 * buckets based on how long it has been since we last saw them.
 */
if ( $api_users_result["rows"] < 1 ) {
    /* key/host pair doesnt exist - create it */
    $query = "INSERT INTO api_users " . 
	"(api_key_id, address, data_bucket, duration_bucket, last_access) " . 
	"VALUES ( " . 
	"'" . $api_key_info["api_key_id"] . "'," .
	"'$host'," .
	"'" . $api_key_info["max_data_bucket"] . "'," .
	"'" . $api_key_info["max_duration_bucket"] . "'," .
	"'" . date("r") . "')";

    //echo $query . ";\n";
    if ( queryAndCheckDB($db, $query) == NULL ) {
	dbCheckAndClose($db);
	printError($format, 500, "Database error", 
		"Error creating key/host pair");
    }

    $api_user_info["api_key_id"] = $api_key_info["api_key_id"];
    $api_user_info["address"] = $host;
    $api_user_info["data_bucket"] = $api_key_info["max_data_bucket"];
    $api_user_info["duration_bucket"] = $api_key_info["max_duration_bucket"];

} else {
    /* already exists, update their bins if required */
    $now = time();
    $delta = $now - strtotime($api_user_info["last_access"]);
    //echo $delta . "\n";

    /* increase = time since last access X refill rate */
    
    /* we've locked the row for updating, noone can change our values! */
    $data = $api_user_info["data_bucket"] + 
	($delta * $api_key_info["data_refill_rate"]);
	
    if ( $data  > $api_key_info["max_data_bucket"] )
	$data = $api_key_info["max_data_bucket"];
    
    $duration = $api_user_info["duration_bucket"] + 
	($delta * $api_key_info["duration_refill_rate"]);
	
    if ( $duration  > $api_key_info["max_duration_bucket"] )
	$duration = $api_key_info["max_duration_bucket"];

    $query = "UPDATE api_users SET " . 
	"data_bucket='$data', duration_bucket='$duration', " . 
	"last_access='" . date("r", $now) . "' " .
	"WHERE api_key_id='" . $api_key_info["api_key_id"] . "' AND " .
	"address='$host'";
    //echo $query . ";\n";
    

    if ( queryAndCheckDB($db, $query) == NULL ) {
	dbCheckAndClose($db);
	printError($format, 500, "Database error", 
		"Error updating key/host pair");
    }
    $api_user_info["data_bucket"] = $data;
    $api_user_info["duration_bucket"] = $duration;
}

if ( queryAndCheckDB($db, "COMMIT") == NULL ) {
    dbCheckAndClose($db);
    printError($format, 500, "Database error", "Error committing transaction");
}



/***********************************************/

/*
 * If source isn't set then return all the possible sources
 */
if ( !isset($src) ) {
    /* Cheap: data 1, duration 1 */
    $data = 1;
    $duration = 1;
    if ( updateBucket($db, $api_user_info, $data, $duration) == false ) {
	dbCheckAndClose($db);
	printError($format, 509, "Too many queries", 
		buildTooManyQueriesString($api_key_info, $api_user_info, 
		    $data, $duration));
    }
    $sites = ampSiteList(""); 
    printSiteList($format, $sites->srcNames);
    exit;
} 

/*
 * If destination isn't set then give all the possible destinations from
 * the given source.
 */
if ( !isset($dst) ) {
    /* Cheap: data 1, duration 1 */
    $data = 1;
    $duration = 1;
    if ( updateBucket($db, $api_user_info, $data, $duration) == false ) {
	dbCheckAndClose($db);
	printError($format, 509, "Too many queries", 
		buildTooManyQueriesString($api_key_info, $api_user_info, 
		    $data, $duration));
    }
    $sites = ampSiteList($src);
    printSiteList($format, $sites->srcNames, $src);
    exit;
}


/*
 * If the test isn't set then give all the possible tests between the given
 * source and destination.
 */
if ( !isset($test) ) {
    /* Cheap: data 1, duration 1 */
    $data = 1;
    $duration = 1;
    if ( updateBucket($db, $api_user_info, $data, $duration) == false ) {
	dbCheckAndClose($db);
	printError($format, 509, "Too many queries", 
		buildTooManyQueriesString($api_key_info, $api_user_info, 
		    $data, $duration));
    }
    $tests = ampTestList($src, $dst);
    printTestList($format, $tests->types, $src, $dst);
    exit;
}



/*
 * If the test subtype isn't set then give all the possible subtypes for that
 * test between the given source and destination.
 */
if ( !isset($subtype) ) {
    /* Cheap: data 1, duration 1 */
    $data = 1;
    $duration = 1;
    if ( updateBucket($db, $api_user_info, $data, $duration) == false ) {
	dbCheckAndClose($db);
	printError($format, 509, "Too many queries", 
		buildTooManyQueriesString($api_key_info, $api_user_info, 
		    $data, $duration));
    }
    $subtypes = ampSubtypeList(getTestId($test), $src, $dst);
    printSubtypeList($format, $subtypes->subtypes, getTestName($test),
	    $src, $dst);
    exit;
}

if ( strlen($subtype) < 1 ) {
    $subtype = $test;
}

if ( !isset($start) )
    printError($format, 400, "No start time", "No start time specified");
if ( !isset($end) )
    printError($format, 400, "No start time", "No start time specified");
    
if ( $end - $start < 0 )
    printError($format, 400, "Negative duration", 
	    "End time was earlier than start time - negative duration");



if ( isset($stat) ) {
    switch($stat) {
	case "mean": $stat=array("mean"); break;
	case "max": $stat=array("max"); break;
	case "min": $stat=array("min"); break;
	case "jitter": $stat=array("jitter"); break;
	case "loss": $stat=array("loss"); break;
	case "all":  $stat=array("mean", "max", "min", "jitter", "loss");
	    break;

	default: printError($format, 400, "Invalid statistic", 
	    "Invalid statistic '$stat', " . 
	    "should be one of mean, max, min, jitter, loss, all");
    };
} else {
    $stat = array("mean");
}
/* add these stats which we will always report */
/* XXX ordering is important, perhaps it shouldnt be */
$defaultStats = array("missing", "count");
$stat = array_merge($defaultStats, $stat);


/* validate the API key and make sure it exists */


/* API Key checks: 
 * allowed access to test data 
 * allowed access to the time period (duration, start/end times?)
 * allowed access to the binsize 
 *  - perhaps access to a number of datapoints, so duration/binsize?
 */

$duration = (int)($end - $start);
if ( $binsize > 0 )
    $data = (int)($duration / $binsize);
else
    $data = $duration;

if ( updateBucket($db, $api_user_info, $data, $duration) == false ) {
    dbCheckAndClose($db);
    printError($format, 509, "Too many queries", 
	    buildTooManyQueriesString($api_key_info, $api_user_info, 
		$data, $duration));
}

process($format, getTestid($test), $subtype, $src, $dst, 
	$start, $end, $stat, isset($binsize)?$binsize:0, 
	(isset($summary) && $summary=="true")?true:false);


?>
