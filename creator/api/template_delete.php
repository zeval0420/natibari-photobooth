<?php
/**
 * creator/api/template_delete.php
 * POST { id }
 * → { success: true }
 */
require_once __DIR__ . '/bootstrap.php';
admin_require_api();

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id   = sanitize_id($body['id'] ?? '');

if ($id === '') json_error('id is required.', 400);

try {
    CreatorTemplateManager::delete($id);
    json_response(['success' => true]);
} catch (RuntimeException $e) {
    json_error($e->getMessage(), 404);
}
