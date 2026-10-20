<?php
/**
 * creator/api/template_rename.php
 * POST { id, new_name }
 * → { success: true, new_id }
 */
require_once __DIR__ . '/bootstrap.php';
admin_require_api();

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$id      = sanitize_id($body['id'] ?? '');
$newName = trim($body['new_name'] ?? '');

if ($id === '')      json_error('id is required.', 400);
if ($newName === '') json_error('new_name is required.', 400);

try {
    $newId = CreatorTemplateManager::rename($id, $newName);
    json_response(['success' => true, 'new_id' => $newId]);
} catch (RuntimeException $e) {
    json_error($e->getMessage(), 409);
}
