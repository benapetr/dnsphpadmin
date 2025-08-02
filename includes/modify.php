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
require_once("validator.php");
require_once("zones.php");
require_once("idn.php");

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
    IncrementStat('nsupdate_call');
    return nsupdate($input, $tsig, $tsig_key, $zone_name);
}

function ProcessInsertFromPOST($zone, $record, $value, $type, $ttl)
{
    global $g_enable_idn;

    if (psf_string_is_null_or_empty($record) && psf_string_is_null_or_empty($zone))
        Error("Both record and zone can't be empty");

    $fqdn = $record;
    if (!psf_string_is_null_or_empty($zone))
    {
        // Make sure we don't add trailing dot
        if (!psf_string_is_null_or_empty ($fqdn))
            $fqdn .= '.' . $zone;
        else
            $fqdn = $zone;
    }

    if ($g_enable_idn)
    {
        // Convert UTF-8 domain to ASCII for DNS operations
        $fqdn = IDNConverter::fqdnToASCII($fqdn);
        
        // For certain record types, we need to convert domain names in the value field too
        if (in_array($type, array('CNAME', 'NS', 'MX', 'PTR')))
        {
            if ($type == 'MX' && preg_match('/^(\d+)\s+(.+)$/', $value, $matches))
            {
                // MX record with priority
                $value = $matches[1] . ' ' . IDNConverter::fqdnToASCII($matches[2]);
            } else if ($type != 'TXT')
            {
                // Don't convert TXT records as they may contain arbitrary text
                $value = IDNConverter::fqdnToASCII($value);
            }
        }
    }

    return "update add " . $fqdn . " " . $ttl . " " . $type . " " . $value . "\n";
}

//! Create a new record in given zone, returns false on error - however, some errors may cancel execution
//! This function may crash the app without returning
function DNS_CreateRecord($zone, $record, $value, $type, $ttl, $comment)
{
    global $g_domains;
    $input = "server " . $g_domains[$zone]['update_server'] . "\n";
    $input .= ProcessInsertFromPOST($zone, $record, $value, $type, $ttl);
    $input .= "send\nquit\n";
    $result = ProcessNSUpdateForDomain($input, $zone);
    if (strlen($result) > 0)
        Debug("result: " . $result);
    WriteToAuditFile('create', $record . "." . $zone . " " . $ttl . " " . $type . " " . $value, $comment);
    IncrementStat('create');
    return true;
}

//! Replace record - atomic, returns true on success
function DNS_ModifyRecord($zone, $record, $value, $type, $ttl, $comment, $old, $is_fqdn = false)
{
    global $g_domains;
    if (!NSupdateEscapeCheck($old))
        Error('Invalid data for old record: ' . $old);
    $input = "server " . $g_domains[$zone]['update_server'] . "\n";
    // First delete the existing record
    $input .= "update delete " . $old . "\n";
    if ($is_fqdn == false)
        $input .= ProcessInsertFromPOST($zone, $record, $value, $type, $ttl);
    else
        $input .= ProcessInsertFromPOST(NULL, $record, $value, $type, $ttl);
    $input .= "send\nquit\n";
    $result = ProcessNSUpdateForDomain($input, $zone);
    if (strlen($result) > 0)
        Debug("result: " . $result);
    WriteToAuditFile('replace_delete', $old, $comment);
    IncrementStat('replace_delete');
    WriteToAuditFile('replace_create', $record . "." . $zone . " " . $ttl . " " . $type . " " . $value, $comment);
    IncrementStat('replace_create');
    return true;
}

function DNS_DeleteRecord($zone, $record)
{
    global $g_domains;

    if (strlen($zone) == 0)
        Error("No domain");

    if (!Zones::IsEditable($zone))
        Error("Domain $zone is not writeable");

    if (!IsAuthorizedToWrite($zone))
        Error("You are not authorized to edit $zone");

    if (!NSupdateEscapeCheck($record))
        Error("Invalid delete string: " . $record);

    $input = "server " . $g_domains[$zone]['update_server'] . "\n";
    $input .= "update delete " . $record . "\nsend\nquit\n";
    ProcessNSUpdateForDomain($input, $zone);
    WriteToAuditFile("delete", $record);
    IncrementStat('delete');
    return true;
}

//! Try to insert a PTR record for given IP, on failure, warning is emitted and false returned, true returned on success
//! this function is designed as a helper function that is used together with creation of A record, so it's never fatal
function DNS_InsertPTRForARecord($ip, $fqdn, $ttl, $comment)
{
    global $g_domains;
    Debug('PTR record was requested, checking zone name');
    $ip_parts = explode('.', $ip);
    if (count($ip_parts) != 4)
    {
        DisplayWarning('PTR record was not created: record '. $ip .' is not a valid IPv4 quad');
        return false;
    }
    $arpa = $ip_parts[3] . '.' . $ip_parts[2] . '.' . $ip_parts[1] . '.' . $ip_parts[0] . '.in-addr.arpa';
    $arpa_zone = Zones::GetZoneForFQDN($arpa);
    if ($arpa_zone === NULL)
    {
        DisplayWarning('PTR record was not created: there is no PTR zone for record '. $ip);
        return false;
    }
    if (!Zones::IsEditable($arpa_zone))
    {
        DisplayWarning("PTR record was not created for $ip: zone " . $arpa_zone . ' is read only');
        return false;
    }
    if (!IsAuthorizedToWrite($arpa_zone))
    {
        DisplayWarning("PTR record was not created: you don't have write access to zone " . $arpa_zone);
        return false;
    }

    Debug('Found PTR useable zone: ' . $arpa_zone);

    if (!psf_string_endsWith($fqdn, '.'))
        $fqdn = $fqdn . '.';

    // Let's insert this record
    $input = "server " . $g_domains[$arpa_zone]['update_server'] . "\n";
    $input .= ProcessInsertFromPOST(NULL, $arpa, $fqdn, 'PTR', $ttl);
    $input .= "send\nquit\n";
    $result = ProcessNSUpdateForDomain($input, $arpa_zone);
    if (strlen($result) > 0)
        Debug("result: " . $result);
    WriteToAuditFile('create', $arpa . " " . $ttl . " PTR " . $fqdn, $comment);
    IncrementStat('create');
    return true;
}

//! Try to delete a PTR record for a given IP, on failure, warning is emitted and false returned, true returned on success
//! this function is designed as a helper function that is used together with modifications of A record, so it's never fatal
function DNS_DeletePTRForARecord($ip, $fqdn, $comment)
{
    global $g_domains;
    Debug('PTR record removal was requested, checking zone name');
    $ip_parts = explode('.', $ip);
    if (count($ip_parts) != 4)
    {
        DisplayWarning('PTR record was not deleted: record '. $ip .' is not a valid IPv4 quad');
        return false;
    }
    $arpa = $ip_parts[3] . '.' . $ip_parts[2] . '.' . $ip_parts[1] . '.' . $ip_parts[0] . '.in-addr.arpa';
    $arpa_zone = Zones::GetZoneForFQDN($arpa);
    if ($arpa_zone === NULL)
    {
        DisplayWarning('PTR record was not deleted: there is no PTR zone for record '. $ip);
        return false;
    }
    if (!Zones::IsEditable($arpa_zone))
    {
        DisplayWarning("PTR record was not deleted for $ip: zone " . $arpa_zone . ' is read only');
        return false;
    }
    if (!IsAuthorizedToWrite($arpa_zone))
    {
        DisplayWarning("PTR record was not deleted: you don't have write access to zone " . $arpa_zone);
        return false;
    }

    Debug('Found PTR useable zone: ' . $arpa_zone);

    if (!psf_string_endsWith($fqdn, '.'))
        $fqdn = $fqdn . '.';

    // Let's insert this record
    $input = "server " . $g_domains[$arpa_zone]['update_server'] . "\n";
    $input .= "update delete " . $arpa . " 0 PTR " . $fqdn . "\n";
    $input .= "send\nquit\n";
    $result = ProcessNSUpdateForDomain($input, $arpa_zone);
    if (strlen($result) > 0)
        Debug("result: " . $result);
    WriteToAuditFile('delete', $arpa . " 0 PTR " . $fqdn, $comment);
    IncrementStat('delete');
    return true;
}