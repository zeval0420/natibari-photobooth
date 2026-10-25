<?php
/**
 * includes/ImageHost.php
 * ------------------------------------------------------------------
 * Uploads a finished strip to a free, key-less image host so guests can
 * scan a QR code and download it. Configure in config/settings.json:
 *
 *   "sharing": {
 *       "enabled": true,
 *       "providers": ["litterbox", "catbox", "uguu"],   // tried in order until one works
 *       "litterbox_expiry": "72h"                       // 1h | 12h | 24h | 72h
 *   }
 *
 *   litterbox  temporary (see litterbox_expiry)
 *   catbox     permanent
 *   uguu       temporary (a few hours) — last-resort fallback
 *
 * Uploaded files are public to anyone who has the link. Needs internet
 * access on the booth PC; when every provider fails the upload throws and
 * the kiosk shows a friendly message (the strip is still saved locally).
 * ------------------------------------------------------------------
 */
class ImageHost
{
    public static function enabled(): bool
    {
        return (bool) Settings::get('sharing.enabled', false);
    }

    /** Upload one file (strip PNG or raw-photos ZIP); returns its public URL. Throws if every provider fails. */
    public static function upload(string $absPath, string $mime = 'image/png'): string
    {
        if (!is_file($absPath)) {
            throw new RuntimeException('File to upload not found');
        }
        $providers = Settings::get('sharing.providers', ['litterbox', 'catbox', 'uguu']);
        $errors = [];
        foreach ((array) $providers as $name) {
            try {
                return self::send((string) $name, $absPath, $mime);
            } catch (Throwable $e) {
                $errors[] = $name . ': ' . $e->getMessage();
                error_log('ImageHost ' . $name . ' failed: ' . $e->getMessage());
            }
        }
        throw new RuntimeException('Could not upload to any image host (' . implode(' | ', $errors) . ')');
    }

    private static function send(string $provider, string $absPath, string $mime): string
    {
        $file = new CURLFile($absPath, $mime, basename($absPath));
        switch ($provider) {
            case 'litterbox':
                $expiry = (string) Settings::get('sharing.litterbox_expiry', '72h');
                if (!in_array($expiry, ['1h', '12h', '24h', '72h'], true)) {
                    $expiry = '72h';
                }
                return self::post('https://litterbox.catbox.moe/resources/internals/api.php',
                    ['reqtype' => 'fileupload', 'time' => $expiry, 'fileToUpload' => $file]);
            case 'catbox':
                return self::post('https://catbox.moe/user/api.php',
                    ['reqtype' => 'fileupload', 'fileToUpload' => $file]);
            case 'uguu':
                return self::post('https://uguu.se/upload?output=text', ['files[]' => $file]);
        }
        throw new RuntimeException('unknown provider');
    }

    private static function post(string $url, array $fields): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'natibari-photobooth',
        ]);
        $out = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($out === false) {
            throw new RuntimeException($err);
        }
        $out = trim($out);
        if ($code !== 200 || !preg_match('#^https://\S+$#', $out)) {
            throw new RuntimeException('rejected: ' . substr($out, 0, 100));
        }
        return $out;
    }
}
