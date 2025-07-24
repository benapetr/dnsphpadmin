<?php

// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

define('G_DNSTOOL_ENTRY_POINT', 'cli.php');

require("definitions.php");
require("config.default.php");
require("config.php");
require_once("psf/psf.php");
require_once("includes/common.php");
require_once("includes/debug.php");
require_once("includes/login.php");
require_once("includes/passwd_file.php");

function info_log($text)
{
    print("$text\n");
}

function fatal_log($text)
{
    print("FATAL: $text\n");
}

/**
 * Read a password from the terminal without displaying it
 * 
 * @param string $prompt The prompt to display to the user
 * @return string The password entered by the user
 */
function read_password($prompt = "Enter password: ")
{
    echo $prompt;
    
    // Check if we're on Windows or Unix
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows approach using COM object if available
        if (class_exists('COM'))
        {
            try
            {
                $shell = new COM('WScript.Shell');
                $obj = $shell->Exec('powershell.exe -Command "$password = Read-Host -AsSecureString; $BSTR = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($password); [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($BSTR)"');
                $password = '';
                while (!$obj->StdOut->AtEndOfStream) {
                    $password .= $obj->StdOut->ReadLine();
                }
                return $password;
            } catch (Exception $e) {
                // Fallback method if COM object fails
            }
        }
        
        // Simple fallback for Windows
        $password = '';
        while (($char = fgetc(STDIN)) !== "\r")
        {
            if ($char === "\x08")
            { // Backspace
                if (strlen($password) > 0)
                    $password = substr($password, 0, -1);
            } else
            {
                $password .= $char;
            }
        }
        fgets(STDIN); // Consume the following \n
        echo "\n";
        return $password;
    } else
    {
        // Unix/Linux approach
        system('stty -echo');
        $password = trim(fgets(STDIN));
        system('stty echo');
        echo "\n"; // Add a newline since the user's Enter keypress wasn't displayed
        return $password;
    }
}

function print_help()
{
    global $g_auth;

    if ($g_auth != "file")
    {
        print("This CLI tool is only available when using file based authentication to manage user database (g_auth = 'file')\n");
        exit(1);
    }

    print ("Supported commands:\n" .
           "  help:                     Displays this help.\n" .
           "  user-list:                  Lists all users.\n" .
           "  user-add username password: Adds a new user.\n" .
           "  user-del username:          Deletes a user.\n" .
           "  user-passwd username:       Changes a user's password.\n" .
           "  user-lock username:          Locks a user account.\n" .
           "  user-unlock username:        Unlocks a user account.\n" .
           "  user-role-list username:      Lists roles for a user.\n" .
           "  user-role-add username role:  Adds a role to a user.\n" .
           "  user-role-del username role:  Deletes a role from a user.\n" .
           "\n");
}

function get_passwd()
{
    global $g_auth_file_db;

    if (!file_exists($g_auth_file_db))
    {
        fatal_log("User database file does not exist: " . $g_auth_file_db);
        exit(1);
    }

    $passwd_file = new PasswdFile($g_auth_file_db);
    if (!$passwd_file->Load())
    {
        fatal_log("Failed to read user database.");
        exit(1);
    }
    
    return $passwd_file;
}

if (php_sapi_name() != "cli")
{
    fatal_log("This can be only executed in CLI mode");
    exit(1);
}

if ($argc < 2)
{
    print_help();
    exit(1);
}

$command = $argv[1];
switch ($command)
{
    case 'help':
        print_help();
        break;

    case 'user-list':
        $passwd_file = get_passwd();
        
        $users = $passwd_file->GetUsers();
        foreach ($users as $user)
        {
            print("User: " . $user['username'] . " (Enabled: " . ($user['enabled'] ? 'Yes' : 'No') . ", Roles: " . implode(',', $user['roles']) . ")\n");
        }
        break;

    case 'user-add':
        if ($argc != 3)
        {
            fatal_log("Usage: user-add username");
            exit(1);
        }
        $username = $argv[2];

        // Get password with masking
        $password = read_password("Enter password for user '$username': ");
        
        if (empty($password))
        {
            fatal_log("Password cannot be empty.");
            exit(1);
        }
        
        // Confirm password
        $confirm_password = read_password("Confirm password: ");
        if ($password !== $confirm_password)
        {
            fatal_log("Passwords do not match.");
            exit(1);
        }
        
        $passwd_file = get_passwd();
        
        if ($passwd_file->UserExists($username))
        {
            fatal_log("User '$username' already exists in database.");
            exit(1);
        }
        
        if ($passwd_file->AddUser($username, $password))
        {
            if ($passwd_file->Save())
            {
                info_log("User '" . $username . "' added successfully.");
            }
            else
            {
                fatal_log("Failed to save user database.");
            }
        }
        else
        {
            fatal_log("Failed to add user '" . $username . "'.");
        }
        break;

    case 'user-del':
        if ($argc != 3)
        {
            fatal_log("Usage: user-del username");
            exit(1);
        }
        
        $username = $argv[2];
        $passwd_file = get_passwd();
        
        if (!$passwd_file->UserExists($username))
        {
            fatal_log("User '$username' not found in database.");
            exit(1);
        }
        
        if ($passwd_file->DeleteUser($username))
        {
            if ($passwd_file->Save())
            {
                info_log("User '" . $username . "' deleted successfully.");
            }
            else
            {
                fatal_log("Failed to save user database.");
            }
        }
        else
        {
            fatal_log("Failed to delete user '" . $username . "'.");
        }
        break;
        
    case 'user-lock':
        if ($argc != 3)
        {
            fatal_log("Usage: user-lock username");
            exit(1);
        }
        
        $username = $argv[2];
        $passwd_file = get_passwd();
        
        if (!$passwd_file->UserExists($username))
        {
            fatal_log("User '$username' not found in database.");
            exit(1);
        }
        
        $result = $passwd_file->LockUser($username);
        if ($result === true)
        {
            if ($passwd_file->Save())
            {
                info_log("User '" . $username . "' locked successfully.");
            }
            else
            {
                fatal_log("Failed to save user database.");
            }
        }
        else if ($result === 'already_locked')
        {
            info_log("User '$username' is already locked.");
        }
        else
        {
            fatal_log("Failed to lock user '" . $username . "'.");
        }
        break;
        
    case 'user-unlock':
        if ($argc != 3)
        {
            fatal_log("Usage: user-unlock username");
            exit(1);
        }
        
        $username = $argv[2];
        $passwd_file = get_passwd();
        
        if (!$passwd_file->UserExists($username))
        {
            fatal_log("User '$username' not found in database.");
            exit(1);
        }
        
        $result = $passwd_file->UnlockUser($username);
        if ($result === true)
        {
            if ($passwd_file->Save())
            {
                info_log("User '" . $username . "' unlocked successfully.");
            }
            else
            {
                fatal_log("Failed to save user database.");
            }
        }
        else if ($result === 'already_unlocked')
        {
            info_log("User '$username' is already unlocked.");
        }
        else
        {
            fatal_log("Failed to unlock user '" . $username . "'.");
        }
        break;
        
    case 'user-role-list':
        if ($argc != 3)
        {
            fatal_log("Usage: user-role-list username");
            exit(1);
        }
        
        $username = $argv[2];
        $passwd_file = get_passwd();
        
        if (!$passwd_file->UserExists($username))
        {
            fatal_log("User '$username' not found in database.");
            exit(1);
        }
        
        $roles = $passwd_file->GetUserRoles($username);
        if (empty($roles) || (count($roles) === 1 && empty($roles[0])))
        {
            info_log("User '" . $username . "' has no roles assigned.");
        }
        else
        {
            info_log("Roles for user '" . $username . "': " . implode(", ", $roles));
        }
        break;
        
    case 'user-role-add':
        if ($argc != 4)
        {
            fatal_log("Usage: user-role-add username role");
            exit(1);
        }
        
        $username = $argv[2];
        $role = $argv[3];
        $passwd_file = get_passwd();
        
        if (!$passwd_file->UserExists($username))
        {
            fatal_log("User '$username' not found in database.");
            exit(1);
        }
        
        if ($passwd_file->AddRoleToUser($username, $role))
        {
            if ($passwd_file->Save())
            {
                info_log("Role '" . $role . "' added to user '" . $username . "' successfully.");
            }
            else
            {
                fatal_log("Failed to save user database.");
            }
        }
        else
        {
            fatal_log("Failed to add role '" . $role . "' to user '" . $username . "'.");
        }
        break;
        
    case 'user-role-del':
        if ($argc != 4)
        {
            fatal_log("Usage: user-role-del username role");
            exit(1);
        }
        
        $username = $argv[2];
        $role = $argv[3];
        $passwd_file = get_passwd();
        
        if (!$passwd_file->UserExists($username))
        {
            fatal_log("User '$username' not found in database.");
            exit(1);
        }
        
        if ($passwd_file->DeleteRoleFromUser($username, $role))
        {
            if ($passwd_file->Save())
            {
                info_log("Role '" . $role . "' removed from user '" . $username . "' successfully.");
            }
            else
            {
                fatal_log("Failed to save user database.");
            }
        }
        else
        {
            fatal_log("Failed to remove role '" . $role . "' from user '" . $username . "'.");
        }
        break;
        
    case 'user-passwd':
        if ($argc != 3)
        {
            fatal_log("Usage: user-passwd username");
            exit(1);
        }
        
        $username = $argv[2];
        $passwd_file = get_passwd();
        
        if (!$passwd_file->UserExists($username))
        {
            fatal_log("User '$username' not found in database.");
            exit(1);
        }
        
        // Get new password
        $new_password = read_password("Enter new password for user '$username': ");
        if (empty($new_password))
        {
            fatal_log("Password cannot be empty.");
            exit(1);
        }
        
        // Confirm new password
        $confirm_password = read_password("Confirm new password: ");
        if ($new_password !== $confirm_password)
        {
            fatal_log("Passwords do not match.");
            exit(1);
        }
        
        if ($passwd_file->ChangePassword($username, $new_password))
        {
            if ($passwd_file->Save())
            {
                info_log("Password for user '" . $username . "' changed successfully.");
            }
            else
            {
                fatal_log("Failed to save user database.");
            }
        }
        else
        {
            fatal_log("Failed to change password for user '" . $username . "'.");
        }
        break;

    default:
        fatal_log("Unknown command '$command'. Use 'help' for a list of commands.");
}
