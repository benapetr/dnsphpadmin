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

require_once("modify.php");
require_once("zones.php");

class TabBatch
{
    public static function Process($parent)
    {
        global $g_domains;
        if (!isset($_POST["submit"]))
            return;
        
        $zone = $_POST["zone"];
        if (!CheckEmpty($parent, $zone, "Zone"))
            return;

        if (!Zones::IsEditable($zone))
            Error("Domain $zone is not writeable");

        if (!IsAuthorizedToWrite($zone))
            Error("You are not authorized to edit $zone");

        $record = $_POST["record"];
        if ($record === NULL)
            Error("No zone selected");

        $input = "server " . $g_domains[$zone]["update_server"] . "\n";
        foreach (explode("\n", $record) as $line)
        {
            // Ignore empty
            if (strlen(str_replace(" ", "", $line)) == 0)
                continue;

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
        $comment = NULL;
        if (isset($_POST["comment"]))
            $comment = $_POST["comment"];
        if ($batch_file == NULL)
        {
            $log = str_replace("\n", "; ", $record);
            $log = str_replace("\r", "", $log);
            WriteToAuditFile("batch", "zone: " . $zone . ": " . $log, $comment);
            IncrementStat('batch');
        } else
        {
            WriteToAuditFile("batch", "zone: " . $zone . ": " . $batch_file, $comment);
            IncrementStat('batch');
        }
        $parent->AppendObject(new BS_Alert("Successfully executed batch operation on zone " . $zone));
    }

    public static function GetForm($parent)
    {
        global $g_audit, $g_selected_domain, $g_domains, $g_editable;
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
        $input = new BS_TextBox("record", NULL, NULL, $layout);
        $input->SetMultiline();
        $layout->AppendRow( [ $input ] );
        if ($g_audit)
        {
            $comment = new BS_TextBox("comment", NULL, NULL, $layout);
            $comment->Placeholder = 'Optional comment for audit log';
            $layout->AppendRow( [ $comment ] );
        }
        $form->AppendObject(new BS_Button("submit", "Submit"));
        return $form;
    }
}
