<?php

require (dirname(__FILE__) . "/../psf/psf.php");
require (dirname(__FILE__) . "/../psf/includes/unit_test/ut.php");

define('G_DNSTOOL_ENTRY_POINT', 'unit.php');

require (dirname(__FILE__) . "/../config.default.php");
require (dirname(__FILE__) . "/../includes/zones.php");

$ut = new UnitTest();

$ut->Evaluate('Check for non-existence of PTR zones (none) in empty list', Zones::HasPTRZones() === false);
$g_domains['168.192.in-addr.arpa'] = [ ];
$g_domains['192.in-addr.arpa'] = [ ];
$ut->Evaluate('Check for non-existence of PTR zones (none) in empty list', Zones::HasPTRZones() === true);
$ut->Evaluate('Get zone for FQDN', Zones::GetZoneForFQDN('0.0.168.192.in-addr.arpa') == '168.192.in-addr.arpa');

echo ("\n\n\n");
$ut->PrintResults();

$ut->Exit();
