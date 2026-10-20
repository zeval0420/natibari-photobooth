<?php
/**
 * includes/bootstrap.php
 * ------------------------------------------------------------------
 * Loaded by every PHP entry point (index.php, admin/*.php, api/*.php).
 * Defines absolute filesystem paths once so the rest of the app never
 * has to guess where things live. No database, no Composer — just
 * plain PHP files and the filesystem, as required by the project brief.
 * ------------------------------------------------------------------
 */

// Show errors during development; flip APP_DEBUG to false for a kiosk deployment.
define('APP_DEBUG', true);
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

date_default_timezone_set('Asia/Manila');

// ---- Core paths -----------------------------------------------------
define('ROOT_PATH', dirname(__DIR__));                       // /.../photobooth
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
define('SESSIONS_PATH', ROOT_PATH . '/sessions');
define('FINAL_PATH', ROOT_PATH . '/final');                  // every finished strip + print file, flat
define('ASSETS_PATH', ROOT_PATH . '/assets');

define('SETTINGS_FILE', CONFIG_PATH . '/settings.json');
define('SETTINGS_DEFAULT_FILE', CONFIG_PATH . '/settings.default.json');

// ---- Web-facing base URL (so generated links work from any sub-folder) ----
function app_base_url(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Walk up from the currently executing script to the project root.
    $depth = 0;
    $rel = str_replace(ROOT_PATH, '', dirname(realpath($_SERVER['SCRIPT_FILENAME'] ?? __FILE__)));
    $depth = $rel === '' ? 0 : count(array_filter(explode('/', $rel)));
    $prefix = str_repeat('../', $depth);
    return $prefix === '' ? './' : $prefix;
}

// ---- Autoload the handful of plain classes (no Composer needed) ----
spl_autoload_register(function ($class) {
    $file = INCLUDES_PATH . '/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once INCLUDES_PATH . '/helpers.php';

// Make sure the folders we depend on exist even on a brand new checkout.
foreach ([CONFIG_PATH, TEMPLATES_PATH, SESSIONS_PATH, FINAL_PATH] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}
