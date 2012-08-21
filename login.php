<?php
/*
 * AMP Data Display Interface
 *
 * Login Processing
 *
 * Author:      Matt Brown <matt@crc.net.nz.
 * Version:     $Id: login.php 1712 2010-03-10 22:19:05Z brendonj $
 *
 */
require("amplib.php");

/* Setup the AMP system */
initialise();

/* If user already logged in, go to index */
if (have_login()) {
  goPage("index.php");
}

/* Handle posted form */
$err = "";
$pchecked = "";
if (isset($_POST["do"])) {
  $username = $_POST["username"];
  $pass = $_POST["password"];
  if (isset($_POST["persist"]) && $_POST["persist"]=="yes") {
    $persist = TRUE;
    $pchecked = " checked";
  } else {
    $persist = FALSE;
  }
  /* Check if username is free */
  if (process_login($username, $pass, $persist)) {
    /* Redirect to start page */
    goPage("index.php");
  } else {
    $err = "Incorrect username or password!";
  }
}

/* Initialise the HTML */
templateTop();

echo "<h1>$system_name - Login</h1><br>\n";
?>
<div style="text-align:left;">
Please enter your username and password below to access your saved preferences.
<br>
<br>
If you have not yet created an account, you can use the link in the side bar to
the left to create one.
<br><br>
<?php
if (strlen($err)>0) {
  graphError($err);
  echo "<br>";
}
?>
<form action="login.php" method="post">
<input type="hidden" name="do" value="yes">
<table cellpadding=0 cellspacing=2 border=0>
<tr>
<td class="cellheading">Username:</td>
<td><input type="text" name="username" value="<? echo htmlspecialchars($username); ?>"></td>
</tr>
<tr>
<td class="cellheading">Password:</td>
<td><input type="password" name="password" value=""></td>
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
<td colspan=2><input type="submit" value="Login"></td>
</tr>
</table>
</form>
</div>
<?php

/* Finish off the page */
endPage();

// Emacs control
// Local Variables:
// eval: (c++-mode)

// vim:set sw=2 ts=2 sts=2 et:
?>
