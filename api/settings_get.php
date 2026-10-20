<?php
/**
 * api/settings_get.php
 * GET -> { success: true, settings: {...}, available_printers: [...] }
 * Admin-only: the public kiosk app only ever needs a few individual
 * values (countdown seconds, sound paths, mirror flag) which are
 * already exposed inline by api/session_create.php / api/templates.php,
 * so the full settings tree (including the admin passcode hash) stays
 * behind the admin gate.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
admin_require_api();

$settings = Settings::all();
unset($settings['admin']['passcode_hash']); // never echo the hash back to the browser

json_response([
    'success' => true,
    'settings' => $settings,
    'available_printers' => PrintManager::listPrinters(),
    'storage_stats' => SessionManager::stats(),
    'os' => PrintManager::isWindows() ? 'windows' : 'posix',
    'available_templates' => array_map(function ($t) {
        return ['id' => $t['id'], 'name' => $t['name']];
    }, TemplateManager::getAll()),
]);
