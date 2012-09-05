<?php
function local_templateTop(){

    // work out the base url to use
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
    $base   = $scheme . '://' . urlencode($_SERVER['HTTP_HOST']) . dirname($_SERVER['SCRIPT_NAME']) . '/';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
 <base href="<?php echo $base; ?>" />
 <link rel="stylesheet" href="wand.css" type="text/css" />
 <title>AMP Measurements</title>
 <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
 <!--<script type="text/javascript" src="js/jquery-1.8.0.min.js"></script>-->
 <script type="text/javascript" src="js/jquery-1.4.min.js"></script>
</head>
<body>
<div class="header">
<table>
<tr>
<td>
<a href="http://www.wand.net.nz/" class="nohover">
<img src="/wand-logo.png" alt="WAND Network Research Group"/></a>
</td>
<td>
<center>
<a href="https://ring.nlnog.net/" class="nohover">
<img height="60%" src="http://lg.ring.nlnog.net/static/img/nlnog_ring_logo.png" /></a>
</center>
</td>
<td class="title">
<a href="http://www.waikato.co.nz/" class="nohover">
<img src="/uow-coa.png" alt="University of Waikato"/></a>
</td></tr>
</table>
</div>

<table><tr><td style="vertical-align:top;">

<?if (function_exists("draw_first_menu"))
   { ?>
	<? draw_first_menu(); ?>
<? } ?>

<?if (function_exists("draw_view_menu"))
   { ?>
	<? draw_view_menu(); ?>
<? } ?>

<?if (function_exists("draw_last_menu"))
   { ?>
	<? draw_last_menu(); ?>
<? } ?>


</td><td width="100%" valign="top">
<div class="content" style="text-align:center;">
<?php
}

/* Default Page Bottom Function */
function local_templateBottom(){
         
?>
</div>
</td></tr></table>
<div class="footer"></div>
</body>
</html>
<?php
}

?>
