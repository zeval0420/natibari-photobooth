<?php
/**
 * api/strip_accept.php
 * POST { session_id, printer?, paper_size? }
 * -> { success: true, print_result: {...} }
 *
 * Called when the guest taps "Accept". Honors two settings:
 *   printing.auto_print   — send the strip to the printer now (else status "skipped")
 *   storage.save_mode     — "final_only" deletes the raw photos once the strip is safe
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
if (empty($meta['strip'])) {
    json_error('Strip has not been generated for this session yet', 400);
}

$meta['accepted_at'] = date('c');

// The kiosk's Printer & paper panel may pick a different printer / paper than
// the saved defaults; accept only values that are actually installed / known.
$override = ['fit' => true];
$wantPrinter = trim((string)($body['printer'] ?? ''));
if ($wantPrinter !== '' && in_array($wantPrinter, PrintManager::printerNames(), true)) {
    $override['printer'] = $wantPrinter;
}
$wantPaper = (string)($body['paper_size'] ?? '');
if (in_array($wantPaper, PrintManager::PAPER_SIZES, true)) {
    $override['paper_size'] = $wantPaper;
}

$printResult = ['status' => 'skipped', 'reason' => 'Automatic printing is off.'];
if (Settings::get('printing.auto_print', false)) {
    try {
        $meta['strip_print'] = Compositor::printableFor($sessionId, $meta);
        $printResult = PrintManager::printFile(SessionManager::abs($meta['strip_print']), null, $override);
    } catch (Throwable $e) {
        $printResult = ['status' => 'failed', 'reason' => $e->getMessage()];
    }
}

// Back the strip up to Google Drive if configured. Never blocks the guest on failure.
if (DriveUploader::enabled()) {
    try {
        $meta['drive_file_id'] = DriveUploader::upload(SessionManager::abs($meta['strip']), basename($meta['strip']));
    } catch (Throwable $e) {
        $meta['drive_error'] = $e->getMessage();
    }
}
SessionManager::saveMetadata($sessionId, $meta);
SessionManager::recordPrintStatus($sessionId, $printResult['status'], $printResult);

// Strip is saved and printed (or deliberately not) — drop raw photos if configured.
if (SessionManager::saveMode() === 'final_only') {
    SessionManager::purgeRaw($sessionId);
}

json_response(['success' => true, 'print_result' => $printResult]);
