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

function ShowError($form, $txt)
{
    $msg = new BS_Alert('FATAL: ' . $txt, 'danger', $form);
}

function CheckEmpty($form, $label, $name)
{
    if ($label === NULL || strlen($label) == 0)
    {
        ShowError($form, $name . " must not be empty");
        return false;
    }
    return true;
}

//! Insert a warning message to warning message container, right now this works only with UI, it does nothing when used with API calls
function DisplayWarning($text)
{
    if (G_DNSTOOL_ENTRY_POINT === "api.php")
        return;
    global $g_warning_container;
    $warning_box = new BS_Alert('<b>WARNING:</b> ' . htmlspecialchars($text), 'warning');
    $warning_box->EscapeHTML = false;
    $g_warning_container->AppendObject($warning_box);
}

function GetSwitcher($parent)
{
    global $g_selected_domain, $g_domains;
    $switcher = new DivContainer($parent);
    $switcher->AppendHtmlLine("Zone:");
    $c = new ComboBox("switcher", $switcher);
    $c->OnChangeCallback = "reload()";
    foreach ($g_domains as $domain => $properties)
    {
        if (!IsAuthorizedToRead($domain))
            continue;
        if ($g_selected_domain == $domain)
            $c->AddDefaultValue($domain);
        else
            $c->AddValue($domain);
    }
    $js = new Script("", $parent);
    $js->Source = "function reload()\n" .
                  "{" .
                      'var switcher = document.getElementsByName("switcher");' .
                      'window.open("index.php?action=manage&domain=" + switcher[0].value, "_self");' .
                  "}\n";
    return $switcher;
}