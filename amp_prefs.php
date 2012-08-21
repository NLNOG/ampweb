<?php
/*
 * AMP Data Display Interface
 *
 * User Preference Handling
 *
 * Author:  Matt Brown <matt@crc.net.nz>
 * Author:  Jeremy Kallstrom <jkallstrom@nlanr.net>
 * Version: $Id: amp_prefs.php 2103 2011-04-12 23:59:22Z brendonj $
 *
 * The preference system has two important concepts. Module and Scopes. Modules
 * are logical units of the interface which preferences should be grouped
 * together for, such as ICMP test, Throughput Test, etc. Scope refers to the
 * two sets of timeframe that the interface supports. Preferences can be stored
 * in the long term (weekly) or short term (daily) scope independently of each
 * other. In addition to this there is a global preference module and a special
 * global scope which is used with it for preferences that effect the operation
 * of the entire interface.
 *
 * To register a preference a page should called register_preference function.
 *
 * Preferences are used to determine which graphs are displayed by default. The
 * preferences used to accomplish this should be registered in each display
 * modules file, before the display object itself is registerd. These
 * preferences should exist in the global module rather than specific modules.
 * These preferences are linked to display objects via the display_pref
 * parameter the a display_object_t type.
 *
 * Default preferences can be overriden on a site by site basis by
 * calling update_preference_defaults in const_local.php
 *
 */

/**** Preference Scopes ****/
define('PREF_GLOBAL', "global");
define('PREF_LONGTERM', "longterm");
define('PREF_SHORTTERM', "shortterm");

/**** Preference Types ****/
define('PREF_TYPE_INPUT', "input");
define('PREF_TYPE_BOOL', "bool");
define('PREF_TYPE_TIMEZONE', "timezone");
define('PREF_TYPE_MESH', "mesh");
define('PREF_TYPE_SORT', "sorting");

define('PREF_TRUE', "y");
define('PREF_FALSE', "n");

/**** Global Preferences ****/
define('GP_SHOW_MAPS', "show-maps");
define('GP_TIMEZONE', "timezone");
define('GP_SORT', "sorting");
define('GP_MAP_SIZE_X', "map-x-size");
define('GP_MAP_SIZE_Y', "map-y-size");
define('GP_LT_NUM_WEEKS', "no-weeks");
define('GP_COMPARISONS', "comparisons");
define('GP_MAP', "map");
define('GP_MESH', "mesh");

/**** Semi Global Preferences ****/
/* These preferences are defined globally, but should never be instantiated
 * in the global module. They should be instantiated in specific modules
 * such as ICMP, Iperf, etc.
 */
define('PREF_BINSIZE', "binsize");
define('PREF_YMAX', "ymax");
define('PREF_LINES', "lines");

define('PREF_DISPLAY_MAX', "display-max");
define('PREF_DISPLAY_MIN', "display-min");
define('PREF_DISPLAY_MEAN', "display-mean");
define('PREF_DISPLAY_MEDIAN', "display-median");
define('PREF_DISPLAY_STDDEV', "display-stddev");
define('PREF_DISPLAY_SUMMARY', "display-summary");
/**** Variables ****/

class pref_t {

  /* Placeholder for a preference definition */

  /* List of scopes that the preference is valid in */
  var $scopes;
  /* Module that 'owns' this preference */
  var $module;
  /* Name for the preference (must be unique, no spaces, etc) */
  var $name;
  /* Caption */
  var $caption;
  /* Short Caption */
  var $sCaption;
  /* Default value for the preference for each scope */
  var $defaults;
  /* Type of the preference */
  var $type;
  /* Width of the input box */
  var $width;
  /* Do we display on the side panel */
  var $display;

}

class module_t {
  /* Placeholder for a module definition */

  /* Module Name */
  var $name;
  /* Short Description */
  var $caption;
  /* Help Text */
  var $description;
}

/* Preference Definitions */
$amp_prefs = array();

/* Preferences for the current user, from defaults (or DB if logged in) */
$amp_pref = array();

/* List of preference modules */
$amp_pref_modules = array();

/**** Initialisation ****/

/* Register Modules */
register_module(PREF_GLOBAL, "Global Preferences", "These preferences " .
                "affect the operation of the entire interface.");
/*
//XXX this isn't used anywhere? Should it be? This is mostly covered in the
// global preferences, which graphs to show when.
register_module(PREF_DISPLAY_OBJECTS, "Display Objects", "These preferences " .
                "control which display objects (graphs, etc) are displayed " .
                "by default in the $system_name interface.");
*/
/* Register Global Preferences */
register_preference(array(PREF_GLOBAL),  GP_TIMEZONE, array(PREF_GLOBAL),
                    "Display Time Zone", "", PREF_TYPE_TIMEZONE,
                    array(PREF_GLOBAL=>"UTC"), 20);
register_preference(array(PREF_GLOBAL), GP_SHOW_MAPS, array(PREF_GLOBAL),
                    "Show Maps of AMPlet locations", "", PREF_TYPE_BOOL,
                    array(PREF_GLOBAL=>PREF_TRUE));

register_preference(array(PREF_GLOBAL), GP_SORT, array(PREF_GLOBAL),
		    "Sort by", "", PREF_TYPE_SORT,
		    array(PREF_GLOBAL=>"Longname"), 20);

register_preference(array(PREF_GLOBAL), GP_MAP_SIZE_X, array(PREF_GLOBAL),
                    "Size of Map x-axis", "", PREF_TYPE_INPUT, array(), 3);
register_preference(array(PREF_GLOBAL), GP_MAP_SIZE_Y, array(PREF_GLOBAL),
                    "Size of Map y-axis", "", PREF_TYPE_INPUT, array(), 3);
register_preference(array(PREF_GLOBAL, PREF_TRUE), GP_LT_NUM_WEEKS, array(PREF_GLOBAL),
                    "No. of Weeks in Long Term View", "No. of Weeks", PREF_TYPE_INPUT,
                    array(PREF_GLOBAL=>2), 3);
register_preference(array(PREF_GLOBAL), GP_MAP, array(PREF_GLOBAL), "Default Map", "",
                    PREF_TYPE_INPUT, array(PREF_GLOBAL=>""), 20);

register_preference(array(PREF_GLOBAL), GP_MESH, array(PREF_GLOBAL), "Default Mesh", "",
		    PREF_TYPE_MESH, array(PREF_GLOBAL=>"Any"), 20);

define('PREF_MESHES', "meshes");

register_module(PREF_MESHES, "Mesh Preferences", "These preferences " .
		"control the creation and maintenance of groups of sites to " .
		"display in the source list.");

/**** Functions ****/

/*
 * This function allows a script to modify the default values of a preference.
 * This function must be called after the preference is registered, but before
 * the value is retrieved for it to have the desired effect.
 */
function update_preference_defaults($module, $name, $defaults) {

  global $amp_prefs;

  $pname = "$module-$name";

  if ( ! array_key_exists($pname, $amp_prefs) ) {
    /* Preference doesn't exist - can't update */
    return NULL;
  } else {
    $amp_prefs[$pname]->defaults = $defaults;
  }

}

/*
 * This function is called by modules (or anything else) that wants to
 * register a preference for use in the interface. See the definition of
 * pref_t for an explanation of the parameters.
 */
function register_preference($module, $name, $scope, $caption, $sCaption, $type,
  $defaults, $width=0)
{

  global $amp_prefs;

  $npref = new pref_t();

  $npref->module = $module[0];
  $npref->scope = $scope;
  $npref->name = $name;
  $npref->caption = $caption;
  $npref->sCaption = $sCaption;
  $npref->type = $type;
  $npref->defaults = $defaults;
  $npref->width = $width;
  if ( sizeof($module) > 1 && $module[1] ) {
    $npref->display = $module[1];
  } else {
    $npref->display = PREF_FALSE;
  }

  /* Add to the array */
  $amp_prefs["$module[0]-$name"] = $npref;

}

/*
 * This function is called by modules (or anything else) that wants to
 * register a preference module. See the definition of module_t for an
 * explanation of the parameters.
 */
function register_module($name, $caption, $description) {

  global $amp_pref_modules;

  $nmodule = new module_t();

  $nmodule->name = $name;
  $nmodule->caption = $caption;
  $nmodule->description = $description;

  /* Add to the array */
  $amp_pref_modules["$name"] = $nmodule;

}

/*
 * Draw the appropriate type of input widget for the specified preference
 * and set it's value
 */
function pref_input_widget($module, $name, $scope)
{

  global $amp_prefs;

  $pname = "$module-$name";
  if ( $amp_prefs[$pname]->type == PREF_TYPE_INPUT ) {
    return pref_input_box($pname, $scope);
  } else if ( $amp_prefs[$pname]->type == PREF_TYPE_BOOL ) {
    return pref_option_box($pname, $scope);
  } else if ( $amp_prefs[$pname]->type == PREF_TYPE_TIMEZONE ) {
    return pref_timezone_list($pname, $scope);
  } else if ( $amp_prefs[$pname]->type == PREF_TYPE_MESH ) {
    return pref_mesh_list($pname, $scope);
  } else if ( $amp_prefs[$pname]->type == PREF_TYPE_SORT ) {
    return pref_sort_list($pname, $scope);
  } else {
    return "!Unknown type - " . $amp_prefs[$pname]->type . ", $pname!";
  }

}

function pref_option_box($pname, $scope)
{

  global $amp_prefs;

  $pref = $amp_prefs[$pname];
  if ( $pref->type != PREF_TYPE_BOOL ) {
    return;
  }

  $value = get_saved_preference($pref->module, $pref->name, $scope);
  if ( $value == NULL ) {
    $value = get_default_preference($pref->module, $pref->name, $scope);
  }

  if ( $value == PREF_TRUE ) {
    $tchecked = " checked";
    $fchecked = "";
  } else {
    $tchecked = "";
    $fchecked = " checked";
  }

  return "<input type=\"radio\" name=\"pref-$pname*$scope\" value=\"" .
      PREF_TRUE . "\"$tchecked>&nbsp;yes&nbsp;&nbsp;<input type=\"" .
      "radio\" name=\"pref-$pname*$scope\" value=\"" . PREF_FALSE .
      "\"$fchecked>&nbsp;no\n";

}

function pref_input_box($pname, $scope)
{

  global $amp_prefs;

  $pref = $amp_prefs[$pname];
  if ( $pref->type != PREF_TYPE_INPUT ) {
    return;
  }

  $value = get_saved_preference($pref->module, $pref->name, $scope);
  if ( $value == NULL ) {
    $value = get_default_preference($pref->module, $pref->name, $scope);
  }

  if ( $pref->width>0 ) {
    $size = " size=\"" . $pref->width . "\"";
  } else {
    $size = "";
    }

  return "<input type=\"text\" name=\"pref-$pname*$scope\" value=\"" .
      "$value\"$size>\n";

}

function pref_timezone_list($pname, $scope) {
  global $amp_prefs;
  $pref = $amp_prefs[$pname];
  if ( $pref->type != PREF_TYPE_TIMEZONE ) {
    return;
  }

  $value = get_saved_preference($pref->module, $pref->name, $scope);
  if ( $value == NULL ) {
    $value = get_default_preference($pref->module, $pref->name, $scope);
  }

  $return_val = "<select size=1 name=\"pref-$pname*$scope\" value=\"" .
    "value\">\n";

  $list = getTimeZoneList();

  foreach ( $list as $zone ) {
    $return_val .= "<option value=\"$zone\"";
    if ( $zone == $value ) {
      $return_val .= " selected";
    }
    $return_val .= ">$zone</option>\n";
  }


  $return_val .= "</select>";
  unset($list);
  return $return_val;
}

function pref_mesh_list($pname, $scope) {
  global $amp_prefs;
  $pref = $amp_prefs[$pname];

  if ( $pref->type != PREF_TYPE_MESH ) {
    return;
  }

  $value = get_saved_preference($pref->module, $pref->name, $scope);
  if ( $value == NULL ) {
    $value = get_default_preference($pref->module, $pref->name, $scope);
  }

  $return_val = "<select size=1 name=\"pref-$pname*$scope\" value=\"" .
    "value=\">\n";

  $siteDb = dbconnect($GLOBALS[sitesDB]);
  if ( !$siteDb ) {
    return;
  }

  $username = explode("|", have_login());
  $uid = $username[1];

  $query = "select meshname from meshes where uid = '-1' or uid = '$uid' order by meshname";
  $result = dbquery($siteDb, $query);
  
  if ( !$result || $result{'rows'} == 0 ) {
    return;
  }

  for ( $rowNum = 0; $rowNum < $result{'rows'}; ++$rowNum ) {
    $meshname = $result{'results'}[$rowNum]{'meshname'};
    $return_val .= "<option value=\"$meshname\"";
    if ( $meshname == $value ) {
      $return_val .= " selected";
    }

    $return_val .= " >$meshname</option>\n";
  }

  dbclose($siteDb);

  $return_val .= "</select>";
  return $return_val;
}

function pref_sort_list($pname, $scope) {
  global $amp_prefs;
  $pref = $amp_prefs[$pname];
  
  if ( $pref->type != PREF_TYPE_SORT ) {
    return;
  }
  
  $value = get_saved_preference($pref->module, $pref->name, $scope);
  if ( $value == NULL ) {
    $value = get_default_preference($pref->module, $pref->name, $scope);
  }

  if ( $value == "Longname" || $value == "longname" ) {
    $lchecked = " checked";
    $achecked = "";
  } else {
    $lchecked = "";
    $achecked = " checked";
  }

  return "<input type=\"radio\" name=\"pref-$pname*$scope\" " .
    "value=\"longname\" $lchecked>Longname\n" .
    "<input type=\"radio\" name=\"pref-$pname*$scope\" " .
    "value=\"ampname\" $achecked>Ampname\n";
}

function getTimeZoneList() {
  $list = array();

  array_push($list, "UTC");

  array_push($list, "Africa/Abidjan");
  array_push($list, "Africa/Accra");
  array_push($list, "Africa/Addis_Ababa");
  array_push($list, "Africa/Algiers");
  array_push($list, "Africa/Asmera");
  array_push($list, "Africa/Bamako");
  array_push($list, "Africa/Bangui");
  array_push($list, "Africa/Banjul");
  array_push($list, "Africa/Bissau");
  array_push($list, "Africa/Blantyre");
  array_push($list, "Africa/Brazzaville");
  array_push($list, "Africa/Bujumbura");
  array_push($list, "Africa/Cairo");
  array_push($list, "Africa/Casablanca");
  array_push($list, "Africa/Ceuta");
  array_push($list, "Africa/Conakry");
  array_push($list, "Africa/Dakar");
  array_push($list, "Africa/Dar_es_Salaam");
  array_push($list, "Africa/Djibouti");
  array_push($list, "Africa/Douala");
  array_push($list, "Africa/El_Aaiun");
  array_push($list, "Africa/Freetown");
  array_push($list, "Africa/Gaborone");
  array_push($list, "Africa/Harare");
  array_push($list, "Africa/Johannesburg");
  array_push($list, "Africa/Kampala");
  array_push($list, "Africa/Khartoum");
  array_push($list, "Africa/Kigali");
  array_push($list, "Africa/Kinshasa");
  array_push($list, "Africa/Lagos");
  array_push($list, "Africa/Libreville");
  array_push($list, "Africa/Lome");
  array_push($list, "Africa/Luanda");
  array_push($list, "Africa/Lubumbashi");
  array_push($list, "Africa/Lusaka");
  array_push($list, "Africa/Malabo");
  array_push($list, "Africa/Maputo");
  array_push($list, "Africa/Maseru");
  array_push($list, "Africa/Mbabane");
  array_push($list, "Africa/Mogadishu");
  array_push($list, "Africa/Monrovia");
  array_push($list, "Africa/Nairobi");
  array_push($list, "Africa/Ndjamena");
  array_push($list, "Africa/Niamey");
  array_push($list, "Africa/Nouakchott");
  array_push($list, "Africa/Ouagadougou");
  array_push($list, "Africa/Porto-Novo");
  array_push($list, "Africa/Sao_Tome");
  array_push($list, "Africa/Timbuktu");
  array_push($list, "Africa/Tripoli");
  array_push($list, "Africa/Tunis");
  array_push($list, "Africa/Windhoek");

  array_push($list, "America/Adak");
  array_push($list, "America/Anchorage");
  array_push($list, "America/Anguilla");
  array_push($list, "America/Antigua");
  array_push($list, "America/Araguaina");
  array_push($list, "America/Aruba");
  array_push($list, "America/Asuncion");
  array_push($list, "America/Barbados");
  array_push($list, "America/Belem");
  array_push($list, "America/Belize");
  array_push($list, "America/Boa_Vista");
  array_push($list, "America/Bogota");
  array_push($list, "America/Boise");
  array_push($list, "America/Buenos_Aires");
  array_push($list, "America/Cambridge_Bay");
  array_push($list, "America/Cancun");
  array_push($list, "America/Caracas");
  array_push($list, "America/Catamarca");
  array_push($list, "America/Cayenne");
  array_push($list, "America/Cayman");
  array_push($list, "America/Chicago");
  array_push($list, "America/Chihuahua");
  array_push($list, "America/Cordoba");
  array_push($list, "America/Costa_Rica");
  array_push($list, "America/Cuiaba");
  array_push($list, "America/Curacao");
  array_push($list, "America/Dawson");
  array_push($list, "America/Dawson_Creek");
  array_push($list, "America/Denver");
  array_push($list, "America/Detroit");
  array_push($list, "America/Dominica");
  array_push($list, "America/Edmonton");
  array_push($list, "America/El_Salvador");
  array_push($list, "America/Ensenada");
  array_push($list, "America/Fortaleza");
  array_push($list, "America/Glace_Bay");
  array_push($list, "America/Godthab");
  array_push($list, "America/Goose_Bay");
  array_push($list, "America/Grand_Turk");
  array_push($list, "America/Grenada");
  array_push($list, "America/Guadeloupe");
  array_push($list, "America/Guatemala");
  array_push($list, "America/Guayaquil");
  array_push($list, "America/Guyana");
  array_push($list, "America/Halifax");
  array_push($list, "America/Havana");
  array_push($list, "America/Hermosillo");
  array_push($list, "America/Indiana/Indianapolis");
  array_push($list, "America/Indiana/Knox");
  array_push($list, "America/Indiana/Marengo");
  array_push($list, "America/Indiana/Vevay");
  array_push($list, "America/Indianapolis");
  array_push($list, "America/Inuvik");
  array_push($list, "America/Iqaluit");
  array_push($list, "America/Jamaica");
  array_push($list, "America/Jujuy");
  array_push($list, "America/Juneau");
  array_push($list, "America/Kentucky/Louisville");
  array_push($list, "America/Kentucky/Monticello");
  array_push($list, "America/La_Paz");
  array_push($list, "America/Lima");
  array_push($list, "America/Los_Angeles");
  array_push($list, "America/Louisville");
  array_push($list, "America/Maceio");
  array_push($list, "America/Managua");
  array_push($list, "America/Manaus");
  array_push($list, "America/Martinique");
  array_push($list, "America/Mazatlan");
  array_push($list, "America/Mendoza");
  array_push($list, "America/Menominee");
  array_push($list, "America/Merida");
  array_push($list, "America/Mexico_City");
  array_push($list, "America/Miquelon");
  array_push($list, "America/Monterrey");
  array_push($list, "America/Montevideo");
  array_push($list, "America/Montreal");
  array_push($list, "America/Montserrat");
  array_push($list, "America/Nassau");
  array_push($list, "America/New_York");
  array_push($list, "America/Nipigon");
  array_push($list, "America/Nome");
  array_push($list, "America/Noronha");
  array_push($list, "America/Panama");
  array_push($list, "America/Pangnirtung");
  array_push($list, "America/Paramaribo");
  array_push($list, "America/Phoenix");
  array_push($list, "America/Port-au-Prince");
  array_push($list, "America/Port_of_Spain");
  array_push($list, "America/Porto_Acre");
  array_push($list, "America/Porto_Velho");
  array_push($list, "America/Puerto_Rico");
  array_push($list, "America/Rainy_River");
  array_push($list, "America/Rankin_Inlet");
  array_push($list, "America/Regina");
  array_push($list, "America/Rosario");
  array_push($list, "America/Santiago");
  array_push($list, "America/Santo_Domingo");
  array_push($list, "America/Sao_Paulo");
  array_push($list, "America/Scoresbysund");
  array_push($list, "America/Shiprock");
  array_push($list, "America/St_Johns");
  array_push($list, "America/St_Kitts");
  array_push($list, "America/St_Lucia");
  array_push($list, "America/St_Thomas");
  array_push($list, "America/St_Vincent");
  array_push($list, "America/Swift_Current");
  array_push($list, "America/Tegucigalpa");
  array_push($list, "America/Thule");
  array_push($list, "America/Thunder_Bay");
  array_push($list, "America/Tijuana");
  array_push($list, "America/Tortola");
  array_push($list, "America/Vancouver");
  array_push($list, "America/Whitehorse");
  array_push($list, "America/Winnipeg");
  array_push($list, "America/Yakutat");
  array_push($list, "America/Yellowknife");

  array_push($list, "Antarctica/Casey");
  array_push($list, "Antarctica/Davis");
  array_push($list, "Antarctica/DumontDUrville");
  array_push($list, "Antarctica/Mawson");
  array_push($list, "Antarctica/McMurdo");
  array_push($list, "Antarctica/Palmer");
  array_push($list, "Antarctica/South_Pole");
  array_push($list, "Antarctica/Syowa");

  array_push($list, "Arctic/Longyearbyen");

  array_push($list, "Asia/Aden");
  array_push($list, "Asia/Almaty");
  array_push($list, "Asia/Amman");
  array_push($list, "Asia/Anadyr");
  array_push($list, "Asia/Aqtau");
  array_push($list, "Asia/Aqtobe");
  array_push($list, "Asia/Ashgabat");
  array_push($list, "Asia/Ashkhabad");
  array_push($list, "Asia/Baghdad");
  array_push($list, "Asia/Bahrain");
  array_push($list, "Asia/Baku");
  array_push($list, "Asia/Bangkok");
  array_push($list, "Asia/Beirut");
  array_push($list, "Asia/Bishkek");
  array_push($list, "Asia/Brunei");
  array_push($list, "Asia/Calcutta");
  array_push($list, "Asia/Chungking");
  array_push($list, "Asia/Colombo");
  array_push($list, "Asia/Dacca");
  array_push($list, "Asia/Damascus");
  array_push($list, "Asia/Dili");
  array_push($list, "Asia/Dubai");
  array_push($list, "Asia/Dushanbe");
  array_push($list, "Asia/Gaza");
  array_push($list, "Asia/Harbin");
  array_push($list, "Asia/Hong_Kong");
  array_push($list, "Asia/Hovd");
  array_push($list, "Asia/Irkutsk");
  array_push($list, "Asia/Istanbul");
  array_push($list, "Asia/Jakarta");
  array_push($list, "Asia/Jayapura");
  array_push($list, "Asia/Jerusalem");
  array_push($list, "Asia/Kabul");
  array_push($list, "Asia/Kamchatka");
  array_push($list, "Asia/Karachi");
  array_push($list, "Asia/Kashgar");
  array_push($list, "Asia/Katmandu");
  array_push($list, "Asia/Krasnoyarsk");
  array_push($list, "Asia/Kuala_Lumpur");
  array_push($list, "Asia/Kuching");
  array_push($list, "Asia/Kuwait");
  array_push($list, "Asia/Macao");
  array_push($list, "Asia/Magadan");
  array_push($list, "Asia/Manila");
  array_push($list, "Asia/Muscat");
  array_push($list, "Asia/Nicosia");
  array_push($list, "Asia/Novosibirsk");
  array_push($list, "Asia/Omsk");
  array_push($list, "Asia/Phnom_Penh");
  array_push($list, "Asia/Pyongyang");
  array_push($list, "Asia/Qatar");
  array_push($list, "Asia/Rangoon");
  array_push($list, "Asia/Riyadh");
  array_push($list, "Asia/Saigon");
  array_push($list, "Asia/Samarkand");
  array_push($list, "Asia/Seoul");
  array_push($list, "Asia/Shanghai");
  array_push($list, "Asia/Singapore");
  array_push($list, "Asia/Taipei");
  array_push($list, "Asia/Tashkent");
  array_push($list, "Asia/Tbilisi");
  array_push($list, "Asia/Tehran");
  array_push($list, "Asia/Thimbu");
  array_push($list, "Asia/Thimphu");
  array_push($list, "Asia/Tokyo");
  array_push($list, "Asia/Ujung_Pandang");
  array_push($list, "Asia/Ulaanbaatar");
  array_push($list, "Asia/Ulan_Bator");
  array_push($list, "Asia/Urumqi");
  array_push($list, "Asia/Vientiane");
  array_push($list, "Asia/Vladivostok");
  array_push($list, "Asia/Yakutsk");
  array_push($list, "Asia/Yekaterinburg");
  array_push($list, "Asia/Yerevan");

  array_push($list, "Atlantic/Azores");
  array_push($list, "Atlantic/Bermuda");
  array_push($list, "Atlantic/Canary");
  array_push($list, "Atlantic/Cape_Verde");
  array_push($list, "Atlantic/Faeroe");
  array_push($list, "Atlantic/Jan_Mayen");
  array_push($list, "Atlantic/Madeira");
  array_push($list, "Atlantic/Reykjavik");
  array_push($list, "Atlantic/South_Georgia");
  array_push($list, "Atlantic/St_Helena");
  array_push($list, "Atlantic/Stanley");

  array_push($list, "Australia/Adelaide");
  array_push($list, "Australia/Brisbane");
  array_push($list, "Australia/Broken_Hill");
  array_push($list, "Australia/Darwin");
  array_push($list, "Australia/Hobart");
  array_push($list, "Australia/Lindeman");
  array_push($list, "Australia/Lord_Howe");
  array_push($list, "Australia/Melbourne");
  array_push($list, "Australia/Perth");
  array_push($list, "Australia/Sydney");

  array_push($list, "Europe/Amsterdam");
  array_push($list, "Europe/Andorra");
  array_push($list, "Europe/Athens");
  array_push($list, "Europe/Belfast");
  array_push($list, "Europe/Belgrade");
  array_push($list, "Europe/Berlin");
  array_push($list, "Europe/Bratislava");
  array_push($list, "Europe/Brussels");
  array_push($list, "Europe/Bucharest");
  array_push($list, "Europe/Budapest");
  array_push($list, "Europe/Chisinau");
  array_push($list, "Europe/Copenhagen");
  array_push($list, "Europe/Dublin");
  array_push($list, "Europe/Gibraltar");
  array_push($list, "Europe/Helsinki");
  array_push($list, "Europe/Istanbul");
  array_push($list, "Europe/Kaliningrad");
  array_push($list, "Europe/Kiev");
  array_push($list, "Europe/Lisbon");
  array_push($list, "Europe/Ljubljana");
  array_push($list, "Europe/London");
  array_push($list, "Europe/Luxembourg");
  array_push($list, "Europe/Madrid");
  array_push($list, "Europe/Malta");
  array_push($list, "Europe/Minsk");
  array_push($list, "Europe/Monaco");
  array_push($list, "Europe/Moscow");
  array_push($list, "Europe/Nicosia");
  array_push($list, "Europe/Oslo");
  array_push($list, "Europe/Paris");
  array_push($list, "Europe/Prague");
  array_push($list, "Europe/Riga");
  array_push($list, "Europe/Rome");
  array_push($list, "Europe/Samara");
  array_push($list, "Europe/San_Marino");
  array_push($list, "Europe/Sarajevo");
  array_push($list, "Europe/Simferopol");
  array_push($list, "Europe/Skopje");
  array_push($list, "Europe/Sofia");
  array_push($list, "Europe/Stockholm");
  array_push($list, "Europe/Tallinn");
  array_push($list, "Europe/Tirane");
  array_push($list, "Europe/Tiraspol");
  array_push($list, "Europe/Uzhgorod");
  array_push($list, "Europe/Vaduz");
  array_push($list, "Europe/Vatican");
  array_push($list, "Europe/Vienna");
  array_push($list, "Europe/Vilnius");
  array_push($list, "Europe/Warsaw");
  array_push($list, "Europe/Zagreb");
  array_push($list, "Europe/Zaporozhye");
  array_push($list, "Europe/Zurich");

  array_push($list, "Indian/Antananarivo");
  array_push($list, "Indian/Chagos");
  array_push($list, "Indian/Christmas");
  array_push($list, "Indian/Cocos");
  array_push($list, "Indian/Comoro");
  array_push($list, "Indian/Kerguelen");
  array_push($list, "Indian/Mahe");
  array_push($list, "Indian/Maldives");
  array_push($list, "Indian/Mauritius");
  array_push($list, "Indian/Mayotte");
  array_push($list, "Indian/Reunion");

  array_push($list, "Pacific/Apia");
  array_push($list, "Pacific/Auckland");
  array_push($list, "Pacific/Chatham");
  array_push($list, "Pacific/Easter");
  array_push($list, "Pacific/Efate");
  array_push($list, "Pacific/Enderbury");
  array_push($list, "Pacific/Fakaofo");
  array_push($list, "Pacific/Fiji");
  array_push($list, "Pacific/Funafuti");
  array_push($list, "Pacific/Galapagos");
  array_push($list, "Pacific/Gambier");
  array_push($list, "Pacific/Guadalcanal");
  array_push($list, "Pacific/Guam");
  array_push($list, "Pacific/Honolulu");
  array_push($list, "Pacific/Johnston");
  array_push($list, "Pacific/Kiritimati");
  array_push($list, "Pacific/Kosrae");
  array_push($list, "Pacific/Kwajalein");
  array_push($list, "Pacific/Majuro");
  array_push($list, "Pacific/Marquesas");
  array_push($list, "Pacific/Midway");
  array_push($list, "Pacific/Nauru");
  array_push($list, "Pacific/Niue");
  array_push($list, "Pacific/Norfolk");
  array_push($list, "Pacific/Noumea");
  array_push($list, "Pacific/Pago_Pago");
  array_push($list, "Pacific/Palau");
  array_push($list, "Pacific/Pitcairn");
  array_push($list, "Pacific/Ponape");
  array_push($list, "Pacific/Port_Moresby");
  array_push($list, "Pacific/Rarotonga");
  array_push($list, "Pacific/Saipan");
  array_push($list, "Pacific/Tahiti");
  array_push($list, "Pacific/Tarawa");
  array_push($list, "Pacific/Tongatapu");
  array_push($list, "Pacific/Truk");
  array_push($list, "Pacific/Wake");
  array_push($list, "Pacific/Wallis");
  array_push($list, "Pacific/Yap");

  return $list;
}

/* Return the caption for a scope */
function scope_name($scope)
{

  if ($scope == PREF_GLOBAL) {
    return "Global";
  } else if ($scope == PREF_LONGTERM) {
   return "Long Term";
  } else if ($scope == PREF_SHORTTERM) {
    return "Short Term";
  }

  return "!!Unknown Scope!!";

}

/* Display the default preference */
function display_default_preference($module, $name, $scope)
{

  global $amp_prefs;

  $value = get_default_preference($module, $name, $scope);

  $pref = $amp_prefs["$module-$name"];

  if ( $pref->type == PREF_TYPE_INPUT || $pref->type == PREF_TYPE_TIMEZONE 
       || $pref->type == PREF_TYPE_MESH || $pref->type == PREF_TYPE_SORT ) {
      return $value;
  } else if ( $pref->type == PREF_TYPE_BOOL ) {
    if ( $value == PREF_TRUE ) {
      return "True";
    } else {
      return "False";
    }
  } else {
    return "Unknown";
  }

}

/*
 * This function initialises the preference array for the current session.
 * If there is a user logged in their preferences are retrieved from the
 * database, otherwise the defaults specified for each preference are used.
 */
function init_preferences()
{

  global $amp_prefs, $amp_pref;

  /* Check user is logged in */
  $username = have_login();
  if ( ! $username ) {
    return;
  }

  /* Get database connection */
  $db = dbconnect($GLOBALS["webusersDB"]);
  if ( ! $db ) {
    log_error("Could not connect to webusers database. Please try again " .
              "later.");
    flush();
    return;
  }

  $username = explode("|", $username);
  $username = $username[0];

  /* Retrieve preferences for this user */
  $query = "SELECT * FROM amp_prefs WHERE username='$username'";
  $result = queryAndCheckDB($db, $query);

  dbclose($db);

  $rows = $result["rows"];
  for ( $i=0; $i<$rows; $i++) {

    $data = $result["results"][$i];
    /* Find this preference in the list of loaded preferences */
    $name = $data["1"] . "-" . $data["2"];
    if ( ! array_key_exists($name, $amp_prefs) ) {
      /* Can't do anything if we don't know about the pref */
      continue;
    }
    /* Setup the preferences array */
    if (!array_key_exists($name, $amp_pref))
    $amp_pref[$name] = array();
    /* Store the preference in the user array*/
    $scope = $data["3"];
    $amp_pref[$name][$scope] = $data["4"];
  }

}

/*
 * Return an array of all of the preference types for the specified module
 *
 */
function get_module_preferences($module)
{

  global $amp_prefs;

  $rpref = array();

  foreach ( $amp_prefs as $fullname=>$data ) {
    if ( $data->module == $module ) {
      $rpref[$data->name] = $data;
    }
  }

  return $rpref;

}

/* Return an array of the scopes that are available for the specified
 * set of preferences
 */
function get_scopes($prefs)
{

  $scopes = array();

  foreach ( $prefs as $fullname=>$data ) {
    $scopes = array_merge($scopes, $data->scope);
  }

  return array_unique($scopes);

}

/*
 * Return an array of all of the available preference modules
 *
 */
function get_modules()
{

  global $amp_pref_modules;

  return $amp_pref_modules;

}

/*
 * Returns the current value of the preference, Taking into account defaults
 * and the users stored settings.
 */
function get_preference($module, $name, $scope)
{

  global $amp_pref;

  $pname = "$module-$name";

  //echo "<!-- Finding Pref $module $name $scope -->";
  if ( ! array_key_exists($pname, $amp_pref) ) {
    /* No user setting for this preference, use default */
    return get_default_preference($module, $name, $scope);
  } else {
    /* User has a setting for this preference, check scope */
    if ( ! array_key_exists($scope, $amp_pref[$pname]) ) {
      return get_default_preference($module, $name, $scope);
    } else {
      /* User has a setting for this preference and scope, return it */
      return $amp_pref[$pname][$scope];
    }
  }

  return NULL;

}

/*
 * Returns the default value for the specified preference and scope
 */
function get_default_preference($module, $name, $scope)
{

  global $amp_prefs;

  $pname = "$module-$name";

  //echo "<!-- Finding Default Pref $module $name $scope -->";
  if ( ! array_key_exists($pname, $amp_prefs) ) {
    /* Preference doesn't exist full stop! */
    return NULL;
  } else {
    if ( ! array_key_exists($scope, $amp_prefs[$pname]->defaults) ) {
      /* No default for the requested scope */
      return NULL;
    } else {
      return $amp_prefs[$pname]->defaults[$scope];
    }
  }

}

/*
 * Returns the value of the preference from the database
 */
function get_saved_preference($module, $name, $scope)
{

  global $amp_prefs;

  /* Check user is logged in */
  $username = have_login();
  if (!$username) {
    return;
  }

  $username = explode("|", $username);
  $username = $username[0];

  /* Get database connection */
  $db = dbconnect($GLOBALS["webusersDB"]);
  if (!$db) {
    log_error("Could not connect to webusers database. " .
      "Please try again later.");
    return;
  }

  $sql = "SELECT value FROM amp_prefs WHERE username='$username' AND module='" .
    "$module' AND pref='$name' AND scope='$scope'";
  $res = queryAndCheckDB($db, $sql);
  if ( $res["rows"] > 0 ) {
    $value = $res["results"][0]["0"];
    return $value;
  }

  return NULL;

}

/*
 * Sets the value of the preference in the database
 */
function set_saved_preference($module, $name, $scope, $value)
{

  /* Check user is logged in */
  $username = have_login();
  if (!$username) {
    return;
  }

  $username = explode("|", $username);
  $username = $username[0];

  /* Don't save if same as default */
  $default = get_default_preference($module, $name, $scope);
  if ( $default == $value ) {
    return;
  }

  clear_saved_preference($module, $name, $scope);

  /* Get database connection */
  $db = dbconnect($GLOBALS["webusersDB"]);
  if ( ! $db ) {
    log_error("Could not connect to webusers database. " .
      "Please try again later.");
    return FALSE;
  }

  $sql = "INSERT INTO amp_prefs (username, module, pref, scope, value) " .
    "VALUES ('$username', '$module', '$name', '$scope', '$value')";
  $res = queryAndCheckDB($db, $sql);

  return TRUE;

}

/*
 * Clears the value of the preference in the database
 */
function clear_saved_preference($module, $name, $scope)
{

  /* Check user is logged in */
  $username = have_login();
  if (!$username) {
    return;
  }

  $username = explode("|", $username);
  $username = $username[0];

  /* Get database connection */
  $db = dbconnect($GLOBALS["webusersDB"]);
  if ( ! $db ) {
    log_error("Could not connect to webusers database. " .
              "Please try again later.");
    return FALSE;
  }

  $sql = "DELETE FROM amp_prefs WHERE username='$username' AND module=" .
    "'$module' AND pref='$name' AND scope='$scope'";
  $res = queryAndCheckDB($db, $sql);

  return TRUE;

}

/*
 * Clears all the saved preferences for the specified module
 */
function clear_saved_preferences($module)
{

  /* Check user is logged in */
  $username = have_login();
  if (!$username) {
    return;
  }
  $username = explode("|", $username);
  $username = $username[0];


  /* Get database connection */
  $db = dbconnect($GLOBALS["webusersDB"]);
  if (!$db) {
    log_error("Could not connect to webusers database. " .
              "Please try again later.");
    return FALSE;
  }

  $sql = "DELETE FROM amp_prefs WHERE username='$username' AND module='$module'";
  $res = queryAndCheckDB($db, $sql);
  dbclose($db);

  return TRUE;

}

  // Emacs control
  // Local Variables:
  // eval: (c++-mode)
// vim:set sw=2 ts=2 sts=2 et:
?>
