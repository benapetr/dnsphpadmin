<?php

require (dirname(__FILE__) . "/../psf/psf.php");
require (dirname(__FILE__) . "/../psf/includes/unit_test/ut.php");

define('G_DNSTOOL_ENTRY_POINT', 'unit.php');

require_once (dirname(__FILE__) . "/../config.default.php");
require_once (dirname(__FILE__) . "/../includes/record_list.php");
require_once (dirname(__FILE__) . "/../includes/validator.php");
require_once (dirname(__FILE__) . "/../includes/zones.php");

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

$ut->Evaluate('Check for non-existence of PTR zones (none) in empty list', Zones::HasPTRZones() === false);
$g_domains['168.192.in-addr.arpa'] = [ ];
$g_domains['192.in-addr.arpa'] = [ ];
$ut->Evaluate('Check for non-existence of PTR zones (none) in empty list', Zones::HasPTRZones() === true);
$ut->Evaluate('Get zone for FQDN', Zones::GetZoneForFQDN('0.0.168.192.in-addr.arpa') == '168.192.in-addr.arpa');

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
$ut->Evaluate('Validator - invalid #1', IsValidHostName('-invalid') === false);
$ut->Evaluate('Validator - invalid #2', IsValidHostName('---') === false);
$ut->Evaluate('Validator - invalid #3', IsValidHostName('google domain') === false);
$ut->Evaluate('Validator - invalid #4', IsValidHostName('google.com;rm -rf /') === false);
$ut->Evaluate('Validator - invalid #5', IsValidHostName("google.com\ntest") === false);
$ut->Evaluate('Validator - invalid #6', IsValidHostName("google.com\ttest") === false);
$ut->Evaluate('Validator - invalid #7', IsValidHostName("'google.com") === false);
$ut->Evaluate('Validator - invalid #8', IsValidHostName("\"google.com") === false);

echo ("\n");
$ut->PrintResults();

$ut->Exit();
