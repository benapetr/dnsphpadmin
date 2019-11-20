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

function OpenSyslog()
{
    global $g_syslog, $g_syslog_facility, $g_syslog_ident;
    if (!$g_syslog)
        return;
    openlog($g_syslog_ident, LOG_PID, $g_syslog_facility);
}

function WriteToSyslog($text, $priority = LOG_INFO)
{
    syslog($priority, $text);
}

function CloseSyslog()
{
    global $g_syslog;
    if (!$g_syslog)
        return;
    closelog();
}

function WriteToErrorLog($text)
{
    global $g_error_log, $g_eid, $g_syslog_targets, $g_syslog;
    if ($g_error_log === NULL && $g_syslog !== true)
        return;

    // Remove newlines
    $text = trim(preg_replace('/\s+/', ' ', $text));
    
    // Prepare line of data to write to both syslog and file log
    $raw_line = 'entry point: ' . G_DNSTOOL_ENTRY_POINT . ' eid: ' . $g_eid . " ip: " . $_SERVER['REMOTE_ADDR'] . " ERROR: " . $text;

    if ($g_syslog && $g_syslog_targets['error'] === true)
    {
        WriteToSyslog($raw_line, LOG_ERR);
    }

    if ($g_error_log !== NULL)
    {
        // Prepare audit log line
        $log_line = date('m/d/Y h:i:s a', time()) . ' ' . $raw_line . "\n";

        $result = file_put_contents($g_error_log, $log_line, FILE_APPEND | LOCK_EX);
        if ($result === false)
            throw new Exception('Unable to write to error log file: ' . $g_error_log);
    }
}