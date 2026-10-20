<?php
/**
 * creator/api/preview_generate.php
 * POST { template_id, output: {width,height}, photos: [...] }
 * → { success: true, preview_url }
 *
 * Called by the editor's "Generate Preview" button.  Composites the
 * four sample images into the current (possibly unsaved) slot layout
 * and returns the URL of the resulting preview.png.
 */
require_once __DIR__ . '/bootstrap.php';
admin_require_api();

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$templateId = sanitize_id($body['template_id'] ?? '');
$output     = $body['output'] ?? [];
$photos     = $body['photos'] ?? [];

if ($templateId === '') json_error('template_id is required.', 400);

$dir = TEMPLATES_PATH . '/' . $templateId;
if (!is_dir($dir) || !is_file($dir . '/frame.png')) {
    json_error('Template folder or frame image not found.', 404);
}

$config = [
    'frame'   => 'frame.png',
    'output'  => $output,
    'photos'  => $photos,
];

ThumbnailGenerator::generate($dir, $config, true);

json_response([
    'success'     => true,
    'preview_url' => 'templates/' . $templateId . '/preview.png?t=' . time(),
]);
