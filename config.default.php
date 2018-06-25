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

// List of records that can be edited
$g_editable = [ "A", "AAAA", "NS", "PTR", "SRV", "TXT", "SPF", "MX" ];

// Path to executable of dig
$g_dig = '/usr/bin/dig';

// Path to executable of nsupdate
$g_nsupdate = '/usr/bin/nsupdate';

// If true all changes will go to this file
$g_audit = false;
$g_audit_log = '/var/log/dns_audit.log';

// TSIG authentication for nsupdate
$g_tsig = false;
$g_tsig_key = '';

// Will print debug statements into html output
$g_debug = false;
