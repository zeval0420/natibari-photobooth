<?php
/**
 * includes/Settings.php
 * ------------------------------------------------------------------
 * Loads / saves the application's configuration. Settings are persisted
 * as plain JSON on disk (config/settings.json) — no database. Defaults
 * live in config/settings.default.json so settings.json can be deleted
 * at any time to "factory reset" the booth.
 * ------------------------------------------------------------------
 */
class Settings
{
    /** @var array<string,mixed> */
    private static array $cache = [];
    private static bool $loaded = false;

    /** Load settings (defaults merged with any saved overrides). */
    public static function all(): array
    {
        if (self::$loaded) {
            return self::$cache;
        }
        $defaults = read_json_file(SETTINGS_DEFAULT_FILE, []);
        $saved = read_json_file(SETTINGS_FILE, []);
        self::$cache = array_merge_recursive_distinct($defaults, $saved);
        self::$loaded = true;
        return self::$cache;
    }

    /** Dot-notation getter, e.g. Settings::get('printing.copies', 1) */
    public static function get(string $dottedKey, $default = null)
    {
        $data = self::all();
        foreach (explode('.', $dottedKey) as $part) {
            if (!is_array($data) || !array_key_exists($part, $data)) {
                return $default;
            }
            $data = $data[$part];
        }
        return $data;
    }

    /** Replace the whole settings tree (used by the admin Settings page) and persist it. */
    public static function saveAll(array $newSettings): bool
    {
        $defaults = read_json_file(SETTINGS_DEFAULT_FILE, []);
        $merged = array_merge_recursive_distinct($defaults, $newSettings);
        $ok = write_json_file(SETTINGS_FILE, $merged);
        if ($ok) {
            self::$cache = $merged;
            self::$loaded = true;
        }
        return $ok;
    }

    /** Set a single dotted key and persist immediately. */
    public static function set(string $dottedKey, $value): bool
    {
        $all = self::all();
        $parts = explode('.', $dottedKey);
        $ref = &$all;
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $ref[$part] = $value;
            } else {
                if (!isset($ref[$part]) || !is_array($ref[$part])) {
                    $ref[$part] = [];
                }
                $ref = &$ref[$part];
            }
        }
        return self::saveAll($all);
    }
}
