<?php
//require_once("amp_prefs.php");
/* Override Preference Defaults */

// EXAMPLE: Display 3 weeks in weekly view by default
//update_preference_defaults(PREF_GLOBAL, GP_LT_NUM_WEEKS, 
//    array(PREF_GLOBAL=>3))


//override constants for local settings here
update_preference_defaults(PREF_GLOBAL, GP_TIMEZONE, 
    array(PREF_GLOBAL=>"UTC"))
?>
