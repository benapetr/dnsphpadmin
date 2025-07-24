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

require_once("nsupdate.php");
require_once("audit.php");
require_once("common.php");
require_once("zones.php");

// If true hidden record types will be shown
$g_show_hidden_types = false;

// This variable is changed to true if hidden variable types are present in record list, we need to know this later
// when rendering UI so that we know if "show / hide" button should be even present or not
$g_hidden_types_present = false;

// Counts of visible items
$g_hidden_records_count = 0;
$g_total_records_count = 0;

function GetStatusOfZoneAsNote($domain)
{
    global $g_domains;

    if (!array_key_exists($domain, $g_domains))
        die("No such domain: $domain");

    $domain_info = $g_domains[$domain];

    $is_ok = true;
    $status = new BS_Alert('', 'info');
    $status->EscapeHTML = false;

    if (array_key_exists('in_transfer', $domain_info) && $domain_info['in_transfer'] === true)
    {
        $is_ok = false;
        $status->Text .= '<span class="bi bi-arrow-repeat" title="In transfer"></span> <b>Warning:</b> This domain is being transfered between different master servers<br>';
    }
    if (array_key_exists('read_only', $domain_info) && $domain_info['read_only'] === true)
    {
        $is_ok = false;
        $status->Text .= '<span class="bi bi-lock" title="Read-Only"></span> <b>Warning:</b> This domain is read only<br>';
    }
    if (!IsAuthorizedToRead($domain))
    {
        $is_ok = false;
        $status->Text .= '<span class="bi bi-exclamation-triangle"></span> <b>Can\'t read:</b> you are not authorized to read this zone.<br>';
    }
    if (!IsAuthorizedToWrite($domain))
    {
        $is_ok = false;
        $status->Text .= '<span class="bi bi-exclamation-triangle"></span> <b>Can\'t write:</b> you are not authorized to write this zone.<br>';
    }
    if (array_key_exists('maintenance_note', $domain_info))
    {
        $is_ok = false;
        $status->Text .= '<span class="bi bi-exclamation-triangle"></span> <b>Maintenance note:</b> ' .$domain_info['maintenance_note'];
    }
    if (array_key_exists('note', $domain_info))
    {
        $is_ok = false;
        $status->Text .= '<span class="bi bi-info-circle"></span> <b>Note:</b> ' .$domain_info['note'];
    }

    if ($is_ok)
        return NULL;

    return $status;
}

// This function will go through parsed zone data and will return SOA record if present, otherwise will return NULL
function GetSOAFromData($data)
{
    $soa = NULL;
    foreach ($data as $record)
    {
        if ($record[3] === 'SOA')
        {
            $soa = $record[4];
            break;
        }
    }
    return $soa;
}

//! Go through whole zone and check if SOA is present on end
function CheckIfZoneIsComplete($data)
{
    if ($data[0][3] !== 'SOA')
        return false;
    if (end($data)[3] !== 'SOA')
        return false;
    return true;
}

function GetRecordList($zone)
{
    global $g_caching_engine, $g_caching_engine_instance, $g_retry_on_error;
    if (!IsAuthorizedToRead($zone))
        return array();

    // Check if zone data exist in cache
    if ($g_caching_engine_instance->IsCached($zone))
    {
        // There is something in the zone cache
        Debug('Zone ' . $zone . ' exist in cache, checking if SOA record is identical');
        $current_soa = GetSOAFromData(get_zone_soa($zone));
        $cached_soa = $g_caching_engine_instance->GetSOA($zone);
        if ($current_soa === NULL)
        {
            // Something is very wrong - there is no SOA record in our query
            // Check if retry on error is enabled, if yes, try N more times, if it doesn't help, show error
            if ($g_retry_on_error > 0)
            {
                $retry = $g_retry_on_error;
                $current_retry = 0;
                while ($retry-- > 0)
                {
                    $current_retry++;
                    Debug("Unable to retrieve SOA record for " . $zone . " (dig SOA returned no data), retrying dig ($current_retry/$g_retry_on_error)...");
                    $current_soa = GetSOAFromData(get_zone_soa($zone));
                    if ($current_soa !== NULL)
                    {
                        DisplayWarning("Transfer NS for " . $zone . " had troubles returning SOA record, had to retry $current_retry times, check your network");
                        break;
                    }
                }
            }
            // if SOA is still NULL, show non-blocking error
            if ($current_soa === NULL)
                Error("Unable to retrieve SOA record for " . $zone . " - (dig SOA) transfer NS didn't return any data for it", false);
        } else if ($current_soa != $cached_soa)
        {
            Debug("Cache miss: '$current_soa' != '$cached_soa'");
        } else if ($current_soa == $cached_soa)
        {
            Debug("Cache match! Not running a full zone transfer");
            // Return data from cache instead of running full zone transfer
            $result = $g_caching_engine_instance->GetData($zone);
            if ($result === false)
            {
                Debug('SOA exist in cache, but data not (corrupted cache), dropping memory and falling back to full zone transfer');
                $g_caching_engine_instance->Drop($zone);
            } else
            {
                WriteToAuditFile("display", $zone, "(cached)");
                IncrementStat('display_zone_cached');
                return $result;
            }
        }
    } else if ($g_caching_engine !== NULL)
    {
        Debug('Zone ' . $zone . ' does not exist in cache, running full zone transfer');
    }
    
    WriteToAuditFile("display", $zone, "(full transfer)");
    IncrementStat('display_zone');
    Debug('Running full zone transfer for: ' . $zone);
    $data = get_zone_data($zone);
    $soa = GetSOAFromData($data);
    if ($soa === NULL)
    {
        // Again - server returned no SOA record, there is some network issue
        if ($g_retry_on_error > 0)
        {
            $retry = $g_retry_on_error;
            $current_retry = 0;
            while ($retry-- > 0)
            {
                $current_retry++;
                Debug("Unable to retrieve SOA record for " . $zone . " (dig AXFR returned no data), retrying dig ($current_retry/$g_retry_on_error)...");
                $data = get_zone_data($zone);
                $soa = GetSOAFromData($data);
                if ($soa !== NULL)
                {
                    DisplayWarning("Transfer NS for " . $zone . " had troubles returning SOA record during AXFR, had to retry $current_retry times, check your network");
                    break;
                }
            }
        }
        if ($soa === NULL)
            Error("Unable to retrieve SOA record for " . $zone . " - (dig AXFR) transfer NS didn't return any data for it", false);
        else
            $g_caching_engine_instance->CacheZone($zone, $soa, $data);
    } else
    {
        $g_caching_engine_instance->CacheZone($zone, $soa, $data);
    }

    if (count($data) > 0 && !CheckIfZoneIsComplete($data))
        DisplayWarning("Transfer NS for " . $zone . " didn't return full zone, last SOA record is missing, zone data are incomplete");

    return $data;
}

function GetRecordListTable($parent, $domain)
{
    global $g_editable, $g_show_hidden_types, $g_hidden_record_types, $g_hidden_types_present, $g_total_records_count, $g_hidden_records_count;
    $table = new HtmlTable($parent);
    $table->Condensed = true;
    $table->Headers = [ "Record", "TTL", "Scope", "Type", "Value", "Options" ];
    $table->ClassName = 'table table-bordered table-hover table-sm';
    $table->SetColumnWidth(2, '80px'); // Scope
    $table->SetColumnWidth(3, '80px'); // Type
    $table->SetColumnWidth(5, '80px'); // Options
    $records = GetRecordList($domain);
    $is_editable = Zones::IsEditable($domain) && IsAuthorizedToWrite($domain);
    $has_ptr = Zones::HasPTRZones();
    foreach ($records as $record)
    {
        $g_total_records_count++;
        if (in_array($record[3], $g_hidden_record_types))
        {
            $g_hidden_types_present = true;
            if (!$g_show_hidden_types)
            {
                $g_hidden_records_count++;
                continue;
            }
        }
        if (!$is_editable || !in_array($record[3], $g_editable))
        {
            $record[] = '';
        } else
        {
            $delete_record = '<a href="index.php?action=manage&domain=' . $domain . '&delete=' .  urlencode($record[0] . " " . $record[1] . " " . $record[3] . " " . $record[4]) .
                             '" onclick="return confirm(\'Are you sure you want to delete ' . $record[0] . '?\')"><span class="bi bi-trash" title="Delete"></span></a>';
            $delete_record_with_ptr = '';
            if ($has_ptr && $record[3] == 'A')
            {
                // Optional button to delete record together with PTR record, show only if there are PTR zones in cfg
                $delete_record_with_ptr = '<a href="index.php?action=manage&ptr=true&key=' . urlencode($record[0]) . '&value=' . urlencode($record[4]) . '&type=' . $record[3] . '&domain=' . $domain .
                                          '&delete=' .  urlencode($record[0] . ' ' . $record[1] . " " . $record[3] . " " . $record[4]) .
                                          '" onclick="return confirm(\'Are you sure you want to delete ' . $record[0] . '?\')"><span style="color: #ff0000;" class="bi bi-trash" title="Delete together with associated PTR record (if any exist)"></span></a>';
            }
            $large_space = '&nbsp;&nbsp;';
            $record[] = $delete_record . $large_space . '<a href="index.php?action=edit&domain=' . $domain . '&key=' .
                        urlencode($record[0]) . "&ttl=" . $record[1] . "&type=" . $record[3] . "&value=" . urlencode($record[4]) .
                        "&old=" . urlencode($record[0] . " " . $record[1] . " " . $record[3] . " " . $record[4]) .
                        '"><span title="Edit" class="bi bi-pencil"></span></a>' . $large_space . $delete_record_with_ptr;
        }
        $record[4] = '<span class="value">' . $record[4] . '</span>';
        $table->AppendRow($record);
    }
    if ($is_editable) {
	$add = '<a href="index.php?action=new&domain=' . $domain . '"><span title="Add New" class="bi bi-plus"></span></a>';
	$table->AppendRow(['', '', '', '', '', $add]);
    }
    return $table;
}

//! This return similar results to GetRecordListTable but without option buttons in format friendly for exporting
function GetRecordListTablePlainFormat($parent, $domain)
{
    $table = new HtmlTable($parent);
    $table->Headers = [ "Record", "TTL", "Scope", "Type", "Value" ];
    $records = GetRecordList($domain);
    foreach ($records as $record)
    {
        $table->AppendRow($record);
    }
    return $table;
}
