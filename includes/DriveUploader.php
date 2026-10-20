<?php
/**
 * includes/DriveUploader.php
 * ------------------------------------------------------------------
 * Uploads a finished strip to a Google Drive folder using a service
 * account (no Composer — just curl + openssl). Configure in
 * config/settings.json:
 *
 *   "storage": { "drive": {
 *       "enabled": true,
 *       "folder_id": "<id from the folder's URL>",
 *       "credentials_file": "drive-service-account.json"   // inside config/
 *   } }
 *
 * The folder must be shared with the service account's client_email
 * (Editor). config/ is denied by .htaccess, so the key is not web-reachable.
 * ------------------------------------------------------------------
 */
class DriveUploader
{
    private const SCOPE = 'https://www.googleapis.com/auth/drive';

    public static function enabled(): bool
    {
        return (bool) Settings::get('storage.drive.enabled', false)
            && (string) Settings::get('storage.drive.folder_id', '') !== '';
    }

    /** Upload one PNG; returns the Drive file id. Throws on failure. */
    public static function upload(string $absPath, string $name): string
    {
        if (!is_file($absPath)) {
            throw new RuntimeException('File to upload not found');
        }
        $token = self::accessToken();
        $meta = ['name' => $name, 'parents' => [Settings::get('storage.drive.folder_id')]];

        $boundary = 'pb' . bin2hex(random_bytes(8));
        $body = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n"
            . json_encode($meta) . "\r\n"
            . "--$boundary\r\nContent-Type: image/png\r\n\r\n"
            . file_get_contents($absPath) . "\r\n--$boundary--";

        $res = self::request(
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id',
            $body,
            ['Authorization: Bearer ' . $token, "Content-Type: multipart/related; boundary=$boundary"]
        );
        if (empty($res['id'])) {
            throw new RuntimeException('Drive upload failed: ' . json_encode($res));
        }
        return $res['id'];
    }

    private static function accessToken(): string
    {
        $file = CONFIG_PATH . '/' . basename((string) Settings::get('storage.drive.credentials_file', 'drive-service-account.json'));
        $creds = is_file($file) ? json_decode(file_get_contents($file), true) : null;
        if (!is_array($creds) || empty($creds['private_key']) || empty($creds['client_email'])) {
            throw new RuntimeException('Drive credentials missing or invalid: ' . basename($file));
        }

        $b64 = fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();
        $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode([
            'iss' => $creds['client_email'],
            'scope' => self::SCOPE,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        if (!openssl_sign($unsigned, $sig, $creds['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign Drive JWT');
        }

        $res = self::request(
            'https://oauth2.googleapis.com/token',
            http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned . '.' . $b64($sig)]),
            ['Content-Type: application/x-www-form-urlencoded']
        );
        if (empty($res['access_token'])) {
            throw new RuntimeException('Drive auth failed: ' . json_encode($res));
        }
        return $res['access_token'];
    }

    private static function request(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
        ]);
        $out = curl_exec($ch);
        if ($out === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Drive request failed: ' . $err);
        }
        curl_close($ch);
        return json_decode($out, true) ?? [];
    }
}
