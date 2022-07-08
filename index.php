<?php

// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// Register index.php as valid entry point
define('G_DNSTOOL_ENTRY_POINT', 'index.php');

require("definitions.php");
require("config.default.php");
require("config.php");
require_once("psf/psf.php");
require_once("includes/common.php");
require_once("includes/fatal.php");
require_once("includes/menu.php");
require_once("includes/modify.php");
require_once("includes/record_list.php");
require_once("includes/tab_overview.php");
require_once("includes/tab_manage.php");
require_once("includes/tab_edit.php");
require_once("includes/tab_batch.php");
require_once("includes/login.php");

if ($g_debug === true)
    psf_php_enable_debug();

date_default_timezone_set($g_timezone);

if ($g_use_local_bootstrap)
{
    $psf_bootstrap_js_url = 'bootstrap-3.3.7/dist/js/bootstrap.min.js';
    $psf_bootstrap_css_url = 'bootstrap-3.3.7/dist/css/bootstrap.min.css';
}

// Start up the program, initialize all sorts of resources, syslog, session data etc.
Initialize();

if ($g_user_config_prefix !== null)
    include($g_user_config_prefix.GetCurrentUserName().".php");

// Save us some coding
$psf_containers_auto_insert_child = true;

// Global vars
$g_selected_domain = null;
$g_action = null;

$website = new HtmlPage("DNS management");
$website->ExternalCss[] = 'style.css';
if (!$g_use_local_jquery)
    $website->ExternalJs[] = "https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js";
else
    $website->ExternalJs[] = "jquery-3.3.1.min.js";
$website->Style->items["td"]["word-wrap"] = "break-word";
$website->Style->items["td"]["max-width"] = "280px";
bootstrap_init($website);

// Create a bootstrap fluid containers, one for whole website and one for errors, which are dynamically inserted to error container as they are generated
$fc = new BS_FluidContainer($website);

if (isset($_GET['login']))
    ProcessLogin();

if (isset($_GET['logout']))
    session_unset();

// Recover unfinished request
// we must do this before checking POST and GET parameters - because they take precedence over preserved data
if (isset($_SESSION['preserved_domain']))
{
    $g_selected_domain = $_SESSION['preserved_domain'];
    unset($_SESSION['preserved_domain']);
}

if (isset($_SESSION['preserved_action']))
{
    $g_action = $_SESSION['preserved_action'];
    unset($_SESSION['preserved_action']);
}

if (isset($_GET['action']))
    $g_action = $_GET['action'];

if (isset($_GET['domain']))
    $g_selected_domain = $_GET['domain'];
else if (isset($_POST['zone']))
    $g_selected_domain = $_POST['zone'];

// Check if login is needed
if (RequireLogin())
{
    // If we were trying to run action=manage on some domain, preserve the link as much as we can,
    // so that user can resume the operation after login
    if ($g_action === 'manage' || $g_action === 'new' || $g_action === 'batch')
        $_SESSION['preserved_action'] = $g_action;
    if ($g_selected_domain !== null)
        $_SESSION['preserved_domain'] = $g_selected_domain;
    $fc->AppendHeader('Login to ' . G_HEADER);
    if ($g_auth_login_banner !== NULL)
        $fc->AppendObject(new BS_Alert($g_auth_login_banner, 'info'));

    // Display warnings and errors if there are any
    $fc->AppendObject($g_warning_container);
    $fc->AppendObject($g_error_container);

    if ($g_login_failed)
        $fc->AppendObject(new BS_Alert($g_login_failure_reason, 'danger'));
    $fc->AppendObject(GetLogin());
} else
{
    $header = new DivContainer($fc);
    $header->ClassName = 'header';
    $header->AppendObject(new Image("favicon.png", "DNS"));
    $header->AppendHeader(G_HEADER);
    if ($g_logged_in)
        $fc->AppendHtml(GetLoginInfo());

    // Display warnings if there are any
    $fc->AppendObject($g_warning_container);
    $fc->AppendObject($g_error_container);

    $fc->AppendObject(GetMenu($fc));

    if ($g_action === null)
    {
        $fc->AppendHeader("Select a zone to manage", 2);
        $fc->AppendObject(TabOverview::GetSelectForm($fc));
    } else if ($g_action == "manage")
    {
        TabManage::ProcessDelete($fc);
        TabManage::GetContents($fc);
    } else if ($g_action == 'csv')
    {
        // Export the current zone as CSV (if user can actually read it)
        $table = GetRecordListTablePlainFormat($fc, $g_selected_domain);
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename=' . $g_selected_domain . '.csv');
        header('Pragma: no-cache');
        print ($table->ToCSV(';', true));
        exit(0);
    } else if ($g_action == 'new')
    {
        // Process previous inserting call (via submit) in case there was some
        TabEdit::Process($fc);
        $fc->AppendObject(TabEdit::GetInsertForm($fc));
        $fc->AppendObject(TabEdit::GetHelp());
    } else if ($g_action == 'edit')
    {
        // Process previous edit call (via submit) in case there was some
        TabEdit::Process($fc);
        $fc->AppendObject(TabEdit::GetEditForm($fc));
        $fc->AppendObject(TabEdit::GetHelp());
    } else if ($g_action == 'batch')
    {
        // Process any previous pending batch operation
        TabBatch::Process($fc);
        $fc->AppendObject(TabBatch::GetForm($fc));
    }
}

// Bug workaround - the footer seems to take up some space
$website->AppendHtml("<br><br><br>");

$website->AppendHtmlLine("<footer class='footer'><div class='container'>Created by Petr Bena [petr@bena.rocks] (c) 2018 - 2020, source code at ".
                    "<a href='https://github.com/benapetr/dnsphpadmin'>https://github.com/benapetr/dnsphpadmin</a> Version: " . G_DNSTOOL_VERSION . "</div></footer>");

$website->PrintHtml();

Debug('Generated in ' . psf_get_execution_time() . 's');
if ($g_debug)
{
    psf_print_debug_as_html();
}

// Close open FD's etc
ResourceCleanup();

