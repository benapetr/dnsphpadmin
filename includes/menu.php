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

function GetMenu($parent)
{
    global $g_action, $g_selected_domain;
    $domain = "";
    if ($g_selected_domain !== null)
        $domain = "&domain=" . $g_selected_domain;
    $menu_items = [
                    "<a href='index.php'>Zone overview</a>",
                    "<a href='index.php?action=manage" . $domain . "'>Manage zone</a>",
                    "<a href='index.php?action=new" . $domain. "'>New / edit record</a>",
                    "<a href='index.php?action=batch" . $domain. "'>Batch operations</a>"
                  ];
    $menu = new BS_Tabs($menu_items, $parent);
    switch ($g_action)
    {
        case "manage":
            $menu->SelectedTab = 1;
            break;
        case "new":
        case "edit":
            $menu->SelectedTab = 2;
            break;
        case "batch":
            $menu->SelectedTab = 3;
            break;
    }
    return $menu;
}
