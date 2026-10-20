<?php
/**
 * creator/api/bootstrap.php
 * ------------------------------------------------------------------
 * Bootstraps the creator module. Simply requires the main app's
 * bootstrap (which sets up paths, autoloading, helpers, Settings etc.)
 * and then registers the creator-specific classes.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

// Autoload the three creator-specific classes from this same directory.
foreach (['CreatorTemplateManager', 'ThumbnailGenerator', 'ImportExportManager'] as $class) {
    require_once __DIR__ . '/' . $class . '.php';
}
