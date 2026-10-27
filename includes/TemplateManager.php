<?php
/**
 * includes/TemplateManager.php
 * ------------------------------------------------------------------
 * Implements the "modular template system" requirement:
 *
 *   templates/
 *     <template-id>/
 *       frame.png        (RGBA overlay, transparent windows = photo slots)
 *       thumbnail.png     (small gallery preview)
 *       config.json       (name, positions, output size, etc.)
 *
 * The directory is rescanned every time getAll() is called — dropping a
 * new folder in templates/ makes it available immediately, with zero
 * code changes. Invalid / incomplete template folders are simply
 * skipped (and logged) rather than crashing the app.
 * ------------------------------------------------------------------
 */
class TemplateManager
{
    /**
     * Scan templates/ and return every valid template definition.
     * @return array<int, array<string,mixed>>
     */
    public static function getAll(): array
    {
        $templates = [];
        if (!is_dir(TEMPLATES_PATH)) {
            return $templates;
        }

        $folders = scandir(TEMPLATES_PATH);
        sort($folders);
        foreach ($folders as $folder) {
            if ($folder === '.' || $folder === '..') {
                continue;
            }
            $dir = TEMPLATES_PATH . '/' . $folder;
            if (!is_dir($dir)) {
                continue;
            }
            $template = self::loadOne($folder);
            if ($template !== null) {
                $templates[] = $template;
            }
        }
        // Templates with a "priority" in config.json come first (ascending);
        // the rest keep their alphabetical folder order.
        usort($templates, function ($a, $b) {
            $pa = $a['priority'] ?? PHP_INT_MAX;
            $pb = $b['priority'] ?? PHP_INT_MAX;
            return $pa <=> $pb ?: strcmp($a['id'], $b['id']);
        });
        return $templates;
    }

    /** Load and validate a single template folder. Returns null if invalid. */
    public static function loadOne(string $id): ?array
    {
        $id = sanitize_id($id);
        $dir = TEMPLATES_PATH . '/' . $id;
        $configFile = $dir . '/config.json';

        if (!is_file($configFile)) {
            return null;
        }
        $config = read_json_file($configFile, null);
        if (!is_array($config)) {
            return null;
        }

        // Required fields per the spec: name, thumbnail, frame, photo positions, output size.
        $required = ['name', 'thumbnail', 'frame', 'output', 'photos'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $config)) {
                error_log("Template '$id' is missing required field '$field' — skipped.");
                return null;
            }
        }
        if (!is_array($config['photos']) || count($config['photos']) < 1 || count($config['photos']) > 8) {
            error_log("Template '$id' must define between 1 and 8 photo positions — skipped.");
            return null;
        }

        $framePath = $dir . '/' . $config['frame'];
        $thumbPath = $dir . '/' . $config['thumbnail'];
        if (!is_file($framePath) || !is_file($thumbPath)) {
            error_log("Template '$id' is missing frame.png or thumbnail.png — skipped.");
            return null;
        }

        return [
            'id' => $id,
            'name' => $config['name'],
            'description' => $config['description'] ?? '',
            'thumbnail_url' => 'templates/' . $id . '/' . $config['thumbnail'],
            'frame_url' => 'templates/' . $id . '/' . $config['frame'],
            'frame_path' => $framePath,
            'output' => $config['output'],
            'photos' => $config['photos'],
            'background' => $config['background'] ?? '#FFFFFF',
            'priority' => isset($config['priority']) ? (int) $config['priority'] : null,
        ];
    }

    /** Convenience: find one template by id, or null. */
    public static function find(string $id): ?array
    {
        return self::loadOne($id);
    }
}
