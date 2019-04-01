<?php

// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

define('G_DNSTOOL_ENTRY_POINT', 'api.php');

// This is useful for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require("definitions.php");
require("config.default.php");
require("config.php");
require("includes/record_list.php");
require("includes/login.php");
require_once("psf/psf.php");

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
    global $api;
    $api->ThrowError("Not implemented", "This function is not implemented yet");
    return false;
}

function api_call_logout($api)
{
    session_unset();
    print_success();
    return true;
}

function api_call_login_token($api)
{
    global $api;
    $api->ThrowError("Not implemented", "This function is not implemented yet");
    return false;
}

function api_call_list($api)
{
    echo("works");
    return true;
}

function api_call_is_logged($api)
{
    global $api;
    $api->PrintObj(is_authenticated($api->AuthenticationBackend));
    return true;
}

function register_api($name, $short_desc, $long_desc, $callback, $auth = true, $required_params = [], $optional_params = [], $example = NULL)
{
    global $api;
    $call = new PsfApi($name, $callback, $short_desc, $long_desc, $required_params, $optional_params);
    $call->Example = $example;
    $call->RequiresAuthentication = $auth;
    $api->RegisterAPI_Action($call);
    return $call;
}

function is_authenticated($backend)
{
    return !RequireLogin();
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
$api->AuthenticationBackend->callback_Privileged = "is_privileged";

register_api("is_logged", "Returns information whether you are currently logged in, or not", "Returns information whether you are currently logged in or not.", "api_call_is_logged", false, [], [], '?action=is_logged');
register_api("login", "Logins via username and password", "Login into API via username and password using exactly same login method as index.php", "api_call_login", false,
             [ new PsfApiParameter("username", PsfApiParameterType::String, "Username to login"), new PsfApiParameter("password", PsfApiParameterType::String, "Password") ],
             [], '?action=login&username=bob&password=banana');
register_api("logout", "Logs you out", "Logs you out and clear your session data", "api_call_logout", true, [], [], '?action=logout');
register_api("login_token", "Logins via token", "Login into API via application token", "api_call_login_token", false,
             [ new PsfApiParameter("token", PsfApiParameterType::String, "Token that is used to login with") ],
             [], '?action=login_token&token=123ngfshegkernker5');
register_api("list_zones", "List all existing zones that you have access to", "List all existing zones that you have access to.", "api_call_list", true,
             [], [], '?action=list_zones');
register_api("list_records", "List all existing records for a specified zone", "List all existing records for a specified zone", "api_call_list", true,
             [ new PsfApiParameter("zone", PsfApiParameterType::String, "Zone to list records for") ],
             [], '?action=list_records&zone=domain.org');

$api->Process();
