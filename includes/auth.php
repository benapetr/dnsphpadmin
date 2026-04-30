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

require_once("passwd_file.php");

$g_login_failed = false;
$g_logged_in = false;
//! Error message that is displayed in case that login fails
$g_login_failure_reason = "Invalid username or password";

class Auth
{
    //! Return true if application supports and require user to login, no matter if current user
    //! is logged in or not. Don't confuse with Auth::RequireLogin() which returns false
    //! even when login is enabled in case user is already logged in
    public static function LoginRequired()
    {
        global $g_auth;
        if ($g_auth === NULL)
            return false;

        if ($g_auth === 'ldap' || $g_auth === 'file')
            return true;

        Error("Unsupported authentication mechanism");
        return true;
    }

    public static function IsAuthorized($domain, $privilege)
    {
        global $g_auth_roles, $g_auth_default_role, $g_auth_roles_map;

        if ($g_auth_roles === NULL || !self::LoginRequired())
            return true;

        $roles = [ $g_auth_default_role ];
        $user = $_SESSION['user'];
        if ($user === NULL || $user === '')
            Error('Invalid username in session');

        if (array_key_exists($user, $g_auth_roles_map))
            $roles = $g_auth_roles_map[$user];

        if (in_array('root', $roles))
            return true;

        foreach ($roles as $role)
        {
            if (!array_key_exists($role, $g_auth_roles))
                continue;
            $role_info = $g_auth_roles[$role];
            if (!array_key_exists($domain, $role_info))
                continue;
            $permissions = $role_info[$domain];
            if ($privilege == 'rw' && $permissions == 'rw')
                return true;
            if ($privilege == 'r' && ($permissions == 'rw' || $permissions == 'r'))
                return true;
        }

        return false;
    }

    public static function IsAuthorizedToRead($domain)
    {
        return self::IsAuthorized($domain, 'r');
    }

    public static function IsAuthorizedToWrite($domain)
    {
        return self::IsAuthorized($domain, 'rw');
    }

    public static function GetCurrentUserName()
    {
        global $g_auth, $g_api_token_mask;
        if ($g_api_token_mask && isset($_SESSION["logged_in"]) && $_SESSION["logged_in"] === true && isset($_SESSION["token"]) && $_SESSION["token"] === true)
        {
            $trimmed_name = $_SESSION["user"];
            if (psf_string_contains($trimmed_name, '_'))
                $trimmed_name = substr($trimmed_name, 0, strrpos($trimmed_name, '_'));
            return $trimmed_name;
        }
        if (($g_auth === "ldap" || $g_auth === "file") && isset($_SESSION["user"]))
            return $_SESSION["user"];
        if (!isset($_SERVER['REMOTE_USER']))
            return "unknown user";
        return $_SERVER['REMOTE_USER'];
    }

    public static function RefreshSession()
    {
        global $g_session_timeout, $g_auth_session_name, $g_auth_roles_map;

        $headers = getallheaders();
        if (isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches))
        {
            $sessionId = $matches[1];
            session_id($sessionId);
        }

        session_name($g_auth_session_name);
        session_set_cookie_params([
                                      'lifetime' => 0,
                                      'path' => '/',
                                      'httponly' => true,
                                      'samesite' => 'Lax'
                                  ]);
        session_start();
        if (isset($_SESSION["time"]))
        {
            if ((time() - $_SESSION["time"]) > $g_session_timeout)
            {
                // This session timed out
                session_unset();
            }
        }
        $_SESSION["time"] = time();
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] && isset($_SESSION['user']) && isset($_SESSION['groups']))
        {
            // This user is logged in - we cached group list within session so that we don't need to query LDAP every single time
            $g_auth_roles_map[$_SESSION['user']] = $_SESSION['groups'];
        }
    }

    public static function GetCSRFToken()
    {
        if (!isset($_SESSION['csrf_token']))
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        return $_SESSION['csrf_token'];
    }

    public static function CheckCSRFToken()
    {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']))
            Error('Invalid CSRF token');
    }

    public static function GetLoginInfo()
    {
        global $g_auth_roles_map;
        $role_info = '';
        if ($g_auth_roles_map !== NULL && array_key_exists($_SESSION['user'], $g_auth_roles_map))
        {
            $role_info = ' (' . psf_string_auto_trim(implode (', ', $g_auth_roles_map[$_SESSION['user']]), 80, '...') . ')';
        }
        $csrf_token = htmlspecialchars(self::GetCSRFToken(), ENT_QUOTES, 'UTF-8');
        return '<div class="login_info"><span class="bi bi-person-fill"></span>' . htmlspecialchars($_SESSION["user"], ENT_QUOTES, 'UTF-8') . htmlspecialchars($role_info, ENT_QUOTES, 'UTF-8') .
               ' <form method="post" action="index.php" class="inline-form"><input type="hidden" name="csrf_token" value="' . $csrf_token . '">' .
               '<button type="submit" name="logout" value="1" class="icon-button"><span class="bi bi-box-arrow-right" title="logout"></span></button></form></div>';
    }

    private static function ProcessLoginError($reason)
    {
        global $g_login_failure_reason, $g_login_failed;
        $extra = '';
        if (isset($_POST["loginUsername"]))
           $extra = 'username=' . $_POST["loginUsername"] . ' ';
        $g_login_failed = true;
        $g_login_failure_reason = $reason;
        $_SESSION['logged_in'] = false;
        Audit::Write('login_fail', $extra . 'reason=' . $reason);
        Common::IncrementStat('login_error');
    }

    public static function ProcessTokenLogin()
    {
        global $g_login_failed, $g_api_tokens;
        if (!isset($_POST['token']))
        {
            self::ProcessLoginError("No token");
            return;
        }
        $token = $_POST['token'];
        if (in_array($token, $g_api_tokens))
        {
            session_regenerate_id(true);
            $_SESSION["user"] = $token;
            $_SESSION["logged_in"] = true;
            $_SESSION["token"] = true;
            Audit::Write('login_success');
            Common::IncrementStat('token_login_success');
            return;
        }
        // Invalid token
        $g_login_failed = true;
        $_SESSION["logged_in"] = false;
        Audit::Write('login_fail', 'token=' . $token . ' reason=invalid token');
        Common::IncrementStat('token_login_error');
    }

    private static function LDAPGroupNameFromCN($name)
    {
        if (!psf_string_startsWith($name, 'CN='))
            return $name;

        $name = substr($name, 3);
        if (!psf_string_contains($name, ','))
            return $name;

        return substr($name, 0, strpos($name, ','));
    }

    private static function LDAPEscapeFilterValue($value)
    {
        if (function_exists('ldap_escape'))
            return ldap_escape($value, '', LDAP_ESCAPE_FILTER);

        return str_replace(
            ['\\', '*', '(', ')', "\x00"],
            ['\\5c', '\\2a', '\\28', '\\29', '\\00'],
            $value
        );
    }

    private static function FetchDomainGroups($ldap, $login_name)
    {
        global $g_auth_domain_prefix, $g_auth_ldap_dn, $g_auth_roles_map, $g_auth_roles;
        $ldap_user_search_string = $_POST["loginUsername"];
        // Automatically correct user name
        if ($g_auth_domain_prefix !== NULL && psf_string_startsWith($ldap_user_search_string, $g_auth_domain_prefix))
            $ldap_user_search_string = substr($ldap_user_search_string, strlen($g_auth_domain_prefix));

        // Read groups and insert them to list of roles this user is member of
        $ldap_user_search_string = self::LDAPEscapeFilterValue($ldap_user_search_string);
        $ldap_groups = ldap_search($ldap, $g_auth_ldap_dn, "(samaccountname=$ldap_user_search_string)", array("memberof", "primarygroupid"));
        if ($ldap_groups === false)
        {
            CommonUI::DisplayWarning("Unable to retrieve list of groups for this user from LDAP (ldap_search() returned false) - is your ldap_dn correct?");
            return;
        } else
        {
            $entries = ldap_get_entries($ldap, $ldap_groups);
            if ($entries === false)
            {
                CommonUI::DisplayWarning("Unable to retrieve list of groups for this user from LDAP (ldap_get_entries() returned false) - is your ldap_dn correct?");
                return;
            }
            if ($entries['count'] == 0)
            {
                CommonUI::DisplayWarning('Unable to retrieve list of groups for this user from LDAP ($entries[\'count\'] == 0) - is your ldap_dn correct?');
                return;
            }
            if (!array_key_exists($login_name, $g_auth_roles_map))
            {
                // Create an empty array to fill up with groups this user is member of
                $g_auth_roles_map[$login_name] = [];
            }
            // Convert these insane LDAP strings to human readable format that it's far easier to work with
            $ldap_group_entries = [];
            foreach ($entries[0]['memberof'] as $ldap_group_entry)
            {
                $ldap_group_name = self::LDAPGroupNameFromCN($ldap_group_entry);
                // Only store relevant groups, users are typically members of many groups, but we only care about these which also exist as roles
                if (array_key_exists($ldap_group_name, $g_auth_roles))
                    $ldap_group_entries[] = $ldap_group_name;
            }
            $g_auth_roles_map[$login_name] = array_merge($g_auth_roles_map[$login_name], $ldap_group_entries);
            // Preserve the list of groups this user is in
            $_SESSION['groups'] = $g_auth_roles_map[$login_name];
        }
    }

    private static function ProcessLDAPLogin()
    {
        global $g_auth_ldap_url, $g_login_failed, $g_auth_allowed_users, $g_auth_fetch_domain_groups, $g_auth_roles_map,
               $g_auth_ldap_dn, $g_auth_domain_prefix, $g_auth_roles, $g_auth_disallow_users_with_no_roles;

        // Security hole - some LDAP servers will allow anonymous bind so empty password = access granted
        // PHP also kind of suck with strlen, so we need to check for multiple return values

        // This probably could be replaced with empty() which however has weird behaviour depending on PHP versions
        // so let's be safe here since this is a security thing and implement our own "is_really_empty_string"
        $pwl = strlen($_POST["loginPassword"]);
        if ($pwl === NULL || $pwl === 0)
        {
            self::ProcessLoginError('Empty password is not allowed');
            return;
        }

        $ldap = ldap_connect($g_auth_ldap_url);
        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

        $login_name = $_POST["loginUsername"];
        // Check if we need to tweak the username
        if ($g_auth_domain_prefix !== NULL)
        {
            if (!psf_string_startsWith($login_name, $g_auth_domain_prefix))
                $login_name = $g_auth_domain_prefix . $login_name;
        }

        if ($bind = ldap_bind($ldap, $login_name, $_POST["loginPassword"]))
        {
            // Login OK
            if ($g_auth_allowed_users !== NULL)
            {
                // Check if this user is allowed to login
                if (!in_array($login_name, $g_auth_allowed_users))
                {
                    self::ProcessLoginError("This user is not allowed to login to this tool (username not present in config.php)");
                    return;
                }
            }

            // If it's enabled get a list of LDAP groups for this user
            if ($g_auth_fetch_domain_groups)
                self::FetchDomainGroups($ldap, $login_name);

            // Check if only users with some groups are allowed to login
            if ($g_auth_roles !== NULL && $g_auth_disallow_users_with_no_roles)
            {
                if (empty($g_auth_roles_map[$login_name]))
                {
                    self::ProcessLoginError('You are not a member of any group with access to this tool');
                    return;
                }
            }
            session_regenerate_id(true);
            $_SESSION['user'] = $login_name;
            $_SESSION['logged_in'] = true;
            Audit::Write('login_success');
            Common::IncrementStat('login_success');
        } else
        {
            // Invalid user / pw
            Audit::Write('login_fail', 'username=' . $login_name . ' reason=invalid username or password');
            $g_login_failed = true;
            $_SESSION["logged_in"] = false;
            Common::IncrementStat('login_fail');
        }
    }

    // This functions uses a custom file based DB located in $g_auth_file_db which is a text file in this format:
    // username:password_hash:enabled(true/false):role1,role2,role3
    private static function ProcessFileLogin()
    {
        global $g_auth_file_db, $g_login_failed, $g_auth_allowed_users, $g_auth_roles_map;

        // Check if we have the credentials
        if (!isset($_POST["loginUsername"]) || !isset($_POST["loginPassword"]))
        {
            self::ProcessLoginError("No credentials provided (loginUsername or loginPassword missing)");
            return;
        }

        $username = $_POST["loginUsername"];
        $password = $_POST["loginPassword"];

        // Check if this user is allowed to login
        if ($g_auth_allowed_users !== NULL && !in_array($username, $g_auth_allowed_users))
        {
            self::ProcessLoginError("This user is not allowed to login to this tool (username not present in config.php)");
            return;
        }

        $passwd = new PasswdFile($g_auth_file_db);
        if (!$passwd->Load())
        {
            self::ProcessLoginError("Unable to load password file $g_auth_file_db");
            return;
        }

        $user = $passwd->GetUser($username);

        if ($user === null)
        {
            self::ProcessLoginError("Invalid username or password");
            return;
        }

        if (!$user['enabled'])
        {
            self::ProcessLoginError("This user is disabled");
            return;
        }

        if (!password_verify($password, $user['password_hash']))
        {
            self::ProcessLoginError("Invalid password for user '$username'");
            return;
        }

        // Login OK
        session_regenerate_id(true);
        $_SESSION['user'] = $username;
        $_SESSION['logged_in'] = true;
        $roles = $user['roles'];
        if (count($roles) > 0)
        {
            // Store roles in global map
            $g_auth_roles_map[$username] = $roles;
            $_SESSION['groups'] = $roles; // Also store in session for later use
        }
        Audit::Write('login_success');
        Common::IncrementStat('login_success');
    }

    public static function ProcessLogin()
    {
        global $g_auth;

        // If user is already logged in, do nothing (probably just hit refresh in browser and re-sent POST data)
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true)
        {
            CommonUI::DisplayWarning('You are already logged in, if you want to login again as someone else, logout first');
            return;
        }

        // Check if we have the credentials
        if (!isset($_POST["loginUsername"]) || !isset($_POST["loginPassword"]))
        {
            self::ProcessLoginError("No credentials provided (loginUsername or loginPassword missing)");
            return;
        }

        // LDAP
        if ($g_auth == "ldap")
        {
            self::ProcessLDAPLogin();
            return;
        }

        // File
        if ($g_auth == "file")
        {
            self::ProcessFileLogin();
            return;
        }

        self::ProcessLoginError("Unsupported authentication mechanism");
        return;
    }

    public static function RequireLogin()
    {
        global $g_auth, $g_logged_in;
        if ($g_logged_in)
            return false;

        if ($g_auth === NULL)
            return false;

        if ($g_auth != "ldap" && $g_auth != "file")
            Error("Unsupported authentication mechanism");

        // Check if we have the credentials
        if (!isset( $_SESSION["user"] ) || !isset( $_SESSION["logged_in"] ))
            return true;

        if ($_SESSION["logged_in"])
        {
            $g_logged_in = true;
            return false;
        }

        return true;
    }

    public static function GetLogin()
    {
        $login_form = new LoginForm();
        return $login_form;
    }
}
