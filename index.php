<?php

// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

require("psf/psf.php");
require_once("psf/default_config.php");

// Save us some coding
$psf_containers_auto_insert_child = true;

$website = new HtmlPage("DNS management");
bootstrap_init($website);

$fc = new BS_FluidContainer($website);
$fc->AppendHeader("DNS management tool");

$website->PrintHtml();

