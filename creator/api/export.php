<?php
/**
 * creator/api/export.php
 * GET ?id=<template_id>
 * Streams the template folder as a ZIP file download.
 */
require_once __DIR__ . '/bootstrap.php';
admin_require_api();

$id = sanitize_id($_GET['id'] ?? '');
if ($id === '') json_error('id is required.', 400);

ImportExportManager::exportToZip($id); // streams + exits
