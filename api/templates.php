<?php
/**
 * api/templates.php
 * GET -> { success: true, templates: [...] }
 * Rescans templates/ on every call (cheap — handful of folders) so
 * dropping in a new template folder is picked up immediately.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$templates = TemplateManager::getAll();

// Strip the server-side-only absolute path before sending to the browser.
$publicTemplates = array_map(function ($t) {
    unset($t['frame_path']);
    return $t;
}, $templates);

json_response([
    'success' => true,
    'templates' => $publicTemplates,
    'default_template' => Settings::get('templates.default_template', $publicTemplates[0]['id'] ?? null),
]);
