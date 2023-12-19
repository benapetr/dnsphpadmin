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

require("definitions.php");
require("config.default.php");
require("config.php");
require_once("psf/psf.php");
require_once("includes/common.php");
require_once("includes/debug.php");
require_once("includes/fatal_api.php");
require_once("includes/record_list.php");
require_once("includes/modify.php");
require_once("includes/notifications.php");
require_once("includes/login.php");
require_once("includes/validator.php");
require_once("includes/zones.php");

if ($g_api_enabled !== true)
    die('API subsystem is disabled, change $g_api_enabled to true in your config.php file to enable this' . "\n");

if ($g_debug === true)
    psf_php_enable_debug();

date_default_timezone_set($g_timezone);

function print_result($result)
{
    global $api, $g_api_warnings, $g_api_errors;
    $json = [ 'result' => $result ];
    if (!empty($g_api_warnings))
        $json['warnings'] = $g_api_warnings;
    if (!empty($g_api_errors))
        $json['errors'] = $g_api_errors;
    $api->PrintObj($json);
}

function api_warning($text)
{
    global $g_api_warnings;
    $g_api_warnings[] = $text;
}

function print_success()
{
    print_result('success');
}

function print_login_error($reason)
{
    global $api;
    http_response_code(400);
    $api->PrintObj([
                       'result' => 'failure',
                       'error' => 'Login failed',
                       'message' => $reason,
                       'code' => G_API_ELOGIN
                   ]);
    die(G_API_ELOGIN);
}

function api_call_login($source)
{
    global $api, $g_login_failed, $g_login_failure_reason;
    ProcessLogin();
    if ($g_login_failed)
    {
        print_login_error($g_login_failure_reason);
        return true;
    }
    print_success();
    return true;
}

function api_call_logout($source)
{
    session_unset();
    print_success();
    return true;
}

function api_call_login_token($source)
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
        print_login_error($g_login_failure_reason);
        return true;
    }
    print_success();
    return true;
}

function api_call_list($source)
{
    global $api;
    $api->PrintObj(Zones::GetZoneList());
    return true;
}

function api_call_list_records($source)
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

function api_call_is_logged($source)
{
    global $api, $g_auth_roles_map;
    $logged = is_authenticated($api->AuthenticationBackend);
    $result = [ 'is_logged' => $logged ];
    if ($logged && isset($_SESSION['user']))
    {
        $result['user'] = $_SESSION['user'];
        if ($g_auth_roles_map !== NULL && array_key_exists($_SESSION['user'], $g_auth_roles_map))
            $result['role'] = implode (',', $g_auth_roles_map[$_SESSION['user']]);
    }
    $api->PrintObj($result);
    return true;
}

function check_zone_access($zone)
{
    global $api, $g_domains;
    if (!array_key_exists($zone, $g_domains))
    {
        $api->ThrowError('No such zone', "No such zone: $zone");
        return false;
    }

    if (!Zones::IsEditable($zone))
    {
        $api->ThrowError('Unable to write: Read-only zone', "Domain $zone is not writeable");
        return false;
    }

    if (!IsAuthorizedToWrite($zone))
    {
        $api->ThrowError('Permission denied', "You are not authorized to edit $zone");
        return false;
    }

    return true;
}

function get_required_post_get_parameter($name)
{
    global $api;
    $result = NULL;
    if (isset($_GET[$name]))
        $result = $_GET[$name];
    else if (isset($_POST[$name]))
        $result = $_POST[$name];
    else
        $api->ThrowError('Missing parameter: ' . $name, 'This parameter is required' );

    if ($result === NULL || strlen($result) == 0)
        $api->ThrowError('Missing parameter: ' . $name, 'This parameter is required' );

    if (psf_string_contains($result, "\n"))
        $api->ThrowError('Newline not allowed', 'Parameter values must not contain newlines for safety reasons');

    return $result;
}

function get_optional_post_get_parameter($name)
{
    global $api;
    $result = NULL;
    if (isset($_GET[$name]))
        $result = $_GET[$name];
    else if (isset($_POST[$name]))
        $result = $_POST[$name];

    if ($result !== NULL && psf_string_contains($result, "\n"))
        $api->ThrowError('Newline not allowed', 'Parameter values must not contain newlines for safety reasons');

    return $result;
}

function get_zone_for_fqdn_or_throw($fqdn)
{
    global $api;
    $zone = Zones::GetZoneForFQDN($fqdn);

    if ($zone === NULL)
        $api->ThrowError('No such zone', 'Zone for given fqdn was not found');

    return $zone;
}

function validate_type_or_throw($type)
{
    global $api;

    if (!IsValidRecordType($type))
    {
        $api->ThrowError('Invalid type', "Type $type is not a valid DNS record type");
        return false;
    }

    return true;
}

function api_call_create_record($source)
{
    global $api, $g_domains;
    $zone = get_optional_post_get_parameter('zone');
    $record = get_required_post_get_parameter('record');
    $ttl = get_required_post_get_parameter('ttl');
    $type = get_required_post_get_parameter('type');
    $value = get_required_post_get_parameter('value');
    $comment = get_optional_post_get_parameter('comment');
    $ptr = IsTrue(get_optional_post_get_parameter('ptr'));
    $merge_record = true;

    if ($zone === NULL)
    {
        $merge_record = false;
        $zone = get_zone_for_fqdn_or_throw($record);
    }

    if (!check_zone_access($zone))
        return false;

    if (!validate_type_or_throw($type))
        return false;

    if (!is_numeric($ttl))
    {
        $api->ThrowError('Invalid ttl', "TTL must be a number");
        return false;
    }

    $record = SanitizeHostname($record);
    if (!IsValidHostName($record))
    {
        $api->ThrowError('Invalid hostname', "Hostname is containing invalid characters");
        return false;
    }

    $n = "server " . $g_domains[$zone]['update_server'] . "\n";
    $merged_record = NULL;
    if ($merge_record)
    {
        $n .= ProcessInsertFromPOST($zone, $record, $value, $type, $ttl);
        $merged_record = $record . "." . $zone;
    } else
    {
        $n .= ProcessInsertFromPOST("" , $record, $value, $type, $ttl);
        $merged_record = $record;
    }
    $n .= "send\nquit\n";

    ProcessNSUpdateForDomain($n, $zone);
    WriteToAuditFile("create", $merged_record . " " . $ttl . " " . $type . " " . $value, $comment);

    if ($ptr == true)
    {
        Debug('PTR record was requested for ' . $merged_record . ' creating one');
        if ($type != 'A')
        {
            api_warning('Requested PTR record was not created: PTR record can be only created when you are inserting A record, you created ' . $type . ' record instead');
        } else
        {
            DNS_InsertPTRForARecord($value, $merged_record, $ttl, $comment);
        }
    }

    print_success();
    return true;
}

function api_call_replace_record($source)
{
    global $api, $g_domains;
    $zone = get_optional_post_get_parameter('zone');
    $record = get_required_post_get_parameter('record');
    $ttl = get_required_post_get_parameter('ttl');
    $type = get_required_post_get_parameter('type');
    $new_value = get_required_post_get_parameter('new_value');
    $value = get_optional_post_get_parameter('value');
    $comment = get_optional_post_get_parameter('comment');
    $new_record = get_optional_post_get_parameter('new_record');
    $new_type = get_optional_post_get_parameter('new_type');
    $ptr = IsTrue(get_optional_post_get_parameter('ptr'));
    $merge_record = true;

    // Auto-fill optional
    if ($new_type === NULL)
        $new_type = $type;

    if ($new_record === NULL)
        $new_record = $record;

    if ($zone === NULL)
    {
        $merge_record = false;
        $zone = get_zone_for_fqdn_or_throw($record);
    }

    if (!check_zone_access($zone))
        return false;

    if (!validate_type_or_throw($type))
        return false;

    if (!validate_type_or_throw($new_type))
        return false;

    if (!is_numeric($ttl))
    {
        $api->ThrowError('Invalid ttl', "TTL must be a number");
        return false;
    }

    $old = NULL;
    $old_record = NULL;
    $merged_record = NULL;
    if (!$merge_record)
    {
        $old = $record . ' 0 ' . $type;
        $old_record = $record;
        $merged_record = $new_record;
    } else
    {
        $old = $record . '.' . $zone . ' 0 ' . $type;
        $old_record = $record . '.' . $zone;
        $merged_record = $new_record . '.' . $zone;
    }

    if ($value !== NULL)
        $old .= ' ' . $value;

    DNS_ModifyRecord($zone, $new_record, $new_value, $new_type, $ttl, $comment, $old, !$merge_record);

    if ($ptr)
    {
        if ($type != 'A' && $new_type != 'A')
        {
            api_warning("You requested to modify underlying PTR record, but neither new or old record type is A record, ignoring PTR update request");
        } else
        {
            // PTR update was requested, if old type was A, delete it. If new type is A, create it
            if ($type == 'A')
            {
                if ($value === NULL)
                    api_warning("Old PTR record was not deleted, because parameter value was not provided - so we don't know what to delete");
                else
                    DNS_DeletePTRForARecord($value, $old_record, $comment);
            }
            if (($new_type === NULL && $type == 'A') || $new_type == 'A')
            {
                DNS_InsertPTRForARecord($new_value, $merged_record, $ttl, $comment);
            }
        }
    }

    print_success();
    return true;
}

function api_call_delete_record($source)
{
    global $api, $g_domains;
    $zone = get_optional_post_get_parameter('zone');
    $record = get_required_post_get_parameter('record');
    $ttl = 0;
    $type = get_required_post_get_parameter('type');
    $value = get_optional_post_get_parameter('value');
    $comment = get_optional_post_get_parameter('comment');
    $ptr = get_optional_post_get_parameter('ptr');
    $merge_record = true;

    if ($zone === NULL)
    {
        $merge_record = false;
        $zone = get_zone_for_fqdn_or_throw($record);
    }

    if (!check_zone_access($zone))
        return false;

    if (!validate_type_or_throw($type))
        return false;

    $record = SanitizeHostname($record);
    if (!IsValidHostName($record))
    {
        $api->ThrowError('Invalid hostname', "Hostname is containing invalid characters");
        return false;
    }

    // Value is optional, so in order to make nsupdate call more simple, we prefix it with space
    $original_value = $value;
    if (!psf_string_is_null_or_empty($value))
        $value = " " . $value;
    else
        $value = "";

    $n = "server " . $g_domains[$zone]['update_server'] . "\n";

    $merged_record = "";
    if ($merge_record)
    {
        $n .= "update delete " . $record . "." . $zone . " 0 " . $type . $value . "\n";
        $merged_record = $record . "." . $zone;
    } else
    {
        $n .= "update delete " . $record . " 0 " . $type . $value . "\n";
        $merged_record = $record;
    }
    $n .= "send\nquit\n";

    ProcessNSUpdateForDomain($n, $zone);
    WriteToAuditFile("delete", $merged_record . " 0 " . $type . $value, $comment);

    if ($ptr == true)
    {
        Debug('PTR record deletion was requested for ' . $merged_record);
        if ($type != 'A')
        {
            api_warning('Requested PTR record was not deleted: PTR record can be only deleted when you are changing A record, you deleted ' . $type . ' record instead');
        } else
        {
            DNS_DeletePTRForARecord($original_value, $merged_record, $comment);
        }
    }

    print_success();
    return true;
}

function api_call_get_zone_for_fqdn($source)
{
    global $api;
    $fqdn = get_required_post_get_parameter('fqdn');
    $zone = Zones::GetZoneForFQDN($fqdn);
    if ($zone === NULL)
        $api->ThrowError('No such zone', 'Zone for given fqdn was not found');
    $api->PrintObj(['zone' => $zone]);
    return true;
}

function api_call_get_record($source)
{
    global $api;
    $record = get_required_post_get_parameter('record');
    $record = SanitizeHostname($record);
    if (!IsValidHostName($record))
    {
        $api->ThrowError('Invalid hostname', "Hostname $record is not a valid hostname");
        return false;
    }
    $type = get_optional_post_get_parameter('type');
    if ($type === NULL)
        $type = 'A';
    
    if (!IsValidRecordType($type))
    {
        $api->ThrowError('Invalid type', "Type $type is not a valid DNS record type");
        return false;
    }

    $zone = get_optional_post_get_parameter('zone');
    if ($zone === NULL)
    {
        $zone = get_zone_for_fqdn_or_throw($record);
    } else
    {
        $record .= '.' . $zone;
    }
    if (!IsAuthorizedToRead($zone))
        $api->ThrowError('Permission denied', "You don't have access to read data from this zone");
    
    WriteToAuditFile("get_record", $record . ' ('. $zone .')');
    $api->PrintObj(get_records_from_zone($record, $type, $zone));
    return true;
}

function api_call_get_version($source)
{
    global $api;
    $api->PrintObj([ 'version' => G_DNSTOOL_VERSION ]);
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
        $api->ThrowError('Login failed', $g_login_failure_reason);
        return false;
    }
    return true;
}

function is_privileged($backend, $privilege)
{
    return true;
}

// Start up the program, initialize all sorts of resources, syslog, session data etc.
Initialize();

$api = new PsfApiBase_JSON();
$api->ShowHelpOnNoAction = false;
$api->ExamplePrefix = "/api.php";
$api->AuthenticationBackend = new PsfCallbackAuth($api);
$api->AuthenticationBackend->callback_IsAuthenticated = "is_authenticated";
$api->AuthenticationBackend->callback_IsPrivileged = "is_privileged";

register_api("is_logged", "Returns information whether you are currently logged in, or not", "Returns information whether you are currently logged in or not.",
             "api_call_is_logged", false, [], [], '?action=is_logged');
register_api("login", "Logins via username and password", "Login into API via username and password using exactly same login method as index.php. This API can be only accessed via POST method",
             "api_call_login", false,
             [ new PsfApiParameter("loginUsername", PsfApiParameterType::String, "Username to login"), new PsfApiParameter("loginPassword", PsfApiParameterType::String, "Password") ],
             [], '?action=login', true);
register_api("logout", "Logs you out", "Logs you out and clear your session data", "api_call_logout", true, [], [], '?action=logout');
register_api("login_token", "Logins via token", "Login into API via application token", "api_call_login_token", false,
             [ new PsfApiParameter("token", PsfApiParameterType::String, "Token that is used to login with") ],
             [], '?action=login_token&token=123ngfshegkernker5', true);
register_api("list_zones", "List all existing zones that you have access to", "List all existing zones that you have access to.",
             "api_call_list", true, [], [], '?action=list_zones');
register_api('list_records', "List all existing records for a specified zone", "List all existing records for a specified zone", "api_call_list_records", true,
             [ new PsfApiParameter("zone", PsfApiParameterType::String, "Zone to list records for") ],
             [], '?action=list_records&zone=domain.org');
register_api('create_record', 'Creates a new DNS record in specified zone', 'Creates a new DNS record in specific zone. Please mind that domain name / zone is appended to record name automatically, ' .
                                                                           'so if you want to add test.domain.org, name of key is only test.', 'api_call_create_record', true,
             // Required parameters
             [ new PsfApiParameter("record", PsfApiParameterType::String, "Record name, if you don't provide zone name explicitly, this should be FQDN"),
               new PsfApiParameter("ttl", PsfApiParameterType::Number, "Time to live (seconds)"), new PsfApiParameter("type", PsfApiParameterType::String, "Record type"),
               new PsfApiParameter("value", PsfApiParameterType::String, "Value of record") ],
             // Optional parameters
             [ new PsfApiParameter("zone", PsfApiParameterType::String, "Zone to modify, if not specified and record is fully qualified, it's automatically looked up from config file"),
               new PsfApiParameter("ptr", PsfApiParameterType::Boolean, "Optionally create PTR record, works only when you are adding A records"),
               new PsfApiParameter("comment", PsfApiParameterType::String, "Optional comment for audit logs") ],
             // Example call
             '?action=create_record&zone=domain.org&record=test&ttl=3600&type=A&value=0.0.0.0');
register_api('delete_record', 'Deletes DNS record(s) in specified zone', 'Deletes DNS record(s) in specific zone. If you don\'t provide value, all records of given type will be deleted.', 'api_call_delete_record', true,
             // Required parameters
             [ new PsfApiParameter("record", PsfApiParameterType::String, "Record name, if you don't provide zone name explicitly, this should be FQDN"),
               new PsfApiParameter("type", PsfApiParameterType::String, "Record type") ],
             // Optional parameters
             [ new PsfApiParameter("ttl", PsfApiParameterType::Number, "Time to live (seconds). Please note that nsupdate ignores TTL in delete requests. This parameter exists only for compatiblity reasons and is silently ignored."),
               new PsfApiParameter("zone", PsfApiParameterType::String, "Zone to modify, if not specified and record is fully qualified, it's automatically looked up from config file"),
               new PsfApiParameter("value", PsfApiParameterType::String, "Value of record. If not provided, all records with given type will be removed."),
               new PsfApiParameter("ptr", PsfApiParameterType::Boolean, "Optionally delete PTR record, works only when you are deleting A records"),
               new PsfApiParameter("comment", PsfApiParameterType::String, "Optional comment for audit logs") ],
             // Example call
             '?action=delete_record&zone=domain.org&record=test&ttl=3600&type=A&value=0.0.0.0');
register_api('replace_record', 'Removes old and create a new DNS record in single nsupdate transaction', 'Replaces specific record. Both records must be within same zone, but may be of different type. Note that due to nature of nsupdate, if record you want to replace ' .
                                                                           'doesn\'t exist, it will not fail. So replace_record on non-existent record will still create a new record.', 'api_call_replace_record', true,
             // Required parameters
             [ new PsfApiParameter("record", PsfApiParameterType::String, "Name of existing record you want to replace, if you don't provide zone name explicitly, this should be FQDN"),
               new PsfApiParameter("type", PsfApiParameterType::String, "Type of current record that you want to replace"),
               new PsfApiParameter("ttl", PsfApiParameterType::Number, "Time to live (seconds)"),
               new PsfApiParameter("new_value", PsfApiParameterType::String, "Value of new record")],
             // Optional parameters
             [ new PsfApiParameter("zone", PsfApiParameterType::String, "Zone to modify, if not specified and record is fully qualified, it's automatically looked up from config file"),
               new PsfApiParameter("value", PsfApiParameterType::String, "Value of record. If not provided, all records with given type will be removed and replaced with a single new record"),
               new PsfApiParameter("new_record", PsfApiParameterType::String, "New record name, if you are not changing name of key, this can be omitted. If you don't provide zone name explicitly, this should be FQDN"),
               new PsfApiParameter("new_type", PsfApiParameterType::String, "Type of record, if you are not changing type, this can be omitted."),
               new PsfApiParameter("ptr", PsfApiParameterType::Boolean, "Optionally replace associated PTR record, works only when either new, old or both records are A records"),
               new PsfApiParameter("comment", PsfApiParameterType::String, "Optional comment for audit logs") ],
             // Example call
             '?action=replace_record&record=test.zone.org&ttl=3600&type=A&value=0.0.0.0&new_value=2.2.2.2&ptr=true');
register_api('get_zone_for_fqdn', 'Returns zone name for given FQDN', 'Attempts to look up zone name for given FQDN using configuration file of php dns admin using auto-lookup function',
             'api_call_get_zone_for_fqdn', false, [ new PsfApiParameter("fqdn", PsfApiParameterType::String, "FQDN") ], [], '?action=get_zone_for_fqdn&fqdn=test.example.org');
register_api('get_record', 'Return single record with specified FQDN', 'Lookup single record from master server responsible for zone that hosts this record', 'api_call_get_record', true,
             [ new PsfApiParameter("record", PsfApiParameterType::String, "Record name, if you don't provide zone name explicitly, this should be FQDN") ],
             [ new PsfApiParameter("type", PsfApiParameterType::String, "Record type (if not specified, will be A)"),
               new PsfApiParameter("zone", PsfApiParameterType::String, "Zone to modify, if not specified and record is fully qualified, it's automatically looked up from config file") ],
             '?action=get_record&record=test.example.org');
register_api('get_version', 'Returns version', 'Returns version of this tool.', 'api_call_get_version', false, [], [], '?action=get_version');
if (!$api->Process())
{
    if (isset($_GET['action']) || isset($_POST['action']))
    {
        $api->ThrowError('Unknown action', 'This action is unknown. Please refer to help. Open api.php with no parameters to see help in HTML form.');
    } else
    {
        $api->PrintHelpAsHtml();
    }
} else
{
    IncrementStat('api');
}

ResourceCleanup();
