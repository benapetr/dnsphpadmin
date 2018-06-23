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
require_once("includes/nsupdate.php");
require_once("config.php");

function GetRecordList($domain)
{
    $records = array();
    $data = explode("\n", get_zone_data($domain));
    foreach ($data as $line)
    {
        if (psf_string_startsWith($line, ";"))
            continue;
        // Sanitize string, we replace all double tabs with single tabs, then replace all tabs with spaces and then replace
        // double spaces, so that each item is separated only with single space
        $line = str_replace("\t", " ", $line);
        while (psf_string_contains($line, "  "))
            $line = str_replace("  ", " ", $line);
        if (strlen(str_replace(" ", "", $line)) == 0)
            continue;
        $records[] = explode(" ", $line, 5);
    }
    return $records;
}

function GetRecordListTable($parent, $domain)
{
    $table = new BS_Table($parent);
    $table->Condensed = true;
    $table->Headers = [ "Record", "TTL", "Scope", "Type", "Value" ];
    $records = GetRecordList($domain);
    foreach ($records as $record)
        $table->AppendRow($record);
    return $table;
}