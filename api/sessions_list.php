<?php
/**
 * api/sessions_list.php
 * GET -> { success: true, sessions: [...] }
 * Admin-only. Reads every sessions/<id>/metadata.json from disk — no DB.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
admin_require_api();

json_response([
    'success' => true,
    'sessions' => SessionManager::listAll(),
    'stats' => SessionManager::stats(),
    'save_mode' => SessionManager::saveMode(),
]);
