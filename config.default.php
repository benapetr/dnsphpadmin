<?php

// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// List of domains, each domain has separate value for "transfer server" which server that
// dig will do zone transfer on when reading zone data, and update_server which is where
// nsupdate will send its requests
$g_domains = [ 'example.domain' => [ 'transfer_server' => 'localhost', 'update_server' => 'localhost' ] ];

// You can also specify custom TSIG override
// $g_domains = [ 'example.domain' => [ 'transfer_server' => 'localhost', 'update_server' => 'localhost', 'tsig' => true, 'tsig_key' => 'some_key' ] ];

// List of records that can be edited
$g_editable = [ "A", "AAAA", "NS", "PTR", "SRV", "TXT", "SPF", "MX" ];

// Path to executable of dig
$g_dig = '/usr/bin/dig';

// Path to executable of nsupdate
$g_nsupdate = '/usr/bin/nsupdate';

// If true all changes will go to this file
$g_audit = false;
$g_audit_log = '/var/log/dns_audit.log';

// Where the batch operations should be logged, each batch operation will be stored in separate file
$g_audit_batch_location = null;

// TSIG authentication for nsupdate - global config
// you can specify individual TSIG settings per each domain, if you don't this is default value
$g_tsig = false;
$g_tsig_key = '';

// Will print debug statements into html output
$g_debug = false;

// How long do sessions last in seconds
$g_session_timeout = 3600;

// Authentication setup - by default, don't provide any authentication mechanism, leave it up to sysadmin
$g_auth = NULL;

// Example auth
// $g_auth = "ldap";
// $g_auth_ldap_url = "ldap.example.com";

// Set up a filter for usernames that are allowed to login
// $g_auth_allowed_users = array( "domain\\bob", "joe" );

// Use local bootstrap instead of CDN (useful for clients behind firewall)
// In order for this to work, you need to download bootstrap 3.3.7 so that it's in root folder of htdocs (same level as index.php) example:
// /bootstrap-3.3.7
// /index.php
$g_use_local_bootstrap = false;
