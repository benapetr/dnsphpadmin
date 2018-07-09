<?php

// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

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

function WriteToAuditFile($operation, $text)
{
    global $g_audit, $g_audit_log;
    if (!$g_audit)
        return;

    // Prepare audit log line
    $log_line = date('m/d/Y h:i:s a', time());
    $log_line .= " user: " . $_SERVER['REMOTE_USER'] . " ip: " . $_SERVER['REMOTE_ADDR'] . " operation: " . $operation . " change: " . $text . "\n";

    $my_file = $g_audit_log;
    $handle = fopen($my_file, 'a') or die('Cannot open file:  ' . $my_file);
    fwrite($handle, $log_line);
    fclose($handle);
}
