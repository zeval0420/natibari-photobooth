<?php
/**
 * api/print_strip.php
 * POST { session_id }
 * Admin-only manual (re)print of any past strip, regardless of auto_print.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
admin_require_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST required', 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$sessionId = sanitize_id($body['session_id'] ?? '');

$meta = SessionManager::getMetadata($sessionId);
if (empty($meta['strip'])) {
    json_error('This session has no generated strip to print', 404);
}

try {
    $meta['strip_print'] = Compositor::printableFor($sessionId, $meta);
    SessionManager::saveMetadata($sessionId, $meta);
    $result = PrintManager::printFile(SessionManager::abs($meta['strip_print']));
} catch (Throwable $e) {
    json_error('Could not prepare the print: ' . $e->getMessage(), 500);
}
SessionManager::recordPrintStatus($sessionId, $result['status'], $result);

json_response(['success' => true, 'print_result' => $result]);
