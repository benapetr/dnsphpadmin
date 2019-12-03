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
require_once("debug.php");
require_once("nsupdate.php");
require_once("zones.php");

//! Wrapper around nsupdate from nsupdate.php that checks if there are custom TSIG overrides for given domain
function ProcessNSUpdateForDomain($input, $domain)
{
    global $g_domains;
    if (!array_key_exists($domain, $g_domains))
        die("No such domain: " . $domain);
    $tsig = NULL;
    $tsig_key = NULL;
    $domain_info = $g_domains[$domain];
    Debug("Processing update for: " . $domain);
    if (array_key_exists("tsig", $domain_info))
        $tsig = $domain_info["tsig"];
    if (array_key_exists("tsig_key", $domain_info))
        $tsig_key = $domain_info["tsig_key"];
    $zone_name = NULL;
    if (!array_key_exists("explicit", $domain_info) || $domain_info["explicit"] === true)
        $zone_name = $domain;
    return nsupdate($input, $tsig, $tsig_key, $zone_name);
}

function ProcessDelete($well)
{
    global $g_domains, $g_selected_domain;
    if (!isset($_GET["delete"]))
        return;

    if (strlen($g_selected_domain) == 0)
        Error("No domain");

    if (!Zones::IsEditable($g_selected_domain))
        Error("Domain $g_selected_domain is not writeable");

    if (!IsAuthorizedToWrite($g_selected_domain))
        Error("You are not authorized to edit $g_selected_domain");
    
    $record = $_GET["delete"];

    if (psf_string_contains($record, "\n"))
        Error("Invalid delete string");

    $input = "server " . $g_domains[$g_selected_domain]["update_server"] . "\n";
    $input .= "update delete " . $record . "\nsend\nquit\n";
    ProcessNSUpdateForDomain($input, $g_selected_domain);
    WriteToAuditFile("delete", $record);
    $well->AppendObject(new BS_Alert("Successfully deleted record " . $record));
}

function ProcessInsertFromPOST($zone, $record, $value, $type, $ttl)
{
    if (empty($record) && empty($zone))
        Error("Both record and zone can't be empty");

    $fqdn = $record;
    if (!empty($zone))
    {
        // Make sure we don't add trailing dot
        if (!empty ($fqdn))
            $fqdn .= '.' . $zone;
        else
            $fqdn = $zone;
    }

    return "update add " . $fqdn . " " . $ttl . " " . $type . " " . $value . "\n";
}
