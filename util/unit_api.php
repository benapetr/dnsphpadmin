<?php

define('G_DNSTOOL_ENTRY_POINT', 'unit_api.php');

require (dirname(__FILE__) . "/../psf/psf.php");
require (dirname(__FILE__) . "/../psf/includes/unit_test/ut.php");

require (dirname(__FILE__) . "/../definitions.php");

function api($action)
{
    $api_url = 'http://localhost/dns/api.php';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $action);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    return json_decode(curl_exec($ch), true);
}

$ut = new UnitTest();

$version = api("action=get_version");

$ut->Evaluate("get_version", $version['version'] == G_DNSTOOL_VERSION);

$logged = api("action=is_logged");
$ut->Evaluate("is_logged", $logged['is_logged']);

$list = api("action=list_zones");
//var_export($list);
$ut->Evaluate("list_zones contains local test", array_key_exists('test.local', $list));
$ut->Evaluate("list_zones contains update server localhost", $list['test.local']['update_server'] == 'localhost');
$ut->Evaluate("list_zones contains transfer server localhost", $list['test.local']['transfer_server'] == 'localhost');

$zone_transfer = api("action=list_records&zone=test.local");
$ut->Evaluate("list_records contains SOA for local", $zone_transfer[0][3] == "SOA");
//var_export($zone_transfer);

$output = api('action=create_record&record=test1.test.local&ttl=10&type=A&value=10.2.2.8');
$ut->Evaluate('create_record test A', $output['result'] == 'success');

$record = api('action=get_record&record=test1.test.local');
$ut->Evaluate('get_record TTL', $record[0][1] == '10');
$ut->Evaluate('get_record value', $record[0][4] == '10.2.2.8');
$ut->Evaluate('get_record name', $record[0][0] == 'test1.test.local.');

$output = api('action=delete_record&record=test1.test.local&ttl=10&type=A&value=10.2.2.8');
$ut->Evaluate('delete_record test A', $output['result'] == 'success');

$login = api("action=logout");
$ut->Evaluate("logout", $login['result'] == 'success');



echo ("\n\n\n");
$ut->PrintResults();

$ut->Exit();
