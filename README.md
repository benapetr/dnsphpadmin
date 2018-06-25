# dnsphpadmin
DNS admin panel, designed to operate via nsupdate, for all kinds of RFC compliant DNS servers

# How it works
This is a simple wrapper around standard DNS commands dig and nsupdate. It can display all records in a DNS zone using
dig axfr (zone transfer) command, and it allows you to add or modify DNS records using convenient web interface.

It's just a wrapper for CLI tools, nothing else. If you don't have nsupdate and dig on your server, it won't work.

# Features
* Database-less simple stupid setup
* Communicates directly with DNS servers, no external DB, can be used in combination with other interfaces or tools
* Different servers for querying zone info (transfer) and for update, useful for load balancing
* Audit logs of changes

# How to set it up
Copy config.default.php to config.php, then change it to match your setup. Domains are in variable $g_domains and each of them
has defined servers for transfer and update, which is usually the same server, but may be different. 

For now the tool has not authentication mechanism, so you need to setup auth using htpasswd
