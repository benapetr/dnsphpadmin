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
require_once("idn.php");

// If true hidden record types will be shown
$g_show_hidden_types = false;

// This variable is changed to true if hidden variable types are present in record list, we need to know this later
// when rendering UI so that we know if "show / hide" button should be even present or not
$g_hidden_types_present = false;

// Counts of visible items
$g_hidden_records_count = 0;
$g_total_records_count = 0;

class Records
{
    public static function GetStatusOfZoneAsNote($domain)
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
        if (!Auth::IsAuthorizedToRead($domain))
        {
            $is_ok = false;
            $status->Text .= '<span class="bi bi-exclamation-triangle"></span> <b>Can\'t read:</b> you are not authorized to read this zone.<br>';
        }
        if (!Auth::IsAuthorizedToWrite($domain))
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
    public static function GetSOAFromData($data)
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
    public static function CheckIfZoneIsComplete($data)
    {
        if ($data[0][3] !== 'SOA')
            return false;
        if (end($data)[3] !== 'SOA')
            return false;
        return true;
    }

    public static function GetRecordList($zone)
    {
        global $g_domains, $g_caching_engine, $g_caching_engine_instance, $g_retry_on_error;
        if (!array_key_exists($zone, $g_domains))
            Error("No such zone: " . $zone);

        if (!Auth::IsAuthorizedToRead($zone))
            return array();

        // Check if zone data exist in cache
        if ($g_caching_engine_instance->IsCached($zone))
        {
            // There is something in the zone cache
            Debug('Zone ' . $zone . ' exist in cache, checking if SOA record is identical');
            $current_soa = self::GetSOAFromData(get_zone_soa($zone));
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
                        $current_soa = self::GetSOAFromData(get_zone_soa($zone));
                        if ($current_soa !== NULL)
                        {
                            CommonUI::DisplayWarning("Transfer NS for " . $zone . " had troubles returning SOA record, had to retry $current_retry times, check your network");
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
                    Audit::Write("display", $zone, "(cached)");
                    Common::IncrementStat('display_zone_cached');
                    return $result;
                }
            }
        } else if ($g_caching_engine !== NULL)
        {
            Debug('Zone ' . $zone . ' does not exist in cache, running full zone transfer');
        }

        Audit::Write("display", $zone, "(full transfer)");
        Common::IncrementStat('display_zone');
        Debug('Running full zone transfer for: ' . $zone);
        $data = get_zone_data($zone);
        $soa = self::GetSOAFromData($data);
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
                    $soa = self::GetSOAFromData($data);
                    if ($soa !== NULL)
                    {
                        CommonUI::DisplayWarning("Transfer NS for " . $zone . " had troubles returning SOA record during AXFR, had to retry $current_retry times, check your network");
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

        if (count($data) > 0 && !self::CheckIfZoneIsComplete($data))
            CommonUI::DisplayWarning("Transfer NS for " . $zone . " didn't return full zone, last SOA record is missing, zone data are incomplete");

        return $data;
    }

    public static function GetRecordListTable($parent, $domain)
    {
        global $g_editable, $g_show_hidden_types, $g_hidden_record_types, $g_hidden_types_present, $g_total_records_count, $g_hidden_records_count, $g_enable_idn;
        $table = new HtmlTable($parent);
        $table->Condensed = true;
        $table->Headers = [ "", "Record", "TTL", "Scope", "Type", "Value", "Options" ];
        $table->ClassName = 'table table-bordered table-hover table-sm';
        $table->SetColumnWidth(0, '28px'); // Select
        $table->SetColumnWidth(3, '80px'); // Scope
        $table->SetColumnWidth(4, '80px'); // Type
        $table->SetColumnWidth(6, '80px'); // Options
        $records = self::GetRecordList($domain);
        $is_editable = Zones::IsEditable($domain) && Auth::IsAuthorizedToWrite($domain);
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

            // Convert record name to UTF-8 for display
            if ($g_enable_idn)
            {
                $record[0] = IDNConverter::fqdnToUTF8($record[0]);
                // Also convert any domain names in the value field for certain record types
                if (in_array($record[3], array('CNAME', 'NS', 'MX', 'PTR')))
                {
                    // For MX records, we need to preserve the priority number
                    if ($record[3] == 'MX' && preg_match('/^(\d+)\s+(.+)$/', $record[4], $matches))
                        $record[4] = $matches[1] . ' ' . IDNConverter::fqdnToUTF8($matches[2]);
                    else
                        $record[4] = IDNConverter::fqdnToUTF8($record[4]);

                }
            }

            $display_record0 = htmlspecialchars($record[0], ENT_QUOTES, 'UTF-8');
            $display_record1 = htmlspecialchars($record[1], ENT_QUOTES, 'UTF-8');
            $display_record2 = htmlspecialchars($record[2], ENT_QUOTES, 'UTF-8');
            $display_record3 = htmlspecialchars($record[3], ENT_QUOTES, 'UTF-8');
            $display_record4 = htmlspecialchars($record[4], ENT_QUOTES, 'UTF-8');
            $display_domain = htmlspecialchars($domain, ENT_QUOTES, 'UTF-8');
            $delete_confirmation = htmlspecialchars('Are you sure you want to delete ' . $record[0] . '?', ENT_QUOTES, 'UTF-8');

            if (!$is_editable || !in_array($record[3], $g_editable))
            {
                $select_record = '';
                $record[] = '';
            } else
            {
                // We need to use the original ASCII version in the URLs
                $ascii_record0 = $record[0];
                $ascii_record4 = $record[4];
                if ($g_enable_idn)
                {
                    $ascii_record0 = IDNConverter::fqdnToASCII($record[0]);
                    if (in_array($record[3], array('CNAME', 'NS', 'MX', 'PTR')))
                    {
                        if ($record[3] == 'MX' && preg_match('/^(\d+)\s+(.+)$/', $record[4], $matches))
                            $ascii_record4 = $matches[1] . ' ' . IDNConverter::fqdnToASCII($matches[2]);
                        else
                            $ascii_record4 = IDNConverter::fqdnToASCII($record[4]);
                    } else
                    {
                        $ascii_record4 = $record[4];
                    }
                }

                $delete_value = $ascii_record0 . " " . $record[1] . " " . $record[3] . " " . $ascii_record4;
                $select_record = '<input type="checkbox" class="record-select" value="' . htmlspecialchars($delete_value, ENT_QUOTES, 'UTF-8') . '">';
                $delete_record = '<a href="#" data-confirm="' . $delete_confirmation . '" data-delete="' . htmlspecialchars($delete_value, ENT_QUOTES, 'UTF-8') .
                                 '" onclick="return submitDeleteRecord(this)"><span class="bi bi-trash" title="Delete"></span></a>';
                $delete_record_with_ptr = '';
                if ($has_ptr && $record[3] == 'A')
                {
                    // Optional button to delete record together with PTR record, show only if there are PTR zones in cfg
                    $delete_record_with_ptr = '<a href="#" data-confirm="' . $delete_confirmation . '" data-delete="' . htmlspecialchars($delete_value, ENT_QUOTES, 'UTF-8') .
                                              '" data-ptr="true" onclick="return submitDeleteRecord(this)"><span style="color: #ff0000;" class="bi bi-trash" title="Delete together with associated PTR record (if any exist)"></span></a>';
                }
                $large_space = '&nbsp;&nbsp;';
                $record[] = $delete_record . $large_space . '<a href="index.php?action=edit&domain=' . $display_domain . '&key=' .
                            urlencode($ascii_record0) . "&ttl=" . $record[1] . "&type=" . $record[3] . "&value=" . urlencode($ascii_record4) .
                            "&old=" . urlencode($ascii_record0 . " " . $record[1] . " " . $record[3] . " " . $ascii_record4) .
                            '"><span title="Edit" class="bi bi-pencil"></span></a>' . $large_space . $delete_record_with_ptr;
            }
            $record[0] = $display_record0;
            $record[1] = $display_record1;
            $record[2] = $display_record2;
            $record[3] = $display_record3;
            $record[4] = '<span class="value">' . $display_record4 . '</span>';
            array_unshift($record, $select_record);
            $table->AppendRow($record);
        }
        if ($is_editable) {
            $add = '<a href="index.php?action=new&domain=' . $domain . '"><span title="Add New" class="bi bi-plus"></span></a>';
            $table->AppendRow(['', '', '', '', '', '', $add]);
        }
        return $table;
    }

    //! This return similar results to GetRecordListTable but without option buttons in format friendly for exporting
    public static function GetRecordListTablePlainFormat($parent, $domain)
    {
        $table = new HtmlTable($parent);
        $table->Headers = [ "Record", "TTL", "Scope", "Type", "Value" ];
        $records = self::GetRecordList($domain);
        foreach ($records as $record)
        {
            $table->AppendRow($record);
        }
        return $table;
    }
}
