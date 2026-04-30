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

require_once("notifications.php");
require_once("auth.php");

class CommonUI
{
    public static function ShowError($form, $txt)
    {
        $msg = new BS_Alert('FATAL: ' . $txt, 'danger', $form);
    }

    public static function CheckEmpty($form, $label, $name)
    {
        if ($label === NULL || strlen($label) == 0)
        {
            self::ShowError($form, $name . " must not be empty");
            return false;
        }
        return true;
    }

    public static function DisplayWarning($text)
    {
        Notifications::DisplayWarning($text);
    }

    public static function GetSwitcher($parent)
    {
        global $g_selected_domain, $g_domains;
        $switcher = new DivContainer($parent);
        $switcher->AppendHtmlLine("Zone:");
        $c = new ComboBox("switcher", $switcher);
        $c->OnChangeCallback = "reload()";
        foreach ($g_domains as $domain => $properties)
        {
            if (!Auth::IsAuthorizedToRead($domain))
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
}

function ShowError($form, $txt)
{
    return CommonUI::ShowError($form, $txt);
}

function CheckEmpty($form, $label, $name)
{
    return CommonUI::CheckEmpty($form, $label, $name);
}

function DisplayWarning($text)
{
    return CommonUI::DisplayWarning($text);
}

function GetSwitcher($parent)
{
    return CommonUI::GetSwitcher($parent);
}
