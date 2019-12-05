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

require_once("common_ui.php");

class TabManage
{
    public static function GetContents($fc)
    {
        global $g_auth_session_name, $g_domains, $g_selected_domain, $g_show_hidden_types, $g_hidden_types_present;

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
        $fc->AppendObject(GetSwitcher($fc));
        $fc->AppendHeader($g_selected_domain, 2);
        $fc->AppendHtml('<div class="export_csv"><a href="?action=csv&domain=' . $g_selected_domain . '">Export as CSV</a></div>');
        $fc->AppendObject(GetStatusOfZoneAsNote($g_selected_domain));
        // First get the record list - this function will fill up g_hidden_types_present variable
        $record_list = GetRecordListTable(NULL, $g_selected_domain);
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
