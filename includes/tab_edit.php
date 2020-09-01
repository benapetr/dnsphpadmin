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
require_once("debug.php");
require_once("validator.php");
require_once("modify.php");
require_once("zones.php");

class TabEdit
{
    //! This function checks if there is a request to edit any record in POST data and if yes, it processes it
    public static function Process($form)
    {
        global $g_domains;
        if (!isset($_POST["submit"]))
            return;
        
        $zone = $_POST["zone"];

        if (!Zones::IsEditable($zone))
            Error("Domain $zone is not writeable");

        if (!IsAuthorizedToWrite($zone))
            Error("You are not authorized to edit $zone");

        if (!CheckEmpty($form, $zone, "Zone"))
            return;
        $record = $_POST["record"];
        if ($record === NULL)
            $record = "";
        $ttl = $_POST["ttl"];
        if (!CheckEmpty($form, $ttl, "ttl"))
            return;
        $value = $_POST["value"];
        if (!CheckEmpty($form, $value, "Value"))
            return;
        $type = $_POST["type"];
        if (!CheckEmpty($form, $type, "Type"))
            return;

        if (!IsValidRecordType($type))
            Error("Type $type is not a valid DNS record type");

        if (!is_numeric($ttl))
            Error('TTL must be a number');

        // Sanitize input from user
        $record = SanitizeHostname($record);

        if (!IsValidHostName($record))
            Error('Invalid hostname: ' . $record);

        $comment = NULL;
        if (isset($_POST["comment"]))
            $comment = $_POST["comment"];

        if ($_POST['submit'] == 'Create')
        {
            if (DNS_CreateRecord($zone, $record, $value, $type, $ttl, $comment))
                $form->AppendObject(new BS_Alert("Successfully inserted record " . $record . "." . $zone));
        } else if ($_POST["submit"] == "Edit")
        {
            if (!isset($_POST["old"]))
                Error("Missing old record necessary for update");
            if (DNS_ModifyRecord($zone, $record, $value, $type, $ttl, $comment, $_POST["old"]))
            {
                $form->AppendObject(new BS_Alert("Successfully replaced " . $_POST["old"] . " with " . $record . "." . $zone . " " .
                                                 $ttl . " " . $type . " " . $value));
            }
            // Delete PTR if wanted
            if (isset($_POST['ptr']) && $_POST['ptr'] === "true")
            {
                if (isset($_POST["old_type"]) && $_POST["old_type"] == "A")
                {
                    // Check if all necessary values are present
                    if (!isset($_POST["old_value"]) || !isset($_POST["old_record"]))
                    {
                        DisplayWarning("PTR record was not deleted, because old_record or old_value was missing");
                    } else
                    {
                        DNS_DeletePTRForARecord($_POST["old_value"], $_POST["old_record"], $comment);
                    }
                } else
                {
                    Debug("Not removing PTR, original type was " . $_POST["old_type"]);
                }
            }
        } else
        {
            Error("Unknown modify mode");
        }

        // Create PTR if wanted
        if (isset($_POST['ptr']) && $_POST['ptr'] === "true")
        {
            if ($type !== "A")
            {
                DisplayWarning('PTR record was not created: PTR record can be only created when you are inserting A record, you created ' . $type . ' record instead');
                return;
            }
            DNS_InsertPTRForARecord($value, $record . '.' . $zone, $ttl, $comment);
        }
    }

    public static function GetInsertForm($parent, $edit_mode = false, $default_key = "", $default_ttl = NULL, $default_type = "A", $default_value = "", $default_comment = "")
    {
        global $g_audit, $g_selected_domain, $g_domains, $g_editable, $g_default_ttl;

        // In case we are returning to insert form from previous insert, make default type the one we used before
        if (isset($_POST['type']))
            $default_type = $_POST['type'];
        else if (isset($_GET['type']))
            $default_type = $_GET['type'];
        else if (psf_string_endsWith($g_selected_domain, ".in-addr.arpa"))
            $default_type = "PTR";
        
        // Reuse some values from previous POST request
        if (isset($_POST['comment']))
            $default_comment = $_POST['comment'];

        // If ttl is not specified use default one from config file
        if ($default_ttl === NULL)
            $default_ttl = strval($g_default_ttl);
        
        $form = new Form("index.php?action=new", $parent);
        $form->Method = FormMethod::Post;
        $layout = new HtmlTable($form);
        $layout->BorderSize = 0;
        $layout->ColWidth[4] = '40%';
        $layout->Headers = [ "Record", "Zone", "TTL", "Type", "Value" ];
        if ($g_audit)
            $layout->Headers[] = 'Comment';
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
        $value_box = new BS_TextBox("value", $default_value, NULL, $layout);
        $value_box->Size = 45;
        $form_items[] = $value_box;
        if ($g_audit)
        {
            $comment = new BS_TextBox("comment", $default_comment, NULL, $layout);
            $comment->Placeholder = 'Optional comment for audit log';
            $comment->Size = 80;
            $form_items[] = $comment;
        }
        $layout->AppendRow($form_items);
        if (Zones::HasPTRZones())
        {
            if (!$edit_mode)
                $form->AppendObject(new BS_CheckBox("ptr", "true", false, NULL, $form, "Create PTR record for this IP (works only with A records)"));
            else
                $form->AppendObject(new BS_CheckBox("ptr", "true", false, NULL, $form, "Modify underlying PTR records (works only if original, new or both values are A records)"));
        }
        if (isset($_GET["old"]))
            $form->AppendObject(new Hidden("old", htmlspecialchars($_GET["old"])));
        
        if ($edit_mode)
        {
            // Preserve old values, we need to work with them when modifying PTR records
            $form->AppendObject(new Hidden("old_record", htmlspecialchars($_GET["key"])));
            $form->AppendObject(new Hidden("old_ttl", htmlspecialchars($default_ttl)));
            $form->AppendObject(new Hidden("old_type", htmlspecialchars($default_type)));
            $form->AppendObject(new Hidden("old_value", htmlspecialchars($default_value)));
        
            $form->AppendObject(new BS_Button("submit", "Edit"));
        } else
        {
            $form->AppendObject(new BS_Button("submit", "Create"));
        }
        return $form;
    }

    public static function GetHelp()
    {
        $help = new DivContainer();
        $help->AppendLine();
        $help->AppendHtmlLine('<a data-toggle="collapse" href="#collapseHelp">Display help</a>');
        $c = new DivContainer($help);
        $c->ClassName = "collapse";
        $c->ID = "collapseHelp";
        $c->AppendHeader("Record", 3);
        $c->AppendHtmlLine('Name of the key you want to add. If you want to create DNS record <code>test.domain.org</code> in zone domain.org, then value of field record will be just <code>test</code>. <b>Do not append zone name to record name, this is done automatically</b>. Record can be also left blank if you want to add a record for zone apex (zone itself), such as MX records.');
        $c->AppendHeader("Zone", 3);
        $c->AppendHtmlLine('Name of zone you want to create record in. In case that subzone exist (for example you want to add record <code>subzone.test.domain.org</code> but subzone <code>test.domain.org</code> exists in dropdown menu), you must create the record withing the subzone, not in the parent zone, otherwise it will not be visible in domain name system. If no subzone exists, then you can create a record <code>subzone.test</code> inside of <code>domain.org</code>.');
        $c->AppendHeader("TTL", 3);
        $c->AppendHtmlLine('Time to live tells caching name servers for how long can this record be cached for. Too low TTL may lead to performance issues as the request to resolve such record will be forwarded to authoritative name server most of the time. Too long TTL can make it complicated to change the value of record, because caching name servers will hold the cached value for too long. If you are not sure which value to pick, leave the default value.');
        $c->AppendHeader("Type", 3);
        $c->AppendHtmlLine('Type of DNS record, following record types are most common:');
        $record_types = new BS_Table($c);
        $record_types->Headers = [ 'Type', 'Description' ];
        $record_types->AppendRow( [ 'A', 'IPv4 record, value of this record is IPv4 address, for example 1.2.3.4' ]);
        $record_types->AppendRow( [ 'AAAA', 'IPv6 record, value of this record is IPv6 address, for example ::1' ]);
        $record_types->AppendRow( [ 'TXT', 'Text record, must be max 255 characters in length, otherwise you need to split it to multiple parts within quotes ("), each part max. 255 characters in size' ]);
        $record_types->AppendRow( [ 'MX', 'Mail server record, value consist of two parts, priority and hostname of mail server, for example: <code>10 mail.domain.org</code>']);
        $record_types->AppendRow( [ 'NS', 'Delegates a record to another name server. If used on zone apex it defines authoritative name servers for a zone.']);
        $record_types->AppendRow( [ 'SSHFP', 'SSH fingerprint, used by SSH client when verifying that target server has authentic fingerprint']);
        $record_types->AppendRow( [ 'CNAME', 'Redirect record to another domain name, this will redirect all record types for given record name and therefore can\'t be used on zone apex']);
        $record_types->AppendRow( [ 'SOA', 'Start of authority record - this record exists only for apex of zone and denotes existence of a zone, it includes administrative data for zone, this record is returned twice in zone transfer, as first and last record']);
        $c->AppendHtmlLine('See <a href="https://en.wikipedia.org/wiki/List_of_DNS_record_types" target="_blank">https://en.wikipedia.org/wiki/List_of_DNS_record_types</a> for a more complete and detailed list');
        $c->AppendHeader("Value", 3);
        $c->AppendHtmlLine('Value of record, format depends on record type');
        $c->AppendHeader("Comment", 3);
        $c->AppendHtmlLine('Optional comment for audit log of DNS tool, this has no effect on DNS server itself. This field is available only if audit subsystem is enabled.');
        //$c->AppendObject(new BS_List);
        return $help;
    }

    public static function GetEditForm($parent)
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

        return self::GetInsertForm($parent, true, $k, $_GET["ttl"], $_GET["type"], $_GET["value"]);
    }
}
