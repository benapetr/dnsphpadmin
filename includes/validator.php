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

function IsValidHostName($fqdn)
{
    global $g_strict_hostname_checks, $g_enable_idn;
    // Few extra checks to prevent shell escaping
    if (!ShellEscapeCheck($fqdn))
        return false;
    if (psf_string_contains($fqdn, "'"))
        return false;
    if (psf_string_contains($fqdn, '"'))
        return false;
    if (psf_string_contains($fqdn, ' '))
        return false;
    if (psf_string_contains($fqdn, "\t"))
        return false;
    if (psf_string_contains($fqdn, "\n"))
        return false;
    // security fix + and - are switches used by dig so we need to make sure they aren't first symbol even if strict checking is not enabled
    if (psf_string_startsWith($fqdn, "+"))
        return false;
    if (psf_string_startsWith($fqdn, "-"))
        return false;
    
    // If strict hostname checks are enabled, we need to convert UTF-8 to ASCII first
    if ($g_strict_hostname_checks)
    {
        // Check if this is UTF-8
        if (preg_match('/[^\x20-\x7E]/', $fqdn))
        {
            if ($g_enable_idn)
            {
                // Convert to ASCII first and then check
                $ascii_fqdn = IDNConverter::fqdnToASCII($fqdn);
                return preg_match('/[^0-9\*a-zA-Z_\-\.]/', $ascii_fqdn) ? false : true;
            }
        }
        // If no UTF-8 characters or converter not available, do the normal check
        if (preg_match('/[^0-9\*a-zA-Z_\-\.]/', $fqdn))
            return false;
    }
    return true;
}

function SanitizeHostname($hostname)
{
    global $g_enable_idn;
    // First trim whitespace
    $hostname = trim($hostname);
    
    // Convert UTF-8 hostname to ASCII punycode for DNS operations
    // This is needed because nsupdate doesn't support IDN/UTF-8 directly
    if ($g_enable_idn)
    {
        $ascii_hostname = IDNConverter::fqdnToASCII($hostname);
        return $ascii_hostname;
    }
    
    return $hostname;
}

function NSupdateEscapeCheck($string)
{
    if (psf_string_contains($string, "\n"))
        return false;
    return true;
}

function ShellEscapeCheck($string)
{
    if (psf_string_contains($string, ";"))
        return false;

    return true;
}
