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

// Shared functions used by both fatal.php and fatal_api.php

function WriteToErrorLog($text)
{
    global $g_error_log;
    if ($g_error_log === NULL)
        return;

    // Remove newlines
    $text = trim(preg_replace('/\s+/', ' ', $text));

    // Prepare audit log line
    $log_line = date('m/d/Y h:i:s a', time());
    $log_line .= ' entry point: ' . G_DNSTOOL_ENTRY_POINT . " ip: " . $_SERVER['REMOTE_ADDR'] . " ERROR: " . $text . "\n";

    $result = file_put_contents($g_error_log, $log_line, FILE_APPEND | LOCK_EX);
    if ($result === false)
        throw new Exception('Unable to write to error log file: ' . $g_error_log);
}
