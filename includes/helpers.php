<?php
/**
 * includes/helpers.php
 * ------------------------------------------------------------------
 * Small, generic helper functions shared across the app. Anything that
 * isn't really "a class" lives here to keep includes/*.php focused on
 * one responsibility each.
 * ------------------------------------------------------------------
 */

/** Send a JSON response and stop execution. Used by every api/*.php file. */
function json_response($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Shorthand for a JSON error response. */
function json_error(string $message, int $statusCode = 400): void
{
    json_response(['success' => false, 'error' => $message], $statusCode);
}

/** Read + decode a JSON file, returning $default if it doesn't exist or is invalid. */
function read_json_file(string $path, $default = [])
{
    if (!is_file($path)) {
        return $default;
    }
    $raw = file_get_contents($path);
    $decoded = json_decode($raw, true);
    return $decoded === null ? $default : $decoded;
}

/** Encode + atomically write a JSON file (write to temp file then rename). */
function write_json_file(string $path, $data): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    $ok = file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if ($ok === false) {
        return false;
    }
    return rename($tmp, $path);
}

/** Recursively merge $override on top of $base (used for settings + defaults). */
function array_merge_recursive_distinct(array $base, array $override): array
{
    foreach ($override as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = array_merge_recursive_distinct($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }
    return $base;
}

/** Build a filesystem-safe timestamped session folder name, e.g. 2026-06-28_14-05-02 */
function make_session_timestamp(): string
{
    return date('Y-m-d_H-i-s');
}

/** Decode a `data:image/...;base64,xxxx` string into raw binary bytes. */
function decode_data_url(string $dataUrl): ?string
{
    if (strpos($dataUrl, 'base64,') === false) {
        return null;
    }
    [, $b64] = explode('base64,', $dataUrl, 2);
    $binary = base64_decode($b64, true);
    return $binary === false ? null : $binary;
}

/** Very small sanitizer for ids coming from the client (template ids, session ids). */
function sanitize_id(string $id): string
{
    return preg_replace('/[^A-Za-z0-9_\-]/', '', $id);
}

/**
 * Hidden-admin gate. Uses PHP's built-in filesystem-backed session
 * (no database) to remember that someone entered the correct passcode.
 * Call admin_is_authenticated() to check, admin_login() to verify a
 * submitted passcode, admin_require() to hard-stop non-admin API calls.
 */
function admin_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function admin_is_authenticated(): bool
{
    admin_start_session();
    return !empty($_SESSION['photobooth_admin']);
}

function admin_login(string $passcode): bool
{
    $hash = Settings::get('admin.passcode_hash', '');
    if ($hash !== '' && hash('sha256', $passcode) === $hash) {
        admin_start_session();
        $_SESSION['photobooth_admin'] = true;
        return true;
    }
    return false;
}

function admin_logout(): void
{
    admin_start_session();
    unset($_SESSION['photobooth_admin']);
}

/** For api/*.php endpoints that must be admin-only. */
function admin_require_api(): void
{
    if (!admin_is_authenticated()) {
        json_error('Admin authentication required.', 401);
    }
}
