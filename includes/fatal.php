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
require_once("config.php");

$g_error = false;
$g_error_message = NULL;

//! Display a fatal error - if error is blocking, it will stop execution of program and just display an error message in very ugly way
//! otherwise program will continue execution and error will be rendered somewhere in the interface
function Error($msg, $blocking = true)
{
    global $g_debug, $g_error, $g_error_message, $g_error_container;
    WriteToErrorLog($msg);
    if ($blocking)
    {
        $web = new HtmlPage("Error");
        bootstrap_init($web);
        $web->AppendObject(new BS_Alert("ERROR: " . $msg, "danger"));
        $web->PrintHtml();
        if ($g_debug)
            psf_print_debug_as_html();
        die(1);
    } else
    {
        // Store last error message just in case we needed to work with it anywhere else and in case we needed to know whether there was some error during execution
        $g_error = true;
        $g_error_message = $msg;

        $fatal_box = new BS_Alert('<b>ERROR:</b> ' . $g_error_message, 'danger');
        $fatal_box->EscapeHTML = false;
        $g_error_container->AppendObject($fatal_box);
    }
}
