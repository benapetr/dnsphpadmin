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
require_once("fatal_shared.php");

//! Similar to Error from fatal.php except API errors are always blocking, so second parameter is irrelevant
function Error($msg, $blocking = true)
{
    global $api, $g_debug;
    WriteToErrorLog($msg);
    $api->ThrowError('ERROR: ' . $msg, $msg);
    die(1);
}
