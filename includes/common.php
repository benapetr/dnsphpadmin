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

require_once("debug.php");
require_once("logging.php");
require_once("notifications.php");
require_once("caching_memcache.php");
require_once("caching_memcached.php");
require_once("auth.php");

class Common
{
    public static function Initialize()
    {
        Logging::OpenSyslog();
        self::InitializeCaching();
        Auth::RefreshSession();
    }

    public static function ResourceCleanup()
    {
        Logging::CloseSyslog();
    }

    public static function InitializeCaching()
    {
        global $g_caching_engine, $g_caching_engine_instance;
        switch ($g_caching_engine)
        {
            case NULL:
                $g_caching_engine_instance = new PHPDNS_CachingEngine();
                break;
            case 'memcache':
                $g_caching_engine_instance = new PHPDNS_CachingEngine_Memcache();
                break;
            case 'memcached':
                $g_caching_engine_instance = new PHPDNS_CachingEngine_Memcached();
                break;
            default:
                die('Invalid caching engine: ' . $g_caching_engine);
        }
        Debug('Caching engine: ' . $g_caching_engine_instance->GetEngineName());
        $g_caching_engine_instance->Initialize();
    }

    public static function IncrementStat($stat)
    {
        global $g_caching_stats_enabled, $g_caching_engine_instance;
        if ($g_caching_stats_enabled !== true)
            return;

        if ($g_caching_engine_instance === NULL)
            return;

        $g_caching_engine_instance->IncrementStat($stat);
    }

    //! Display warning message
    public static function Warning($text)
    {
        if (G_DNSTOOL_ENTRY_POINT === "api.php")
            return;
        Notifications::DisplayWarning($text);
    }

    public static function IsValidRecordType($type)
    {
        global $g_editable;
        return in_array($type, $g_editable);
    }

    //! Required to handle various non-standard boolean interpretations, mostly for strings from API requests
    public static function IsTrue($bool)
    {
        if ($bool === true)
            return true;

        // Check string version
        if ($bool == "true" || $bool == "t")
            return true;

        // Check int version
        if (is_numeric($bool) && $bool != 0)
            return true;

        return false;
    }
}
