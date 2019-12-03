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

// Global containers for error and warning messages
$g_error_container = new BS_FluidContainer();
$g_warning_container = new BS_FluidContainer();

//! Provides interface to all notification functions that hold and display errors, warnings, etc.
class Notifications
{
    public static function DisplayError($text)
    {
        global $g_warning_container;
        $warning_box = new BS_Alert('<b>WARNING:</b> ' . htmlspecialchars($text), 'warning');
        $warning_box->EscapeHTML = false;
        $g_warning_container->AppendObject($warning_box);
    }

    public static function DisplayWarning($text)
    {
        global $g_error_container;
        $fatal_box = new BS_Alert('<b>ERROR:</b> ' . $text, 'danger');
        $fatal_box->EscapeHTML = false;
        $g_error_container->AppendObject($fatal_box);
    }
}