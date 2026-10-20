<?php
/**
 * api/printers_list.php
 * GET -> { success, printers: [{name, status}], current }
 * Admin-only; backs the "Refresh printers" button.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
admin_require_api();

json_response([
    'success' => true,
    'printers' => PrintManager::listPrinters(),
    'current' => Settings::get('printing.printer_name', ''),
]);
