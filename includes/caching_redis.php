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

class PHPDNS_CachingEngine_Redis extends PHPDNS_CachingEngine
{
    private $redis = NULL;

    function Initialize()
    {
        global $g_debug, $g_caching_redis_host, $g_caching_redis_port, $g_caching_redis_password, $g_caching_redis_database;
        $this->redis = new Redis();
        if (!$this->redis->connect($g_caching_redis_host, $g_caching_redis_port))
            die('Unable to connect to Redis at ' . $g_caching_redis_host . ':' . $g_caching_redis_port);
        if ($g_caching_redis_password !== NULL)
        {
            if (!$this->redis->auth($g_caching_redis_password))
                die('Redis authentication failed');
        }
        if ($g_caching_redis_database !== 0)
            $this->redis->select($g_caching_redis_database);
        if ($g_debug)
        {
            $info = $this->redis->info('server');
            Debug('redis version: ' . $info['redis_version']);
        }
    }

    function GetEngineName()
    {
        return 'redis';
    }

    function IsCached($zone)
    {
        return (bool)$this->redis->exists($this->getPrefix() . 'soa_' . $zone);
    }

    function GetSOA($zone)
    {
        return $this->redis->get($this->getPrefix() . 'soa_' . $zone);
    }

    function CacheZone($zone, $soa, $data)
    {
        global $g_caching_redis_expiry;
        Debug('Storing zone ' . $zone . " (SOA $soa) to redis");
        if ($g_caching_redis_expiry > 0)
        {
            $ok = $this->redis->setex($this->getPrefix() . 'soa_' . $zone, $g_caching_redis_expiry, $soa) &&
                  $this->redis->setex($this->getPrefix() . 'data_' . $zone, $g_caching_redis_expiry, $data);
        } else
        {
            $ok = $this->redis->set($this->getPrefix() . 'soa_' . $zone, $soa) &&
                  $this->redis->set($this->getPrefix() . 'data_' . $zone, $data);
        }
        if (!$ok)
            die('Unable to store data in Redis');
    }

    function GetData($zone)
    {
        return $this->redis->get($this->getPrefix() . 'data_' . $zone);
    }

    function Drop($zone)
    {
        $this->redis->del([$this->getPrefix() . 'data_' . $zone,
                           $this->getPrefix() . 'soa_' . $zone]);
    }

    function IncrementStat($stat)
    {
        // Redis INCR atomically creates the key (starting from 0) if it doesn't exist
        $this->redis->incr($this->getPrefix() . 'stat_' . $stat);
    }

    private function getPrefix()
    {
        global $g_auth_session_name;
        return $g_auth_session_name . "_";
    }
}
