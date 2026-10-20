<?php
/**
 * creator/api/template_duplicate.php
 * POST { source_id, new_name }
 * → { success: true, new_id }
 */
require_once __DIR__ . '/bootstrap.php';
admin_require_api();

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$sourceId = sanitize_id($body['source_id'] ?? '');
$newName  = trim($body['new_name'] ?? '');

if ($sourceId === '') json_error('source_id is required.', 400);
if ($newName  === '') json_error('new_name is required.', 400);

try {
    $newId = CreatorTemplateManager::duplicate($sourceId, $newName);
    json_response(['success' => true, 'new_id' => $newId]);
} catch (RuntimeException $e) {
    json_error($e->getMessage(), 409);
}
