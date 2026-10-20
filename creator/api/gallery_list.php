<?php
/**
 * creator/api/gallery_list.php
 * GET → { success: true, templates: [...] }
 * Admin-only.
 */
require_once __DIR__ . '/bootstrap.php';
admin_require_api();

json_response([
    'success'   => true,
    'templates' => CreatorTemplateManager::listAll(),
]);
