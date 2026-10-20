<?php
/**
 * creator/api/frame_upload.php
 * POST (multipart) frame=<file>, template_id=<optional existing id>
 * → { success: true, template_id, frame_url, width, height }
 *
 * Creates a temporary working folder for the template under a uuid-like
 * id when template_id is not supplied (i.e. brand-new template).  The
 * folder becomes permanent once save.php is called.
 */
require_once __DIR__ . '/bootstrap.php';
admin_require_api();

if (empty($_FILES['frame'])) {
    json_error('No frame file uploaded.', 400);
}

$file = $_FILES['frame'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    json_error('Upload error code: ' . $file['error'], 400);
}

// Detect image type and allow PNG / JPG / WEBP.
$info = @getimagesize($file['tmp_name']);
if (!$info) json_error('Uploaded file is not a valid image.', 400);

$allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
if (!in_array($info[2], $allowedTypes, true)) {
    json_error('Only PNG, JPG and WEBP files are accepted.', 415);
}

$imgWidth  = $info[0];
$imgHeight = $info[1];
$ext       = $info[2] === IMAGETYPE_PNG ? 'png' : ($info[2] === IMAGETYPE_WEBP ? 'webp' : 'jpg');

// Determine or create the template working folder.
$templateId = sanitize_id($_POST['template_id'] ?? '');
if ($templateId === '') {
    $templateId = 'tpl-' . bin2hex(random_bytes(5));
}

$dir = TEMPLATES_PATH . '/' . $templateId;
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

// Always normalise the saved frame to PNG so the compositor and the
// browser <img> both handle it the same way regardless of upload format.
$destPath = $dir . '/frame.png';

switch ($info[2]) {
    case IMAGETYPE_PNG:  $src = imagecreatefrompng($file['tmp_name']); break;
    case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($file['tmp_name']); break;
    case IMAGETYPE_WEBP: $src = imagecreatefromwebp($file['tmp_name']); break;
    default: json_error('Unsupported image type.', 415);
}

imagesavealpha($src, true);
imagepng($src, $destPath, 6);
imagedestroy($src);

json_response([
    'success'     => true,
    'template_id' => $templateId,
    'frame_url'   => 'templates/' . $templateId . '/frame.png?t=' . time(),
    'width'       => $imgWidth,
    'height'      => $imgHeight,
]);
