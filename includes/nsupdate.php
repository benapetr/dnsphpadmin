<?php

// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

require_once("config.php");
require_once("fatal.php");

function nsupdate($input)
{
    global $g_nsupdate;
    $desc = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w')
    );
    $pipes = array();
    $cwd = '/tmp';
    $env = array();
    $proc = proc_open($g_nsupdate, $desc, $pipes, $cwd, $env);
    if (!is_resource($proc))
    {
        Error("Unable to execute " . $g_nsupdate);
    }
    fwrite($pipes[0], $input);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $ret = proc_close($proc);
    if ($ret > 0)
    {
        Error($g_nsupdate . " return code " . $ret . ": " . $errors);
    }
    return $output;
}

function dig($parameters)
{
    global $g_dig;
    return shell_exec($g_dig . " " . $parameters);
}

function get_zone_data($zone)
{
    global $g_domains;
    $zone_servers = $g_domains[$zone];
    $data = dig("axfr " . $zone . " @" . $zone_servers["transfer_server"]);
    return $data;
}