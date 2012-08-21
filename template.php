<?php
/*
 * AMP Data Display Interface
 *
 * User Interface Template
 *
 * Author:      Tony McGregor <tonym@cs.waikato.ac.nz>
 * Author:      Matt Brown <matt@crc.net.nz.
 * Author:      Jeremy Kallstrom <jkallstrom@nlanr.net>
 * Version:     $Id: template.php 1712 2010-03-10 22:19:05Z brendonj $
 *
 * This file provides a default user interface for the AMP system. If you
 * want to provide a customised user interface you should utilise the
 * template_local.php file as it will not be overwritten on upgrades. Within
 * your template_local.php file you should create functions called
 * local_templateTop() and local_templateBottom()
 * If these functions exist they will be used instead of the defaults provided
 * here
 */
@include_once("template_local.php");

/* Default Page Header Function */
function templateTop(){

  global $template_displayed;

  /* Use local template if available */
  if ( function_exists("local_templateTop") ) {
    local_templateTop();
    $template_displayed = TRUE;
    return;
  }

    /* Otherwise use default */
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
 <link rel="stylesheet" href="amp.css" type="text/css" />
 <title>AMP Measurements</title>
 <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
 <script type="text/javascript" src="js/jquery-1.4.min.js"></script>
</head>
<body>
<!-- <?php echo "Using AMP extension version: " . AMPEXT_REVISION; ?> -->
<div class="header">
<table>
<tr>
<td>
<a href="http://www.waikato.co.nz/" class="nohover">
<img src="/uow-coa.png" alt="University of Waikato"/></a>

</td><td class="title">
<a href="http://www.wand.net.nz/" class="nohover">
<img src="/wand-logo.png" alt="WAND Network Research Group"/></a>
</td></tr>
</table>
</div>

<table><tr><td style="vertical-align:top;">
<?
if ( function_exists("draw_first_menu") ) {
    draw_first_menu();
}
if ( function_exists("draw_view_menu") ) {
    draw_view_menu();
}
if ( function_exists("draw_last_menu") ) {
    draw_last_menu();
}
?>
</td><td width="100%" style="vertical-align:top;">
<div class="content" style="text-align:center;">
<?php
    $template_displayed = TRUE;

}


/* Default Page Bottom Function */
function templateBottom(){

  /* Use local template if available */
  if ( function_exists("local_templateBottom") ) {
    local_templateBottom();
    return;
  }

  /* Otherwise use default */
?>
</div>
</td></tr></table>
<div class="footer"></div>
</body>
</html>
<?php
}

function draw_first_menu()
{

  global $system_name;

  echo "<div class=\"menu\">\n";
  echo "<h2>$system_name Measurements</h2>\n";

  echo "<ul>\n";
  echo "<li><a href=\"index.php\">Home</a></li>\n";
  echo "<li><a href=\"download_raw.php\">Download Raw Data</a></li>\n";
  echo "<li><a href=\"add_event.php\">Add Event</a></li>\n";
  echo "<li><a href=\"performance_map.php\">Performance Map</a></li>\n";
  echo "</ul>\n";

  $res = have_login();
  if ( ! $res ) {
    echo "<ul>\n";
    echo "<li><a href=\"login.php\">Login</a></li>\n";
    echo "<li><a href=\"create_account.php\">Create Account</a></li>\n";
    echo "</ul>\n";
  } else {
    $res = explode("|", $res);
    $res = $res[0];
    echo 'Welcome back <b>'.htmlspecialchars($res).'</b>,<br />'."\n";
    echo "<ul>\n";
    echo "<li><a href=\"prefs.php\">Edit Your Preferences</a></li>\n";
    echo "<li><a href=\"logout.php\">Logout</a></li>\n";
    echo "</ul>\n";
  }
  echo "</div>\n";

}

function draw_last_menu()
{

  global $display_objects, $display_cats, $pageClass;
  global $displayPrefMenu;

  /* If preferences page - draw preferences menu */
  if ( $displayPrefMenu == TRUE ) {
    draw_pref_menu();
    return;
  }

  /* Graph Options Menu */
  draw_graph_options_menu();

  /* Comparisons Menu */
  draw_comparisons_menu();

}

function draw_comparisons_menu()
{
  global $page_name;

  /* Exit now if comparisons not enabled */
  if (get_preference(PREF_GLOBAL, GP_COMPARISONS, PREF_GLOBAL)==PREF_FALSE) {
    return;
  }
  /* Show Comparisons Box if Enabled */
  echo "<div class=\"menu\">\n";
  echo "<h2>Comparison List</h2><br />\n";

  if ( ! isset($_SESSION["comparison_items"]) ) {
    echo "No graphs selected<br />\n";
  } else {
    foreach($_SESSION["comparison_items"] as $idx=>$item) {
      $object = get_display_object($item["object"]);
      echo "<b>" . htmlspecialchars($object->category) . "</b> - " . htmlspecialchars($object->title) . "<br />";
      echo htmlspecialchars($item["src"]) . " to " . htmlspecialchars($item["dst"]) . "<br />";
      echo "<form action=\"$page_name\" method=\"post\">\n";
      echo "<input type=\"hidden\" value=\"yes\" name=\"do\">\n";
      echo "<input type=\"hidden\" value=\"del\" name=\"comparison\">\n";
      echo '<input type="hidden" value="'.htmlspecialchars($idx).'" name="idx">'."\n";
      echo "<input type=\"submit\" value=\"Remove &gt;&gt;\" " .
        "name=\"remove\" class=\"graphoptionssubmit\">\n";
      echo "</form>\n";
    }

  }

  echo "<br /><a href=\"comparison.php\">View Comparisons &gt;&gt;</a>\n";
  echo "<br /><br />\n";
  echo "<form action=\"$page_name\" method=\"post\">\n";
  echo "<input type=\"hidden\" name=\"do\" value=\"yes\">\n";
  echo "<input type=\"hidden\" name=\"comparison\" value=\"reset\">\n";
  echo "<input type=\"submit\" value=\"Reset Comparison List &gt;&gt;\" " .
      "name=\"reset-comparison\" class=\"graphoptionssubmit\">\n";
  echo "</form>\n";

  echo "</div>\n";


}

function draw_view_menu()
{
  global $page_name;

  echo "<div class=\"menu\">\n";
  echo "<h2>View Mode</h2>\n";

  echo "<ul>\n";
  echo "<li><a href=\"src.php\">Source View</a></li>\n";
  echo "<li><a href=\"matrix.php\">Matrix View</a></li>\n";
  echo "</ul>\n";

  echo "</div>\n";
}

function draw_graph_options_menu()
{
  global $display_items, $display_cats, $pageClass, $amp_prefs, $page_settings,
    $page_name;

  /* Only display graph menu on short and long term pages */
  if ($pageClass != PREF_LONGTERM && $pageClass != PREF_SHORTTERM) {
    return;
  }

  // get rid of the graph specifier for the url this form loads
  // -- if the user is clicking on things and submitting this form,
  // they want to see the graphs that they just selected
  $formurl = preg_replace("/&graph=[\w-]*/", "", $page_name);

  // make sure this is set right so clicking the button doesnt reset it...
  if(isset($_REQUEST["rge"]) && substr_count($formurl, "&rge=") < 1)
    $formurl .= "&amp;rge=" . urlencode($_REQUEST["rge"]);

  echo "<div class=\"menu\">\n";
  echo "<h2>Available Graphs</h2><br />\n";
  //echo "<form action=\"$page_name\" method=\"POST\" name=\"graph-selection\">\n";
  echo "<form action=\"$formurl\" method=\"POST\" name=\"graph-selection\">\n";
  echo "<input type=\"hidden\" name=\"do\" value=\"yes\">\n";
  echo "<input type=\"hidden\" name=\"form\" value=\"graph-selection\">\n";
  echo "<input type=\"hidden\" name=\"form_prefix\" value=\"show-\">\n";
  foreach ($display_cats as $cat) {
    echo '<b>'.htmlspecialchars($cat).'</b><br />'."\n";
    foreach ($display_items as $key=>$item) {
      if ( $item->category != $cat ) {
        continue;
      }
      $object = get_display_object($item->displayObject);
      $d = is_item_displayed($item->name, $object->displayPref, $pageClass);
      if ( $d ) {
        $checked = " checked";
      } else {
        $checked = "";
      }
      $name = build_item_formname($item->name);
      echo "<label>";
      echo '<input type="checkbox" name="'.htmlspecialchars($name).'"'.$checked.'>&nbsp;' .
        htmlspecialchars($item->title) . "</label><br />\n";
    }
    echo "<br />";
  }

  // all this seems to display is the option to select the number of
  // weeks to display? I've already got that with my new select box
  // so we can probably do without this for now
  /*
  $displayed = 0;
  foreach ( $amp_prefs as $pref ) {
    if ( $pageClass == PREF_LONGTERM ) {
      if ( $pref->display == PREF_TRUE ) {
	if ( !$displayed ) {
	  echo "<h2>Options</h2><br />\n";
	  $displayed = 1;
	}
	
	if ( $pref->type == PREF_TYPE_INPUT ) {
	  echo "<input type=\"text\" name=\"show-$pref->name\" value=\"";
	  if ( $page_settings["graph-selection"]['show-no-weeks'] ) {
	    echo $page_settings["graph-selection"]['show-no-weeks'];
	  } else {
	    echo $pref->defaults{'global'};
	  }
	  echo "\" size = $pref->width> $pref->sCaption<Br />";
	}
      }
    }
  }

    */
  echo "<Br /><input type=\"submit\" value=\"Refresh Display\" " .
      "name=\"select-graphs-button\">\n";
  echo "</form>\n";


  echo "<br />";
  echo "<form action=\"$page_name\" method=\"POST\" name=\"page-reset\">\n";
  echo "<input type=\"hidden\" name=\"reset\" value=\"yes\">\n";
  echo "<input type=\"submit\" value=\"Reset Display to Default &gt;&gt;\" " .
      "name=\"reset-display\" class=\"graphoptionssubmit\">\n";
  echo "</form>";
  echo "</div>\n";

}

function draw_pref_menu()
{

  echo "<div class=\"menu\">\n";
  echo "<h2>Edit Preferences</h2><br />\n";
  echo "<ul>\n";

  $mods = get_modules();

  foreach ($mods as $mname=>$mdata) {
    if ( count(get_module_preferences($mname) )<=0 && $mname != PREF_MESHES ) {
      continue;
    }
    /* Heading */
    echo "<li><a href=\"prefs.php?module=" . urlencode($mdata->name) . "\">" .
        $mdata->caption . "</a></li>\n";
  }
  echo "</ul><ul><li><a href=\"prefs.php?resetall=true\">Reset All " .
      "Preferences<br />to Default</a></li>\n";
  echo "</ul>\n";
  echo "</div>\n";

}

  // Emacs control
  // Local Variables:
  // eval: (c++-mode)

// vim:set sw=2 ts=2 sts=2 et:
?>
