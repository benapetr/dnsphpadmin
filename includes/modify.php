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
    $form->AppendObject(new BS_Alert("Successfully deleted record " . $record));
}

function HandleEdit($form)
{
    global $g_domains;
    if (!isset($_POST["submit"]))
        return;
    
    if ($_POST["submit"] == "Create")
    {
        $record = $_POST["record"];
        if (!Check($form, $record, "Record"))
            return;
        $zone = $_POST["zone"];
        if (!Check($form, $zone, "Zone"))
            return;
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
        $input .= "update add " . $record . "." . $zone . " " . $ttl . " " . $type . " " . $value . "\nsend\nquit\n";
        nsupdate($input);
        $form->AppendObject(new BS_Alert("Successfully inserted record " . $record . "." . $zone));
    }
}

function GetInsertForm($parent)
{
    global $g_selected_domain, $g_domains;
    HandleEdit($parent);
    $form = new Form("index.php?action=new", $parent);
    $form->Method = FormMethod::Post;
    $layout = new HtmlTable($form);
    $layout->BorderSize = 0;
    $layout->Headers = [ "Record", "Zone", "TTL", "Type", "Value" ];
    $form_items = [];
    $form_items[] = new BS_TextBox("record", NULL, NULL, $layout);
    $dl = new ComboBox("zone", $layout);
    foreach ($g_domains as $key => $info)
    {
        if ($g_selected_domain == $key)
            $dl->AddDefaultValue($key, "." . $key);
        else
            $dl->AddValue($key, '.' . $key);
    }
    $form_items[] = $dl;
    $form_items[] = new BS_TextBox("ttl", "3600", NULL, $layout);
    $tl = new ComboBox("type", $layout);
    $tl->AddValue("A");
    $tl->AddValue("AAAA");
    $tl->AddValue("NS");
    $tl->AddValue("PTR");
    $tl->AddValue("SRV");
    $tl->AddValue("TXT");
    $tl->AddValue("SPF");
    $form_items[] = $tl;
    $form_items[] = new BS_TextBox("value", NULL, NULL, $layout);
    $layout->AppendRow($form_items);
    $form->AppendObject(new BS_CheckBox("ptr", "true", false, NULL, $form, "Create PTR record for this (works only with A records)"));
    $form->AppendObject(new BS_Button("submit", "Create"));
    return $form;
}
