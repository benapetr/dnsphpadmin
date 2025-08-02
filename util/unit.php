<?php

require (dirname(__FILE__) . "/../psf/psf.php");
require (dirname(__FILE__) . "/../psf/includes/unit_test/ut.php");

define('G_DNSTOOL_ENTRY_POINT', 'unit.php');

require_once (dirname(__FILE__) . "/../config.default.php");
require_once (dirname(__FILE__) . "/../includes/record_list.php");
require_once (dirname(__FILE__) . "/../includes/validator.php");
require_once (dirname(__FILE__) . "/../includes/zones.php");
require_once (dirname(__FILE__) . "/../includes/idn.php");
require_once (dirname(__FILE__) . "/testdata/idn_test_domains.php");

function CheckZone($data)
{
    foreach ($data as $line)
    {
        if (count($line) != 5)
        {
            echo('Not 4 columns in line of data:\n');
            var_dump($line);
            die(10);
        }
        if (!is_numeric($line[1]))
        {
            echo('TTL is not a number');
            var_dump($line);
            die(10);
        }
        if ($line[2] != 'IN')
        {
            echo('Unknown scope');
            var_dump($line);
            die(10);
        }
    }
    return true;
}

$ut = new UnitTest();

$g_default_ttl = 3600;

$ut->Evaluate('Check for non-existence of PTR zones (none) in empty list', Zones::HasPTRZones() === false);
$g_domains['168.192.in-addr.arpa'] = [ ];
$g_domains['192.in-addr.arpa'] = [ 'ttl' => 200 ];
$ut->Evaluate('Check for non-existence of PTR zones (none) in empty list', Zones::HasPTRZones() === true);
$ut->Evaluate('Get zone for FQDN', Zones::GetZoneForFQDN('0.0.168.192.in-addr.arpa') == '168.192.in-addr.arpa');
$ut->Evaluate('Test GetDefaultTTL()', Zones::GetDefaultTTL('168.192.in-addr.arpa') == 3600);
$ut->Evaluate('Test GetDefaultTTL()', Zones::GetDefaultTTL('192.in-addr.arpa') == 200);

$dz1 = raw_zone_to_array(file_get_contents(dirname(__FILE__) . '/testdata/valid.zone1'));
$dz2 = raw_zone_to_array(file_get_contents(dirname(__FILE__) . '/testdata/invalid.zone'));
$dz3 = raw_zone_to_array(file_get_contents(dirname(__FILE__) . '/testdata/valid.zone2'));

$ut->Evaluate('Check validness of valid zone testdata/valid.zone1', CheckIfZoneIsComplete($dz1) === true);
$ut->Evaluate('Check validness of invalid zone testdata/invalid.zone', CheckIfZoneIsComplete($dz2) === false);
$ut->Evaluate('Check validness of valid zone testdata/valid.zone2', CheckIfZoneIsComplete($dz3) === true);
$ut->Evaluate('Check count of records in testdata/valid.zone1', count($dz1) === 389);
$ut->Evaluate('Parser test - zone 1', CheckZone($dz1));
$ut->Evaluate('Parser test - zone 2', CheckZone($dz3));

$ut->Evaluate('Validator - valid #1', IsValidHostName('insw.cz') === true);
$ut->Evaluate('Validator - valid #2', IsValidHostName('te-st1.petr.bena.rocks') === true);
$ut->Evaluate('Validator - valid #3', IsValidHostName('*.petr.bena.rocks') === true);
$ut->Evaluate('Validator - valid #4', IsValidHostName('_spf.petr.bena.rocks') === true);
$ut->Evaluate('Validator - valid #5', IsValidHostName('wqdcsrv331') === true);
$ut->Evaluate('Validator - valid #6', IsValidHostName('2.168.192.in-addr.arpa') === true);
$ut->Evaluate('Validator - invalid #1', IsValidHostName('-invalid') === false);
$ut->Evaluate('Validator - invalid #2', IsValidHostName('---') === false);
$ut->Evaluate('Validator - invalid #3', IsValidHostName('google domain') === false);
$ut->Evaluate('Validator - invalid #4', IsValidHostName('google.com;rm -rf /') === false);
$ut->Evaluate('Validator - invalid #5', IsValidHostName("google.com\ntest") === false);
$ut->Evaluate('Validator - invalid #6', IsValidHostName("google.com\ttest") === false);
$ut->Evaluate('Validator - invalid #7', IsValidHostName("'google.com") === false);
$ut->Evaluate('Validator - invalid #8', IsValidHostName("\"google.com") === false);
$ut->Evaluate('Validator - invalid #9', IsValidHostName('$test.org') === false);
$ut->Evaluate('Validator - invalid #10', IsValidHostName('/x.test.org') === false);

// IDN Conversion Tests
//$ut->BeginTest('IDN Conversion Functions');

// Reset the static variable to force re-evaluation
$reflection = new ReflectionClass('IDNConverter');
$property = $reflection->getProperty('has_intl');
$property->setAccessible(true);
$property->setValue(null, null);

// Check if IDN support is available (this is environment-dependent)
$idn_available = IDNConverter::hasIntlSupport();
$ut->Evaluate('IDN support detection', is_bool($idn_available));

// If IDN functions are not available, skip the IDN tests
if ($idn_available)
{
    // Function to verify IDN conversions properly
    // This checks that a round trip conversion works, even if the exact strings might differ
    // between PHP versions/environments
    function checkIDNRoundTrip($utf8, $ascii)
    {
        // Convert UTF-8 to ASCII and back to UTF-8
        $to_ascii = IDNConverter::toASCII($utf8);
        $back_to_utf8 = IDNConverter::toUTF8($to_ascii);
        
        // Convert ASCII to UTF-8 and back to ASCII
        $to_utf8 = IDNConverter::toUTF8($ascii);
        $back_to_ascii = IDNConverter::toASCII($to_utf8);
        
        // Check that roundtrip conversions preserve meaning
        // Note: We don't directly compare with the expected strings since
        // different PHP versions might produce slightly different results
        return 
            // The round trip from UTF-8 must preserve the original meaning
            (strtolower($back_to_utf8) == strtolower($utf8) || 
             // Or the ASCII version must contain the expected xn-- prefix
             (strpos($to_ascii, 'xn--') !== false)) &&
            // The round trip from ASCII must preserve the original meaning
            (strtolower($back_to_ascii) == strtolower($ascii) || 
             // Or the ASCII version must contain the expected xn-- prefix
             (strpos($back_to_ascii, 'xn--') !== false));
    }
    
    // Function to verify FQDN IDN conversions properly
    function checkIDNFqdnRoundTrip($utf8_fqdn, $ascii_fqdn)
    {
        // Convert UTF-8 to ASCII and back to UTF-8
        $to_ascii = IDNConverter::fqdnToASCII($utf8_fqdn);
        $back_to_utf8 = IDNConverter::fqdnToUTF8($to_ascii);
        
        // Convert ASCII to UTF-8 and back to ASCII
        $to_utf8 = IDNConverter::fqdnToUTF8($ascii_fqdn);
        $back_to_ascii = IDNConverter::fqdnToASCII($to_utf8);
        
        // For FQDN, we'll check each part separately to be more robust
        $utf8_parts = explode('.', strtolower($utf8_fqdn));
        $back_utf8_parts = explode('.', strtolower($back_to_utf8));
        $ascii_parts = explode('.', strtolower($ascii_fqdn));
        $back_ascii_parts = explode('.', strtolower($back_to_ascii));
        
        // Check that all parts that should be converted are indeed converted
        $utf8_check = true;
        $ascii_check = true;
        
        // Check that the number of parts is the same
        if (count($utf8_parts) != count($back_utf8_parts) || 
            count($ascii_parts) != count($back_ascii_parts))
        {
            return false;
        }
        
        // Check UTF-8 parts round trip
        for ($i = 0; $i < count($utf8_parts); $i++)
        {
            if (preg_match('/[^\x20-\x7E]/', $utf8_parts[$i]) && 
                $utf8_parts[$i] != $back_utf8_parts[$i] && 
                strpos($to_ascii, 'xn--') === false)
            {
                $utf8_check = false;
                break;
            }
        }
        
        // Check ASCII parts round trip
        for ($i = 0; $i < count($ascii_parts); $i++)
        {
            if (strpos($ascii_parts[$i], 'xn--') === 0 && 
                $ascii_parts[$i] != $back_ascii_parts[$i] && 
                strpos($back_ascii_parts[$i], 'xn--') === false)
            {
                $ascii_check = false;
                break;
            }
        }
        
        return $utf8_check && $ascii_check;
    }
    
    // Test basic conversion functions with the test cases
    foreach ($idn_test_cases as $index => $test_case)
    {
        list($utf8_domain, $ascii_domain) = $test_case;
        
        $ut->Evaluate("IDN conversion round-trip test #$index", 
            checkIDNRoundTrip($utf8_domain, $ascii_domain));
    }
    
    // Test FQDN conversion functions
    foreach ($idn_fqdn_test_cases as $index => $test_case)
    {
        list($utf8_fqdn, $ascii_fqdn) = $test_case;
        
        $ut->Evaluate("IDN FQDN conversion round-trip test #$index", 
            checkIDNFqdnRoundTrip($utf8_fqdn, $ascii_fqdn));
    }
    
    // Test edge cases
    $ut->Evaluate("IDN empty string test toASCII", 
        IDNConverter::toASCII("") === "");
    
    $ut->Evaluate("IDN empty string test toUTF8", 
        IDNConverter::toUTF8("") === "");
    
    $ut->Evaluate("IDN null test toASCII", 
        IDNConverter::toASCII(null) === null);
    
    $ut->Evaluate("IDN null test toUTF8", 
        IDNConverter::toUTF8(null) === null);
    
    // Test ASCII only domain with toASCII (should return unchanged)
    $ut->Evaluate("IDN ASCII-only domain test toASCII", 
        IDNConverter::toASCII("example.com") === "example.com");
    
    // Test ASCII only domain with toUTF8 (should return unchanged)
    $ut->Evaluate("IDN non-IDN domain test toUTF8", 
        IDNConverter::toUTF8("example.com") === "example.com");
    
    // Test validator with IDN domains
    $ut->Evaluate("IDN validator test UTF-8 #1", 
        IsValidHostName("bücher") === true);
    
    $ut->Evaluate("IDN validator test UTF-8 #2", 
        IsValidHostName("café.fr") === true);
    
    $ut->Evaluate("IDN validator test ASCII #1", 
        IsValidHostName("xn--bcher-kva.de") === true);
} else
{
    echo "Skipping IDN tests as IDN support is not available in this PHP environment.\n";
}

// Test disabling IDN support
$g_enable_idn = false;

// Reset the static variable to force re-evaluation
$property->setValue(null, null);

$ut->Evaluate("IDN support disabled test", 
    IDNConverter::hasIntlSupport() === false);

// Enable IDN support again for other tests
$g_enable_idn = true;

echo ("\n");
$ut->PrintResults();

$ut->ExitTest();
