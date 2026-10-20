<?php
/**
 * creator/api/ImportExportManager.php
 * ------------------------------------------------------------------
 * Handles ZIP-based template portability:
 *
 *   Export  → pack  templates/<id>/ into a ZIP stream sent to the browser
 *   Import  → unpack an uploaded ZIP into templates/, validate it, then
 *              regenerate thumbnail/preview via ThumbnailGenerator
 *
 * Requires the php-zip extension (ZipArchive).
 * ------------------------------------------------------------------
 */
class ImportExportManager
{
    /**
     * Stream a template's folder as a ZIP download.
     * Sends HTTP headers and exits; must be called before any output.
     */
    public static function exportToZip(string $templateId): void
    {
        $id  = sanitize_id($templateId);
        $dir = TEMPLATES_PATH . '/' . $id;
        if (!is_dir($dir)) {
            json_error("Template '$id' not found.", 404);
        }

        $tmpZip = sys_get_temp_dir() . '/photobooth_tpl_' . $id . '_' . time() . '.zip';
        $zip    = new ZipArchive();
        if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            json_error('Could not create ZIP archive.', 500);
        }

        // Walk the template folder and add every file under <id>/<filename>
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = $id . '/' . ltrim(str_replace($dir, '', $file->getPathname()), '/\\');
            $zip->addFile($file->getPathname(), $relative);
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $id . '.zip"');
        header('Content-Length: ' . filesize($tmpZip));
        header('Cache-Control: no-cache');
        readfile($tmpZip);
        unlink($tmpZip);
        exit;
    }

    /**
     * Unpack a posted ZIP file into the templates/ directory.
     * Returns an array with the new template id and name on success.
     * Throws RuntimeException with a human-readable message on any failure.
     *
     * @param  array $uploadedFile  One element from $_FILES
     * @return array{ id: string, name: string }
     */
    public static function importFromZip(array $uploadedFile): array
    {
        if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
            throw new RuntimeException('No file was uploaded.');
        }
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload error code: ' . $uploadedFile['error']);
        }

        $zip = new ZipArchive();
        if ($zip->open($uploadedFile['tmp_name']) !== true) {
            throw new RuntimeException('Could not open the uploaded ZIP file.');
        }

        // Security: only allow safe filenames — block path traversal.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, '..') !== false || preg_match('/[\\\\<>|:*?"]/', $name)) {
                $zip->close();
                throw new RuntimeException("ZIP contains unsafe path: $name");
            }
        }

        // The ZIP should contain exactly one top-level folder.
        // Detect the folder prefix from the first entry.
        $first    = $zip->getNameIndex(0);
        $parts    = explode('/', trim($first, '/'));
        $topDir   = $parts[0];
        $targetId = sanitize_id($topDir);

        if ($targetId === '') throw new RuntimeException('Could not determine template id from ZIP structure.');

        // Validate that config.json exists inside.
        $cfgEntry = $topDir . '/config.json';
        $cfgRaw   = $zip->getFromName($cfgEntry);
        if ($cfgRaw === false) {
            // Fallback: try without subdir prefix (flat ZIP)
            $cfgRaw = $zip->getFromName('config.json');
            if ($cfgRaw === false) {
                $zip->close();
                throw new RuntimeException('The ZIP does not contain a config.json file.');
            }
            $topDir = ''; // flat ZIP
        }

        $config = json_decode($cfgRaw, true);
        if (!is_array($config) || empty($config['name'])) {
            $zip->close();
            throw new RuntimeException('config.json is invalid or missing the "name" field.');
        }

        // Ensure we don't clobber an existing template with the same id.
        $destDir = TEMPLATES_PATH . '/' . $targetId;
        if (is_dir($destDir)) {
            // Suffix with a timestamp to avoid collision.
            $targetId = $targetId . '-' . date('His');
            $destDir  = TEMPLATES_PATH . '/' . $targetId;
        }
        mkdir($destDir, 0775, true);

        // Extract files into $destDir.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // Strip the top-level folder from the path.
            if ($topDir !== '') {
                if (strpos($name, $topDir . '/') !== 0) continue;
                $relative = substr($name, strlen($topDir) + 1);
            } else {
                $relative = $name;
            }
            if ($relative === '' || substr($relative, -1) === '/') continue; // skip dirs

            $destFile = $destDir . '/' . $relative;
            $destSubDir = dirname($destFile);
            if (!is_dir($destSubDir)) mkdir($destSubDir, 0775, true);
            file_put_contents($destFile, $zip->getFromIndex($i));
        }
        $zip->close();

        // Re-generate thumbnails with the installed sample photos.
        ThumbnailGenerator::generate($destDir, $config, true);

        return ['id' => $targetId, 'name' => $config['name']];
    }
}
