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

require_once("common.php");
require_once("common_ui.php");
require_once("debug.php");
require_once("modify.php");

class TabManage
{
    //! Called from index.php this function is supposed to process deleting if requested in UI
    public static function ProcessDelete($well)
    {
        global $g_domains, $g_selected_domain;
        if (!isset($_GET["delete"]))
            return;
    
        $record = $_GET["delete"];
        
        if (DNS_DeleteRecord($g_selected_domain, $record))
            $well->AppendObject(new BS_Alert("Successfully deleted record " . $record));

        if (isset($_GET["ptr"]) && $_GET["ptr"] == true)
        {
            Debug('PTR record deletion was requested for ' . $record);
            if (!isset($_GET['key']) || !isset($_GET['value']) || !isset($_GET['type']))
            {
                Warning('PTR record was not removed because either key, value or type was not specified');
                return;
            }
            $key = $_GET['key'];
            $type = $_GET['type'];
            $value = $_GET['value'];
            if ($type != 'A')
            {
                Warning('Requested PTR record was not deleted: PTR record can be only deleted when you are changing A record, you deleted ' . $type . ' record instead');
            } else
            {
                DNS_DeletePTRForARecord($value, $key, '');
            }
        }
    }

    public static function GetContents($fc)
    {
        global $g_auth_session_name, $g_domains, $g_selected_domain, $g_total_records_count, $g_hidden_records_count, $g_show_hidden_types, $g_hidden_types_present;

        // Check toggle for hidden
        if (isset($_GET['hidden_types']))
        {
            if ($_GET['hidden_types'] == 'show')
            {
                setcookie($g_auth_session_name . '_show_hidden_types', true);
                $g_show_hidden_types = true;
            } else
            {
                setcookie($g_auth_session_name . '_show_hidden_types', false);
                $g_show_hidden_types = false;
            }
        } else
        {
            // Check if there is a cookie for hidden types
            if (isset($_COOKIE[$g_auth_session_name . '_show_hidden_types']))
                $g_show_hidden_types = $_COOKIE[$g_auth_session_name . '_show_hidden_types'];
        }

        if ($g_selected_domain == null)
        {
            reset($g_domains);
            $g_selected_domain = key($g_domains);
        }
        // First get the record list - this function will fill up g_hidden_types_present variable as well as global counters
        $record_list = GetRecordListTable(NULL, $g_selected_domain);
        $record_count = "";
        if ($g_total_records_count > 0)
        {
            if ($g_hidden_records_count == 0)
                $record_count = " ($g_total_records_count records)";
            else
                $record_count = " ($g_total_records_count records, $g_hidden_records_count hidden)";
        }
        $fc->AppendObject(GetSwitcher($fc));
        $fc->AppendHeader($g_selected_domain . $record_count, 2);
        $fc->AppendHtml('<div class="export_csv"><a href="?action=csv&domain=' . $g_selected_domain . '">Export as CSV</a></div>');
        $fc->AppendObject(GetStatusOfZoneAsNote($g_selected_domain));
        if ($g_hidden_types_present === true)
        {
            // This zone contains some hidden record types, show toggle for user
            if (!$g_show_hidden_types)
                $fc->AppendHtml('<div class="hidden_types">This zone contains record types that are hidden by default, click <a href="?action=manage&domain=' . $g_selected_domain . '&hidden_types=show">here</a> to show them</div>');
            else
                $fc->AppendHtml('<div class="hidden_types">This zone contains record types that are hidden by default, click <a href="?action=manage&domain=' . $g_selected_domain . '&hidden_types=hide">here</a> to hide them</div>');
        }
        $fc->AppendObject($record_list);
    }
}
