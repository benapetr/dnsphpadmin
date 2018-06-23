<?php

// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

require("config.php");
require("includes/menu.php");
require("includes/modify.php");
require("includes/record_list.php");
require("includes/select_form.php");
require_once("psf/psf.php");
require_once("psf/default_config.php");

// Save us some coding
$psf_containers_auto_insert_child = true;

// Global vars
$g_selected_domain = null;
$g_action = null;

$website = new HtmlPage("DNS management");
$website->Style->items["td"]["word-wrap"] = "break-word";
$website->Style->items["td"]["max-width"] = "280px";
bootstrap_init($website);

$fc = new BS_FluidContainer($website);
$fc->AppendHeader("DNS management tool");

if (isset($_GET['action']))
    $g_action = $_GET['action'];
if (isset($_GET['domain']))
    $g_selected_domain = $_GET['domain'];

$fc->AppendObject(GetMenu());

if ($g_action === null)
{
    $fc->AppendHeader("Select a zone to manage", 2);
    $well = new BS_Well($fc);
    $well->AppendObject(GetSelectForm($well));
} else if ($g_action == "manage")
{
    $fc->AppendObject(GetSwitcher());
    if ($g_selected_domain === null)
    {
        $fc->AppendObject(new BS_Alert("Please select a zone to manage"));
    } else
    {
        $fc->AppendHeader($g_selected_domain, 2);
        $well = new BS_Well($fc);
        $well->AppendObject(GetRecordListTable($well, $g_selected_domain));
    }
} else if ($g_action == "new")
{
    $fc->AppendObject(GetInsertForm($fc));
}

$website->PrintHtml();

