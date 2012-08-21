<?php
/*
 * AMP Data Display Interface
 *
 * User Preference Handling
 *
 * Author:      Matt Brown <matt@crc.net.nz>
 * Author:      Jeremy Kallstrom <jkallstrom@nlanr.net>
 * Version:     $Id: prefs.php 2103 2011-04-12 23:59:22Z brendonj $
 *
 * User Interface to allow the user to store / retrieve their saved preferences
 *
 */
require("amplib.php");

/* Setup the AMP system */
initialise();

/* If no user logged in, go to index */
if (!have_login()) {
  goPage("index.php");
}

/* If no module is set default to GLOBAL */
if (!isset($_REQUEST["module"])) {
  $module = PREF_GLOBAL;
} else {
  $module = $_REQUEST["module"];
}

$mods = get_modules();

$mdata = $mods[$module];

/* Now list all the preferences for this module */
$gps = get_module_preferences($module);

/* Get a list of the scopes this module allows */
$scopes = get_scopes($gps);

// Get the user id and username
$username = explode("|", have_login());
$uid = $username[1];
$username = $username[0];

global $adminContact;

/* Check for reset all prefs clicked */
if (isset($_REQUEST["reset"])) { // && $_REQUEST["reset"]=="true") {
  foreach ($mods as $name=>$data) {
    clear_saved_preferences($name);
  }
}


/* Process Posted Form */
if ( isset($_POST["do"]) ) {
  $module = $_POST["module"];
  //We need to handle the meshes preferences quite a bit differently
  if ( $module == PREF_MESHES ) {
    $siteDb = dbconnect($GLOBAL["sitesDB"]);
    if ( !siteDb ) {
      page_error("Could not connect to the database at this time.  " .
		 "Please try again later.\n");
      exit;
    }

    if ( $_POST["do"] == "Delete" ) {
      $meshname = $_POST{"mesh"};
    
      $query = "select mid from meshes where meshname = '$meshname' and " .
	"uid = '$uid'";

      $result = dbquery($siteDb, $query);
      
      $mid = $result{'results'}[0]['mid'];

      if ( !$mid ) {
	page_error("Could not find mesh entry in the database.  " .
		   "Please try again later.  " .
		   "If this problem persists please contact " .
		   "$adminContact.  Thank you.\n");
	exit;
      }
      
      $query = "delete from meshes_sites where mid = '$mid'";
      dbquery($siteDb, $query);
      
      $query = "delete from meshes where mid = '$mid'";
      dbquery($siteDb, $query);
      
      $preamble = "<font color=\"red\">Mesh $meshname deleted</font><br />";
    }

    if ( isset($_POST["action"]) ) {

      $action = $_POST["action"];
      if ( $action == "create" ) {
	$meshname = $_POST["meshname"];

	unset($_POST["new"]);

	$counter = 0;
	$prefixes = array();
	//Get a list of all possible amp prefixes, these are defined in
	//src/ext/php/ampext.c.
	while ( defined("PREFIX$counter") ) {
	  array_push($prefixes, constant("PREFIX$counter"));
	  ++$counter;
	}

	if ( $meshname == "" ) {
	  page_error("You must enter a meshname, hit back in your browser " .
		     "to try again.\n");
	} else if ( $meshname == "Any" ) {
	  page_error("Invalid meshname entered, 'Any', hit back in your " .
		     "browser and try a  different name.\n");
	} else {
	  $query = "insert into meshes values ( '$meshname', '$uid' )";
	  $result = dbquery($siteDb, $query);
	  
	  $query = "select mid from meshes where meshname = '$meshname' and " .
	    "uid = '$uid'";
	  $result = dbquery($siteDb, $query);

	  $mid = $result{'results'}[0]['mid'];

	  $query = "select ampname, sid from sites";
	  $result = dbquery($siteDb, $query);
	  
	  for ( $rowNum = 0; $rowNum < $result{'rows'}; ++$rowNum ) {
	    $siteInfo{$result{'results'}[$rowNum]{'ampname'}} = 
	      $result{'results'}[$rowNum]{'sid'};
	  }

	  //Look through the post variables and seperate out those which refer
	  //to amp sites
	  foreach ( array_keys($_POST) as $key ) {
	    $found = 0;
	    foreach ( $prefixes as $prefix ) {
	      //Note that this should be === and not ==
	      if ( strpos($key, $prefix) === 0 ) {
		$found = 1;
		break;
	      }
	    }
	    if ( $found == 0 ) {
	      continue;
	    }
	    
	    if ( $_POST[$key] == 1 ) {
	      $sid = $siteInfo{$key};
	      $query = "insert into meshes_sites values ('$mid', '$sid')";
	      dbquery($siteDb, $query);
	    }
	  }
	}

	$preamble = "<font color=\"red\">Mesh $meshname created</font><Br />";

      } else if ( $action == "edit" ) {
	$meshname = $_POST{"mesh"};
	unset($_POST["edit"]);
	
	$counter = 0;
	
	$query = "select mid from meshes where meshname = '$meshname' and " .
	  "uid = '$uid'";
	$result = dbquery($siteDb, $query);
	if ( !$result ) {
	  page_error("Could not find mesh entry in the database.  " .
		     "Please try again later.  " .
		     "If this problem persists please contact " .
		     "$adminContact.  Thank you.\n");
	  exit;
	}

	$mid = $result{'results'}[0]{'mid'};
	
	$query = "delete from meshes_sites where mid = '$mid'";
	dbquery($siteDb, $query);

	$counter = 0;
	$prefixes = array();
	//Get a list of all possible amp prefixes, these are defined in
	//src/ext/php/ampext.c.
	while ( defined("PREFIX$counter") ) {
	  array_push($prefixes, constant("PREFIX$counter"));
	  ++$counter;
	}

	$query = "select ampname, sid from sites";
	$result = dbquery($siteDb, $query);

	for ( $rowNum = 0; $rowNum < $result{'rows'}; ++$rowNum ) {
	  $siteInfo{$result{'results'}[$rowNum]{'ampname'}} = 
	    $result{'results'}[$rowNum]{'sid'};
	}

	//Look through the post variables and seperate out those which refer
	//to amp sites
	foreach ( array_keys($_POST) as $key ) {
	  $found = 0;
	  foreach ( $prefixes as $prefix ) {
	    //Note that this should be === and not ==
	    if ( strpos($key, $prefix) === 0 ) {
	      $found = 1;
	      break;
	    }
	  }

	  if ( $found == 0 ) {
	    continue;
	  }

	  if ( $_POST[$key] == 1 ) {
	    $sid = $siteInfo{$key};
	    $query = "insert into meshes_sites values ('$mid', '$sid')";
	    dbquery($siteDb, $query);
	  }
	}
      }

      $preamble = "<font color=\"red\">Mesh $meshname succesfully " .
	"changed</font><br />";

    }
    dbclose($siteDb);
  } 

  /* Clear current preferences */
  clear_saved_preferences($module);
  if ( isset($_POST["update"]) ) {
    /* Loop through post vars looking for prefs */
    $prefix = "pref-$module-";
    $plen = strlen($prefix);
    foreach ( $_POST as $var=>$value ) {
      if ( strlen($value)<=0 ) {
        continue;
      }
      if ( substr($var, 0, $plen) == $prefix ) {
        $dp = strrpos($var, "*");
        $scope = substr($var, $dp+1, strlen($var)-$dp+1);
        $pref = substr($var, $plen, $dp-$plen);
        set_saved_preference($module, $pref, $scope, $value);
      }
    } // foreach ($_POST as $var=>$value)
  } // (isset($_POST["update"]))
} // (isset($_POST["do"]))

/* Initialise the HTML */
$displayPrefMenu = TRUE;
templateTop();

/* Page Heading & Instructions */
echo "<h2 class=\"graph\">$system_name - Edit Preferences</h2><br>\n";

if ( isset($preamble) ) {
  echo htmlspecialchars($preamble) . "<br />";
}

echo "<div style=\"text-align:left;\">\n";
echo "This page allows you to set your preferences for the $system_name " .
    "system. Your preferences control the default display of the graphs " .
    "and other data. You can override each preference as you display " .
    "each graph.<br>\n";
echo "<br>\n";
echo "Select the set of preferences that you would like to edit from the" .
    " bottom menu on the left.<br>\n";
echo "<br>\n";

/* Module Heading */
echo "<h2>" . htmlspecialchars($mdata->caption) . "</h2>\n";
echo htmlspecialchars($mdata->description) . "<br><br>\n";

if ( $module == PREF_MESHES ) {
  
  $siteDb = dbconnect($GLOBALS["sitesDB"]);
  if ( !$siteDb ) {
    page_error("Could not connect to the database at this time. " .
	       "Please try again later.\n");
    exit;
  }
  
  $new = $_POST["new"];
  $edit = $_POST["edit"];
  $sort = $_POST["sort"];
  
  if ( !$sort ) {
    $sort = get_preference(PREF_GLOBAL, GP_SORT, PREF_GLOBAL);
  } else {
    $sort = strtolower($sort);
  }
  
  if ( $new ) { //Display page to create a new mesh

    echo "<table width=\"100%\" cellspacing=0 cellpadding=0><tr><td rowspan=2" .
      " style=\"width: 8em\"><h3>Create Mesh</h3></td><td class=weekheading>" .
      "&nbsp</td></tr><tr><td>&nbsp;</td></tr></table>";
    echo "<i>Set the mesh name, select the sites which you want to be in the" . 
      " mesh, and finally click on \"Create Mesh\"</i><Br /><Br />";

    echo "<form action=\"prefs.php\" method=\"POST\">\n";
    echo "<input type=\"hidden\" name=\"module\" value=\"meshes\">\n";
    echo "<input type=\"hidden\" name=\"action\" value=\"create\">\n";
    echo "<input type=\"hidden\" name=\"new\" value=\"continue\">\n";
    echo "Mesh name: <input type=\"text\" name=\"meshname\">&nbsp; &nbsp;\n";
    echo "<input type=\"submit\" value=\"Create Mesh\" name=\"do\">".
      "<Br /><br />\n";
    printSourceTable($sort, array(), $siteDb);
    echo "</form>\n";
  } else if ( $edit ) {  //Display page to edit a mesh

    $meshname = $_POST["mesh"];

    $query = "select mid from meshes where meshname = '$meshname'";
    $result = dbquery($siteDb, $query);

    $mid = $result{'results'}[0]{'mid'};

    if ( !$mid ) {
      page_error("Could not find mesh entry in the database.  Please try " .
		 "again later. If this problem persists please contact " .
		 "$adminContact.  Thank you.\n");
      exit;
    }

    $query = "select ampname from sites natural join meshes_sites where " .
      "mid = '$mid'";
    $result = dbquery($siteDb, $query);
    if ( !$result ) {
      page_error("Could not query database at this time. Please try again " .
		 "later.\n");
      exit;
    }

    $selected = array();

    for ( $rowNum = 0; $rowNum < $result{'rows'}; ++$rowNum ) {
      array_push($selected, $result{'results'}[$rowNum]{'ampname'});
    }
    
    echo "<table width=\"100%\" cellspacing=0 cellpadding=0>" .
      "<tr><td rowspan=2 style=\"width: 8em\"><h3>Update Mesh</h3></td>" .
      "<td class=weekheading>&nbsp</td></tr><tr><td>&nbsp;</td></tr></table>\n";
    echo "<i>Change the selection of sites which you want associated with " .
      "this mesh and click on \"Update Mesh\"</i><br /><Br />\n";
    echo "<form action=\"prefs.php\" method=\"POST\">";
    echo "<input type=\"hidden\" name=\"module\" value=\"meshes\">\n";
    echo "<input type=\"hidden\" name=\"action\" value=\"edit\">\n";
    echo "<input type=\"hidden\" name=\"edit\" value=\"continue\">\n";
    echo '<input type="hidden" name="mesh" value="'.htmlspecialchars($meshname).'">'."\n";
    echo 'Mesh name: '.htmlspecialchars($meshname).'&nbsp;&nbsp;';
    echo "<input type=\"submit\" value=\"Update Mesh\" name=\"do\">" .
      "<br /><br />\n";

    printSourceTable($sort, $selected, $siteDb);

    echo "<table cellpadding=\"2\" cellspacing=\"0\" width=\"100%\" " .
      "class=\"prefs\">\n";
    echo "<tr class=\"th\">";

  } else { //Display the options of what to do
    echo "<form action=\"prefs.php\" method=\"POST\">\n";
    echo "<select name=\"mesh\">\n";

    $query = "select meshname from meshes where uid = '$uid'";
    $result = dbquery($siteDb, $query);
    
    if ( $result{'rows'} > 0 ) {
      for ( $rowNum = 0; $rowNum < $result{'rows'}; ++$rowNum ) {
	$meshName = $result{'results'}[$rowNum]{'meshname'};
	echo '<option value="'.htmlspecialchars($meshName).'">'.htmlspecialchars($meshName).'</option>'."\n";
      }
    } else {
      echo "<option value=\"-1\">No meshes exist</option>\n";
    }
    
    echo "</select>";
    echo "<input type=\"hidden\" name=\"module\" value=\"meshes\">";
    echo "<input class=\"srclist\" type=\"submit\" value=\"Edit\" " .
      "name=\"edit\">,\n";
    echo "<input class=\"srclist\" type=\"submit\" value=\"Delete\" " .
      "name=\"do\"><Br /><Br />\n";
    echo "<input class=\"srclist\" type=\"submit\" value=\"Create New Mesh\" " .
      "name=\"new\">";
    
    echo "</form>";
  }
} else {
  echo "<form action=\"prefs.php\" method=\"post\">\n";
  echo "<input type=\"hidden\" name=\"do\" value=\"yes\">\n";
  echo '<input type="hidden" name="module" value="'.htmlspecialchars($module).'">'."\n";
  echo "<table cellpadding=2 cellspacing=0 width=\"100%\" class=\"prefs\">\n";
  echo "<tr class=\"th\">\n";
  echo "<th class=\"preft\">&nbsp;</th>\n";
  foreach ($scopes as $idx=>$scope) {
    $caption = scope_name($scope);
    echo '<th colspan="2" class="preft">'.htmlspecialchars($caption).' Graphs</th>'."\n";
  }
  echo "</tr>\n";
  echo "<tr class=\"th\">\n";
  echo "<th class=\"preft\" style=\"border-bottom:1px solid white;\">" .
    "Preference</th>\n";
  foreach ($scopes as $idx=>$scope) {
    $caption = scope_name($scope);
  echo "<th class=\"prefv\">Value</th><th class=\"prefd\">Default</th>\n";
  }
  echo "</tr>\n";
  $n=0;
  foreach ($gps as $name=>$data) {
    if ($n==1) {
      $n=0;
    } else {
      $n=1;
    }
    echo "<tr class=\"row$n\">\n";
    echo "<td class=\"preft\" style=\"border-bottom:1px solid white;\">" .
      $data->caption . "</td>";
    foreach ($scopes as $idx=>$scope) {
      /* Check preference exists in this scope */
      if (!in_array($scope, $data->scope)) {
        echo "<td class=\"prefv\">&nbsp;</td>" .
          "<td class=\"prefd\">&nbsp;</td>\n";
        continue;
      }
      $caption = scope_name($scope);
      echo "<td class=\"prefv\">" .
        pref_input_widget($module, $data->name, $scope) . "</td>";
      echo "<td class=\"prefd\">&nbsp;" .
        display_default_preference($module, $data->name, $scope) . "</td>\n";
    }
    echo "</tr>\n";
  }

  echo "</table><br>\n";
  echo "<input type=\"submit\" name=\"update\" value=\"" .
    "Update Saved Preferences\">\n";
  echo "<input type=\"submit\" name=\"reset\" value=\"Reset to Default\">\n";
  echo "<br><br>\n";
  echo "</form>\n";
}

/*
 * Prints out the list of sources, selected those listed in
 * $selected.
 */
function printSourceTable($sort, $selected, $siteDb) {
  $query = "select ampname, longname from sites order by $sort";
  $result = dbquery($siteDb, $query);
  dbClose($siteDb);
  if ( !$result ) {
    page_error("Could not query database at this time. Please try " .
	       "again later.\n");
    exit;
  }

  echo "<table cellpadding=\"2\" cellspacing=\"0\" width=\"100%\" " .
    "class=\"prefs\">\n";
  echo "<tr class=\"th\">\n";
  if ( $sort == "longname" ) {
    echo "<th class=\"prefv\" style=\"border-bottom:1px solid white;\">\n";
    echo "<input type=\"submit\" name=\"sort\" class=\"prefsort\" " .
      "value=\"Ampname\"></th>\n"; 
    echo "<th class=\"preft\" style=\"border-bottom:1px solid white;\">\n";
    echo "<i>Longname</i></th>\n";
  } else {
    echo "<th class=\"prefv\" style=\"border-bottom:1px solid white;\">\n";
    echo "<i>Ampname</i></th>\n";
    echo "<th class=\"preft\" style=\"border-bottom:1px solid white;\">\n";
    echo "<input type=\"submit\" name=\"sort\" class=\"prefsort\" " .
      "value=\"Longname\"></th>\n";
  }

  echo "<th class=\"prefv\">Setting</th>";
  echo "</tr>";

  for ( $rowNum = 0; $rowNum < $result{'rows'}; ++$rowNum ) {
    if ( $rowNum %2 == 0 ) {
      $value = 1;
    } else {
      $value = 0;
    }
    $ampname = $result{'results'}[$rowNum]{'ampname'};
    $longname = $result{'results'}[$rowNum]{'longname'};
    echo "<tr class=\"row$value\"><td class=\"prefv\" " .
      "style=\"border-bottom:1px solid white;\">".htmlspecialchars($ampname)."</td>\n";
    echo "<td class=\"preft\" " .
      "style=\"border-bottom:1px solid white;\">".htmlspecialchars($longname)."</td>\n";
    echo "<td class=\"prefv\" " .
      "style=\"border-bottom:1px solid white;\">";
    if ( in_array($ampname, $selected) ) {
      echo "<input type=\"radio\" name=\"".htmlspecialchars($ampname)."\" checked value=\"1\">Show" .
      "<input type=\"radio\" name=\"$ampname\" value=\"0\">Don't show</td>\n";
    } else {
      echo "<input type=\"radio\" name=\"".htmlspecialchars($ampname)."\" value=\"1\">Show" .
      "<input type=\"radio\" name=\"".htmlspecialchars($ampname)."\" checked value=\"0\">" .
	"Don't show</td>\n";
    }
    echo "</tr>";
  }

  echo "</table>";

}

echo "</div>\n";

/* Finish off the page */
endPage();

  // Emacs control
  // Local Variables:
  // eval: (c++-mode)

// vim:set sw=2 ts=2 sts=2 et:
?>
