<?php
/*
 * AMP Data Display Interface 
 *
 * Logout Processing
 *
 * Author:      Matt Brown <matt@crc.net.nz.
 * Version:     $Id: logout.php 1712 2010-03-10 22:19:05Z brendonj $
 *
 */
require("amplib.php");

/* Setup the AMP system */
initialise();

/* Logout */
unset($_SESSION["user_uid"]);
session_unset();
setcookie("amp_persist", "", 0, "/");

/* Back to main page */
goPage("index.php");

/* Finish Up */
endPage();

?>
