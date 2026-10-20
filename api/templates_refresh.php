<?php
/**
 * api/templates_refresh.php
 * POST -> { success: true, count, templates: [...] }
 * Templates are always scanned live (see TemplateManager::getAll), so this
 * endpoint mainly exists to give the admin a clear, explicit "Refresh"
 * action and immediate feedback ("Found 6 templates") in the UI.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
admin_require_api();

clearstatcache(); // make sure newly-added folders/files are picked up immediately
$templates = TemplateManager::getAll();

json_response([
    'success' => true,
    'count' => count($templates),
    'templates' => array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name']], $templates),
]);
