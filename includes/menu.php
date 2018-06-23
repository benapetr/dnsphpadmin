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

function GetMenu($parent)
{
    global $g_action;
    $menu_items = [
                    "<a href='index.php'>Zone overview</a>",
                    "<a href='index.php?action=manage'>Manage zone</a>",
                    "<a href='index.php?action=new'>Insert / edit record</a>"
                  ];
    $menu = new BS_Tabs($menu_items, $parent);
    switch ($g_action)
    {
        case "manage":
            $menu->SelectedTab = 1;
            break;
        case "new":
            $menu->SelectedTab = 2;
            break;
    }
    return $menu;
}