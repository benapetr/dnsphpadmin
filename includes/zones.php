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

// Namespace for all sorts of zone (domain) operations
class Zones
{
    public static function GetZoneList()
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

    public static function IsEditable($domain)
    {
        global $g_domains;
        if (!array_key_exists($domain, $g_domains))
            die("No such zone: $domain");

        $domain_info = $g_domains[$domain];

        if (array_key_exists('read_only', $domain_info) && $domain_info['read_only'] === true)
            return false;

        return true;
    }

    public static function GetZoneForFQDN($fqdn)
    {
        global $g_domains;
        do
        {
            if (!array_key_exists($fqdn, $g_domains))
            {
                $fqdn= substr($fqdn, strpos($fqdn, '.') + 1);
                continue;
            }
            return $fqdn;
        } while (psf_string_contains($fqdn, '.'));
        return NULL;
    }

    public static function HasPTRZones()
    {
        global $g_domains;
        foreach ($g_domains as $key => $info)
        {
            if (psf_string_endsWith($key, ".in-addr.arpa"))
                return true;
        }
        return false;
    }

    public static function GetDefaultTTL($domain)
    {
        global $g_default_ttl, $g_domains;

        if ($domain === NULL)
            return $g_default_ttl;

        if (!array_key_exists($domain, $g_domains))
            die("No such zone: $domain");

        $domain_info = $g_domains[$domain];

        if (array_key_exists('ttl', $domain_info))
            return $domain_info['ttl'];

        return $g_default_ttl;
    }
}

