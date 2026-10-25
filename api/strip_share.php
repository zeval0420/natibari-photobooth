<?php
/**
 * api/strip_share.php
 * POST { session_id }
 * -> { success: true, url }
 *
 * Uploads the session's final strip to the free image host (once — the URL
 * is cached in metadata.json) so the kiosk can show a download QR code.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST required', 405);
}
if (!ImageHost::enabled()) {
    json_error('Sharing is turned off in settings.', 403);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$sessionId = sanitize_id($body['session_id'] ?? '');
if ($sessionId === '' || !is_dir(SessionManager::dir($sessionId))) {
    json_error('Unknown session', 404);
}
$meta = SessionManager::getMetadata($sessionId);
if (empty($meta['strip'])) {
    json_error('Strip has not been generated for this session yet', 400);
}

if (empty($meta['share_url'])) {
    try {
        $meta['share_url'] = ImageHost::upload(SessionManager::abs($meta['strip']));
    } catch (Throwable $e) {
        json_error($e->getMessage(), 502);
    }
    SessionManager::saveMetadata($sessionId, $meta);
}

json_response(['success' => true, 'url' => $meta['share_url']]);
