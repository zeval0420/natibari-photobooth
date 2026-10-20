<?php
/**
 * creator/api/CreatorTemplateManager.php
 * ------------------------------------------------------------------
 * Manages the gallery listing, duplication, renaming and deletion of
 * templates as seen by the Template Creator.  Shares the filesystem
 * convention (templates/<id>/config.json etc.) with the main app's
 * TemplateManager so the two always stay in sync — no separate
 * registration step is ever needed.
 * ------------------------------------------------------------------
 */
class CreatorTemplateManager
{
    /**
     * Return every template folder as a rich descriptor array.
     * @return array<int, array<string, mixed>>
     */
    public static function listAll(): array
    {
        $results = [];
        if (!is_dir(TEMPLATES_PATH)) return $results;

        foreach (scandir(TEMPLATES_PATH) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $dir  = TEMPLATES_PATH . '/' . $entry;
            if (!is_dir($dir)) continue;
            $cfg  = $dir . '/config.json';
            if (!is_file($cfg)) continue;

            $config = read_json_file($cfg, null);
            if (!is_array($config)) continue;

            $results[] = self::descriptor($entry, $dir, $config);
        }

        // Sort by last-modified (newest first)
        usort($results, fn($a, $b) => $b['modified_ts'] - $a['modified_ts']);
        return $results;
    }

    /** Build a public-safe descriptor for one template folder. */
    public static function descriptor(string $id, string $dir, array $config): array
    {
        $frameFile = $config['frame'] ?? 'frame.png';
        $thumbFile = $config['thumbnail'] ?? 'thumbnail.png';
        $prevFile  = 'preview.png';

        $modified = filemtime($dir . '/config.json') ?: 0;

        return [
            'id'            => $id,
            'name'          => $config['name'] ?? $id,
            'description'   => $config['description'] ?? '',
            'author'        => $config['author'] ?? '',
            'output'        => $config['output'] ?? ($config['width'] ? ['width'=>$config['width'],'height'=>$config['height']] : ['width'=>0,'height'=>0]),
            'photos_count'  => count($config['photos'] ?? []),
            'thumbnail_url' => is_file($dir.'/'.$thumbFile) ? "templates/{$id}/{$thumbFile}" : null,
            'preview_url'   => is_file($dir.'/'.$prevFile)  ? "templates/{$id}/{$prevFile}"  : null,
            'frame_url'     => is_file($dir.'/'.$frameFile) ? "templates/{$id}/{$frameFile}" : null,
            'modified_ts'   => $modified,
            'modified'      => date('Y-m-d H:i', $modified),
            'config'        => $config,
        ];
    }

    /**
     * Duplicate an existing template to a new id/name.
     * Returns the new id on success, throws RuntimeException on failure.
     */
    public static function duplicate(string $sourceId, string $newName): string
    {
        $srcDir = TEMPLATES_PATH . '/' . sanitize_id($sourceId);
        if (!is_dir($srcDir)) throw new RuntimeException("Source template not found: $sourceId");

        $newId  = self::nameToId($newName);
        $dstDir = TEMPLATES_PATH . '/' . $newId;
        if (is_dir($dstDir)) throw new RuntimeException("A template named '$newName' already exists.");

        self::copyDir($srcDir, $dstDir);

        // Update the name field in the copied config.json
        $cfgPath = $dstDir . '/config.json';
        $cfg = read_json_file($cfgPath, []);
        $cfg['name'] = $newName;
        write_json_file($cfgPath, $cfg);

        return $newId;
    }

    /** Rename a template folder and its config.name. */
    public static function rename(string $id, string $newName): string
    {
        $oldDir = TEMPLATES_PATH . '/' . sanitize_id($id);
        if (!is_dir($oldDir)) throw new RuntimeException("Template not found: $id");

        $newId  = self::nameToId($newName);
        $newDir = TEMPLATES_PATH . '/' . $newId;

        if ($newId !== sanitize_id($id)) {
            if (is_dir($newDir)) throw new RuntimeException("A template named '$newName' already exists.");
            rename($oldDir, $newDir);
        }

        $cfgPath = $newDir . '/config.json';
        $cfg = read_json_file($cfgPath, []);
        $cfg['name'] = $newName;
        write_json_file($cfgPath, $cfg);

        return $newId;
    }

    /** Permanently delete a template folder and all its files. */
    public static function delete(string $id): void
    {
        $dir = TEMPLATES_PATH . '/' . sanitize_id($id);
        if (!is_dir($dir)) throw new RuntimeException("Template not found: $id");
        self::removeDir($dir);
    }

    /** Create a slug-style folder name from a human display name. */
    public static function nameToId(string $name): string
    {
        $id = strtolower(trim($name));
        $id = preg_replace('/[^a-z0-9]+/', '-', $id);
        $id = trim($id, '-');
        return $id === '' ? 'template-' . time() : $id;
    }

    // ---- Filesystem helpers ------------------------------------------------

    private static function copyDir(string $src, string $dst): void
    {
        mkdir($dst, 0775, true);
        foreach (new DirectoryIterator($src) as $item) {
            if ($item->isDot()) continue;
            $s = $src . '/' . $item->getFilename();
            $d = $dst . '/' . $item->getFilename();
            if ($item->isDir()) self::copyDir($s, $d);
            else copy($s, $d);
        }
    }

    private static function removeDir(string $path): void
    {
        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot()) continue;
            $p = $path . '/' . $item->getFilename();
            if ($item->isDir()) self::removeDir($p);
            else unlink($p);
        }
        rmdir($path);
    }
}
