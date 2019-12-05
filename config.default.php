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

// List of domains (zones), each zone has separate value for "transfer server" - which is a server that would be used by dig
// to perform a zone transfer when reading zone data, and "update_server" which is server used to write to via nsupdate
// There are some extra options that can be specified for each zone (see next example)
$g_domains = [ 'example.domain' => [ 'transfer_server' => 'localhost', 'update_server' => 'localhost' ] ];

// You can specify multiple custom options per domain, this example here contains all available options with documentation:
// You can also specify custom TSIG override
// $g_domains = [ 'example.domain' => [ 'transfer_server' => 'localhost',
//                                      'update_server' => 'localhost',
//                                      'explicit' => true, // by default true, will explicitly tell nsupdate to perform updates to this zone, if set to false nsupdate will automatically try to figure out the zone name
//                                      'read_only' => false, // by default false, if true domain will be read only
//                                      'in_transfer' => false, // if true domain will be marked as "in transfer" which means it's being transfered from one DNS master to another, so the records may not reflect truth
//                                      'maintenance_note' => 'This domain is being configured now', // maintenance note to display for this domain
//                                      'tsig' => true,
//                                      'tsig_key' => 'some_key' ] ];

// List of record types that can be edited
// https://en.wikipedia.org/wiki/List_of_DNS_record_types
$g_editable = [ 'A', 'AAAA', 'CNAME', 'DNAME', 'DS', 'NS', 'PTR', 'SRV', 'SSHFP', 'TXT', 'SPF', 'MX' ];

// List of record types that are hidden from UI by default, this is not a security feature, it's for user comfort and can be easily disabled in UI
// API is not affected by this
$g_hidden_record_types = [ 'NSEC', 'RRSIG' ];

// Default TTL for new DNS records
$g_default_ttl = 3600;

// Path to executable of dig, you can also use this to specify some dig options for example:
// $g_dig = '/usr/bin/dig +tcp +time=10';
$g_dig = '/usr/bin/dig';

// Path to executable of nsupdate
$g_nsupdate = '/usr/bin/nsupdate';

// If set to value higher than 0, dig will be retried for N times, this is useful on broken networks with heavy packet loss
$g_retry_on_error = 2;

// Error log, keep NULL to disable error logging to external file, or set to absolute path to writeable error log file
$g_error_log = NULL;

// Whether audit subsystem should be enabled
$g_audit = false;

// Define which events are logged into audit log
$g_audit_events = [
                    'login_success' => true,
                    'login_fail' => true,
                    'batch' => true,
                    'create' => true,
                    'replace_delete' => true,
                    'replace_create' => true,
                    'delete' => true,
                    'display' => false,
                    'get_record' => false
                  ];

// Destination file to which the audit events are written to
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

// Will print debug messages into specified file (lot of text)
$g_debug_log = NULL;

// Log to syslog
$g_syslog = false;

$g_syslog_targets = [
                      'error' => true,
                      'audit' => true,
                      'debug' => false
                    ];

// Syslog facility
$g_syslog_facility = LOG_LOCAL0;

// Syslog ident (program name)
$g_syslog_ident = 'dnsphpmyadmin';

// Optional execution ID used to identify separate executions in logs (debug / audit / error)
$g_eid = bin2hex(openssl_random_pseudo_bytes(8));

// How long do sessions last in seconds
$g_session_timeout = 3600;

// Authentication setup - by default, don't provide any authentication mechanism, leave it up to sysadmin
// Only supported authentication backend right now is LDAP ($g_auth = "ldap";)
$g_auth = NULL;

// Application ID for sessions, if you have multiple separate installations of dns php admin, you should create unique strings for each of them
// to prevent sharing session information between them, this string is also used as prefix for caching keys and cookies
$g_auth_session_name = 'dnsphpadmin';

// Few words about LDAP integration within dns php admin:
// This tool was written in a very large corporation world with extreme edge use-cases in mind. Therefore it's very flexible and it has
// large amount of options that may look quite hard to understand on first sight. While it supports generic LDAP protocol it was written
// with Active Directory in mind. This tool supports multiple authentication schemes such as:
// * anyone who has access to LDAP / AD can use it without limits (keep g_auth_roles and g_auth_allowed_users NULL)
// * selected users can login only (g_auth_allowed_users)
// * RBAC access - there are roles defined with fine-grained permissions where each user is bound to one or more of these roles (groups)
// Many of the options present in this config may be left as default value unless you are aiming for one of these edge cases that I unfortunatelly
// had to prepare this tool for.

// Example auth
// $g_auth = "ldap";
// URL of LDAP server, prefix with ldaps:// to get SSL, if you need to ignore invalid certificate, follow this:
// https://stackoverflow.com/questions/3866406/need-help-ignoring-server-certificate-while-binding-to-ldap-server-using-php
// $g_auth_ldap_url = "ldap.example.com";
$g_auth_ldap_url = NULL;

// Custom login information
// Example:
// $g_auth_login_banner = "You can login with your domain name";
$g_auth_login_banner = NULL;

// Set up optional filter for usernames that are allowed to login
// Example:
// $g_auth_allowed_users = array( "domain\\bob", "joe" );
$g_auth_allowed_users = NULL;

// Optional prefix for users - this prefix is automatically appended in front of every username unless it's already present
// this is useful for AD domain logins where domain has to be specified in front of username
// Example:
// $g_auth_domain_prefix = "CORP\\";
// will result in joe being changed to CORP\joe while authenticating to LDAP, but when retrieving a list of groups, only joe will be used
$g_auth_domain_prefix = NULL;

// If true, following string will be used to fetch group membership for each user. These groups will be added to list of roles that user is member of.
// If you want to grant some privileges to an LDAP group, you should create a special role with exactly same name as LDAP group, that way each member
// of this group will have these privileges
$g_auth_fetch_domain_groups = false;

// This is only used if g_auth_fetch_domain_groups option is set to true to fetch list of groups user is in
$g_auth_ldap_dn = "CN=Users,DC=ad,DC=domain";

// You can also setup authentication roles and their privileges here, there is special built-in role "root" which has unlimited privileges
// Privileges are one of 'rw', 'r' or '' for nothing
// Examples:
// $g_auth_roles = [ 'users' => [ 'example.domain' => 'rw' ] ];
// $g_auth_roles = [ 'DOMAIN.GROUP.WITH.FANCY.NAME' => [ 'example.domain' => 'rw' ] ]; // in combination with g_auth_fetch_domain_groups
// $g_auth_roles = [ 'admins' => [ 'example.domain' => 'rw' ], 'users' => [ 'example.domain' => 'r' ] ];
// IMPORTANT: if you are using LDAP groups, you will still need to define some authentication roles here and later you can bind these roles to
//            individual groups
$g_auth_roles = NULL;

// Each user can be member of multiple roles, in case no role is specified for user, this is default role
$g_auth_default_role = NULL;

// Don't allow users who don't belong to any role to login to this tool - this is only enforced in case that g_auth_roles is not nullptr
$g_auth_disallow_users_with_no_roles = true;

// You can assign roles to users or LDAP groups here
// Example:
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
// It's recommended to optionally prefix each secret with a memorable string (user name) and underscore, for example:
// my_favorite_tool_secretstring123345
// In this case if masking is enabled, audit logs will not contain the last part after last underscore to prevent secret from leaking
// into the audit logs
$g_api_tokens = [ ];

// If enabled text after last underscore of each api token will be removed from audit logs
$g_api_token_mask = true;

// Transfer cache is optional and used to cache the results of zone transfer in order to prevent unnecessary transfers, that might put heavy load
// on both DNS server as well as network. Caching will store a whole zone and instead of performing full zone transfer, DNS tool will just query SOA record and it will
// check if record serial is matching serial in our cache. If it doesn't, full zone transfer will be executed.
// You can check whether caching is functioning in debug logs - see $g_debug. Following caching engines are provided:
// NULL - no caching
// 'memcache' - Memcache daemon (using memcache class, not memcached class - PHP has two classes for same purpose) https://www.php.net/manual/en/book.memcache.php
// 'memcached' - Memcache daemon (using memcached class)
$g_caching_engine = NULL;

// In case you decide to use memcached as caching engine, you can adjust some parameters with these variables
// NOTE: memcached engine uses $g_auth_session_name as key prefixes
$g_caching_memcached_host = 'localhost';
$g_caching_memcached_port = 11211;
$g_caching_memcached_expiry = 0;
