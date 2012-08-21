<?
/*
 * TODO: make more generic for all metric types?
 * TODO: return more information than just latency and loss? Better tooltips
 */
require_once("matrix_lib.php");
$now = time();
$expires = $now + 600;
header("Expires: $expires");
header("Cache-Control: public, max-age=$expires");
header("Last-Modified: $now");
header_remove("Pragma");

/* we get a few historical periods for latency */
$intervals = array(
	//'10mins' => SECONDS_10MINS,
	'1hour'	=> SECONDS_1HOUR,
	'1day'	=> SECONDS_1DAY,
	'1week'	=> SECONDS_1WEEK
	);
/* and cache the longer periods for longer */
$cachetimes = array(
	//'10mins' => SECONDS_1MIN,
	'1hour' => SECONDS_5MINS,
	'1day' => SECONDS_30MINS,
	'1week' => SECONDS_1HOUR
	);

if ( !isset($_REQUEST["src"]) || !isset($_REQUEST["dst"]) ) {
    echo "<div>src and dst both need to be set!</div>";
    exit;
}

//TODO check ampsource,ampdest,metric are all valid
$ampsource = $_REQUEST["src"];
$ampdest = $_REQUEST["dst"];
$response = array();

getSites($sites, "", "");
if ( !array_key_exists($ampsource, $sites) ) {
    echo "<div>Unknown source $ampsource</div>";
    exit; 
}

getSites($sites, $ampsource, "");
if ( !array_key_exists($ampdest, $sites) ) {
    echo "<div>Unknown destination $ampdest</div>";
    exit;
}

$memcache_connected = false;
if(class_exists('Memcache')) {
    $memcache = new Memcache;
    $memcache_connected = $memcache->connect('localhost', 11211);
}

foreach($intervals as $i => $seconds) {
    $cache_hit = false;
    $key = $ampsource . $ampdest . "latency" . $i;
    if ( $memcache_connected ) {
	if ( ($data = $memcache->get($key)) != false ) {
	    $cache_hit = true;
	}
    } 
    /* fetch and save it if it isn't already in cache */
    if ( $cache_hit == false ) {
	$data = getLatencyData($ampsource, $ampdest, $seconds);
	/* cache data according to length of period */
	if ( $memcache_connected )
	    $memcache->set($key, $data, 0, $cachetimes[$i]);
    }
    //echo "<br />$cache_hit<br />";
    /* pass the data back to be displayed */
    $response["latency"][$i] = roundRTT($data["latency"]);
    $response["latency-stddev"][$i] = roundRTT($data["latency-stddev"]);
    $response["loss"][$i] = $data["loss"];
}

if ( $memcache_connected ) {
    $memcache->close();
}

/* format data for display in a tooltip */
$content = "<table>" .
    "<thead>" .
    "<tr><th colspan='4'>" . $ampsource . " to " . $ampdest . "</th></tr>" .
    "<tr>" .
    "<th>&nbsp;</th>" .
    "<th>1 Hour<br />(average)</a></th>" .
    "<th>24 Hour<br />(average)</th>" .
    "<th>7 Day<br />(average)</th>" .
    "</tr>" .
    "</thead>" .
    "<tbody>" .
    "<tr>" .
    "<td class='head'>Latency&nbsp;(ms)</td>" .
    "<td>" . $response['latency']['1hour'] . "</td>" .
    "<td>" . $response['latency']['1day'] . "</td>" .
    "<td>" . $response['latency']['1week'] . "</td>" .
    "</tr>" .
    "<tr>" .
    "<td class='head'>Packet&nbsp;Loss&nbsp;(%)</td>" .
    "<td>" . $response['loss']['1hour'] . "</td>" .
    "<td>" . $response['loss']['1day'] . "</td>" .
    "<td>" . $response['loss']['1week'] . "</td>" .
    "</tr>" .
    "</tbody>" .
    "</table>";

echo $content;

?>
