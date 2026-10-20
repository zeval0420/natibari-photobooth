<?php
/**
 * creator/api/import.php
 * POST (multipart) zipfile=<file>
 * → { success: true, id, name }
 */
require_once __DIR__ . '/bootstrap.php';
admin_require_api();

if (empty($_FILES['zipfile'])) {
    json_error('No ZIP file was uploaded.', 400);
}

try {
    $result = ImportExportManager::importFromZip($_FILES['zipfile']);
    json_response(['success' => true, 'id' => $result['id'], 'name' => $result['name']]);
} catch (RuntimeException $e) {
    json_error($e->getMessage(), 422);
}
