<?php
/**
 * api/strip_share.php
 * POST { session_id, kind?: "strip" (default) | "raw" }
 * -> { success: true, url }
 *
 * Uploads the session's final strip (or, for kind=raw, a ZIP of the raw photos)
 * to the free image host (once — the URL is cached in metadata.json) so the
 * kiosk can show download QR codes.
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

$kind = ($body['kind'] ?? 'strip') === 'raw' ? 'raw' : 'strip';
$key = $kind === 'raw' ? 'share_raw_url' : 'share_url';

if (empty($meta[$key])) {
    $tmpZip = null;
    try {
        if ($kind === 'raw') {
            $photos = glob(SessionManager::rawDir($sessionId) . '/photo*.jpg') ?: [];
            if (!$photos) {
                json_error('No raw photos available for this session', 404);
            }
            sort($photos, SORT_NATURAL);
            $tmpZip = tempnam(sys_get_temp_dir(), 'pbraw') . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not create the photos ZIP');
            }
            foreach ($photos as $p) {
                $zip->addFile($p, basename($p));
            }
            $zip->close();
            $meta[$key] = ImageHost::upload($tmpZip, 'application/zip');
        } else {
            $meta[$key] = ImageHost::upload(SessionManager::abs($meta['strip']));
        }
    } catch (Throwable $e) {
        json_error($e->getMessage(), 502);
    } finally {
        if ($tmpZip) {
            @unlink($tmpZip);
        }
    }
    SessionManager::saveMetadata($sessionId, $meta);
}

json_response(['success' => true, 'url' => $meta[$key]]);
