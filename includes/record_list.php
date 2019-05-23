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
require_once("includes/nsupdate.php");
require_once("common.php");
require_once("config.php");

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
        $status->Text .= '<span class="glyphicon glyphicon-refresh" title="In transfer"></span> <b>Warning:</b> This domain is being transfered between different master servers<br>';
    }
    if (array_key_exists('read_only', $domain_info) && $domain_info['read_only'] === true)
    {
        $is_ok = false;
        $status->Text .= '<span class="glyphicon glyphicon-floppy-remove" title="Read-Only"></span> <b>Warning:</b> This domain is read only<br>';
    }
    if (!IsAuthorizedToRead($domain))
    {
        $is_ok = false;
        $status->Text .= '<span class="glyphicon glyphicon-alert"></span> <b>Can\'t read:</b> you are not authorized to read this zone.<br>';
    }
    if (!IsAuthorizedToWrite($domain))
    {
        $is_ok = false;
        $status->Text .= '<span class="glyphicon glyphicon-alert"></span> <b>Can\'t write:</b> you are not authorized to write this zone.<br>';
    }
    if (array_key_exists('maintenance_note', $domain_info))
    {
        $is_ok = false;
        $status->Text .= '<span class="glyphicon glyphicon-alert"></span> <b>Maintenance note:</b> ' .$domain_info['maintenance_note'];
    }

    if ($is_ok)
        return NULL;

    return $status;
}

function GetRecordList($domain)
{
    $records = array();
    if (!IsAuthorizedToRead($domain))
        return $records;
    $data = explode("\n", get_zone_data($domain));
    foreach ($data as $line)
    {
        if (psf_string_startsWith($line, ";"))
            continue;
        // Sanitize string, we replace all double tabs with single tabs, then replace all tabs with spaces and then replace
        // double spaces, so that each item is separated only with single space
        $line = str_replace("\t", " ", $line);
        while (psf_string_contains($line, "  "))
            $line = str_replace("  ", " ", $line);
        if (strlen(str_replace(" ", "", $line)) == 0)
            continue;
        $records[] = explode(" ", $line, 5);
    }
    return $records;
}

function GetRecordListTable($parent, $domain)
{
    global $g_editable;
    $table = new BS_Table($parent);
    $table->Condensed = true;
    $table->Headers = [ "Record", "TTL", "Scope", "Type", "Value", "Options" ];
    $table->SetColumnWidth(2, '80px'); // Scope
    $table->SetColumnWidth(3, '80px'); // Type
    $table->SetColumnWidth(5, '80px'); // Options
    $records = GetRecordList($domain);
    $is_editable = IsEditable($domain) && IsAuthorizedToWrite($domain);
    foreach ($records as $record)
    {
        if (!$is_editable || !in_array($record[3], $g_editable))
        {
            $record[] = '';
        } else
        {
            $record[] = '<a href="index.php?action=manage&domain=' . $domain . '&delete=' .
                        urlencode($record[0] . " " . $record[1] . " " . $record[3] . " " . $record[4]) .
                        '" onclick="return confirm(\'Are you sure?\')"><span class="glyphicon glyphicon-trash" title="Delete"></span></a>&nbsp;&nbsp;' .
                        '<a href="index.php?action=edit&domain=' . $domain . '&key=' .
                        urlencode($record[0]) . "&ttl=" . $record[1] . "&type=" . $record[3] . "&value=" . urlencode($record[4]) .
                        "&old=" . urlencode($record[0] . " " . $record[1] . " " . $record[3] . " " . $record[4]) .
                        '"><span title="Edit" class="glyphicon glyphicon-pencil"></span></a>';
        }
        $table->AppendRow($record);
    }
    return $table;
}
