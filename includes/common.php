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

require_once("config.php");
require_once("fatal.php");

function IsEditable($domain)
{
    global $g_domains;
    if (!array_key_exists($domain, $g_domains))
        die("No such domain: $domain");

    $domain_info = $g_domains[$domain];

    if (array_key_exists('read_only', $domain_info) && $domain_info['read_only'] === true)
        return false;

    return true;
}

function LoginRequired()
{
    global $g_auth;
    if ($g_auth === NULL || $g_auth !== 'ldap')
        return false;
    return true;
}

function IsAuthorized($domain, $privilege)
{
    global $g_auth_roles, $g_auth_default_role, $g_auth_roles_map;

    if ($g_auth_roles === NULL || !LoginRequired())
        return true;

    $roles = [ $g_auth_default_role ];
    $user = $_SESSION['user'];
    if ($user === NULL || $user === '')
        Error('Invalid username in session');

    if (array_key_exists($user, $g_auth_roles_map))
        $roles = $g_auth_roles_map[$user];

    if (in_array('root', $roles))
        return true;

    foreach ($roles as $role)
    {
        if (!array_key_exists($role, $g_auth_roles))
            continue;
        $role_info = $g_auth_roles[$role];
        if (!array_key_exists($domain, $role_info))
            continue;
        $permissions = $role_info[$domain];
        if ($privilege == 'rw' && $permissions == 'rw')
            return true;
        if ($privilege == 'r' && ($permissions == 'rw' || $permissions == 'r'))
            return true;
    }

    return false;
}

function IsAuthorizedToRead($domain)
{
    return IsAuthorized($domain, 'r');
}

function IsAuthorizedToWrite($domain)
{
    return IsAuthorized($domain, 'rw');
}
