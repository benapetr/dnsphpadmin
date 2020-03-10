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

require_once("caching.php");

class PHPDNS_CachingEngine_Memcached extends PHPDNS_CachingEngine
{
    private $memcached = NULL;

    function Initialize()
    {
        global $g_debug, $g_caching_memcached_port, $g_caching_memcached_host;
        $this->memcached = new Memcached();
        $this->memcached->addServer($g_caching_memcached_host, $g_caching_memcached_port);
        if ($g_debug)
        {
            $memcached_version = $this->memcached->getVersion();
            Debug('memcached version: ' . reset($memcached_version));
        }
    }

    function GetEngineName()
    {
        return 'memcached';
    }

    function IsCached($zone)
    {
        return $this->memcached->get($this->getPrefix() . 'soa_' . $zone) !== false;
    }

    function GetSOA($zone)
    {
        return $this->memcached->get($this->getPrefix() . 'soa_' . $zone);
    }

    function CacheZone($zone, $soa, $data)
    {
        global $g_caching_memcached_expiry;
        Debug('Storing zone ' . $zone . " (SOA $soa) to memcache");
        if (!$this->memcached->set($this->getPrefix() . 'soa_' . $zone, $soa, $g_caching_memcached_expiry) ||
            !$this->memcached->set($this->getPrefix() . 'data_' . $zone, $data, $g_caching_memcached_expiry))
        {
            die('Unable to store data in memcached: ' . $this->memcached->getResultMessage());
        }
    }

    function GetData($zone)
    {
        return $this->memcached->get($this->getPrefix() . 'data_' . $zone);
    }

    function Drop($zone)
    {
        $this->memcached->delete($this->getPrefix() . 'data_' . $zone);
        $this->memcached->delete($this->getPrefix() . 'soa_' . $zone);
    }

    function IncrementStat($stat)
    {
        // increment doesn't seem to be able to work if key doesn't exist, so let's first check it does
        if ($this->memcached->get($this->getPrefix() . 'stat_' . $stat) === false)
            $this->memcached->set($this->getPrefix() . 'stat_' . $stat, 1);
        else
            $this->memcached->increment($this->getPrefix() . 'stat_' . $stat);
    }

    private function getPrefix()
    {
        global $g_auth_session_name;
        return $g_auth_session_name . "_";
    }
}
