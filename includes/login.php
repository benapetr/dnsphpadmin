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
require_once("common.php");
require_once("config.php");

$g_login_failed = false;
$g_logged_in = false;
//! Error message that is displayed in case that login fails
$g_login_failure_reason = "Invalid username or password";

function RefreshSession()
{
    global $g_session_timeout, $g_auth_session_name, $g_auth_roles_map;
    session_name($g_auth_session_name);
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
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] && isset($_SESSION['user']) && isset($_SESSION['groups']))
    {
        // This user is logged in - we cached group list within session so that we don't need to query LDAP every single time
        $g_auth_roles_map[$_SESSION['user']] = $_SESSION['groups'];
    }
}

function GetLoginInfo()
{
    global $g_auth_roles_map;
    $role_info = '';
    if ($g_auth_roles_map !== NULL && array_key_exists($_SESSION['user'], $g_auth_roles_map))
    {
        $role_info = ' (' . psf_string_auto_trim(implode (', ', $g_auth_roles_map[$_SESSION['user']]), 80, '...') . ')';
    }
    return '<div class="login_info"><span class="glyphicon glyphicon-user"></span>' . $_SESSION["user"] . $role_info . ' <a href="?logout">logout</a></div>';
}

function ProcessLogin_Error($reason)
{
    global $g_login_failure_reason, $g_login_failed;
    $extra = '';
    if (isset($_POST["loginUsername"]))
       $extra = 'username=' . $_POST["loginUsername"] . ' ';
    $g_login_failed = true;
    $g_login_failure_reason = $reason;
    $_SESSION['logged_in'] = false;
    WriteToAuditFile('login_fail', $extra . 'reason=' . $reason);
}

function ProcessTokenLogin()
{
    global $g_auth, $g_login_failed, $g_api_tokens;
    if (!isset($_POST['token']))
    {
        ProcessLogin_Error("No token");
        return;
    }
    $token = $_POST['token'];
    if (in_array($token, $g_api_tokens))
    {
        $_SESSION["user"] = $token;
        $_SESSION["logged_in"] = true;
        $g_logged_in = true;
        WriteToAuditFile('login_success');
        return;
    }
    // Invalid token
    $g_login_failed = true;
    $_SESSION["logged_in"] = false;
    WriteToAuditFile('login_fail', 'token=' . $token . ' reason=invalid token');
}

function LDAP_GroupNameFromCN($name)
{
    if (!psf_string_startsWith($name, 'CN='))
        return $name;

    $name = substr($name, 3);
    if (!psf_string_contains($name, ','))
        return $name;

    return substr($name, 0, strpos($name, ','));
}

function FetchDomainGroups($ldap, $login_name)
{
    global $g_auth_domain_prefix, $g_auth_ldap_dn, $g_auth_roles_map, $g_auth_roles;
    $ldap_user_search_string = $_POST["loginUsername"];
    // Automatically correct user name
    if ($g_auth_domain_prefix !== NULL && psf_string_startsWith($ldap_user_search_string, $g_auth_domain_prefix))
        $ldap_user_search_string = substr($ldap_user_search_string, strlen($g_auth_domain_prefix));
    
    // Read groups and insert them to list of roles this user is member of
    $ldap_groups = ldap_search($ldap, $g_auth_ldap_dn, "(samaccountname=$ldap_user_search_string)", array("memberof", "primarygroupid"));
    if ($ldap_groups === false)
    {
        DisplayWarning("Unable to retrieve list of groups for this user from LDAP (ldap_search() returned false) - is your ldap_dn correct?");
        return;
    } else
    {
        $entries = ldap_get_entries($ldap, $ldap_groups);
        if ($entries === false)
        {
            DisplayWarning("Unable to retrieve list of groups for this user from LDAP (ldap_get_entries() returned false) - is your ldap_dn correct?");
            return;
        }
        if ($entries['count'] == 0)
        {
            DisplayWarning('Unable to retrieve list of groups for this user from LDAP ($entries[\'count\'] == 0) - is your ldap_dn correct?');
            return;
        }
        if (!array_key_exists($login_name, $g_auth_roles_map))
        {
            // Create an empty array to fill up with groups this user is member of
            $g_auth_roles_map[$login_name] = [];
        }
        // Convert these insane LDAP strings to human readable format that it's far easier to work with
        $ldap_group_entries = [];
        foreach ($entries[0]['memberof'] as $ldap_group_entry)
        {
            $ldap_group_name = LDAP_GroupNameFromCN($ldap_group_entry);
            // Only store relevant groups, users are typically members of many groups, but we only care about these which also exist as roles
            if (array_key_exists($ldap_group_name, $g_auth_roles))
                $ldap_group_entries[] = $ldap_group_name;
        }
        $g_auth_roles_map[$login_name] = array_merge($g_auth_roles_map[$login_name], $ldap_group_entries);
        // Preserve the list of groups this user is in
        $_SESSION['groups'] = $g_auth_roles_map[$login_name];
    }
}

function ProcessLogin()
{
    global $g_auth, $g_auth_ldap_url, $g_login_failed, $g_auth_allowed_users, $g_auth_fetch_domain_groups, $g_auth_roles_map,
           $g_auth_ldap_dn, $g_auth_domain_prefix, $g_auth_roles, $g_auth_disallow_users_with_no_roles;
    
    // We support LDAP at this moment only
    if ($g_auth != "ldap")
    {
        ProcessLogin_Error("Unsupported authentication mechanism");
        return;
    }

    // Check if we have the credentials
    if (!isset($_POST["loginUsername"]) || !isset($_POST["loginPassword"]))
    {
        ProcessLogin_Error("No credentials provided (loginUsername or loginPassword missing)");
        return;
    }

    // Security hole - some LDAP servers will allow anonymous bind so empty password = access granted
    // PHP also kind of suck with strlen, so we need to check for multiple return values

    // This probably could be replaced with empty() which however has weird behaviour depending on PHP versions
    // so let's be safe here since this is a security thing and implement our own "is_really_empty_string"
    $pwl = strlen($_POST["loginPassword"]);
    if ($pwl === NULL || $pwl === 0)
    {
        ProcessLogin_Error('Empty password is not allowed');
        return;
    }

    $ldap = ldap_connect($g_auth_ldap_url);
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

    $login_name = $_POST["loginUsername"];
    // Check if we need to tweak the username
    if ($g_auth_domain_prefix !== NULL)
    {
        if (!psf_string_startsWith($login_name, $g_auth_domain_prefix))
            $login_name = $g_auth_domain_prefix . $login_name;
    }

    if ($bind = ldap_bind($ldap, $login_name, $_POST["loginPassword"]))
    {
        // Login OK
        if ($g_auth_allowed_users !== NULL)
        {
            // Check if this user is allowed to login
            if (!in_array($login_name, $g_auth_allowed_users))
            {
                ProcessLogin_Error("This user is not allowed to login to this tool (username not present in config.php)");
                return;
            }
        }

        // If it's enabled get a list of LDAP groups for this user
        if ($g_auth_fetch_domain_groups)
            FetchDomainGroups($ldap, $login_name);

        // Check if only users with some groups are allowed to login
        if ($g_auth_roles !== NULL && $g_auth_disallow_users_with_no_roles)
        {
            if (empty($g_auth_roles_map[$login_name]))
            {
                ProcessLogin_Error('You are not member of any groups with access to this tool');
                return;
            }
        }
        $_SESSION['user'] = $login_name;
        $_SESSION['logged_in'] = true;
        $g_logged_in = true;
        WriteToAuditFile('login_success');
    } else
    {
        // Invalid user / pw
        WriteToAuditFile('login_fail', 'username=' . $login_name . ' reason=invalid username or password');
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
