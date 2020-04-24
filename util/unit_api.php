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

$login = api("action=logout");
$ut->Evaluate("logout", $login['result'] == 'success');


echo ("\n\n\n");
$ut->PrintResults();

$ut->Exit();
