<?php
/**
 * api/photo_save.php
 * POST { session_id, index (1-4), image: "data:image/jpeg;base64,..." }
 * -> { success: true, path }
 *
 * Used both for the initial capture of photo<N> and for re-takes
 * (the client just calls this again with the same index).
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST required', 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$sessionId = sanitize_id($body['session_id'] ?? '');
$index = (int)($body['index'] ?? 0);
$imageData = $body['image'] ?? '';

if ($sessionId === '' || !is_dir(SessionManager::dir($sessionId))) {
    json_error('Unknown session', 404);
}
if ($index < 1 || $index > 4) {
    json_error('Photo index must be between 1 and 4', 400);
}

$binary = decode_data_url($imageData);
if ($binary === null) {
    json_error('Invalid image data', 400);
}

$path = SessionManager::savePhoto($sessionId, $index, $binary);

json_response([
    'success' => true,
    'index' => $index,
    'path' => 'raw/photo' . $index . '.jpg',
    'url' => 'sessions/' . $sessionId . '/raw/photo' . $index . '.jpg?t=' . time(),
]);
