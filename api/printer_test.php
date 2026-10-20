<?php
/**
 * api/printer_test.php
 * POST { printer?: "name" }  (defaults to the saved printer; the form's unsaved choice is used if given)
 * Admin-only. Prints a test page so the operator can check the printer,
 * paper size and margins before an event.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
admin_require_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST required', 405);
}
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$printer = trim((string)($body['printer'] ?? ''));
if ($printer !== '' && !in_array($printer, PrintManager::printerNames(), true)) {
    json_error('That printer is not installed on this computer.', 400);
}

// Strip-proportioned page with a border and diagonals so cropping/margins are obvious.
$w = 600;
$h = 1800;
$img = imagecreatetruecolor($w, $h);
$white = imagecolorallocate($img, 255, 255, 255);
$blue = imagecolorallocate($img, 37, 99, 235);
$grey = imagecolorallocate($img, 90, 100, 114);
imagefill($img, 0, 0, $white);
imagesetthickness($img, 8);
imagerectangle($img, 4, 4, $w - 5, $h - 5, $blue);
imageline($img, 0, 0, $w, $h, $blue);
imageline($img, $w, 0, 0, $h, $blue);
imagestring($img, 5, 40, 40, 'PHOTOBOOTH TEST PRINT', $grey);
imagestring($img, 4, 40, 70, date('Y-m-d H:i:s'), $grey);
$tmp = sys_get_temp_dir() . '/photobooth_test_' . bin2hex(random_bytes(3)) . '.png';
imagepng($img, $tmp);
imagedestroy($img);

$override = ['copies' => 1];
if ($printer !== '') {
    $override['printer'] = $printer;
}
$result = PrintManager::printFile($tmp, null, $override);
@unlink($tmp);

json_response(['success' => true, 'print_result' => $result]);
