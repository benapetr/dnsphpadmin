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

class TabOverview
{
    private static function getStatusOfZone($domain)
    {
        global $g_domains;
    
        if (!array_key_exists($domain, $g_domains))
            die("No such domain: $domain");
    
        $domain_info = $g_domains[$domain];
    
        $is_ok = true;
        $status = "";
    
        if (array_key_exists('in_transfer', $domain_info) && $domain_info['in_transfer'] === true)
        {
            $is_ok = false;
            $status .= '<span class="bi bi-arrow-repeat" title="In transfer"></span>&nbsp;';
        }
        if (!IsAuthorizedToWrite($domain) || (array_key_exists('read_only', $domain_info) && $domain_info['read_only'] === true))
        {
            $is_ok = false;
            $status .= '<span class="bi bi-lock" title="Read-Only"></span>&nbsp;';
        }
        if (array_key_exists('maintenance_note', $domain_info))
        {
            $is_ok = false;
            $status .= '<span class="bi bi-exclamation-triangle" title="' . $domain_info['maintenance_note'] . '"></span>&nbsp;';
        }
        if (array_key_exists('note', $domain_info))
        {
            $status .= '&nbsp;<span class="bi bi-chat" title="' . $domain_info['note'] . '"></span>&nbsp;';
        }
    
        if ($is_ok)
            return '<span class="bi bi-check-circle" title="OK"></span>' . $status;
        return $status;
    }

    //! Generates a PSF table object with all zones with links to manage each zone, including their status
    public static function GetSelectForm($parent)
    {
        global $g_domains;
        $table = new HtmlTable($parent);
        $table->Headers = [ "Domain name", "Status", "Update server", "Transfer server" ];
        $table->ClassName = 'table table-bordered table-hover table-sm';
        $table->SetColumnWidth(1, '80px');
        foreach ($g_domains as $domain => $properties)
        {
            if (!IsAuthorizedToRead($domain))
                continue;
            $table->AppendRow([ '<a href="?action=manage&domain=' . $domain . '">' . $domain . '</a>', self::getStatusOfZone($domain), $properties["update_server"], $properties["transfer_server"] ]);
        }
        return $table;
    }
}
