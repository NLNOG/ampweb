<?php
/*
 * AMP Matrix Display Interface
 *
 * Author:	Brad Cowie <bmc26@waikato.ac.nz>
 * Author:      Brendon Jones <bcj3@cs.waikato.ac.nz>
 * Author:      Yu Wei (Alex) Wang <yww4@cs.waikato.ac.nz>
 * Version:     $Id: new_matrix.php 1533 2009-12-08 22:24:44Z bmc26 $
 *
 */

// store options in here, default options are set
$options = array("mesh" => "RING", "metric" => "latency", "protocol" => "ipv4", "dest" => "RING");
$metrics = array("latency", "loss", "hops", "mtu");
$protocols = array("ipv4", "ipv6"/*, "ethernet"*/);
$extras = array("ampz", "commodity", "karen", "wix", "ape", "auckland");


function getDisplayName($name) {
  /* strip extra name parts that we don't need to display */
  $display = str_replace(array('ring-', 'ampz-', 'www.'), '', $name);

  /* put a bit more information into the names of some sites */
  switch ( $display ) {
    case "ns2b": $display = "ns2b-digiweb"; break;
    case "ns2b:v6": $display = "ns2b-digiweb:v6"; break;
    case "ns3a": $display = "ns3a-avalon"; break;
    case "ns3a:v6": $display = "ns3a-avalon:v6"; break;
    case "ns3b": $display = "ns3b-iconz"; break;
    case "ns3b:v6": $display = "ns3b-iconz:v6"; break;
    case "ns4a": $display = "ns4a-orcon"; break;
    case "ns4a:v6": $display = "ns4a-orcon:v6"; break;
  };

  return $display;
}

/*
 * Display the tabbed interface at the top of the matrix that allows selection
 * between different protocols and metrics
 */
function printTabInterface($options) {
  global $protocols;

  echo "<ul id='tabs'>\n";
  foreach($protocols as $protocol) {
    printMainTab($protocol, $options);
  }
  echo "</ul>\n";

  printSubTabs($protocol, $options);
}


/*
 * Display the main tabs at the top of the matrix that allow selection between
 * the different protocols that are available.
 */
function printMainTab($protocol, $options) {

  echo "<li>";
  echo "<a href='matrix.php/$protocol/" . 
    $options["metric"] . "/".
    urlencode($options["mesh"])."/".
    urlencode($options["dest"])."'";
  if ( $options["protocol"] == $protocol ) {
    echo " class='here'";
  }
  echo ">";
  echo "<span>$protocol</span>";
  echo "</a>";
  echo "</li>\n";
}
 
/*
 * Display the second level of tabs at the top of the matrix that allow 
 * selection between the different metrics that are available.
 */
function printSubTabs($protocol, $options) {

  global $metrics;

  echo "\n";
  echo "<br class='clear' />\n";
  echo "<ul id='tabs'>\n";

  foreach ( $metrics as $metric ) {
    echo "<li>";
    echo "<a href='matrix.php/" . $options["protocol"] . "/$metric/" . 
      urlencode($options["mesh"])."/".urlencode($options["dest"])."'";
    if ( $options["metric"] == $metric )
      echo " class='here'";
    echo ">$metric</a>";
    echo "</li>\n";
  }

  echo "</ul>\n";
}


/*
 * Currently unused testing function to display a series of checkboxes near
 * the top of the matrix that can be used to futher filter the sites shown,
 * based on properties that aren't neccessarily displayed.
 */ 
function printExtraOptionsString($options) {

  /* TODO: only show those with data available */
  global $extras;
  $string = "";

  $string .= "<ul id='subtab'>";

  foreach ( $extras as $extra ) {
    $string .= "<li><input type='checkbox' name='check_$extra' id='check_$extra'";
    //if ( $options["extras"] == $extra )
      $string .= " checked='checked'";
    /* XXX testing */
    $string .= " onclick='customFilter(\\\"" . $extra . "\\\", this.checked)'";
    $string .= " /><label for='check_$extra'>$extra</label></li>";
  }

  $string .= "</ul>";

  return $string;
}





/* Decode argument string */
if($_SERVER['argc'] > 0) {
    $prefs = urldecode($_SERVER['QUERY_STRING']);

    // the user has some options set, lets save those to a cookie
    setcookie('matrix_prefs', $prefs, time()+(3600*24*365), "/");
}
else if ( isset($_SERVER['PATH_INFO']) ) {
    // format is: matrix.php/{protocol}/{metric}/{mesh}/{destination group}
    $prefs = explode('/', urldecode($_SERVER['PATH_INFO']));
    if ( sizeof($prefs) == 4 )
      $prefs['4'] = "";
    $prefs = "protocol={$prefs[1]}&metric={$prefs['2']}&mesh={$prefs['3']}&dest={$prefs['4']}";

    // the user has some options set, lets save those to a cookie
    setcookie('matrix_prefs', $prefs, time()+(3600*24*365), "/");
} else {
    // lets restore the user's settings
    $prefs = $_COOKIE['matrix_prefs'];
}



// parse options
if(!empty($prefs)) $prefs = explode('&', $prefs);
if(is_array($prefs) && count($prefs) > 0) {
    foreach($prefs as $pref) {
        list($key, $val) = explode('=', $pref, 2);
        $options[$key] = $val;
    }
}

/* check that the values given are slightly sensible */
if ( isset($options['protocol']) && !in_array($options['protocol'], $protocols))
  $options['protocol'] = "ipv4";
if ( isset($options['metric']) && !in_array($options['metric'], $metrics) )
  $options['metric'] = "latency";
if ( !isset($options['mesh']) || strlen($options['mesh']) < 1 )
  $options['mesh'] = "RING";
if ( !isset($options['dest']) || strlen($options['dest']) < 1 )
  $options['dest'] = "RING";


/* redirect to a cleaner url if required */
if((!isset($_SERVER['PATH_INFO']) && count($options) > 0) || 
    (isset($_SERVER['PATH_INFO']) && $_SERVER['argc'] > 0)) {

    $scheme = (isset($_SERVER['HTTPS']) && 
        $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';

    $url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] .
      '/' . urlencode($options['protocol']) . 
      '/' . urlencode($options['metric']) . 
      '/' . urlencode($options['mesh']);

    if($options['dest']) $url .= '/' . urlencode($options['dest']);

    header("Location: $url");
}



// set cache headers
session_cache_limiter('public');
session_cache_expire(5);

require('matrix_lib.php');

// get data
require('api.php');

//---------------------------------------------------------------------------
// Begin HTML Output
templateTop();
?>
<meta http-equiv="refresh" content="300" />
<link rel="stylesheet" href="matrix.css" type="text/css" />
<link rel="stylesheet" href="js/jquery.qtip.min.css" type="text/css" />
<!--<script type="text/javascript" src="js/jquery.qtip-1.0.0-rc3.min.js"></script>-->
<script type="text/javascript" src="js/dataTables/media/js/jquery.dataTables.min.js"></script>
<script type="text/javascript">
var celldata = new Array();
</script>

<?php
if ( $dbwarning ) {
  echo "Unable to access database, you may experience a loss of " .
    "functionality<Br />";
}



if ( isset($options['dest']) && strlen($options['dest']) > 0 ) {
  echo "<h2>Matrix - Viewing ";
  echo htmlspecialchars($options['protocol']);
  echo " "; 
  echo htmlspecialchars($options['metric']);
  echo " (10 min average) from ";
  echo htmlspecialchars($options['mesh']);
  echo " to ";
  echo htmlspecialchars($options['dest']);
  echo "</h2>";
} else {
  echo "<h2>Matrix - Viewing "; 
  echo htmlspecialchars($options['protocol']);
  echo " ";
  echo htmlspecialchars($options['metric']); 
  echo " (10 min average) from ";
  echo htmlspecialchars($options['mesh']);
  echo " to ";
  echo htmlspecialchars($options['mesh']);
  echo "</h2>";
}

/* work out which meshes are destination only meshes */
$destonly = array();

foreach($meshes as $mesh) {
    $destonly[$mesh] = true;

    $sites = array();
    getSites($sites, "", $mesh);

    foreach(array_keys($sites) as $site) {
      if(lastSeenAll($site) >= 1) {
        $destonly[$mesh] = false;
        break;
      }
    }
}
/* we don't need "Any", "RING" and "AMP Monitors" all in the source list */
$destonly["AMP Monitors"] = true;
/* temporarily force the RING mesh to be both source and dest - not having any
 * data at the moment is getting it set to dest only
 */
$destonly["RING"] = false;

/* select source mesh */
echo "<div style='text-align:left'>";
echo "<form action='$page_name' method='get'>\n";
echo "<select id='mesh' name='mesh'>\n";
if(is_array($meshes)) {
  foreach($meshes as $mesh) {
    if(!$destonly[$mesh]) {
      if($options['mesh'] == $mesh) {
        echo '<option value="'.htmlspecialchars($mesh).
          '" selected="selected">'.htmlspecialchars($mesh).'</option>';
      } else {
        echo '<option value="'.htmlspecialchars($mesh).'">'.
          htmlspecialchars($mesh).'</option>';
      }
    }
  }
}
echo "</select>";

echo "<label for='dest'> to </label>";

/* select destination mesh */
echo "<select id='dest' name='dest'>";
echo "<option value=''>---</option>";
if(is_array($meshes)) {
  foreach($meshes as $mesh) {
    if(isset($options['dest']) && $options['dest'] == $mesh) {
      echo '<option selected="selected">'.htmlspecialchars($mesh).'</option>';
    } else {
      echo '<option>'.htmlspecialchars($mesh).'</option>';
    }
  }
}
echo "</select>";
echo "<input type='hidden' name='protocol' value='".$options['protocol']."' />";
echo "<input type='hidden' name='metric' value='".$options['metric']."' />";
echo "<input type='submit' value='Update' />";
echo "</form>";
echo "</div>";



/*****************************************************************/
printTabInterface($options);

/* always draw the basic table outline at least */
echo "<table cellpadding='0' cellspacing='4' border='0' id='matrix'>\n";

/* but only display table contents if there are sites */
if ( count($siteInfo) > 0 && count($siteInfoDest) > 0 ) {

  /* table headers */
  echo "<thead>";
  echo "<tr>";
  echo "<th class='dest-title' colspan='" . (count($siteInfoDest) + 1) . "'>";
  echo "Destination:</th></tr>";

  echo "<tr>";
  echo "<th class='src-title'>";
  echo "<span class='sorticon'>&nbsp;</span>Source:</th>";

  // Print out all of the titles
  foreach(array_keys($siteInfoDest) as $destSite) {
    // remove the "ampz-" and "www." prefix from site names
    $destName = getDisplayName($destSite);
    echo "<th class='src-title'>";
    echo "<span class='sorticon'>&nbsp;</span>";
    echo "<a href='graph.php?src=".urlencode($options['mesh']).
      "&amp;dst=".urlencode($destSite).
      "' title='".htmlspecialchars($magicArray[$destSite]['longname']).
      "'>".htmlspecialchars(stripSiteSuffixes($destName))."</a>";
    echo "</th>";
  }
  echo "</tr>";

  echo "</thead>\n";


  /* table body */
  echo "<tbody>";
  // caches all the meshes which each site is a member of
  $site_meshes = array();

  foreach(array_keys(array_merge($siteInfo, $siteInfoDest)) as $site) {
    // populate the $site_meshes cache
    $site_meshes[$site] = getMeshesBySite($site, true);
  }

  
  foreach(array_keys($siteInfo) as $sourceSite) {
    $site = $magicArray[$sourceSite];

    // remove the "ampz-" prefix from site names
    $sourceName = getDisplayName($sourceSite);
    echo '<tr>';
    $destGroup  = (isset($options['dest']) && !empty($options['dest'])) ? 
      $options['dest'] : $options['mesh'];
    echo "<td class='head'>";
    echo "<a href='graph.php?src=" . urlencode($sourceSite) .
      "&amp;dst=".urlencode($destGroup) .
      "' title='".htmlspecialchars($site['longname']) . "'>" .
      htmlspecialchars(stripSiteSuffixes($sourceName)) . "</a></td>";

    foreach(array_keys($siteInfoDest) as $destSite) {
      $result = isset($site['results'][$destSite]) ? $site['results'][$destSite] : 0;

      // remove the "ampz-" prefix from site names
      $destName = getDisplayName($destSite);

      // initialise tmp variable to store rounded results
      $rounded = array();

      // unique id for this source->destination
      //$id = str_replace(array('.', ':'), '-', "{$sourceName}_{$destName}");
      $id = str_replace(array(':'), '-', "{$sourceSite}_{$destSite}");

      // time intervals we are expecting to get back from the API
      $intervals = array(
          '10mins'    => SECONDS_10MINS,
          /*
          '1hour'	=> SECONDS_1HOUR,
          '1day'	=> SECONDS_1DAY,
          '1week'	=> SECONDS_1WEEK
          */
          );

      //var_dump($result);

      foreach($intervals as $interval => $secs) {
        if($result['latency'][$interval] !== NULL && 
            $result['latency'][$interval] !== '') {
          // round latency
          $precision = ($result['latency'][$interval] > 1) ? 0 : 1;
          $rounded['latency'][$interval] = 
            round($result['latency'][$interval], $precision);
        } else {
          $rounded['latency'][$interval] = "";
        }

        if($result['loss'][$interval] !== '') {
          $rounded['loss'][$interval] = $result['loss'][$interval];
        }
      }

      /* everything inside of <a></a> is a metric to be displayed/sorted, 
       * everything in span.hide is extra information such as stddev
       */
      if(stripSiteSuffixes($destName) == stripSiteSuffixes($sourceName) || 
          count(array_intersect($site_meshes[$sourceSite], 
              $site_meshes[$destSite])) == 0) {
        /* if the destination is the source, or the sites don't share a 
         * mesh in common they won't be able to talk, so mark accordingly
         */
        echo "<td id='".htmlspecialchars($id)."' class='status-broken'>";
        echo "</td>";
      } else {
    
        if ( isset($rounded[$options['metric']]) &&
            isset($rounded[$options['metric']]['10mins']) &&
            is_array($result[$options['metric']]) ) {
          $metricvalue = $rounded[$options['metric']]['10mins'];
        } else {
          if ( isset($result[$options['metric']]) )
            $metricvalue = $result[$options['metric']];
          else
            $metricvalue = "";
        }


        echo "<td id='" . htmlspecialchars($id) .
          "' class='" . htmlspecialchars($options["metric"])."'>";
        echo "<a href='graph.php?src=" . urlencode($sourceSite) . 
          "&amp;dst=" . urlencode($destSite) . "'>" .
          htmlspecialchars($metricvalue) . "</a>";

	echo '<span class="hide">';

        if ( isset($result[$options['metric']]) &&
            isset($result[$options['metric']]['10mins']) &&
            $result[$options['metric']]['10mins'] ) {
          echo '<span class="10mins">' .
            htmlspecialchars($result[$options['metric']]['10mins']).'</span>';
        }

        if ( isset($result[$options['metric']]) &&
            isset($result[$options['metric']]['1day']) &&
            $result[$options['metric']]['1day'] ) {
          echo '<span class="1day">' .
            htmlspecialchars($result[$options['metric']]['1day']).'</span>';
        }

        if($options['metric'] == 'latency') {
          echo '<span class="stddev_1day">' .
            htmlspecialchars($result['latency-stddev']['1day']).'</span>';
        }

        echo '</span></td>';

      }
    }
		    
    echo '</tr>\n';
  }
  echo "</tbody>";
}
		?>
</table>



<div id="bottom">

    <div id="scale">
        <h3>Scale</h3>
        <table>
            <tbody>
            <?php
            switch($options['metric']) {
              case 'latency':
                $steps = array(1, 20, 40, 60, 80, 150, 250);
                $plus = $steps[count($steps) - 1];
                break;
              case 'loss': 
                $steps = array(0, 20, 40, 60, 80, 100);
                $plus = -1;
                break;
              case 'hops':
                $steps = array(1, 5, 10, 15, 20, 25, 30);
                $plus = $steps[count($steps) - 1];
                break;
              case 'mtu':
                $steps = array(0, 576, 1280, 1480, 1492, 1500, 9000);
                $plus = -1;
                break;
              default:
                $steps = array();
                $plus = -1;
                break;
            };

            foreach ( $steps as $step ) {
              echo '<tr>';
              echo '<td class="'.htmlspecialchars($options['metric']).'">';
              echo '<a>'.$step.'</a>';
              if ( $plus > 0 && $step == $plus )
                echo "+";
              echo '</td>';
              echo '</tr>';
            }
            ?>
            </tbody>
        </table>
    </div>


    <div id="legend">
        <h3>Legend</h3>
        <table>
            <tbody>
                <? 
                  switch ( $options['metric'] ) {
                    case 'latency':
                      echo "<tr>";
                      echo "<td class='example down'>";
                      echo "<span class='status'>&darr;</span></td>";
                      echo "<td>Average latency in the last 10 minutes is " . 
                        "more than 1<br />standard deviation below the " . 
                        "average of the last 24 hours</td>";
                      echo "</tr>";
                      
                      echo "<tr>";
                      echo "<td class='example up-moderate'>";
                      echo "<span class='status'>&uarr;</span></td>";
                      echo "<td>Average latency in the last 10 minutes is " . 
                        "more than 1<br />standard deviation above the " . 
                        "average of the last 24 hours</td>";
                      echo "</tr>";
                      
                      echo "<tr>";
                      echo "<td class='example up-bad'>";
                      echo "<span class='status'>&uarr;</span></td>";
                      echo "<td>Average latency in the last 10 minutes is " . 
                        "more than 2<br />standard deviations above the " . 
                        "average of the last 24 hours</td>";
                      echo "</tr>";
                    break;

                    case 'loss': break;

                    case 'hops':
                      echo "<tr>";
                      echo "<td class='example path-timeout'>";
                      echo "<span class='status'>&Dagger;</span></td>";
                      echo "<td>Traceroute ended in timeouts for 5 " . 
                        "consecutive TTL values<br /> without " . 
                        "reaching the destination";
                      echo "</tr>";
                      break;
                    
                    case 'mtu':
                      echo "<tr>";
                      echo "<td class='example path-timeout'>";
                      echo "<span class='status'>&Dagger;</span></td>";
                      echo "<td>True PMTUD failed but the MTU was inferred " . 
                        "<br />by sending packets of varying sizes";
                      echo "</tr>";
                      break;

                  };
                  ?>
                <tr>
                    <td class="example status-broken">&nbsp;&nbsp;</td>
                    <td>Not tested</td>
                </tr>
                <tr>
                    <td class="example status-missing">&nbsp;&nbsp;</td>
                    <td>No data available</td>
                </tr>
            </tbody>
        </table>
    </div>

</div>

<br class="clear" />



<script type="text/javascript" src="js/jquery.qtip.min.js"></script>
<script type="text/javascript">
//<![CDATA[

// XXX restore this once we want to filter on extra options
var extraFilter = new Array();
<?
/*
  foreach($extras as $extra)
    echo "extraFilter[\"$extra\"] = true;\n";
*/
?>

/* XXX used with the extraFilter, currently disabled */
function customFilter(desc, value) {

  /* 
   * oTable.fnGetData() - only gets data rows, not table header rows
   */

  /* XXX this really needs to be rewritten with some understanding of
   * how datatables/jquery/js works - there must be a better way to get 
   * at the contents of the cell (textContent didn't work...). Ideally
   * we should be able to get at the table headers, but the only way I've
   * seen to do that will only fetch the _visible_ elements!
   */
  extraFilter[desc] = value;

  var data = oTable.fnGetData(0);
  var columns = data.length;
  for(i=1; i<columns; i++) {
    var contents = data[i];
    var start;
    var tmp;
    var end;
    var dst;
    /* use the destination if it is set, otherwise the source in the 
     * first column will have the site name 
     */
    if ( contents.length > 0 ) {
      start = contents.lastIndexOf("dst")+4;
      tmp = contents.slice(start);
      end = tmp.indexOf("\"");
      dst = tmp.slice(0, end);
    } else {
      contents = data[0];
      start = contents.lastIndexOf("src")+4;
      tmp = contents.slice(start);
      end = tmp.indexOf("&");
      dst = tmp.slice(0, end);
    }

    /* if a checkbox is newly enabled, turn on any that match */
    if ( value && dst.search(desc) >= 0 ) {
      oTable.fnSetColumnVis(i, true);
      continue;
    }

    /* if a checkbox is newly disabled, make sure no other filters
     * apply to this column before hiding it
     */
    var stillEnabled = false;
    for ( var filter in extraFilter ) {
      if ( extraFilter[filter] && dst.search(filter) >= 0 ) {
        stillEnabled = true;
        break;
      }
    }
    /* nothing enabled that matches, turn off visibility */
    if ( !stillEnabled )
      oTable.fnSetColumnVis(i, false);

  }


  /* XXX which behaviour is wanted? probably just filtering columns? */
  /* filter table rows, this is easy */
    /*
    if ( value ) {
      oTable.fnFilter(desc);
    } else {
      oTable.fnFilter("", null, false);
    }*/

  }




  var oTable;

  $(document).ready(function() {
      /**
       * Implement a HTML numeric comparator that allows our matrix columns to
       * be sorted numerically as opposed to alphabetically
       */
      jQuery.fn.dataTableExt.oSort['html-metric-asc']  = function(a,b) {
        var a = jQuery.trim($($(a).siblings('a').get(0)).text());
        var b = jQuery.trim($($(b).siblings('a').get(0)).text());

        // consider empty values as the worst
        if(a == '' && b == '') return 0;
        if(a == '') return 1;
        if(b == '') return -1;

        var x = a == "-" ? 0 : a;
        var y = b == "-" ? 0 : b;
        return x - y;
      };

      jQuery.fn.dataTableExt.oSort['html-metric-desc'] = function(a,b) {
        var a = jQuery.trim($($(a).siblings('a').get(0)).text());
        var b = jQuery.trim($($(b).siblings('a').get(0)).text());

        // consider empty values as the worst
        if(a == '' && b == '') return 0;
        if(a == '') return -1;
        if(b == '') return 1;

        var x = a == "-" ? 0 : a;
        var y = b == "-" ? 0 : b;
        return y - x;
      };

      /**
       * Filter function for the source names
       */
      $.fn.dataTableExt.ofnSearch['html'] = function(sData) {
        var title = $(sData).attr('title');
        sData = sData.replace(/\n/g," ").replace( /<.*?>/g, "" );

        // return the site name and the title which contains a description
        // so both can be filtered
        return sData+" "+title;
      };

      // Take a number from 0..255 (inclusive) and convert to a gradiant.
      function getSimpleColour(value) {
        // Colour things from a gradient.
        var value = Math.floor(value);

        if (value>255)
          value=255;

        // if in first half, colour = (2*value, 100%, 0)
        if(value < 128) {
          value = value*2;
          value = value.toString(16); // Convert to hex.
          if(value.length == 1) value = "0"+value; // pad hex

          return '#'+value+'ff00';
        }

        // if in second half, colour = (100%, 100%-2*value, 0)
        value = 255-(2*(value-128));
        value = value.toString(16);
        if(value.length == 1) value = "0"+value; // pad hex

        return '#ff'+value+'00';
      }

      function getColourForLatency(latency)
      {
        /*
        // Use Black for extremely large (European from NZ) values
        if (latency > 250)
          return "#000000";
        // Use Navy Blue for values that are large (US from NZ)
        if (latency > 150)
          return "#0000A0";
         */
        // Fall back to the old system (gradient)
        return getSimpleColour((latency/80)*255)
      }
      
      /* try to colour cells based on how different to normal the latency is */
      function getStyleForLatency2(latency, mean, stddev) {
	      var diff;
	      if ( latency <= mean || latency < 1 || stddev == 0)
		      return { border: '3px #00FF00 solid' };

	      diff = (latency - mean) / stddev;
	      if ( diff < 4 ) {
		      return { border: '3px '+getColourForLatency(diff*10)+' solid' };
	      }
	      //alert(latency + " " + mean + " " + stddev + " diff:" + diff);
	      if ( diff < 8 ) {
		      //color = (diff-1)*100; // 0..100
		      color = (diff-1)*33; // 0..100
		      color = color * 128.0/100; // 0..128
		      color = 255-Math.round(color);
		      color = color.toString(16);
		      if(color.length == 1) color = "0"+color; // pad hex
		      return {
border: '3px #FF0000 solid',
		background: '#FF'+color+color
		      };

	      }
	      return {
border: '3px #FF0000 solid',
		background: '#F00000',
	      };
      }

      function getStyleForLatency(latency)
      {
        if (latency < 150) {
          return { border: '3px '+getColourForLatency(latency)+' solid' };
        }
        if (latency < 250) {
          color = latency-150; // 0..100
          color = color * 128.0/100; // 0..128
          color = 255-Math.round(color);
          color = color.toString(16);
          if(color.length == 1) color = "0"+color; // pad hex
          return {
            border: '3px #FF0000 solid',
            background: '#FF'+color+color
          };
        } else {
          /* >= 250ms */
          return {
            border: '3px #FF0000 solid',
            background: '#F00000',
          };
        }
      }

      function getColourForMtu(mtu)
      {
        var mtu1500colour = 0xaa;
        /* -1 means no mtu data - is something being firewalled? */
        if ( mtu <= 0 )
          return "#ff0000";

        /* 1500 is "ok" so it's green, just not as green as it could be */
        if ( mtu == 1500 )
          return "#" + mtu1500colour.toString(16) + "ff00"

        if ( mtu >= 9000 )
          return "#00ff00";

        /* 
         * Anything less than 1500 is going to be more yellow/red, lets start
         * it on the downward part of the colour range to make it look worse,
         * and have it quite a steep curve so it looks very bad very quickly.
         * 
         */
        if ( mtu < 1500 ) {
          /* drag it into the range 0-255 on a curve, needs adjusting? */
          //value = (255.0 / (1500.0*1500.0)) * (mtu*mtu);
          value = (255.0 / Math.pow(1500, 7)) * Math.pow(mtu, 7);
          value = parseInt(value);
          value = value.toString(16);
          if(value.length == 1) value = "0"+value; // pad hex
          return "#ff"+value+"00";
        }

        /* 
         * Create a buffer between 1500 and 1501 so that the difference is
         * slightly visible - anything above 1500 should stand out. Also 
         * have a gap between 8999 and 9000, because 9k should stand out as 
         * being far and away the best.
         * Currently have a gap of 32 between 1500 (0xaa) and 1501 (0x8a) and
         * a gap of 64 between 8999 (0x40) and 9000 (0x00). This gives 74
         * usable colour values for MTUs between 1501 and 8999.
         */
        value = ( (8999-mtu) / 8999.0 * (0x8a-0x40)) + 0x40;
        value = parseInt(value);
        value = value.toString(16);
        if(value.length == 1) value = "0"+value; // pad hex
        return "#"+value+"ff00";
      }

      /* 
       * Round the RTT value to something nice and short that we can print 
       * easily in a cell of the matrix or in the pop-up tooltip. Anything
       * below one gets a single value after the decimal point, everything
       * else gets the integer value printed.
       */
      function roundRTT(rtt) {
        if ( rtt == '' ) 
          return rtt; 
        if ( rtt < 1 )
          return rtt.toFixed(1);
        return rtt.toFixed(0);
      }

      var elems = $('#matrix tbody tr td[class!="status-broken"][class!="head"]').each(function(i) {
        $.attr(this, 'tooltip', $.attr(this, 'id'));
        var parts = $.attr(this, 'id').split("_");
        $.attr(this, 'src', parts[0]);
        $.attr(this, 'dst', parts[1]);
      });
      
      $('<div />').qtip({
        content: {
          text: ' ',
          ajax: {
            url: "http://amp.ring.nlnog.net/tooltipdata.php",
            /*data: { src: $(this).attr("src"), dst: $(this).attr("dst") },*/
            type: "GET",
          },
        },
        show: {
          target: elems
        },
        hide: {
          target: elems
        },
        position: {
          target: 'event',
          effect: false,
          adjust: {
            method: "flip flip"
          },
          at: "bottom right",
          my: "top left",
          viewport: $(window)
        },
        style: {
          tip: {
            /*corner: 'topLeft',*/
            corner: true,
            width: 8,
            height: 10
          },
          classes: 'ui-tooltip-dark ui-tooltip-shadow',
          /*width: '100%'*/
          width: 800
        },
        events: {
          show: function(event, api) {
            var target = $(event.originalEvent.target);

            if ( target.length ) {
              api.set('content.ajax.data', { src: target.attr('src'), dst: target.attr('dst') });
            }
          }
        }
      });

      /* qtip v2 */
      $('#matrix thead th a[title], #matrix tbody td.head a[title]').qtip({
        content: {
          text: false // Use each elements title attribute
        },
        show: {
          delay: 40
        },
        style: {
          tip: {
            corner: 'top left',
            corner: true,
            width: 8,
            height: 10
          },
          classes: 'ui-tooltip-dark',
          width: '100%'
        },
        position: {
          adjust: {
            method: "flip flip"
          },
          at: "bottom right",
          my: "top left",
          viewport: $(window)
        }
      });

      $('#matrix tbody td a, #scale tbody td a').each(function (){
          if(!$(this).parent().hasClass('head') && 
            !$(this).parent().hasClass('broken')) {

	  var value    = ($(this).text() !== '') ? Number($(this).text()) : '';
          var parent   = $($(this).parent());
          var hide     = $(parent.children('span.hide').get(0));
          var children = hide.children('span');
          var data     = {};

          children.each(function() {
            data[$(this).attr('class')] = Number($(this).text());
            });

          /*
           * avg(10mins) < avg(1day) + 1*stddev(1day) => green
           * avg(10mins) < avg(1day) + 2*stddev(1day) => orange
           * else red
           */
          if(parent.hasClass('latency') && data['10mins'] > 0 && 
            data['1day'] > 0 && data['stddev_1day'] > 0) {

            var downthresh = data['1day'] - (0.7*data['stddev_1day']);
            var upthresh1  = data['1day'] + data['stddev_1day'];
            var upthresh2  = data['1day'] + (2.5*data['stddev_1day']);

            if(data['10mins'] < downthresh) {
              parent.addClass('down');
              parent.prepend('<span class="status">&darr;</span>');
            }
            else if(data['10mins'] < upthresh1) {
              // do nothing
            }
            else if(data['10mins'] < upthresh2) {
              parent.addClass('up-moderate');
              parent.prepend('<span class="status">&uarr;</span>');
            } else {
              parent.addClass('up-bad');
              parent.prepend('<span class="status">&uarr;</span>');
            }
          }

          if(value === '') {
            parent.addClass('status-missing');

            if(!parent.hasClass('status-broken')) {
              $(this).html('&nbsp;');
            }
          } else {
            // clamp value into range 0-255

            value = Math.ceil(value);

            if(parent.hasClass('latency')) {
	      parent.css(getStyleForLatency2(value, data['1day'], data['stddev_1day']));
            }
            else if(parent.hasClass('loss')) {
              if(value != 0) value = (Math.log(value)/Math.log(100)) * 255;
              parent.css({ border: '3px '+getSimpleColour(value)+' solid' });
            }
            else if(parent.hasClass('hops')) {
              // negative value indicates path ends in timeouts
              if(value <= 0) {
                parent.addClass('path-timeout');
                parent.prepend('<span class="status">&Dagger;</span>');
                if(value < 0) {
                  this.innerHTML = value * -1;
                  value = (Math.log(value * -1)/Math.log(30)) * 255;
                }
              } else if(value > 0) {
                value = (Math.log(value)/Math.log(30)) * 255;
              }

              parent.css({ border: '3px '+getSimpleColour(value)+' solid' });

            }
            else if(parent.hasClass('mtu')) {
              // negative value indicates path MTU was inferred
              if(value < 0) {
                parent.addClass('path-timeout');
                parent.prepend('<span class="status">&Dagger;</span>');
                value = value * -1;
                this.innerHTML = value;
              } else if(value == 0 ) {
                this.innerHTML = "?";
              }
              parent.css({ border: '3px '+getColourForMtu(value)+' solid' });
            }

          }
          }
      });

      var dataTableOpts = {
        "bPaginate": false,
        "bInfo": false,
        "bFilter": false,
        "bAutoWidth": false,
        //"bStateSave": true,
        "bStateSave": false,
        "sDom": 'frtip',
        "bSort": false,
        "aoColumns": [ { "sType": "html", "bSearchable": false, "bSortable": false } ]
      };

      for(i=1; i<=<?php echo count($siteInfoDest); ?>; i++)
      {
        dataTableOpts.aoColumns.push({
            "sType": "html-metric",
            "bSearchable": false,
            "bSortable": false
        });
      }
      oTable = $('#matrix').dataTable(dataTableOpts);

  });
//]]>
</script>

<?php

endPage();
// Emacs control
// Local Variables:
// eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
