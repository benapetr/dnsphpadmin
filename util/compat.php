<?php

// Treat warnings and deprecations as failures, including while loading PSF.
error_reporting(E_ALL);
set_error_handler(function ($level, $message, $file, $line)
{
    if (error_reporting() & $level)
        throw new ErrorException($message, 0, $level, $file, $line);
    return false;
});

session_start();
define('G_DNSTOOL_ENTRY_POINT', 'compat.php');
require_once(dirname(__FILE__) . '/../definitions.php');
require_once(dirname(__FILE__) . '/../config.default.php');
require_once(dirname(__FILE__) . '/../psf/psf.php');
require_once(dirname(__FILE__) . '/../includes/records.php');
require_once(dirname(__FILE__) . '/../includes/tab_overview.php');
require_once(dirname(__FILE__) . '/../includes/tab_edit.php');
require_once(dirname(__FILE__) . '/../includes/tab_batch.php');

function check_compat($condition, $message)
{
    if (!$condition)
        throw new Exception($message);
}

check_compat(psf_string_endsWith(NULL, '.in-addr.arpa') === false, 'Missing zone must not match reverse-zone suffix');
foreach (array('psf_string_startsWith', 'psf_string_endsWith', 'psf_string_contains') as $helper)
{
    check_compat($helper(NULL, 'example') === false, 'Null input must not match nonempty text');
    check_compat($helper(NULL, '') === true, 'Null input must behave like an empty string');
    check_compat($helper('example', NULL) === true, 'Null search text must behave like an empty string');
    check_compat($helper(NULL, NULL) === true, 'Two null inputs must behave like empty strings');
    check_compat($helper('example.com', 'example.net') === false, 'Unrelated strings must not match');
}
check_compat(psf_string_auto_trim(NULL, 10) === NULL, 'Trimming null must preserve its value');
check_compat(psf_string2bool(NULL) === false, 'Null must not be interpreted as true');
check_compat(psf_string_startsWith('www.example.com', 'www.') === true, 'Hostname prefixes must still match');
check_compat(psf_string_endsWith('example.com', '.com') === true, 'Hostname suffixes must still match');
check_compat(psf_string_contains('www.example.com', 'example') === true, 'Hostname substrings must still match');
check_compat(psf_string_auto_trim('example.com', 7, '...') === 'example...', 'Long strings must still be trimmed');
check_compat(psf_string2bool('TRUE') === true, 'Boolean string parsing must remain case insensitive');

$psf_containers_auto_insert_child = true;
$g_selected_domain = 'example.com';
$g_action = 'manage';
$g_domains = array('example.com' => array('transfer_server' => 'localhost', 'update_server' => 'localhost'));
$g_caching_engine_instance = new PHPDNS_CachingEngine();
// Exercise record rendering with fixture DNS output, without contacting a server.
$zone_data = "example.com. 3600 IN SOA ns.example.com. hostmaster.example.com. 1 3600 600 86400 3600\n" .
             "www.example.com. 300 IN A 192.0.2.1\n" .
             "example.com. 3600 IN SOA ns.example.com. hostmaster.example.com. 1 3600 600 86400 3600\n";
$g_dig = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('echo ' . var_export($zone_data, true) . ';') . ' --';

$page = new HtmlPage('Compatibility test');
bootstrap_init($page, 5);
$table = Records::GetRecordListTable($page, 'example.com');
$html = $table->ToHtml();
check_compat(strpos($html, 'www.example.com.') !== false, 'Record table must render fixture records');
check_compat(strpos($html, 'table-sm') !== false, 'Record table must retain compact styling');

TabOverview::GetSelectForm($page);
TabEdit::GetInsertForm($page);
$g_selected_domain = NULL;
TabEdit::GetInsertForm($page);
$g_selected_domain = 'example.com';
TabEdit::GetHelp();
TabBatch::GetForm($page);
$login = new LoginForm();
$login->ToHtml();
$page->ToHtml();

$table = new HtmlTable();
$table->AppendRow(array('test'));
$table->RowClass[0] = 'highlight';
check_compat(strpos($table->ToHtml(), 'highlight') !== false, 'Row classes must work without row IDs');
$table->RowClass = array();
$table->RowID[0] = 'record-row';
check_compat(strpos($table->ToHtml(), 'id="record-row"') !== false, 'Row IDs must work without row classes');

foreach (array(3, 5) as $version)
{
    bootstrap_init($page, $version);
    $tabs = new BS_Tabs(array('First', 'Second'));
    $tabs->ToHtml();
    $navbar = new BS_Navbar();
    $navbar->AddItem('Home', '/');
    $navbar->AddItem('Records', '/records');
    $navbar->ToHtml();
    $list = new BulletList(array('First', 'Second'));
    $list->ToHtml();
}
foreach (array(1, 10, 50, 99) as $selected)
{
    $paging = new PSF\PagingWidget(100, $selected);
    check_compat(strpos($paging->ToHtml(), '?page=' . $selected) !== false, 'Paging must include selected page');
}

$g_auth = 'file';
$g_auth_roles = array();
$_SESSION['user'] = 'test';
check_compat(Auth::IsAuthorizedToRead('example.com') === false, 'Unset default role must deny access');

// Endpoint discovery used to create an undeclared EndSessionEndpoint property.
class CompatOIDC extends PsfOIDC
{
    public function SetLogoutEndpoint($endpoint)
    {
        $this->EndSessionEndpoint = $endpoint;
    }
}
$oidc = new CompatOIDC('client', 'secret', 'https://example.com/callback', 'https://example.com');
$oidc->SetLogoutEndpoint('https://example.com/logout');

session_write_close();
echo "PHP compatibility checks passed on " . PHP_VERSION . "\n";
