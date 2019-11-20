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
require_once("config.php");
require_once("logging.php");

function Debug($text)
{
    global $g_debug, $g_debug_log, $g_eid, $g_syslog_targets, $g_syslog;
    if ($g_debug === false && $g_debug_log === NULL && $g_syslog !== true)
        return;

    $lines = explode("\n", $text);

    if ($g_syslog && $g_syslog_targets['debug'] === true)
    {
        foreach ($lines as $line)
            WriteToSyslog('entry point: ' . G_DNSTOOL_ENTRY_POINT . ' eid: ' . $g_eid .  " ip: " . $_SERVER['REMOTE_ADDR'] . " DEBUG: " . $line, LOG_DEBUG);
    }
    if ($g_debug)
    {
        foreach ($lines as $line)
            psf_debug_log($line);
    }
    if ($g_debug_log !== NULL)
    {
        // Write all debug lines into one variable and then append to debug log in one call for performance reasons
        $debug_lines = "";

        foreach ($lines as $line)
       	    $debug_lines .= date('m/d/Y h:i:s a', time()) . ' entry point: ' . G_DNSTOOL_ENTRY_POINT . ' eid: ' . $g_eid .  " ip: " . $_SERVER['REMOTE_ADDR'] . " DEBUG: " . $line . "\n";

        $result = file_put_contents($g_debug_log, $debug_lines, FILE_APPEND | LOCK_EX);
        if ($result === false)
            throw new Exception('Unable to write to debug log file: ' . $g_debug_log);
    }
}
