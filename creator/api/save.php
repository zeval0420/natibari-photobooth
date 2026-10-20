<?php
/**
 * creator/api/save.php
 * POST {
 *   template_id,
 *   name, description, author,
 *   output: { width, height },
 *   photos: [ { x, y, width, height, rotation }, ... ]
 * }
 * → { success: true, template_id, thumbnail_url, preview_url }
 *
 * This is the final step of the editor workflow.  It:
 *  1. Validates the incoming slot data
 *  2. Writes config.json
 *  3. Calls ThumbnailGenerator to regenerate thumbnail.png + preview.png
 */
require_once __DIR__ . '/bootstrap.php';
admin_require_api();

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$templateId  = sanitize_id($body['template_id'] ?? '');
$name        = trim($body['name'] ?? '');
$description = trim($body['description'] ?? '');
$author      = trim($body['author'] ?? '');
$output      = $body['output'] ?? [];
$photos      = $body['photos'] ?? [];

if ($templateId === '')        json_error('template_id is required.', 400);
if ($name === '')              json_error('Template name is required.', 400);
if (empty($output['width']) || empty($output['height'])) json_error('Output dimensions are required.', 400);
if (count($photos) < 1)       json_error('At least one photo slot is required.', 400);

$dir = TEMPLATES_PATH . '/' . $templateId;
if (!is_dir($dir)) {
    json_error("Template folder '$templateId' does not exist. Upload the frame first.", 404);
}

// Validate + normalise each slot.
$cleanPhotos = [];
foreach ($photos as $i => $slot) {
    foreach (['x','y','width','height'] as $field) {
        if (!isset($slot[$field])) json_error("Photo slot $i is missing '$field'.", 400);
    }
    $cleanPhotos[] = [
        'x'        => (int)round((float)$slot['x']),
        'y'        => (int)round((float)$slot['y']),
        'width'    => max(1, (int)round((float)$slot['width'])),
        'height'   => max(1, (int)round((float)$slot['height'])),
        'rotation' => round((float)($slot['rotation'] ?? 0), 2),
    ];
}

$config = [
    'name'        => $name,
    'description' => $description,
    'author'      => $author,
    'thumbnail'   => 'thumbnail.png',
    'frame'       => 'frame.png',
    'background'  => $body['background'] ?? '#FFFFFF',
    'output'      => [
        'width'  => (int)$output['width'],
        'height' => (int)$output['height'],
    ],
    'photos'      => $cleanPhotos,
    'created_at'  => date('c'),
];

if (!write_json_file($dir . '/config.json', $config)) {
    json_error('Could not write config.json — check folder permissions.', 500);
}

// Regenerate thumbnail.png and preview.png.
ThumbnailGenerator::generate($dir, $config, true);

json_response([
    'success'       => true,
    'template_id'   => $templateId,
    'thumbnail_url' => 'templates/' . $templateId . '/thumbnail.png?t=' . time(),
    'preview_url'   => 'templates/' . $templateId . '/preview.png?t='   . time(),
]);
