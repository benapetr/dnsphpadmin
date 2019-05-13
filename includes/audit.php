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
require_once("fatal.php");
require_once("config.php");

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

function GetCurrentUserName()
{
    global $g_auth;
    if ($g_auth === "ldap" && isset($_SESSION["user"]))
        return $_SESSION["user"];
    if (!isset($_SERVER['REMOTE_USER']))
        return "unknown user";
    return $_SERVER['REMOTE_USER'];
}

function WriteToAuditFile($operation, $text)
{
    global $g_audit, $g_audit_log;
    if (!$g_audit)
        return;

    // Prepare audit log line
    $log_line = date('m/d/Y h:i:s a', time());
    $log_line .= ' entry point: ' . G_DNSTOOL_ENTRY_POINT . ' user: ' . GetCurrentUserName() . " ip: " . $_SERVER['REMOTE_ADDR'] . " operation: " . $operation . " record: " . $text . "\n";

    $g_audit_log;
    $result = file_put_contents($g_audit_log, $log_line, FILE_APPEND | LOCK_EX);
    if ($result === false)
        throw new Exception('Unable to write to audit file: ' . $g_audit_log);
}
