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
require_once("config.php");

function GetStatusOfZone($domain)
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
        $status .= '<span class="glyphicon glyphicon-refresh" title="In transfer"></span>&nbsp;';
    }
    if (!IsAuthorizedToWrite($domain) || (array_key_exists('read_only', $domain_info) && $domain_info['read_only'] === true))
    {
        $is_ok = false;
        $status .= '<span class="glyphicon glyphicon-floppy-remove" title="Read-Only"></span>&nbsp;';
    }
    if (array_key_exists('maintenance_note', $domain_info))
    {
        $is_ok = false;
        $status .= '<span class="glyphicon glyphicon-alert" title="' . $domain_info['maintenance_note'] . '"></span>&nbsp;';
    }

    if ($is_ok)
        return '<span class="glyphicon glyphicon-ok" title="OK"></span>' . $status;
    return $status;
}

function GetSelectForm($parent)
{
    global $g_domains;
    $table = new BS_Table($parent);
    $table->Headers = [ "Domain name", "Status", "Update server", "Transfer server" ];
    $table->SetColumnWidth(1, '80px');
    foreach ($g_domains as $domain => $properties)
    {
        if (!IsAuthorizedToRead($domain))
            continue;
        $table->AppendRow([ '<a href="?action=manage&domain=' . $domain . '">' . $domain . '</a>', GetStatusOfZone($domain), $properties["update_server"], $properties["transfer_server"] ]);
    }
    return $table;
}

function GetSwitcher($parent)
{
    global $g_selected_domain, $g_domains;
    $switcher = new DivContainer($parent);
    $switcher->AppendHtmlLine("Zone:");
    $c = new ComboBox("switcher", $switcher);
    $c->OnChangeCallback = "reload()";
    foreach ($g_domains as $domain => $properties)
    {
        if (!IsAuthorizedToRead($domain))
            continue;
        if ($g_selected_domain == $domain)
            $c->AddDefaultValue($domain);
        else
            $c->AddValue($domain);
    }
    $js = new Script("", $parent);
    $js->Source = "function reload()\n" .
                  "{" .
                      'var switcher = document.getElementsByName("switcher");' .
                      'window.open("index.php?action=manage&domain=" + switcher[0].value, "_self");' .
                  "}\n";
    return $switcher;
}
