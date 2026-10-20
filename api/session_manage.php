<?php
/**
 * api/session_manage.php
 * POST { action: "delete" | "purge_raw", session_id }   — one session
 * POST { action: "purge_all_raw" }                      — every session
 * Admin-only housekeeping for the Gallery and the Storage settings.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
admin_require_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST required', 405);
}
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';
$id = sanitize_id($body['session_id'] ?? '');

switch ($action) {
    case 'delete':
        if (!SessionManager::delete($id)) {
            json_error('Could not delete that session.', 404);
        }
        json_response(['success' => true]);
    case 'purge_raw':
        if ($id === '' || !is_dir(SessionManager::dir($id))) {
            json_error('Unknown session', 404);
        }
        SessionManager::purgeRaw($id);
        json_response(['success' => true]);
    case 'purge_all_raw':
        json_response(['success' => true, 'sessions' => SessionManager::purgeAllRaw(), 'stats' => SessionManager::stats()]);
}
json_error('Unknown action', 400);
