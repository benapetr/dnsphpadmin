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
require_once("debug.php");
require_once("nsupdate.php");
require_once("config.php");

//! Wrapper around nsupdate from nsupdate.php that checks if there are custom TSIG overrides for given domain
function ProcessNSUpdateForDomain($input, $domain)
{
    global $g_domains;
    if (!array_key_exists($domain, $g_domains))
        die("No such domain: " . $domain);
    $tsig = NULL;
    $tsig_key = NULL;
    $domain_info = $g_domains[$domain];
    Debug("Processing update for: " . $domain);
    if (array_key_exists("tsig", $domain_info))
        $tsig = $domain_info["tsig"];
    if (array_key_exists("tsig_key", $domain_info))
        $tsig_key = $domain_info["tsig_key"];
    return nsupdate($input, $tsig, $tsig_key);
}

function ShowError($form, $txt)
{
    $msg = new BS_Alert('FATAL: ' . $txt, 'danger', $form);
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

    if (!IsEditable($g_selected_domain))
        Error("Domain $g_selected_domain is not writeable");

    if (!IsAuthorizedToWrite($g_selected_domain))
        Error("You are not authorized to edit $g_selected_domain");
    
    $record = $_GET["delete"];

    if (psf_string_contains($record, "\n"))
        Error("Invalid delete string");

    $input = "server " . $g_domains[$g_selected_domain]["update_server"] . "\n";
    $input .= "update delete " . $record . "\nsend\nquit\n";
    ProcessNSUpdateForDomain($input, $g_selected_domain);
    WriteToAuditFile("delete", $record);
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

    if (!IsEditable($zone))
        Error("Domain $zone is not writeable");

    if (!IsAuthorizedToWrite($zone))
        Error("You are not authorized to edit $zone");

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

    if (!IsValidRecordType($type))
        Error("Type $type is not a valid DNS record type");

    if (!is_numeric($ttl))
        Error('TTL must be a number');

    $input = "server " . $g_domains[$zone]['update_server'] . "\n";
    if ($_POST['submit'] == 'Create')
    {
        $input .= ProcessInsertFromPOST($zone, $record, $value, $type, $ttl);
        $input .= "send\nquit\n";
        $result = ProcessNSUpdateForDomain($input, $zone);
        if (strlen($result) > 0)
            Debug("result: " . $result);
        WriteToAuditFile('create', $record . "." . $zone . " " . $ttl . " " . $type . " " . $value);
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
        $result = ProcessNSUpdateForDomain($input, $zone);
        if (strlen($result) > 0)
            Debug("result: " . $result);
        WriteToAuditFile('replace_delete', $_POST["old"]);
        WriteToAuditFile('replace_create', $record . "." . $zone . " " . $ttl . " " . $type . " " . $value);
        $form->AppendObject(new BS_Alert("Successfully replaced " . $_POST["old"] . " with " . $record . "." . $zone . " " .
                                         $ttl . " " . $type . " " . $value));
        return;
    }
    Error("Unknown modify mode");
}

function GetInsertForm($parent, $edit_mode = false, $default_key = "", $default_ttl = "3600", $default_type = "A", $default_value = "")
{
    global $g_selected_domain, $g_domains, $g_editable;
    HandleEdit($parent);
    if (psf_string_endsWith($g_selected_domain, ".in-addr.arpa"))
        $default_type = "PTR";
    $form = new Form("index.php?action=new", $parent);
    $form->Method = FormMethod::Post;
    $layout = new HtmlTable($form);
    $layout->BorderSize = 0;
    $layout->Headers = [ "Record", "Zone", "TTL", "Type", "Value" ];
    $form_items = [];
    $form_items[] = new BS_TextBox("record", $default_key, NULL, $layout);
    $dl = new ComboBox("zone", $layout);
    if ($edit_mode)
    {
        if ($g_selected_domain === NULL)
        {
            Error("No domain selected");
        }
        $dl->AddDefaultValue($g_selected_domain, "." . $g_selected_domain);
        $dl->Enabled = false;
    } else
    {
        foreach ($g_domains as $key => $info)
        {
            if (!IsAuthorizedToWrite($key))
                continue;
            if ($g_selected_domain == $key)
                $dl->AddDefaultValue($key, "." . $key);
            else
                $dl->AddValue($key, '.' . $key);
        }
    }
    $form_items[] = $dl;
    $form_items[] = new BS_TextBox("ttl", $default_ttl, NULL, $layout);
    $tl = new ComboBox("type", $layout);
    $types = $g_editable;
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
    //$form->AppendObject(new BS_CheckBox("ptr", "true", false, NULL, $form, "Create PTR record for this (works only with A records)"));
    if (isset($_GET["old"]))
    $form->AppendObject(new Hidden("old", htmlspecialchars($_GET["old"])));
    if ($edit_mode)
        $form->AppendObject(new BS_Button("submit", "Edit"));
    else
        $form->AppendObject(new BS_Button("submit", "Create"));
    return $form;
}

function HandleBatch($parent)
{
    global $g_domains;
    if (!isset($_POST["submit"]))
        return;
    
    $zone = $_POST["zone"];
    if (!Check($form, $zone, "Zone"))
        return;

    if (!IsEditable($zone))
        Error("Domain $zone is not writeable");

    if (!IsAuthorizedToWrite($zone))
        Error("You are not authorized to edit $zone");

    $record = $_POST["record"];
    if ($record === NULL)
        Error("No zone selected");

    $input = "server " . $g_domains[$zone]["update_server"] . "\n";
    foreach (explode("\n", $record) as $line)
    {
        if (!psf_string_startsWith($line, "update "))
        {
            Error("Illegal operation for nsupdate, only update is allowed: " . $line);
            return;
        }
    }
    $input .= $record . "\n";
    $input .= "send\nquit\n";
    ProcessNSUpdateForDomain($input, $zone);
    $batch_file = GenerateBatch($input);
    if ($batch_file == NULL)
    {
        $log = str_replace("\n", "; ", $record);
        $log = str_replace("\r", "", $log);
        WriteToAuditFile("batch", "zone: " . $zone . ": " . $log);
    } else
    {
        WriteToAuditFile("batch", "zone: " . $zone . ": " . $batch_file);
    }
    $parent->AppendObject(new BS_Alert("Successfully executed batch operation on zone " . $zone));
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

function GetBatchForm($parent)
{
    global $g_selected_domain, $g_domains, $g_editable;
    HandleBatch($parent);
    $form = new Form("index.php?action=batch", $parent);
    $form->Method = FormMethod::Post;
    $layout = new HtmlTable($form);
    $layout->BorderSize = 0;
    $dl = new ComboBox("zone", $layout);
    foreach ($g_domains as $key => $info)
    {
        if (!IsAuthorizedToWrite($key))
            continue;
        if ($g_selected_domain == $key)
            $dl->AddDefaultValue($key, $key);
        else
            $dl->AddValue($key, $key);
    }
    $layout->Width = "600px";
    $layout->AppendRow( [ "Note: only update statements are allowed, don't put send there, it will be there automatically" ] );
    $layout->AppendRow( [ $dl ] );
    $input = new BS_TextBox("record", $default_key, NULL, $layout);
    $input->SetMultiline();
    $layout->AppendRow( [ $input ] );
    $form->AppendObject(new BS_Button("submit", "Submit"));
    return $form;
}
