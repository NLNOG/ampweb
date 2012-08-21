<?php
/*
 * AMP Data Display Interface
 *
 * User Account Handling
 *
 * Author:      Matt Brown <matt@crc.net.nz.
 * Version:     $Id: amp_users.php 1712 2010-03-10 22:19:05Z brendonj $
 *
 * This file contains user account management functions
 *
 */

/**** Functions ****/

/*
 * Returns true if the specified username is valid
 *
 */
function valid_username($username, &$message)
{

  /* Get database connection */
  $db = dbconnect($GLOBALS["webusersDB"]);
  if (!$db) {
    log_error("Could n connect to webusers database. Please try " .
      "again later.");
    return FALSE;
  }

  $res = queryAndCheckDB($db, "SELECT count(*) as num FROM " .
    "amp_users WHERE username='$username'");

  if ( $res["results"][0][0] == 1 ) {
    $message = "Username already exists";
    return FALSE;
  }

  if ( strpos($username, "|") ) {
    $message = "Invalid character: '|' in username";
    return FALSE;
  }

  return TRUE;

}

/*
 * Creates the specified user account in the database
 */
function create_username($username, $password)
{

  /* Get database connection */
  $db = dbconnect($GLOBALS["webusersDB"]);
  if ( ! $db ) {
    log_error("Could not connect to webusers database. " .
      "Please try again later.");
    return FALSE;
  }

  $md5pass = md5($password);
  $res = queryAndCheckDB($db, "INSERT INTO amp_users (username, " .
    "password) VALUES ('$username', '$md5pass')");

  return;

}

/*
 * Validate a login and setup session variables
 */
function process_login($username, $password, $persist=FALSE)
{

  /* Get database connection */
  $db = dbconnect($GLOBALS["webusersDB"]);
  if ( ! $db ) {
    log_error("Could not connect to webusers database. " .
      "Please try again later.");
    return FALSE;
  }

  $md5pass = md5($password);
  $sql = "SELECT count(*) as num FROM amp_users WHERE username='" .
    "$username' AND password='$md5pass'";
  $res = queryAndCheckDB($db, $sql);

  $num = $res["results"][0]["0"];

  if ( $num == 0 ) {
    unset($_SESSION["user_uid"]);
    return FALSE;
  }

  $uid = "SELECT uid from amp_users where username='$username'";
  $res = queryAndCheckDB($db, $uid);
  $uid = $res["results"][0][0];

  do_login($username, $md5pass, $uid, $persist);

  return TRUE;

}

function do_login($username, $md5pass, $uid, $persist)
{

  /* Setup session vars */
  $_SESSION["user_uid"] = "$username|$uid";

  if ( $persist ) {
    /* Set a login cookie if requested by user */
    $auth = "$username$md5pass";
    $cookie_logon = sprintf("%032s%s",md5($auth),$username);
    setcookie("amp_persist", $cookie_logon, time()+(3600*24*365), "/");
  } else {
    setcookie("amp_persist", "", 0, "/");
  }

}

/*
 * Check if a valid user is logged in
 */
function have_login()
{

  if ( isset($_SESSION["user_uid"]) && strlen($_SESSION["user_uid"]) > 0 ) {
    return $_SESSION["user_uid"];
  }

  return FALSE;

}

/*
 * Check for a login cookie and log the user in using it
 *
 * The cookie has the format
 * sprintf("%032s%s",md5($username.$md5pass),$username);
 */
function do_cookie_logon() {

  /* Check for a cookie */
  if ( ! isset($_COOKIE['amp_persist']) ) {
      return;
  } else {
      $cookiedata = $_COOKIE["amp_persist"];
  }

  /* Break up the cookie into the various parts */
  $username = substr($cookiedata, 32, strlen($cookiedata)-32);
  $authstr = substr($cookiedata, 0, 32);

  /* If cookie has a username */
  if( $username != "" ) {

    /* Get database connection */
    $db = dbconnect($GLOBALS["webusersDB"]);
    if ( ! $db ) {
      log_error("Could not connect to webusers database. " .
        "Please try again later.");
      return;
    }
    $sql = "SELECT username, password, uid FROM amp_users WHERE " .
      "username='$username'";
    $res = queryAndCheckDB($db, $sql);
    $pass = $res["results"][0]["1"];
    $uid = $res["results"][0]["2"];
    $auth = trim("$username$pass");
    $auth = md5($auth);
    if ( $auth == $authstr ) {
      // Successful Logon
      do_login($username, $pass, $uid, TRUE);
    }
  }

}

// vim:set sw=2 ts=2 sts=2 et:
?>
