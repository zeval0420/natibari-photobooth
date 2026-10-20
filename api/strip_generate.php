<?php
/**
 * api/strip_generate.php
 * POST { session_id }
 * -> { success: true, strip_url }
 *
 * Runs the server-side Compositor (GD) using the template that was
 * recorded on this session at creation time, so the layout is always
 * driven by that template's config.json.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST required', 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$sessionId = sanitize_id($body['session_id'] ?? '');

if ($sessionId === '' || !is_dir(SessionManager::dir($sessionId))) {
    json_error('Unknown session', 404);
}

$meta = SessionManager::getMetadata($sessionId);
$template = TemplateManager::find($meta['template'] ?? '');
if ($template === null) {
    json_error('Session references a missing template: ' . ($meta['template'] ?? ''), 500);
}

try {
    $relativeStripPath = Compositor::composeStrip($sessionId, $template);
} catch (Throwable $e) {
    json_error('Failed to generate strip: ' . $e->getMessage(), 500);
}

SessionManager::recordStrip($sessionId, $relativeStripPath);

json_response([
    'success' => true,
    'strip_url' => $relativeStripPath . '?t=' . time(),
]);
