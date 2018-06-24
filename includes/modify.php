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
require_once("nsupdate.php");
require_once("fatal.php");
require_once("config.php");

function ShowError($form, $txt)
{
    $msg = new BS_Alert("FATAL: " . $txt, "danger", $form);
}

function Check($form, $label, $name)
{
    if ($label === NULL || strlen($label) == 0)
    {
        ShowError($form, $name . " must not be empty");
        return false;
    }
    return true;
}

function ProcessDelete($well)
{
    global $g_domains, $g_selected_domain;
    if (!isset($_GET["delete"]))
        return;

    if (strlen($g_selected_domain) == 0)
        Error("No domain");
    
    $record = $_GET["delete"];

    if (psf_string_contains($record, "\n"))
        Error("Invalid delete string");

    $input = "server " . $g_domains[$g_selected_domain]["transfer_server"] . "\n";
    $input .= "update delete " . $record . "\nsend\nquit\n";
    nsupdate($input);
    $well->AppendObject(new BS_Alert("Successfully deleted record " . $record));
}

function ProcessInsertFromPOST($zone, $record, $value, $type, $ttl)
{
    $input = "";
    
    if (strlen($record) == 0)
        $input .= "update add " . $zone . " " . $ttl . " " . $type . " " . $value . "\n";
    else
        $input .= "update add " . $record . "." . $zone . " " . $ttl . " " . $type . " " . $value . "\n";
    return $input;
}

function HandleEdit($form)
{
    global $g_domains;
    if (!isset($_POST["submit"]))
        return;
    
    $zone = $_POST["zone"];
    if (!Check($form, $zone, "Zone"))
        return;
    $record = $_POST["record"];
    if ($record === NULL)
        $record = "";
    $ttl = $_POST["ttl"];
    if (!Check($form, $ttl, "ttl"))
        return;
    $value = $_POST["value"];
    if (!Check($form, $value, "Value"))
        return;
    $type = $_POST["type"];
    if (!Check($form, $type, "Type"))
        return;

    $input = "server " . $g_domains[$zone]["transfer_server"] . "\n";
    if ($_POST["submit"] == "Create")
    {
        $input .= ProcessInsertFromPOST($zone, $record, $value, $type, $ttl);
        $input .= "send\nquit\n";
        nsupdate($input);
        $form->AppendObject(new BS_Alert("Successfully inserted record " . $record . "." . $zone));
        return;
    } else if ($_POST["submit"] == "Edit")
    {
        if (!isset($_POST["old"]))
            Error("Missing old record necessary for update");
        // First delete the existing record
        $input .= "update delete " . $_POST["old"] . "\n";
        $input .= ProcessInsertFromPOST($zone, $record, $value, $type, $ttl);
        $input .= "send\nquit\n";
        nsupdate($input);
        $form->AppendObject(new BS_Alert("Successfully replaced " . $_POST["old"] . " with " . $record . "." . $zone . " " .
                                         $ttl . " " . $type . " " . $value));
        return;
    }
    Error("Unknown modify mode");
}

function GetInsertForm($parent, $edit_mode = false, $default_key = "", $default_ttl = "3600", $default_type = "A", $default_value = "")
{
    global $g_selected_domain, $g_domains;
    HandleEdit($parent);
    $form = new Form("index.php?action=new", $parent);
    $form->Method = FormMethod::Post;
    $layout = new HtmlTable($form);
    $layout->BorderSize = 0;
    $layout->Headers = [ "Record", "Zone", "TTL", "Type", "Value" ];
    $form_items = [];
    $form_items[] = new BS_TextBox("record", $default_key, NULL, $layout);
    $dl = new ComboBox("zone", $layout);
    foreach ($g_domains as $key => $info)
    {
        if ($g_selected_domain == $key)
            $dl->AddDefaultValue($key, "." . $key);
        else
            $dl->AddValue($key, '.' . $key);
    }
    $form_items[] = $dl;
    $form_items[] = new BS_TextBox("ttl", $default_ttl, NULL, $layout);
    $tl = new ComboBox("type", $layout);
    $types = [ "A", "AAAA", "NS", "PTR", "SRV", "TXT", "SPF" ];
    foreach ($types as $type)
    {
        if ($default_type == $type)
            $tl->AddDefaultValue($type);
        else
            $tl->AddValue($type);
    }
    $form_items[] = $tl;
    $form_items[] = new BS_TextBox("value", $default_value, NULL, $layout);
    $layout->AppendRow($form_items);
    $form->AppendObject(new BS_CheckBox("ptr", "true", false, NULL, $form, "Create PTR record for this (works only with A records)"));
    if (isset($_GET["old"]))
    $form->AppendObject(new Hidden("old", $_GET["old"]));
    if ($edit_mode)
        $form->AppendObject(new BS_Button("submit", "Edit"));
    else
        $form->AppendObject(new BS_Button("submit", "Create"));
    return $form;
}

function GetEditForm($parent)
{
    global $g_selected_domain;
    $k = $_GET["key"];
    $suffix = $g_selected_domain;
    if (psf_string_endsWith($k, $suffix))
        $k = substr($k, 0, strlen($k) - strlen($suffix));
    if (psf_string_endsWith($k, $suffix . "."))
        $k = substr($k, 0, strlen($k) - strlen($suffix) - 1);
    while (psf_string_endsWith($k, "."))
        $k = substr($k, 0, strlen($k) - 1);
    return GetInsertForm($parent, true, $k, $_GET["ttl"], $_GET["type"], $_GET["value"]);
}
