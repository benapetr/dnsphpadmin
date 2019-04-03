<?php

// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// This is useful for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('G_DNSTOOL_ENTRY_POINT', 'api.php');

require("definitions.php");
require("config.default.php");
require("config.php");
require("includes/record_list.php");
require("includes/zone_list.php");
require("includes/login.php");
require_once("psf/psf.php");

if ($g_api_enabled !== true)
    die('API subsystem is disabled, change $g_api_enabled to true in your config.php file to enable this');

function print_result($result)
{
    global $api;
    $result = [ 'result' => 'success' ];
    $api->PrintObj($result);
}

function print_success()
{
    print_result('success');
}

function api_call_login($api)
{
    global $api, $g_login_failed, $g_login_failure_reason;
    ProcessLogin();
    if ($g_login_failed)
    {
        $api->ThrowError("Login failed", $g_login_failure_reason);
        return true;
    }
    print_success();
    return true;
}

function api_call_logout($api)
{
    session_unset();
    print_success();
    return true;
}

function api_call_login_token($api)
{
    global $api, $g_login_failed, $g_login_failure_reason;
    if (!isset($_POST['token']))
    {
        $api->ThrowError('No token', 'You need to provide a token');
        return true;
    }
    ProcessTokenLogin();
    if ($g_login_failed)
    {
        $api->ThrowError("Login failed", $g_login_failure_reason);
        return true;
    }
    print_success();
    return true;
}

function api_call_list($api)
{
    global $api;
    $api->PrintObj(GetZoneList());
    return true;
}

function api_call_list_records($api)
{
    global $api, $g_domains;
    $zone = NULL;
    if (isset($_GET['zone']))
        $zone = $_GET['zone'];
    else if (isset($_POST['zone']))
        $zone = $_POST['zone'];
    else
        $api->ThrowError('No zone', 'You provided no zone name to list records for');

    if (!array_key_exists($zone, $g_domains))
        $api->ThrowError('No such zone',  'This zone is not in configuration file');
    
    $api->PrintObj(GetRecordList($zone));
    return true;
}

function api_call_is_logged($api)
{
    global $api, $g_auth_roles_map;
    $logged = is_authenticated($api->AuthenticationBackend);
    $result = [ 'is_logged' => $logged ];
    if ($logged)
    {
        $result['user'] = $_SESSION['user'];
        if ($g_auth_roles_map !== NULL && array_key_exists($_SESSION['user'], $g_auth_roles_map))
            $result['role'] = implode (',', $g_auth_roles_map[$_SESSION['user']]);
    }
    $api->PrintObj($result);
    return true;
}

function register_api($name, $short_desc, $long_desc, $callback, $auth = true, $required_params = [], $optional_params = [], $example = NULL, $post_only = false)
{
    global $api;
    $call = new PsfApi($name, $callback, $short_desc, $long_desc, $required_params, $optional_params);
    $call->Example = $example;
    $call->RequiresAuthentication = $auth;
    $call->POSTOnly = $post_only;
    $api->RegisterAPI_Action($call);
    return $call;
}

function is_authenticated($backend)
{
    global $api, $g_login_failed, $g_login_failure_reason;
    $require_login = RequireLogin();
    
    if (!$require_login)
        return true;
    if ($require_login && !isset($_POST['token']))
        return false;

    // User is not logged in, but provided a token, let's validate it
    ProcessTokenLogin();
    if ($g_login_failed)
    {
        $api->ThrowError("Login failed", $g_login_failure_reason);
        return false;
    }
    return true;
}

function is_privileged($backend, $privilege)
{
    return true;
}

RefreshSession();

$api = new PsfApiBase_JSON();
$api->ExamplePrefix = "/api.php";
$api->AuthenticationBackend = new PsfCallbackAuth($api);
$api->AuthenticationBackend->callback_IsAuthenticated = "is_authenticated";
$api->AuthenticationBackend->callback_IsPrivileged = "is_privileged";

register_api("is_logged", "Returns information whether you are currently logged in, or not", "Returns information whether you are currently logged in or not.", "api_call_is_logged", false, [], [], '?action=is_logged');
register_api("login", "Logins via username and password", "Login into API via username and password using exactly same login method as index.php. This API can be only accessed via POST method", "api_call_login", false,
             [ new PsfApiParameter("loginUsername", PsfApiParameterType::String, "Username to login"), new PsfApiParameter("loginPassword", PsfApiParameterType::String, "Password") ],
             [], '?action=login', true);
register_api("logout", "Logs you out", "Logs you out and clear your session data", "api_call_logout", true, [], [], '?action=logout');
register_api("login_token", "Logins via token", "Login into API via application token", "api_call_login_token", false,
             [ new PsfApiParameter("token", PsfApiParameterType::String, "Token that is used to login with") ],
             [], '?action=login_token&token=123ngfshegkernker5', true);
register_api("list_zones", "List all existing zones that you have access to", "List all existing zones that you have access to.", "api_call_list", true,
             [], [], '?action=list_zones');
register_api("list_records", "List all existing records for a specified zone", "List all existing records for a specified zone", "api_call_list_records", true,
             [ new PsfApiParameter("zone", PsfApiParameterType::String, "Zone to list records for") ],
             [], '?action=list_records&zone=domain.org');

$api->Process();
