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
require_once("config.php");

function Debug($text)
{
    global $g_debug;
    if ($g_debug)
    {
        $lines = explode("\n", $text);
        foreach ($lines as $line)
            psf_debug_log($line);
    }
}
