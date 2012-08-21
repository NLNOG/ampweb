<?php
/*
 * AMP Data Display Interface 
 *
 * Create Account
 *
 * Author:      Matt Brown <matt@crc.net.nz.
 * Version:     $Id: create_account.php 1712 2010-03-10 22:19:05Z brendonj $
 *
 * User interface to creating new accounts for storing preferences 
 *
 */
require("amplib.php");

/* Setup the AMP system */
initialise();

/* Handle posted form */
$err = "";
$pchecked = "";
if ( isset($_POST["do"]) ) {
  
  $username = $_POST["username"];
  $pass1 = $_POST["password1"];
  $pass2 = $_POST["password2"];
  if ( isset($_POST["persist"]) && $_POST["persist"] == "yes" ) {
    $persist = TRUE;
    $pchecked = " checked";
  } else {
    $persist = FALSE;
  }
  /* Check if username is free */
  if ( valid_username($username, $message) ) {
    /* Check passwords match */
    if ( $pass1 == $pass2 ) {
      /* Create account */
      create_username($username, $pass1);
      /* Log the user in */
      process_login($username, $pass1, $persist);
      /* Redirect to welcome page */
      header("Location: welcome.php");
    } else {
      $err = "Passwords do not match!";
    }
  } else {
    $err = $message;
  }
  
}
            
/* Initialise the HTML */
templateTop();

echo "<h1>$system_name - Create User Account</h1><br>\n";
?>
<div style="text-align:left;">
Creating a user account allows you to store preferences that will be remembered
across your visits to the site.<br>
<br>
To create a user account, please enter your desired username and password below.
<br><br>
<?php
if (strlen($err)>0) {
  graphError($err);
  echo "<br>";
}
?>
<form action="create_account.php" method="post">
<input type="hidden" name="do" value="yes">
<table cellpadding=0 cellspacing=2 border=0>
<tr>
<td class="cellheading">Username:</td>
<td><input type="text" name="username" value="<? echo htmlspecialchars($username); ?>"></td>
</tr>
<tr>
<td class="cellheading">Password:</td>
<td><input type="password" name="password1" value=""></td>
</tr>
<tr>
<td class="cellheading">Password (again):</td>
<td><input type="password" name="password2" value=""></td>
</tr>
</tr>
<tr>
<td class="cellheading">Remember Me:</td>
<td><input type="checkbox" name="persist" value="yes"<? echo htmlspecialchars($pchecked); ?>></td>
</tr>
<tr>
<td colspan=2>&nbsp;</td>
</tr>
<tr>
<td colspan=2><input type="submit" value="Create Account"></td>
</tr>
</table>
</form>
</div>
<?php

/* Finish off the page */
endPage();

// vim:set sw=2 ts=2 sts=2 et:
?>
