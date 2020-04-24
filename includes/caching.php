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

$g_caching_engine_instance = NULL;

//! This is a base class of caching engine and can be used as instance for NULL cachine engine (no caching)
class PHPDNS_CachingEngine
{
    function Initialize()
    {
        
    }

    function GetEngineName()
    {
        return 'NULL';
    }

    function IsCached($zone)
    {
        return false;
    }

    function GetSOA($zone)
    {
        return NULL;
    }

    function GetData($zone)
    {
        die("NULL caching engine doesn't support retrieving of data");
    }

    function Drop($zone)
    {
        
    }

    function IncrementStat($stat)
    {
        
    }

    function CacheZone($zone, $soa, $data)
    {
        // nothing to do
    }
}
