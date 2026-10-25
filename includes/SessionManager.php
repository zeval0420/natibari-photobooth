<?php
/**
 * includes/SessionManager.php
 * ------------------------------------------------------------------
 * Every photobooth run ("session") gets its own timestamped folder:
 *
 *   sessions/
 *     2026-06-28_14-05-02/
 *       raw/photo1.jpg .. photo4.jpg
 *       metadata.json
 *
 * Finished files are NOT inside the session folder: they all live together
 * in one flat folder, final/<session_id>.png (+ final/<session_id>_print.png).
 *
 * No database — metadata.json IS the record. SessionManager only
 * touches the filesystem; it knows nothing about cameras, compositing
 * or printing (those are separate classes), keeping responsibilities
 * cleanly separated as required by the architecture brief.
 * ------------------------------------------------------------------
 */
class SessionManager
{
    /** Create a brand-new session folder and an initial metadata.json. */
    public static function create(string $templateId): array
    {
        $id = make_session_timestamp();
        // Guard against a same-second collision (rapid testing, fast taps).
        while (is_dir(SESSIONS_PATH . '/' . $id)) {
            usleep(250000);
            $id = make_session_timestamp();
        }

        $dir = SESSIONS_PATH . '/' . $id;
        mkdir($dir . '/raw', 0775, true);

        $metadata = [
            'session_id' => $id,
            'created_at' => date('c'),
            'template' => $templateId,
            'photos' => [],
            'strip' => null,
            'print_status' => 'not_printed',
            'save_mode' => self::saveMode(),
        ];
        write_json_file($dir . '/metadata.json', $metadata);

        return ['session_id' => $id, 'dir' => $dir];
    }

    /** 'both' (keep raw photos + strip) or 'final_only' (strip only). */
    public static function saveMode(): string
    {
        return Settings::get('storage.save_mode', 'both') === 'final_only' ? 'final_only' : 'both';
    }

    /** Path helpers ----------------------------------------------------- */
    public static function dir(string $sessionId): string
    {
        return SESSIONS_PATH . '/' . sanitize_id($sessionId);
    }

    public static function rawDir(string $sessionId): string
    {
        return self::dir($sessionId) . '/raw';
    }

    /** Absolute path of a session's file in the shared final/ folder. $kind: '' (strip) or 'print'. */
    public static function finalFile(string $sessionId, string $kind = ''): string
    {
        return FINAL_PATH . '/' . sanitize_id($sessionId) . ($kind !== '' ? '_' . $kind : '') . '.png';
    }

    /** Same file, as the app-root-relative path stored in metadata.json / used in URLs. */
    public static function finalRel(string $sessionId, string $kind = ''): string
    {
        return 'final/' . basename(self::finalFile($sessionId, $kind));
    }

    /** Absolute path for an app-root-relative path from metadata.json. */
    public static function abs(string $rel): string
    {
        return ROOT_PATH . '/' . ltrim($rel, '/');
    }

    public static function metadataPath(string $sessionId): string
    {
        return self::dir($sessionId) . '/metadata.json';
    }

    /** Save one captured raw photo (binary JPEG bytes) as photo<N>.jpg. */
    public static function savePhoto(string $sessionId, int $index, string $binaryJpeg): string
    {
        $path = self::rawDir($sessionId) . "/photo{$index}.jpg";
        file_put_contents($path, $binaryJpeg);

        $meta = self::getMetadata($sessionId);
        $meta['photos'][$index] = "raw/photo{$index}.jpg";
        ksort($meta['photos']);
        self::saveMetadata($sessionId, $meta);

        return $path;
    }

    /** Read metadata.json for a session. */
    public static function getMetadata(string $sessionId): array
    {
        return read_json_file(self::metadataPath($sessionId), []);
    }

    /** Persist metadata.json for a session (merging onto the existing record). */
    public static function saveMetadata(string $sessionId, array $metadata): bool
    {
        return write_json_file(self::metadataPath($sessionId), $metadata);
    }

    /** Mark the final strip path + template used once compositing succeeds. */
    public static function recordStrip(string $sessionId, string $relativeStripPath): void
    {
        $meta = self::getMetadata($sessionId);
        $meta['strip'] = $relativeStripPath;
        $meta['composited_at'] = date('c');
        unset($meta['share_url'], $meta['share_raw_url']); // a regenerated strip invalidates old uploads
        self::saveMetadata($sessionId, $meta);
    }

    /** Record print outcome (success/failure/skipped) onto metadata.json. */
    public static function recordPrintStatus(string $sessionId, string $status, array $extra = []): void
    {
        $meta = self::getMetadata($sessionId);
        $meta['print_status'] = $status;
        $meta['print_info'] = array_merge($extra, ['attempted_at' => date('c')]);
        self::saveMetadata($sessionId, $meta);
    }

    /**
     * List every session, newest first — used by the admin Gallery.
     * @return array<int, array<string,mixed>>
     */
    public static function listAll(): array
    {
        if (!is_dir(SESSIONS_PATH)) {
            return [];
        }
        $entries = scandir(SESSIONS_PATH);
        $sessions = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $dir = SESSIONS_PATH . '/' . $entry;
            if (!is_dir($dir) || !is_file($dir . '/metadata.json')) {
                continue;
            }
            $meta = read_json_file($dir . '/metadata.json', []);
            $meta['session_id'] = $meta['session_id'] ?? $entry;
            unset($meta['settings_snapshot']); // legacy sessions stored the full settings (incl. passcode hash)
            $meta['strip_url'] = !empty($meta['strip']) ? $meta['strip'] : null;
            $meta['raw_urls'] = [];
            foreach (glob($dir . '/raw/photo*.jpg') ?: [] as $f) {
                $meta['raw_urls'][] = 'sessions/' . $entry . '/raw/' . basename($f);
            }
            $meta['size_bytes'] = self::dirSize($dir) + self::finalSize($entry);
            $sessions[] = $meta;
        }
        // Newest first (folder names are timestamp-sortable strings).
        usort($sessions, fn($a, $b) => strcmp($b['session_id'], $a['session_id']));
        return $sessions;
    }

    /** Delete the raw photos of one session (keeps the final strip). */
    public static function purgeRaw(string $sessionId): void
    {
        $raw = self::rawDir($sessionId);
        if (is_dir($raw)) {
            self::rrmdir($raw);
        }
        if (is_file(self::metadataPath($sessionId))) {
            $meta = self::getMetadata($sessionId);
            $meta['photos'] = [];
            $meta['raw_deleted_at'] = date('c');
            self::saveMetadata($sessionId, $meta);
        }
    }

    /** Delete a whole session folder. */
    public static function delete(string $sessionId): bool
    {
        $dir = self::dir($sessionId);
        if (sanitize_id($sessionId) === '' || !is_dir($dir)) {
            return false;
        }
        foreach (['', 'print'] as $kind) {
            @unlink(self::finalFile($sessionId, $kind));
        }
        self::rrmdir($dir);
        return !is_dir($dir);
    }

    /**
     * In final_only mode, raw photos should never outlive a session. Called
     * on each new session: sessions older than 2h that were abandoned (no
     * strip) are removed; any that still hold raw photos have them purged.
     */
    public static function cleanupStale(): void
    {
        if (self::saveMode() !== 'final_only' || !is_dir(SESSIONS_PATH)) {
            return;
        }
        foreach (scandir(SESSIONS_PATH) as $entry) {
            $dir = SESSIONS_PATH . '/' . $entry;
            if ($entry[0] === '.' || !is_dir($dir) || !is_file($dir . '/metadata.json')) {
                continue;
            }
            if (time() - filemtime($dir . '/metadata.json') < 7200) {
                continue;
            }
            $meta = read_json_file($dir . '/metadata.json', []);
            if (($meta['save_mode'] ?? 'both') !== 'final_only') {
                continue; // only touch sessions that were created under "final only"
            }
            if (empty($meta['strip'])) {
                self::delete($entry);
            } elseif (is_dir($dir . '/raw')) {
                self::purgeRaw($entry);
            }
        }
    }

    /** Totals for the Settings storage panel and the Gallery header. */
    public static function stats(): array
    {
        $sessions = $withRaw = 0;
        $rawBytes = $totalBytes = 0;
        if (is_dir(SESSIONS_PATH)) {
            foreach (scandir(SESSIONS_PATH) as $entry) {
                $dir = SESSIONS_PATH . '/' . $entry;
                if ($entry[0] === '.' || !is_dir($dir)) {
                    continue;
                }
                $sessions++;
                $totalBytes += self::dirSize($dir);
                if (is_dir($dir . '/raw')) {
                    $size = self::dirSize($dir . '/raw');
                    if ($size > 0) {
                        $withRaw++;
                        $rawBytes += $size;
                    }
                }
            }
        }
        return ['sessions' => $sessions, 'sessions_with_raw' => $withRaw, 'raw_bytes' => $rawBytes, 'total_bytes' => $totalBytes + self::dirSize(FINAL_PATH)];
    }

    /** Delete raw photos from every session. Returns how many sessions were affected. */
    public static function purgeAllRaw(): int
    {
        $n = 0;
        foreach (self::listAllIds() as $id) {
            if (is_dir(self::rawDir($id))) {
                self::purgeRaw($id);
                $n++;
            }
        }
        return $n;
    }

    private static function listAllIds(): array
    {
        $ids = [];
        if (is_dir(SESSIONS_PATH)) {
            foreach (scandir(SESSIONS_PATH) as $entry) {
                if ($entry[0] !== '.' && is_dir(SESSIONS_PATH . '/' . $entry)) {
                    $ids[] = $entry;
                }
            }
        }
        return $ids;
    }

    private static function finalSize(string $sessionId): int
    {
        $n = 0;
        foreach (['', 'print'] as $kind) {
            $f = self::finalFile($sessionId, $kind);
            $n += is_file($f) ? filesize($f) : 0;
        }
        return $n;
    }

    private static function dirSize(string $dir): int
    {
        $bytes = 0;
        if (!is_dir($dir)) {
            return 0;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $bytes += $f->getSize();
        }
        return $bytes;
    }

    private static function rrmdir(string $dir): void
    {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
