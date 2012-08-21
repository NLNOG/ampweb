<?php
/*
 * AMP Data Display Interface
 *
 * Add Network Event
 *
 * Author:    Jeremy Kallstrom <jkallstrom@nlanr.net>
 * Version:   $Id: add_event.php 810 2005-11-08 18:28:14Z jkallstrom $
 *
 * Allows the user to add network events to the database
 *
 */
require("amplib.php");

initialise();

templateTop();

$timeZone = get_preference(PREF_GLOBAL, GP_TIMEZONE, PREF_GLOBAL);

if ( isset($_POST["do"]) ) {
  $source = $_POST["source"];
  $dest = $_POST["dest"];

  $oldTZ = getenv("TZ");
  putenv("TZ=$timeZone");

  list($hour,$minute) = split(":", $_POST["starttime"]);
  $start = mktime($hour, $minute, 0, $_POST["startmonth"], $_POST["startday"],
                  $_POST["startyear"]);

  list($hour,$minute) = split(":", $_POST["endtime"]);
  $end = mktime($hour, $minute, 0, $_POST["endmonth"], $_POST["endday"],
                  $_POST["endyear"]);

  putenv("TZ=$oldTZ");

  $type = $_POST["type"];
  $description = $_POST["description"];

  if ( $start != -1 && $end != -1 && type != "") {
    $result = addevent($start, $end, $type, $description, $source, $dest);
    if ( $result == 1 ) {
      print "<h2>Event added</h2>";
    } else if ( $result == 10 ) {
      print "<h2>This event is already in the database</h2>";
    }
  } else {
    print "<h2>No event entered</h2>";
  }

}

print "<h2 class=graph>$system_name - Add Event</h2>";

?>

<br />
Please enter event information<Br /><br />

<form action="add_event.php" method="post">
<input type="hidden" name="do" value="yes" />
<table width="50" cellpadding=2 cellspacing=2 border=0>
<tr>
<td class="cellheading">Source Amplet:</td>
<td><input name="source" type=text /></td>
</tr>
<tr>
<td class="cellheading">Destination Amplet:</td>
<td><input name="dest" type=text /></td>
</tr>
<tr>
<td class="cellheading">Start time:</td>
<td>
  <select name="startmonth">
    <option value="0">Month</option>
    <option value="1">January</option>
    <option value="2">February</option>
    <option value="3">March</option>
    <option value="4">April</option>
    <option value="5">May</option>
    <option value="6">June</option>
    <option value="7">July</option>
    <option value="8">August</option>
    <option value="9">September</option>
    <option value="10">October</option>
    <option value="11">November</option>
    <option value="12">December</option>
  </select>
  <input type=text size=3 name="startday" value="Day" />
  <input type=text size=4 name="startyear" value="Year" />
  <input type=text size=5 name="starttime" value="Time" />
(ex. January 10 2005 15:00)
</td>
</tr>
<tr>
<td class="cellheading">End time:</td>
<td>
  <select name="endmonth">
    <option value="0">Month</option>
    <option value="1">January</option>
    <option value="2">February</option>
    <option value="3">March</option>
    <option value="4">April</option>
    <option value="5">May</option>
    <option value="6">June</option>
    <option value="7">July</option>
    <option value="8">August</option>
    <option value="9">September</option>
    <option value="10">October</option>
    <option value="11">November</option>
    <option value="12">December</option>
  </select>
  <input type=text size=3 name="endday" value="Day" />
  <input type=text size=4 name="endyear" value="Year" />
  <input type=text size=5 name="endtime" value="Time" />
(ex. January 10 2005 20:00)
</td>
</tr>
<tr>
<td class="cellheading">Event Type:</td>
<td><input name="type" type=text /></td>
</tr>
<tr>
<td class="cellheading">Description:</td>
<td><textarea name="description" rows="4" cols="60"></textarea></td>
</tr>
<tr>
</tr>
<td>&nbsp</td>
<td></td>
<tr>
<td><input type=submit value="Add" /></td>
</tr>
</table>

<?

endPage();

// Emacs control
// Local Variables:
// eval: (c++-mode)
?>
