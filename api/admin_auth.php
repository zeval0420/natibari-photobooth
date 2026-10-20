<?php
/**
 * api/admin_auth.php
 * POST { action: "login", passcode: "1234" }  -> { success: true }
 * POST { action: "logout" }                    -> { success: true }
 * GET                                          -> { success: true, authenticated: bool }
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(['success' => true, 'authenticated' => admin_is_authenticated()]);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';

if ($action === 'login') {
    $ok = admin_login((string)($body['passcode'] ?? ''));
    if ($ok) {
        json_response(['success' => true]);
    }
    json_error('Incorrect passcode', 401);
}

if ($action === 'logout') {
    admin_logout();
    json_response(['success' => true]);
}

json_error('Unknown action', 400);
