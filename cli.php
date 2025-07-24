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
require_once("includes/fatal_api.php");
require_once("includes/record_list.php");
require_once("includes/modify.php");
require_once("includes/notifications.php");
require_once("includes/login.php");
require_once("includes/validator.php");
require_once("includes/zones.php");

function info_log($text)
{
    print("$text\n");
}

function fatal_log($text)
{
    print("FATAL: $text\n");
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
        $users[] = [
            'username' => $username,
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

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $new_lines = [];
    $found = false;

    foreach ($lines as $line)
    {
        list($current_username) = explode(':', $line);
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

        // Interactive password input
        if (function_exists('readline'))
        {
            $password = readline("Enter password for user '$username': ");
        } else {
            print("Enter password for user '$username': ");
            $password = trim(fgets(STDIN));
        }
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

    default:
        fatal_log("Unknown command '$command'. Use 'help' for a list of commands.");
}
