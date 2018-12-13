<?php

// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// Security check
if (!defined('G_DNSTOOL_ENTRY_POINT'))
    die("Not a valid entry point");

require_once("psf/psf.php");
require_once("audit.php");
require_once("fatal.php");
require_once("config.php");

$g_login_failed = false;
$g_logged_in = false;
$g_login_failure_reason = "Invalid username or password";

function RefreshSession()
{
    global $g_session_timeout;
    session_start();
    if (isset($_SESSION["time"]))
    {
        if ((time() - $_SESSION["time"]) > $g_session_timeout)
        {
            // This session timed out
            session_unset();
        }
    }
    $_SESSION["time"] = time();
}

function GetLoginInfo()
{
    return '<div class="login_info"><span class="glyphicon glyphicon-user"></span>' . $_SESSION["user"] . ' <a href="?logout">logout</a></div>';
}

function ProcessLogin()
{
    global $g_auth, $g_auth_ldap_url, $g_login_failure_reason, $g_login_failed, $g_auth_allowed_users;
    
    // We support LDAP at this moment only
    if ($g_auth != "ldap")
        Error("Unsupported authentication mechanism");

    // Check if we have the credentials
    if (!isset($_POST["loginUsername"]) || !isset($_POST["loginPassword"]))
        Error("No credentials provided");
    
    $ldap = ldap_connect($g_auth_ldap_url);
    if ($bind = ldap_bind($ldap, $_POST["loginUsername"], $_POST["loginPassword"]))
    {
        // Login OK
        if ($g_auth_allowed_users !== NULL)
        {
            // Check if this user is allowed to login
            if (!in_array($_POST["loginUsername"], $g_auth_allowed_users))
            {
                $g_login_failure_reason = "This user is not allowed to login to this tool (username not present in config.php)";
                $g_login_failed = true;
                $_SESSION["logged_in"] = false;
                return;
            }
        }
        $_SESSION["user"] = $_POST["loginUsername"];
        $_SESSION["logged_in"] = true;
        $g_logged_in = true;
    } else
    {
        // Invalid user / pw
        $g_login_failed = true;
        $_SESSION["logged_in"] = false;
    }
}

function RequireLogin()
{
    global $g_auth, $g_logged_in;
    if ($g_logged_in)
        return false;

    if ($g_auth === NULL)
        return false;
    
    // We support LDAP at this moment only
    if ($g_auth != "ldap")
        Error("Unsupported authentication mechanism");
    
    // Check if we have the credentials
    if (!isset( $_SESSION["user"] ) || !isset( $_SESSION["logged_in"] ))
        return true;
        
    if ($_SESSION["logged_in"])
    {
        $g_logged_in = true;
        return false;
    }
    
    return true;
}

function GetLogin()
{
    $login_form = new LoginForm();
    return $login_form;
}
