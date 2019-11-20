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
require_once("logging.php");

function GenerateBatch($operation)
{
    global $g_audit, $g_audit_batch_location;
    if (!$g_audit || $g_audit_batch_location === null)
        return NULL;

    $file_name = $g_audit_batch_location . "/" . strval(time()) . ".txt";
    $handle = fopen($file_name, 'w') or die('Cannot open file:  ' . $file_name);
    fwrite($handle, $operation);
    fclose($handle);
    return $file_name;
}

function WriteToAuditFile($operation, $text = '', $comment = NULL)
{
    global $g_audit, $g_audit_log, $g_audit_events, $g_eid, $g_syslog, $g_syslog_targets;
    if (!$g_audit)
        return;
    
    if ($g_audit_events[$operation] !== true)
        return;

    if (empty($comment))
        $comment = '';
    else
        $comment = ' comment: ' . $comment;

    $record = '';
    if (!empty($text))
        $record = " record: " . $text;

    // Line to write both to syslog and to file
    $raw_line = 'entry point: ' . G_DNSTOOL_ENTRY_POINT . ' eid: ' . $g_eid . ' user: ' . GetCurrentUserName() . " ip: " . $_SERVER['REMOTE_ADDR'] . " operation: " . $operation . $record . $comment;
    
    if ($g_syslog && $g_syslog_targets['audit'] === true)
    {
        WriteToSyslog($raw_line);
    }

    if ($g_audit_log !== NULL)
    {
        $log_line = date('m/d/Y h:i:s a', time()) . ' ' . $raw_line . "\n";
        $result = file_put_contents($g_audit_log, $log_line, FILE_APPEND | LOCK_EX);
        if ($result === false)
            throw new Exception('Unable to write to audit file: ' . $g_audit_log);
    }
}