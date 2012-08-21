<?php
/*
 * AMP Data Display Interface 
 *
 * Create Account
 *
 * Author:      Matt Brown <matt@crc.net.nz.
 * Version:     $Id: welcome.php 359 2004-12-10 02:38:26Z mglb1 $
 *
 * Welcomes the new user and gives some instructions. 
 *
 */
require("amplib.php");

/* Setup the AMP system */
initialise();

/* If no user logged in, go to index */
if (!have_login()) {
    goPage("index.php");
}

/* Initialise the HTML */
templateTop();

echo "<h2>$system_name - Account Created!</h2><br>\n";
?>
<div style="text-align:left;">
You have successfully created a user account!<br>
<br>
Use the &quot;Edit Your Preferences&quot; link in the menu bar on the 
left to set your default display options.<br>
<br>
<br>

</div>
<?php

/* Finish off the page */
endPage();


?>
