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

class PHPDNS_CachingEngine_Memcache extends PHPDNS_CachingEngine
{
    private $memcache = NULL;

    function Initialize()
    {
        global $g_debug, $g_caching_memcached_port, $g_caching_memcached_host;
        $this->memcache = new Memcache();
        $this->memcache->connect($g_caching_memcached_host, $g_caching_memcached_port) or die ('Unable to connect to memcached server at ' . $g_caching_memcached_host . ':' . $g_caching_memcached_port);
        if ($g_debug)
        {
            Debug('memcache version: ' . $this->memcache->getVersion());
        }
    }

    function GetEngineName()
    {
        return 'memcache';
    }

    function IsCached($zone)
    {
        return $this->memcache->get($this->getPrefix() . 'soa_' . $zone) !== false;
    }

    function GetSOA($zone)
    {
        return $this->memcache->get($this->getPrefix() . 'soa_' . $zone);
    }

    function CacheZone($zone, $soa, $data)
    {
        global $g_caching_memcached_expiry;
        Debug('Storing zone ' . $zone . " (SOA $soa) to memcache");
        if (!$this->memcache->set($this->getPrefix() . 'soa_' . $zone, $soa, $g_caching_memcached_expiry) ||
            !$this->memcache->set($this->getPrefix() . 'data_' . $zone, $data, $g_caching_memcached_expiry))
        {
            die('Unable to store data in memcache');
        }
    }

    function GetData($zone)
    {
        return $this->memcache->get($this->getPrefix() . 'data_' . $zone);
    }

    function Drop($zone)
    {
        $this->memcache->delete($this->getPrefix() . 'data_' . $zone);
        $this->memcache->delete($this->getPrefix() . 'soa_' . $zone);
    }

    function IncrementStat($stat)
    {
        if ($this->memcache->increment($this->getPrefix() . 'stat_' . $stat) === false)
        {
            // This statistic is not in memcache yet
            $this->memcache->set($this->getPrefix() . 'stat_' . $stat, 1);
        }
    }

    private function getPrefix()
    {
        global $g_auth_session_name;
        return $g_auth_session_name . "_";
    }
}
