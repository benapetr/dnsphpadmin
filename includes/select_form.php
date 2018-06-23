<?php

// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

require_once("psf/psf.php");
require_once("config.php");

function GetSelectForm($parent)
{
    global $g_domains;
    $table = new BS_Table($parent);
    $table->Headers = [ "Domain name", "Update server", "Transfer server" ];
    foreach ($g_domains as $domain => $properties)
        $table->AppendRow([ '<a href="?action=manage&domain=' . $domain . '">' . $domain . '</a>', $properties["update_server"], $properties["transfer_server"] ]);
    return $table;
}

function GetSwitcher($parent)
{
    global $g_selected_domain, $g_domains;
    $switcher = new DivContainer($parent);
    $switcher->AppendHtmlLine("Zone:");
    $c = new ComboBox("switcher", $switcher);
    foreach ($g_domains as $domain => $properties)
        $c->AddValue($domain, $domain);
    return $switcher;
}