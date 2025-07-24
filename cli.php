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
           "  user-lock username:          Locks a user account.\n" .
           "  user-unlock username:        Unlocks a user account.\n" .
           "  user-role-list username:      Lists roles for a user.\n" .
           "  user-role-add username role:  Adds a role to a user.\n" .
           "  user-role-del username role:  Deletes a role from a user.\n" .
           "\n");
}

function get_users_from_file($file)
{
    if (!file_exists($file))
    {
        fatal_log("User database file does not exist: $file");
        return false;
    }

    $users = [];
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line)
    {
        list($username, $password_hash, $enabled, $roles) = explode(':', $line);
        // Store lowercase username but display the original
        $users[] = [
            'username' => $username,
            'username_lower' => strtolower($username),
            'password_hash' => $password_hash,
            'enabled' => ($enabled === 'true'),
            'roles' => explode(',', $roles)
        ];
    }
    return $users;
}

function add_user_to_file($file, $username, $password)
{
    if (empty($username) || empty($password))
    {
        fatal_log("Username and password cannot be empty.");
        return false;
    }
    
    // Convert username to lowercase
    $username = strtolower($username);
    
    // Check if user already exists
    if (file_exists($file))
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line)
        {
            $parts = explode(':', $line);
            $existing_username = strtolower($parts[0]);
            if ($existing_username === $username)
            {
                fatal_log("User '$username' already exists in database.");
                return false;
            }
        }
    }

    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $line = "$username:$password_hash:true:\n";

    if (file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false)
    {
        fatal_log("Failed to write to user database file: $file");
        return false;
    }
    return true;
}

function delete_user_from_file($file, $username)
{
    if (!file_exists($file))
    {
        fatal_log("User database file does not exist: $file");
        return false;
    }
    
    // Convert username to lowercase for comparison
    $username = strtolower($username);

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $new_lines = [];
    $found = false;

    foreach ($lines as $line)
    {
        $parts = explode(':', $line);
        $current_username = strtolower($parts[0]);
        if ($current_username === $username)
        {
            $found = true; // User found, skip this line
            continue;
        }
        $new_lines[] = $line; // Keep other users
    }

    if (!$found)
    {
        fatal_log("User '$username' not found in database.");
        return false;
    }

    if (file_put_contents($file, implode("\n", $new_lines) . "\n") === false)
    {
        fatal_log("Failed to write updated user database file: $file");
        return false;
    }
    return true;
}

function lock_user($file, $username)
{
    if (!file_exists($file))
    {
        fatal_log("User database file does not exist: $file");
        return false;
    }
    
    // Convert username to lowercase for comparison
    $username = strtolower($username);

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $new_lines = [];
    $found = false;
    $already_locked = false;

    foreach ($lines as $line)
    {
        $parts = explode(':', $line);
        $current_username = strtolower($parts[0]);
        if ($current_username === $username)
        {
            $found = true;
            // Check if user is already locked
            if ($parts[2] === 'false')
            {
                $already_locked = true;
                // No change needed
            }
            else
            {
                // Replace 'true' with 'false' to lock the account
                $parts[2] = 'false';
                $line = implode(':', $parts);
            }
        }
        $new_lines[] = $line;
    }

    if (!$found)
    {
        fatal_log("User '$username' not found in database.");
        return false;
    }

    if ($already_locked)
    {
        info_log("User '$username' is already locked.");
        return 'already_locked';
    }

    if (file_put_contents($file, implode("\n", $new_lines) . "\n") === false)
    {
        fatal_log("Failed to write updated user database file: $file");
        return false;
    }
    return true;
}

function unlock_user($file, $username)
{
    if (!file_exists($file))
    {
        fatal_log("User database file does not exist: $file");
        return false;
    }
    
    // Convert username to lowercase for comparison
    $username = strtolower($username);

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $new_lines = [];
    $found = false;
    $already_unlocked = false;

    foreach ($lines as $line)
    {
        $parts = explode(':', $line);
        $current_username = strtolower($parts[0]);
        if ($current_username === $username)
        {
            $found = true;
            // Check if user is already unlocked
            if ($parts[2] === 'true')
            {
                $already_unlocked = true;
                // No change needed
            }
            else
            {
                // Replace 'false' with 'true' to unlock the account
                $parts[2] = 'true';
                $line = implode(':', $parts);
            }
        }
        $new_lines[] = $line;
    }

    if (!$found)
    {
        fatal_log("User '$username' not found in database.");
        return false;
    }

    if ($already_unlocked)
    {
        info_log("User '$username' is already unlocked.");
        return 'already_unlocked';
    }

    if (file_put_contents($file, implode("\n", $new_lines) . "\n") === false)
    {
        fatal_log("Failed to write updated user database file: $file");
        return false;
    }
    return true;
}

function list_user_roles($file, $username)
{
    if (!file_exists($file))
    {
        fatal_log("User database file does not exist: $file");
        return false;
    }
    
    // Convert username to lowercase for comparison
    $username = strtolower($username);

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $found = false;

    foreach ($lines as $line)
    {
        $parts = explode(':', $line);
        $current_username = strtolower($parts[0]);
        if ($current_username === $username)
        {
            $found = true;
            $roles = !empty($parts[3]) ? explode(',', $parts[3]) : [];
            return $roles;
        }
    }

    if (!$found)
    {
        fatal_log("User '$username' not found in database.");
        return false;
    }
}

function add_role_to_user($file, $username, $role)
{
    if (!file_exists($file))
    {
        fatal_log("User database file does not exist: $file");
        return false;
    }
    
    // Convert username to lowercase for comparison
    $username = strtolower($username);

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $new_lines = [];
    $found = false;

    foreach ($lines as $line)
    {
        $parts = explode(':', $line);
        $current_username = strtolower($parts[0]);
        if ($current_username === $username)
        {
            $found = true;
            $roles = !empty($parts[3]) ? explode(',', $parts[3]) : [];
            
            // Check if role already exists
            if (in_array($role, $roles))
            {
                info_log("Role '$role' already assigned to user '$username'.");
                return true;
            }
            
            // Add the new role
            $roles[] = $role;
            $parts[3] = implode(',', $roles);
            $line = implode(':', $parts);
        }
        $new_lines[] = $line;
    }

    if (!$found)
    {
        fatal_log("User '$username' not found in database.");
        return false;
    }

    if (file_put_contents($file, implode("\n", $new_lines) . "\n") === false)
    {
        fatal_log("Failed to write updated user database file: $file");
        return false;
    }
    return true;
}

function delete_role_from_user($file, $username, $role)
{
    if (!file_exists($file))
    {
        fatal_log("User database file does not exist: $file");
        return false;
    }
    
    // Convert username to lowercase for comparison
    $username = strtolower($username);

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $new_lines = [];
    $found = false;
    $role_found = false;

    foreach ($lines as $line)
    {
        $parts = explode(':', $line);
        $current_username = strtolower($parts[0]);
        if ($current_username === $username)
        {
            $found = true;
            $roles = !empty($parts[3]) ? explode(',', $parts[3]) : [];
            
            // Find and remove the role
            $key = array_search($role, $roles);
            if ($key !== false)
            {
                unset($roles[$key]);
                $role_found = true;
                $parts[3] = implode(',', $roles);
                $line = implode(':', $parts);
            }
            else
            {
                fatal_log("Role '$role' not assigned to user '$username'.");
                return false;
            }
        }
        $new_lines[] = $line;
    }

    if (!$found)
    {
        fatal_log("User '$username' not found in database.");
        return false;
    }

    if (!$role_found)
    {
        fatal_log("Role '$role' not found for user '$username'.");
        return false;
    }

    if (file_put_contents($file, implode("\n", $new_lines) . "\n") === false)
    {
        fatal_log("Failed to write updated user database file: $file");
        return false;
    }
    return true;
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
        $users = get_users_from_file($g_auth_file_db);
        if ($users === false)
        {
            fatal_log("Failed to read user database.");
            exit(1);
        }
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
        if (add_user_to_file($g_auth_file_db, $username, $password))
        {
            info_log("User '" . $username . "' added successfully.");
        } else
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
        if (delete_user_from_file($g_auth_file_db, $argv[2]))
        {
            info_log("User '" . $argv[2] . "' deleted successfully.");
        }
        else
        {
            fatal_log("Failed to delete user '" . $argv[2] . "'.");
        }
        break;
        
    case 'user-lock':
        if ($argc != 3)
        {
            fatal_log("Usage: user-lock username");
            exit(1);
        }
        $result = lock_user($g_auth_file_db, $argv[2]);
        if ($result === true)
        {
            info_log("User '" . $argv[2] . "' locked successfully.");
        }
        else if ($result === 'already_locked')
        {
            // Message already displayed by the function
        }
        else
        {
            fatal_log("Failed to lock user '" . $argv[2] . "'.");
        }
        break;
        
    case 'user-unlock':
        if ($argc != 3)
        {
            fatal_log("Usage: user-unlock username");
            exit(1);
        }
        $result = unlock_user($g_auth_file_db, $argv[2]);
        if ($result === true)
        {
            info_log("User '" . $argv[2] . "' unlocked successfully.");
        }
        else if ($result === 'already_unlocked')
        {
            // Message already displayed by the function
        }
        else
        {
            fatal_log("Failed to unlock user '" . $argv[2] . "'.");
        }
        break;
        
    case 'user-role-list':
        if ($argc != 3)
        {
            fatal_log("Usage: user-role-list username");
            exit(1);
        }
        $roles = list_user_roles($g_auth_file_db, $argv[2]);
        if ($roles !== false)
        {
            if (empty($roles) || (count($roles) === 1 && empty($roles[0])))
            {
                info_log("User '" . $argv[2] . "' has no roles assigned.");
            }
            else
            {
                info_log("Roles for user '" . $argv[2] . "': " . implode(", ", $roles));
            }
        }
        break;
        
    case 'user-role-add':
        if ($argc != 4)
        {
            fatal_log("Usage: user-role-add username role");
            exit(1);
        }
        if (add_role_to_user($g_auth_file_db, $argv[2], $argv[3]))
        {
            info_log("Role '" . $argv[3] . "' added to user '" . $argv[2] . "' successfully.");
        }
        else
        {
            fatal_log("Failed to add role '" . $argv[3] . "' to user '" . $argv[2] . "'.");
        }
        break;
        
    case 'user-role-del':
        if ($argc != 4)
        {
            fatal_log("Usage: user-role-del username role");
            exit(1);
        }
        if (delete_role_from_user($g_auth_file_db, $argv[2], $argv[3]))
        {
            info_log("Role '" . $argv[3] . "' removed from user '" . $argv[2] . "' successfully.");
        }
        else
        {
            fatal_log("Failed to remove role '" . $argv[3] . "' from user '" . $argv[2] . "'.");
        }
        break;

    default:
        fatal_log("Unknown command '$command'. Use 'help' for a list of commands.");
}
