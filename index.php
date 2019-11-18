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
require("includes/fatal.php");
require("includes/menu.php");
require("includes/modify.php");
require("includes/record_list.php");
require("includes/select_form.php");
require("includes/login.php");
require_once("psf/psf.php");

if ($g_debug === true)
    psf_php_enable_debug();

date_default_timezone_set($g_timezone);

if ($g_use_local_bootstrap)
{
    $psf_bootstrap_js_url = 'bootstrap-3.3.7/dist/js/bootstrap.min.js';
    $psf_bootstrap_css_url = 'bootstrap-3.3.7/dist/css/bootstrap.min.css';
}

InitializeCaching();
RefreshSession();

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
$g_error_container = new BS_FluidContainer();
$g_warning_container = new BS_FluidContainer();

if (isset($_GET['login']))
    ProcessLogin();

if (isset($_GET['logout']))
    session_unset();

if (isset($_GET['action']))
    $g_action = $_GET['action'];
if (isset($_GET['domain']))
    $g_selected_domain = $_GET['domain'];
else if (isset($_POST['zone']))
    $g_selected_domain = $_POST['zone'];

// Check if login is needed
if (RequireLogin())
{
    $fc->AppendHeader('Login to DNS management tool');
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
    $fc->AppendHeader('DNS management tool');
    if ($g_logged_in)
        $fc->AppendHtml(GetLoginInfo());

    // Display warnings if there are any
    $fc->AppendObject($g_warning_container);
    $fc->AppendObject($g_error_container);

    $fc->AppendObject(GetMenu($fc));

    if ($g_action === null)
    {
        $fc->AppendHeader("Select a zone to manage", 2);
        $fc->AppendObject(GetSelectForm($fc));
    } else if ($g_action == "manage")
    {
        ProcessDelete($fc);
        if ($g_selected_domain == null)
        {
            reset($g_domains);
            $g_selected_domain = key($g_domains);
        }
        $fc->AppendObject(GetSwitcher($fc));
        $fc->AppendHeader($g_selected_domain, 2);
        $fc->AppendHtml('<div class="export_csv"><a href="?action=csv&domain=' . $g_selected_domain . '">Export as CSV</a></div>');
        $fc->AppendObject(GetStatusOfZoneAsNote($g_selected_domain));
        $fc->AppendObject(GetRecordListTable($fc, $g_selected_domain));
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
        HandleEdit($fc);
        $fc->AppendObject(GetInsertForm($fc));
    } else if ($g_action == 'edit')
    {
        // Process previous edit call (via submit) in case there was some
        HandleEdit($fc);
        $fc->AppendObject(GetEditForm($fc));
    } else if ($g_action == 'batch')
    {
        $fc->AppendObject(GetBatchForm($fc));
    }
}

// Bug workaround - the footer seems to take up some space
$website->AppendHtml("<br><br><br>");

$website->AppendHtmlLine("<footer class='footer'><div class='container'>Created by Petr Bena [petr@bena.rocks] (c) 2018, source code at ".
                    "<a href='http://github.com/benapetr/dnsphpadmin'>http://github.com/benapetr/dnsphpadmin</a> Version: " . G_DNSTOOL_VERSION . "</div></footer>");

$website->PrintHtml();

if ($g_debug)
{
    Debug('Generated in ' . psf_get_execution_time() . 'ms');
    psf_print_debug_as_html();
}

