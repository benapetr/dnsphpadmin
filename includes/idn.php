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

require_once("debug.php");

/**
 * Class for handling Internationalized Domain Name (IDN) conversions
 * 
 * This handles the conversion between UTF-8 and Punycode (ASCII) representations
 * of domain names that include non-ASCII characters.
 */
class IDNConverter
{
    // Flag to check if the intl extension is available
    private static $has_intl = null;
    
    /**
     * Check if PHP's intl extension is available and IDN support is enabled
     * 
     * @return bool True if intl extension is available and IDN support is enabled, false otherwise
     */
    public static function hasIntlSupport()
    {
        global $g_enable_idn;
        
        if (self::$has_intl === null)
        {
            self::$has_intl = isset($g_enable_idn) && $g_enable_idn === true && 
                            function_exists('idn_to_utf8') && function_exists('idn_to_ascii');
        }
        return self::$has_intl;
    }

     /**
     * Convert an IDN domain from Punycode (ASCII) to UTF-8
     * 
     * @param string $domain Domain in ASCII/Punycode format (e.g. xn--henriknordstrm-7pb.se)
     * @return string Domain in UTF-8 format (e.g. henriknordström.se)
     */
    public static function toUTF8($domain)
    {
        if (empty($domain))
            return $domain;
        
        // If no IDN functions, return unchanged
        if (!self::hasIntlSupport())
        {
            Debug("IDN to UTF-8 conversion requested but intl extension is not available");
            return $domain;
        }
        
        // Only convert if the domain has xn-- prefix indicating IDN
        if (strpos($domain, 'xn--') !== false)
        {
            // IDNA_DEFAULT | IDNA_USE_STD3_RULES options
            $result = idn_to_utf8($domain, IDNA_NONTRANSITIONAL_TO_UNICODE, INTL_IDNA_VARIANT_UTS46);
            
            // If conversion fails, return the original string
            if ($result === false)
            {
                Debug("Failed to convert IDN domain to UTF-8: $domain");
                return $domain;
            }
            
            Debug("Converted IDN domain from ASCII '$domain' to UTF-8 '$result'");
            return $result;
        }
        
        // If no xn-- prefix, return as is
        return $domain;
    }
    
    /**
     * Convert a UTF-8 domain to Punycode (ASCII)
     * 
     * @param string $domain Domain in UTF-8 format (e.g. henriknordström.se)
     * @return string Domain in ASCII/Punycode format (e.g. xn--henriknordstrm-7pb.se)
     */
    public static function toASCII($domain)
    {
        if (empty($domain))
            return $domain;
        
        // If no IDN functions, return unchanged
        if (!self::hasIntlSupport())
        {
            Debug("UTF-8 to IDN conversion requested but intl extension is not available");
            return $domain;
        }
        
        // Check if domain contains non-ASCII characters
        if (preg_match('/[^\x20-\x7E]/', $domain))
        {
            // IDNA_DEFAULT | IDNA_USE_STD3_RULES options
            $result = idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
            
            // If conversion fails, return the original string
            if ($result === false)
            {
                Debug("Failed to convert UTF-8 domain to ASCII: $domain");
                return $domain;
            }
            
            Debug("Converted IDN domain from UTF-8 '$domain' to ASCII '$result'");
            return $result;
        }
        
        // If ASCII only, return as is
        return $domain;
    }
    
    /**
     * Convert a full FQDN with multiple labels to UTF-8
     * 
     * @param string $fqdn Fully qualified domain name in ASCII format
     * @return string FQDN in UTF-8 format
     */
    public static function fqdnToUTF8($fqdn)
    {
        if (empty($fqdn))
            return $fqdn;
        
        // If no IDN functions or no xn-- in the string, return unchanged
        if (!self::hasIntlSupport() || strpos($fqdn, 'xn--') === false)
            return $fqdn;
        
        // First try to convert the whole FQDN at once (more reliable for complex domains)
        $result = idn_to_utf8($fqdn, IDNA_NONTRANSITIONAL_TO_UNICODE, INTL_IDNA_VARIANT_UTS46);
        if ($result !== false)
            return $result;
        
        // Fall back to per-label conversion if whole conversion fails
        Debug("Full FQDN conversion failed for '$fqdn', trying per-label conversion");
        $labels = explode('.', $fqdn);
        foreach ($labels as &$label)
        {
            // Only try to convert if it has the xn-- prefix
            if (strpos($label, 'xn--') === 0)
            {
                $utf8_label = self::toUTF8($label);
                if ($utf8_label !== false)
                    $label = $utf8_label;
            }
        }
        
        return implode('.', $labels);
    }
    
    /**
     * Convert a full FQDN with multiple labels to ASCII
     * 
     * @param string $fqdn Fully qualified domain name in UTF-8 format
     * @return string FQDN in ASCII format
     */
    public static function fqdnToASCII($fqdn)
    {
        if (empty($fqdn))
            return $fqdn;
        
        // If no IDN functions, return unchanged
        if (!self::hasIntlSupport())
            return $fqdn;
        
        // Check if domain contains non-ASCII characters
        if (!preg_match('/[^\x20-\x7E]/', $fqdn))
        {
            // No non-ASCII characters, return as is
            return $fqdn;
        }
        
        // First try to convert the whole FQDN at once (more reliable for complex domains)
        $result = idn_to_ascii($fqdn, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
        if ($result !== false)
            return $result;
        
        // Fall back to per-label conversion if whole conversion fails
        Debug("Full FQDN conversion failed for '$fqdn', trying per-label conversion");
        $labels = explode('.', $fqdn);
        foreach ($labels as &$label)
        {
            // Only try to convert if it contains non-ASCII
            if (preg_match('/[^\x20-\x7E]/', $label))
            {
                $ascii_label = self::toASCII($label);
                if ($ascii_label !== false)
                    $label = $ascii_label;
            }
        }
        
        return implode('.', $labels);
    }
}
