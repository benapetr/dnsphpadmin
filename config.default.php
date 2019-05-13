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

// Timezone (used when writing to audit logs)
$g_timezone = 'UTC';

// List of domains, each domain has separate value for "transfer server" which server that
// dig will do zone transfer on when reading zone data, and update_server which is where
// nsupdate will send its requests
$g_domains = [ 'example.domain' => [ 'transfer_server' => 'localhost', 'update_server' => 'localhost' ] ];

// You can specify multiple custom options per domain, this example here contains all available options with documentation:
// You can also specify custom TSIG override
// $g_domains = [ 'example.domain' => [ 'transfer_server' => 'localhost',
//                                      'update_server' => 'localhost',
//                                      'read_only' => false, // by default false, if true domain will be read only
//                                      'in_transfer' => false, // if true domain will be marked as "in transfer" which means it's being transfered from one DNS master to another, so the records may not reflect truth
//                                      'maintenance_note' => 'This domain is being configured now', // maintenance note to display for this domain
//                                      'tsig' => true,
//                                      'tsig_key' => 'some_key' ] ];

// List of records that can be edited
$g_editable = [ "A", "AAAA", "NS", "PTR", "SRV", "TXT", "SPF", "MX" ];

// Path to executable of dig
$g_dig = '/usr/bin/dig';

// Path to executable of nsupdate
$g_nsupdate = '/usr/bin/nsupdate';

// If true all changes will go to this file
$g_audit = false;
$g_audit_log = '/var/log/dns_audit.log';

// Folder where the batch operations should be logged, each batch operation will be stored in separate file
// Keep this null to log batch operations into single line to $g_audit_log
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
// URL of LDAP server, prefix with ldaps:// to get SSL, if you need to ignore invalid certificate, follow this:
// https://stackoverflow.com/questions/3866406/need-help-ignoring-server-certificate-while-binding-to-ldap-server-using-php
// $g_auth_ldap_url = "ldap.example.com";

// Set up a filter for usernames that are allowed to login
// $g_auth_allowed_users = array( "domain\\bob", "joe" );

// You can also setup authentication roles and their privileges here, there is special built-in role "root" which has unlimited privileges
// Privileges are one of 'rw', 'r' or '' for nothing
// $g_auth_roles = [ 'users' => [ 'example.domain' => 'rw' ] ];
$g_auth_roles = NULL;

// Each user can be member of multiple roles, in case no role is specified for user, this is default role
$g_auth_default_role = NULL;

// You can assign roles to users here
// $g_auth_roles_map = [ 'joe' => [ 'admins', 'users' ] ];
$g_auth_roles_map = [];

// Use local bootstrap instead of CDN (useful for clients behind firewall)
// In order for this to work, you need to download bootstrap 3.3.7 so that it's in root folder of htdocs (same level as index.php) example:
// /bootstrap-3.3.7
// /index.php
$g_use_local_bootstrap = false;

// Use local jquery instead of CDN (useful for clients behind firewall)
// For this to work download compressed jquery 3.3.1 to root folder for example:
// /jquery-3.3.1.min.js
$g_use_local_jquery = false;

// Whether API interface is available or not
$g_api_enabled = false;

// List of access tokens that can be used with API calls (together with classic login)
// This is a simple list of secrets. Each secret is a string that is used to authenticate for API subsystem.
$g_api_tokens = [ ];
