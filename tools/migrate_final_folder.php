<?php
/**
 * One-off: move each old sessions/<id>/final/strip*.png into the shared
 * final/ folder as final/<id>.png and final/<id>_print.png, and rewrite
 * metadata.json. Safe to re-run. Usage: php tools/migrate_final_folder.php
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$moved = 0;
foreach (scandir(SESSIONS_PATH) as $id) {
    $dir = SESSIONS_PATH . '/' . $id;
    if ($id[0] === '.' || !is_file($dir . '/metadata.json')) {
        continue;
    }
    $meta = read_json_file($dir . '/metadata.json', []);
    $safe = sanitize_id($id);
    foreach (['strip.png' => '', 'strip_a5.png' => 'print'] as $old => $kind) {
        if (is_file("$dir/final/$old")) {
            rename("$dir/final/$old", FINAL_PATH . '/' . $safe . ($kind ? "_$kind" : '') . '.png');
            $moved++;
        }
    }
    if (is_dir("$dir/final")) {
        @rmdir("$dir/final");
    }
    if (!empty($meta['strip']) && is_file(SessionManager::finalFile($id))) {
        $meta['strip'] = SessionManager::finalRel($id);
    }
    unset($meta['strip_a5']);
    if (is_file(SessionManager::finalFile($id, 'print'))) {
        $meta['strip_print'] = SessionManager::finalRel($id, 'print');
    }
    write_json_file($dir . '/metadata.json', $meta);
}
echo "Moved $moved file(s) into " . FINAL_PATH . "\n";
