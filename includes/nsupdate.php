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

require_once("config.php");
require_once("debug.php");

function nsupdate($input, $tsig_override = NULL, $tsig_override_key = NULL, $zone_name = NULL)
{
    global $g_nsupdate, $g_tsig_key, $g_tsig;

    // check if we want to use TSIG for this update
    $using_tsig = $g_tsig;
    if ($tsig_override === true || $tsig_override === false)
        $using_tsig = $tsig_override;

    // get TSIG key, it can be overriden on custom requests
    $tsig_key = $g_tsig_key;
    if ($tsig_override_key !== NULL)
        $tsig_key = $tsig_override_key;

    if ($zone_name !== NULL)
        $input = 'zone ' . $zone_name . "\n" . $input;

    if ($using_tsig)
        $input = "key " . $tsig_key . "\n" . $input;
    
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
    Debug("Sending this to nsupdate:\n" . $input);
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
    Debug("shell_exec: " . $g_dig . " " . $parameters);
    return shell_exec($g_dig . " " . $parameters);
}

// Convert standard DNS list of records as returned by transfer to PHP array
function raw_zone_to_array($data)
{
    $records = array();
    $data = explode("\n", $data);
    foreach ($data as $line)
    {
        if (psf_string_startsWith($line, ";"))
            continue;
        // Sanitize string, we replace all double tabs with single tabs, then replace all tabs with spaces and then replace
        // double spaces, so that each item is separated only with single space
        $line = str_replace("\t", " ", $line);
        while (psf_string_contains($line, "  "))
            $line = str_replace("  ", " ", $line);
        if (strlen(str_replace(" ", "", $line)) == 0)
            continue;
        $records[] = explode(" ", $line, 5);
    }
    return $records;
}

function get_zone_data($zone)
{
    global $g_domains;
    $zone_servers = $g_domains[$zone];
    $data = dig("axfr " . $zone . " @" . $zone_servers["transfer_server"]);
    return raw_zone_to_array($data);
}

function get_zone_soa($zone)
{
    global $g_domains;
    $zone_servers = $g_domains[$zone];
    $data = dig("SOA " . $zone . " @" . $zone_servers["transfer_server"]);
    return raw_zone_to_array($data);
}
