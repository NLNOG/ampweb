<?php
/*
 * AMP Data Display Interface
 *
 * Raw Data Extraction
 *
 * Author:      Matt Brown <matt@crc.net.nz.
 * Version:     $Id: download_raw.php 1712 2010-03-10 22:19:05Z brendonj $
 *
 * Allows the user to select amplets and the appropriate test and time period
 *
 */
require("amplib.php");

$months = array("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug",
  "Sep", "Oct", "Nov", "Dec");

initialise();

if (!isset($_REQUEST["dstep"])) {
  $dstep=1;
} else {
  $dstep=$_REQUEST["dstep"];
}

function make_option($value, $display, $svalue)
{
  echo '<option value="'.htmlspecialchars($value).'"';
  if ($value == $svalue) {
    echo " selected";
  }
  echo '>'.htmlspecialchars($display).'</option>';
}

function make_amplet_combo($name, $value, $src)
{

  $srcs = ampSiteList($src);
  sort($srcs->srcNames);

  echo '<select name="'.htmlspecialchars($name).'" value="'.htmlspecialchars($value).'">'."\n";
  for ($srcIndex = 0; $srcIndex<$srcs->count; ++$srcIndex) {
    $srcName = $srcs->srcNames[$srcIndex];
    make_option($srcName, $srcName, $value);
  }
  echo "</select>\n";

}

function make_avail_test_combo($name, $value, $src, $dst)
{

  global $test_names, $raw_data_funcs;

  echo '<select name="'.htmlspecialchars($name).'" value="'.htmlspecialchars($value).'">'."\n";

  $tests = amptestlist($src, $dst);

  foreach ($tests->types as $idx=>$test) {
    $func = $raw_data_funcs[$test];
    if (!function_exists($func)) {
      continue;
    }
    $name = $test_names[$test];
    make_option($test, "$name Test", $value);
  }
  echo "</select>\n";

}

function have_subtypes($src, $dst, $testType)
{

  global $subTypes;

  $t = ampSubtypeList($testType, $src, $dst);

  $subTypes = $t->subtypes;

  if ( count($subTypes)>0 ) {
    return 1;
  } else {
    return 0;
  }

}

function make_test_subtype_combo($name, $value, $src, $dst, $testType)
{

  global $subTypes, $subtype_name_funcs;

  echo '<select name="'.htmlspecialchars($name).'" value="'.htmlspecialchars($value).'">'."\n";

  sort($subTypes);
  foreach ( $subTypes as $idx=>$st ) {
    $func = $subtype_name_funcs[$testType];
    if ( function_exists($func) ) {
      $name = $func($st);
    } else {
      $name = $st;
    }
    make_option($st, $name, $value);
  }

  echo "</select>\n";

}

function make_date_select($name, $value,$js="")
{

  if ( $value==-2 ) {
    # Default to 1 day ago
    $c = localtime(time()-86400, 1);
    $secs = 0;
  } else if ( $value==-1 ) {
    # Default to now
    $c = localtime(time(), 1);
    $secs = 0;
  } else {
    $c = localtime($value, 1);
    $secs = $c["tm_sec"];
  }

  if ( strlen($js)>0 ) {
    $yjs = " onchange=\"update_combo('${name}_year', '${js}_year');\"";
    $mjs = " onchange=\"update_combo('${name}_mon', '${js}_mon');\"";
    $djs = " onchange=\"update_combo('${name}_day', '${js}_day');\"";
    $hjs = " onchange=\"update_combo_inc('${name}_hour', '${js}_hour');\"";
    $minjs = " onchange=\"update_combo('${name}_min', '${js}_min');\"";
    $sjs = " onchange=\"update_combo('${name}_sec', '${js}_sec');\"";

  } else {
    $yjs = "";
    $mjs = "";
    $djs = "";
    $hjs = "";
    $minjs = "";
    $sjs = "";
  }

  make_year_select("${name}_year", $c["tm_year"]+1900, $yjs);
  echo "-&nbsp;";
  make_month_select("${name}_mon", $c["tm_mon"]+1, $mjs);
  echo "-&nbsp;";
  make_numeric_select("${name}_day", $c["tm_mday"], 1, 31, $djs);
  echo "&nbsp;&nbsp;&nbsp;";
  make_numeric_select("${name}_hour", $c["tm_hour"], 0, 23, $hjs);
  echo ":&nbsp;";
  make_numeric_select("${name}_min", $c["tm_min"], 0, 59, $minjs);
  echo ":&nbsp;";
  make_numeric_select("${name}_sec", $secs, 0, 59, $sjs);

}

function make_year_select($name, $value,$js="")
{

  $c = localtime(time(),1);
  $year = $c["tm_year"]+1900;
  $syear = $year-10;
  echo '<select name="'.htmlspecialchars($name).'" id="'.htmlspecialchars($name).'" value="'.htmlspecialchars($value).'"$js>'."\n";
  for ($i=$year; $i>=$syear; $i--) {
    make_option($i, $i, $value);
  }
  echo "</select>\n";

}

function make_month_select($name, $value,$js="")
{
  global $months;

  echo '<select name="'.htmlspecialchars($name).'" id="'.htmlspecialchars($name).'" value="'.htmlspecialchars($value).'"$js>'."\n";
  for ($i=0; $i<12; $i++) {
    make_option($i+1, $months[$i], $value);
  }
  echo "</select>\n";

}

function make_numeric_select($name, $value, $min, $max,$js="")
{

  echo '<select name="'.htmlspecialchars($name).'" id="'.htmlspecialchars($name).'" value="'.htmlspecialchars($value).'"$js>'."\n";
  for ($i=$min; $i<=$max ; $i++) {
    make_option($i, sprintf("%02d", $i), $value);
  }
  echo "</select>\n";

}

templateTop();            //Load any template HTML
?>
<script language="javascript">
function update_combo(src, dst)
{
  combos = document.getElementById(src);
  combod = document.getElementById(dst);
  combod.value = combos.value;
}
function update_combo_inc(src, dst)
{
  combos = document.getElementById(src);
  combod = document.getElementById(dst);
  combod.value = parseInt(combos.value)+1;
}
</script>
<?
print "<h2 class=\"graph\">$system_name - Raw Data Interface</h2>";
?>
<br>
Please select the parameters of the dataset that you would like to download
below.<br>
<br>
The data will be returned to you as a Comma Seperated Value (CSV) file.<br>
<br>
<?php
if ($dstep==1) {
?>
<form action="download_raw.php" method="post">
<table cellpadding=2 cellspacing=2 border=0>
<tr>
<th>Source amplet:</th>
<td><? make_amplet_combo("src", "", ""); ?></td>
</tr>
</table>
<input type="hidden" name="dstep" value="2">
<input type="submit" value="Next Step &gt;">
</form>
<?
} else if ($dstep==2) {
?>
<form action="download_raw.php" method="post">
<table cellpadding=2 cellspacing=2 border=0>
<tr>
<th>Source amplet:</th>
<td>
<?
  $src = $_POST["src"];
  echo htmlspecialchars($_POST["src"]);
  echo '<input type="hidden" name="src" value="'.htmlspecialchars($src).'">';
?>
</td>
</tr>
<tr>
<th>Destination amplet:</th>
<td><? make_amplet_combo("dst", "", $src); ?></td>
</tr>
</tr>
</table>
<input type="hidden" name="dstep" value="3">
<input type="submit" value="Next Step &gt;">
</form>
<?
} else if ($dstep==3) {
?>
<form action="download_raw.php" method="post">
<table cellpadding=2 cellspacing=2 border=0>
<tr>
<th>Source amplet:</th>
<td>
<?
  $src = $_POST["src"];
  echo htmlspecialchars($_POST["src"]);
  echo '<input type="hidden" name="src" value="'.htmlspecialchars($src).'">';
?>
</td>
</tr>
<tr>
<th>Destination amplet:</th>
<td>
<?
  $dst = $_POST["dst"];
  echo htmlspecialchars($_POST["dst"]);
  echo '<input type="hidden" name="dst" value="'.htmlspecialchars($dst).'">';
?>
</td>
</tr>
<tr>
<th>Test:</th>
<td><? make_avail_test_combo("testType", "", $src, $dst); ?></td>
</tr>
<tr>
</table>
<input type="hidden" name="dstep" value="4">
<input type="submit" value="Next Step &gt;">
</form>
<?
} else if ($dstep==4) {
?>
<form action="extract_raw.php" method="post">
<table cellpadding=2 cellspacing=2 border=0>
<tr>
<th>Source amplet:</th>
<td>
<?
  $src = $_POST["src"];
  echo htmlspecialchars($_POST["src"]);
  echo '<input type="hidden" name="src" value="'.htmlspecialchars($src).'">';
?>
</td>
</tr>
<tr>
<th>Destination amplet:</th>
<td>
<?
  $testType = $_POST["dst"];
  echo htmlspecialchars($_POST["dst"]);
  echo '<input type="hidden" name="dst" value="'.htmlspecialchars($dst).'">';
?>
</td>
</tr>
<tr>
<th>Test:</th>
<td>
<?
  $testType = $_POST["testType"];
  echo htmlspecialchars($test_names[$testType]) . " Test";
  echo '<input type="hidden" name="testType" value="'.htmlspecialchars($testType).'">';
?>
</td>
</tr>
<?
if (have_subtypes($src, $dst, $testType)) {
?>
<tr>
<th>Test Sub Type:</th>
<td><?
make_test_subtype_combo("testSubType", "", $src, $dst, $testType);
?></td>
</tr>
<?
}
?>
<tr>
<th>Period Start:</th>
<td><? make_date_select("start", -2, "end"); ?></td>
</tr>
<tr>
<th>Period End:</th>
<td><? make_date_select("end", -1); ?></td>
</tr>
</table>
<input type="submit" value="Download Raw Datafile &gt;">
</form>
<br>
<a href="download_raw.php">&lt; Select new amplet pair</a><br>
<br>
<?

}

endPage();

// vim:set sw=2 ts=2 sts=2 et:
?>
