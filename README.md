# dnsphpadmin
DNS admin panel, designed to operate via nsupdate, for all kinds of RFC compliant DNS servers

# Features
* Database-less simple stupid setup
* Communicates directly with DNS servers, no external DB, can be used in combination with other interfaces or tools
* Different servers for querying zone info (transfer) and for update, useful for load balancing
* Audit logs
* Support LDAP / Active Directory authentication
* Web API
* Individual user and LDAP group permissions to edit zones via roles (read/write)

# How does it work
DNS PHP admin is a very simple GUI utility that helps sysadmins manage their DNS records and also provides easy to use interface for end users, which is more user friendly than low level command line tools that are typically used to manage BIND9 servers.

It also makes it possible to centralize management of multiple separate DNS servers, so that you can edit multiple zones on multiple different DNS servers.

This tool is only a wrapper for Linux commands `dig` and `nsupdate`, it will download all records in a zone via AXFR (zone transfer) and it will change the records via nsupdate commands.

# How to install
First of all make sure that dig and nsupdate are available on system. They should be in /usr/bin, if they are somewhere else, change the paths in config.php later

Then, download release tarball into any folder which is configured a http root of some web server with PHP installed, (for example into /var/www/dns) and unpack it.

```
cd /tmp
wget https://github.com/benapetr/dnsphpadmin/releases/download/1.10.0/dnsphpadmin_1.10.0.tar.gz
cd /var/www/html
tar -xf /tmp/dnsphpadmin_1.10.0.tar.gz
mv dnsphpadmin_1.10.0 dnsphpadmin
cd dnsphpadmin

# Now copy the default config file
cp config.default.php config.php
# Edit in your favorite editor
vi config.php
```

Now update `$g_domains` so that it contains information about zones you want to manage. Web server must have nsupdate and dig Linux commands installed in paths that are in config.php and it also needs to have firewall access to perform zone transfer and to perform nsupdate updates.

**IMPORTANT:** DNS tool doesn't use any authentication by default, so everyone with access to web server will have access to DNS tool. If this is just a simple setup for 1 or 2 admins who should have unlimited access to everything, you should setup login via htaccess or similar see https://httpd.apache.org/docs/2.4/howto/auth.html for apache. If you have LDAP (active directory is also LDAP), you can configure this tool to use LDAP authentication as well.
