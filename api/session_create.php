<?php
/**
 * api/session_create.php
 * POST { template: "01-classic-strip" }
 * -> { success: true, session_id, photos_needed, countdown_seconds, mirror_preview }
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST required', 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$templateId = sanitize_id($body['template'] ?? '');

$template = TemplateManager::find($templateId);
if ($template === null) {
    json_error('Unknown or invalid template: ' . $templateId, 404);
}

SessionManager::cleanupStale();
$session = SessionManager::create($templateId);

json_response([
    'success' => true,
    'session_id' => $session['session_id'],
    'template' => $template['id'],
    'photos_needed' => count($template['photos']),
    'countdown_seconds' => (int)Settings::get('countdown.seconds', 3),
    'mirror_preview' => (bool)Settings::get('camera.mirror_preview', true),
]);
