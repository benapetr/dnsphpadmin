# Changelog

This changelog is based on the differences between git tags. Dates are tag creation dates.

## 2.0.4
- Fixed another crash in api.php endpoint caused by earlier refactor

## 2.0.3
- Fixed crash in api.php endpoint caused by earlier refactor

## 2.0.2

- Added IDN support, including IDNA2008 handling and `dig +noidnout` default behavior.
- Added automatic splitting for long TXT records.
- Added Redis caching support and Redis serialization handling.
- Added support for selecting and deleting multiple records.
- Added CSRF protection and hardened session cookie handling.
- Improved LDAP escaping, validation, and other security-sensitive paths.
- Reorganized parts of the codebase and improved documentation.
- Replaced Travis CI with GitHub Actions.
- Fixed undefined-variable and role-display issues.

## 2.0.1 - 2025-07-29

- Improved file-based authentication user management.
- Added CLI commands to manipulate user roles.
- Added password masking and extra sanity checks to CLI user creation.
- Normalized usernames to lowercase when comparing with the file auth database.
- Fixed missing username in audit logs for file-based authentication.
- Refactored password-file handling.
- Fixed unnecessary empty groups array behavior.
- Added an option to hide the program footer.
- Made the edit form layout more compact.
- Added cache busting for UI assets.
- Updated icons and Bootstrap references.
- Fixed a missing newline in an error message.
- Updated release packaging so `mktar` includes all dependencies.

## 2.0.0 - 2025-07-24

- Upgraded the bundled PSF framework, Bootstrap, and jQuery.
- Added a redesigned login page.
- Added file-based authentication database support.
- Added Bootstrap icon usage and dark mode.
- Added API bearer-token support as an alternative to cookie sessions.
- Removed settings code as part of the 2.0 cleanup.

## 1.12.0 - 2024-04-17

- Added per-user configuration file support.
- Added per-domain default TTL configuration.
- Added generic zone notes.
- Added a new icon in the record list.
- Implemented missing `replace_record` behavior.
- Fixed PTR creation to use the new value when modifying A records.
- Improved compatibility with older PHP versions.
- Updated PSF and documentation.

## 1.11.0 - 2021-05-06

- Added logo and favicon assets.
- Improved UI details, including zone-list switcher rendering and delete confirmation text.
- Remembered TTL values in forms.
- Fixed disabled combo-box behavior.
- Fixed release packaging issues in `mktar`.
- Updated PSF and project links.

## 1.10.0 - 2020-09-01

- Added help documentation.
- Added Ansible examples and improved playbooks.
- Implemented `replace_record` API behavior.
- Added additional validation checks.
- Fixed multiple bugs.
- Updated PSF and release packaging behavior.

## 1.9.0 - 2020-06-18

- Added strict hostname validation and input sanitization.
- Improved API error output and API warning handling.
- Added record counters and hidden-record counting fixes.
- Added API support for creating, deleting, and modifying associated PTR records.
- Added UI support for deleting associated PTR records.
- Reorganized warning and notification handling.
- Improved test coverage and release packaging.
- Adjusted the record value field width in the UI.

## 1.8.0 - 2020-05-19

- Added installation documentation.
- Added unit tests and API test coverage.
- Added zone completeness checks.
- Improved batch operation handling by ignoring empty lines.
- Added hints for the batch tab.
- Fixed API behavior when authentication is disabled.
- Fixed include order, login refresh, and login bugs.
- Improved security hardening and UI sizing.

## 1.7.0 - 2020-04-15

- Added example configuration documentation.
- Added more advanced configuration options.
- Added statistics support.
- Remembered interrupted actions through login/session flow.
- Made the API delete-record value parameter optional.
- Improved API help.
- Fixed `dig` output parsing, including space-separated output and multiple-space rendering.
- Fixed text wrapping issues.

## 1.6.0 - 2020-01-23

- Added retry behavior for AXFR failures.
- Added automatic zone lookup for changes.
- Added `get_record` API call and audit logging for record reads.
- Added memcached extension support in addition to memcache.
- Added packaging and lint tooling.
- Added PTR creation support.
- Added syslog logging.
- Added editable record type configuration.
- Added hidden record type support.
- Improved API behavior, warning handling, debug output, and execution-time reporting.
- Fixed memcache error handling and audit logging issues.

## 1.5.0 - 2019-11-13

- Added memcache-based zone caching.
- Logged cache use in audit output.
- Added per-update explicit zone behavior.
- Added API token masking controls.
- Added configurable default TTL.
- Added optional error logs, retry-on-error behavior, and debug file logs.
- Improved error handling.
- Fixed cache corruption handling and form/comment edge cases.

## 1.4.0 - 2019-08-09

- Improved LDAP support and audit behavior.
- Added CSV export.
- Added audit display.
- Added comments for audit events.
- Added CNAME and DNAME editable record support.
- Improved insert-form memory and UI spacing.
- Fixed quote handling during edit/replace flows.
- Fixed issue #7 and other small logic/variable issues.

## 1.3.0 - 2019-05-21

- Reworked authentication.
- Improved login error reporting.
- Added PHP debug handling.
- Improved documentation.
- Disabled debug by default for production-oriented config.

## 1.2.2 - 2019-05-14

- Fixed a security issue.

## 1.2.0 - 2019-05-13

- Added the web API.
- Added API create support.
- Implemented delete behavior.
- Fixed missing index warnings and other small issues.

## 1.1.0 - 2019-02-18

- Added delete confirmation dialog basics.
- Added domain options.
- Added LDAP v3 support.
- Added role-based access support.
- Displayed only editable zones in relevant combo boxes.
- Improved default column sizing and documentation.

## 1.0.0 - 2018-12-13

- Initial working DNS admin UI.
- Added record insertion, modification, and deletion foundations.
- Added menu, zone switcher, footer, and CSS improvements.
- Added editable record type controls, including MX and PTR-related tweaks.
- Added audit logging and batch operations.
- Added login support.
- Added TSIG support, including per-domain TSIG settings.
- Added options for local Bootstrap and jQuery assets.
- Added early security hardening.
