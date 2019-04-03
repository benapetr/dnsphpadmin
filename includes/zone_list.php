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
require_once("common.php");
require_once("config.php");

function GetZoneList()
{
    global $g_domains;
    $result = [];
    foreach ($g_domains as $domain => $properties)
    {
        if (!IsAuthorizedToRead($domain))
            continue;
        $result[$domain] = [ 'domain' => $domain, 'update_server' =>  $properties['update_server'], 'transfer_server' => $properties['transfer_server'] ];

        if (isset($properties['in_transfer']))
            $result[$domain]['in_transfer'] = $properties['in_transfer'];

        if (isset($properties['maintenance_note']))
            $result[$domain]['maintenance_note'] = $properties['maintenance_note'];

        if (isset($properties['read_only']))
            $result[$domain]['read_only'] = $properties['read_only'];
    }
    return $result;
}

