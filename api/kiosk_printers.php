<?php
/**
 * api/kiosk_printers.php
 * GET -> { success, printers: [{name, status}], paper_sizes: [...], current: {printer, paper_size} }
 *
 * Feeds the kiosk's "Printer & paper" panel. Unlike printers_list.php this
 * is not admin-gated: printer names and paper sizes aren't sensitive, and
 * the panel has to work for whoever is running the booth.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

json_response([
    'success' => true,
    'printers' => PrintManager::listPrinters(),
    'paper_sizes' => PrintManager::PAPER_SIZES,
    'current' => [
        'printer' => Settings::get('printing.printer_name', ''),
        'paper_size' => Settings::get('printing.paper_size', '5R'),
    ],
]);
