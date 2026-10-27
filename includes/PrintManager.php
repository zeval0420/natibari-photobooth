<?php
/**
 * includes/PrintManager.php
 * ------------------------------------------------------------------
 * Sends an image to a printer. Two back-ends:
 *
 *   - Windows: tools/print_image.ps1 (System.Drawing PrintDocument —
 *     works with any installed printer, no extra software).
 *   - Linux/macOS: CUPS `lp`.
 *
 * If no printer is configured the print is *simulated* (and logged in
 * metadata.json) so the kiosk is fully usable without hardware.
 *
 * All options (printer, paper, copies, margins, scale, layout) come
 * from Settings — nothing is hard-coded.
 * ------------------------------------------------------------------
 */
class PrintManager
{
    public const PAPER_SIZES = ['5R', '4x6', '5x7', '2x6', 'A4', 'A5', 'A6', 'Letter'];

    public static function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /** True when the settings ask for printing to a real printer. */
    public static function isConfigured(): bool
    {
        return trim((string)Settings::get('printing.printer_name', '')) !== '';
    }

    /**
     * Print (or simulate) one image using the current printing settings.
     * @param array $override optional keys: printer, copies, paper_size, fit
     *                        (fit = true: zero margin, 100% scale — image fills the paper)
     */
    public static function printFile(string $absoluteImagePath, ?string $paperSizeOverride = null, array $override = []): array
    {
        $p = Settings::get('printing', []);
        $copies = max(1, min(20, (int)($override['copies'] ?? $p['copies'] ?? 1)));
        $printer = trim((string)($override['printer'] ?? $p['printer_name'] ?? ''));
        $paper = $paperSizeOverride ?? ($override['paper_size'] ?? $p['paper_size'] ?? '5R');
        $margins = max(0.0, (float)($p['margins_mm'] ?? 0));
        $scale = max(10, min(200, (int)($p['scale_percent'] ?? 100)));
        if (!empty($override['fit'])) {
            $margins = 0.0;
            $scale = 100;
        }

        if (!is_file($absoluteImagePath)) {
            return ['status' => 'failed', 'reason' => 'File not found.'];
        }
        if ($printer === '') {
            return [
                'status' => 'simulated',
                'reason' => 'No printer selected in Settings — simulated print.',
                'copies' => $copies,
                'paper_size' => $paper,
            ];
        }
        if (!function_exists('exec')) {
            return ['status' => 'failed', 'reason' => 'PHP exec() is disabled on this server.'];
        }

        $cmd = self::isWindows()
            ? self::windowsCommand($absoluteImagePath, $printer, $copies, $paper, $margins, $scale)
            : self::cupsCommand($absoluteImagePath, $printer, $copies, $paper, $margins, $scale);

        $output = [];
        $exit = 0;
        exec($cmd . ' 2>&1', $output, $exit);

        $base = ['printer' => $printer, 'copies' => $copies, 'paper_size' => $paper];
        if ($exit === 0) {
            $notes = array_values(array_filter($output, fn($l) => strpos($l, 'NOTE:') === 0));
            return ['status' => 'printed'] + $base + ($notes ? ['note' => implode(' ', $notes)] : []);
        }
        return ['status' => 'failed', 'reason' => trim(implode(' ', $output)) ?: ('Print command exited with code ' . $exit)] + $base;
    }

    private static function windowsCommand(string $file, string $printer, int $copies, string $paper, float $margins, int $scale): string
    {
        $script = ROOT_PATH . '/tools/print_image.ps1';
        // escapeshellarg on Windows yields "double quoted" arguments, which PowerShell -File accepts.
        return implode(' ', [
            'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -File',
            escapeshellarg($script),
            '-Image', escapeshellarg($file),
            '-Printer', escapeshellarg($printer),
            '-Copies', $copies,
            '-Paper', escapeshellarg($paper),
            '-MarginMm', escapeshellarg((string)$margins),
            '-ScalePercent', $scale,
        ]);
    }

    private static function cupsCommand(string $file, string $printer, int $copies, string $paper, float $margins, int $scale): string
    {
        $mediaMap = ['4x6' => 'Custom.4x6in', '5R' => 'Custom.5x7in', '5x7' => 'Custom.5x7in', '2x6' => 'Custom.2x6in'];
        $parts = [
            'lp', '-d', escapeshellarg($printer), '-n', $copies,
            '-o', escapeshellarg('media=' . ($mediaMap[$paper] ?? $paper)),
            '-o', 'fit-to-page',
        ];
        if ($margins > 0) {
            $pt = (int)round($margins * 2.83465);
            $parts[] = '-o';
            $parts[] = escapeshellarg("page-left={$pt} page-right={$pt} page-top={$pt} page-bottom={$pt}");
        }
        if ($scale !== 100) {
            $parts[] = '-o';
            $parts[] = escapeshellarg('scaling=' . $scale);
        }
        $parts[] = escapeshellarg($file);
        return implode(' ', $parts);
    }

    /**
     * Installed printers with their state, for the Settings page.
     * @return array<int, array{name:string, status:string}>
     */
    public static function listPrinters(): array
    {
        $printers = [];
        if (self::isWindows()) {
            $out = [];
            @exec('powershell -NoProfile -NonInteractive -Command "Get-Printer | ForEach-Object { $_.Name + \'|\' + $_.PrinterStatus }"', $out);
            foreach ($out as $line) {
                if (strpos($line, '|') === false) {
                    continue;
                }
                [$name, $status] = explode('|', $line, 2);
                $name = trim($name);
                if ($name !== '') {
                    $printers[] = ['name' => $name, 'status' => trim($status) ?: 'Normal'];
                }
            }
            return $printers;
        }

        if (trim((string)@shell_exec('command -v lpstat 2>/dev/null')) === '') {
            return [];
        }
        foreach (explode("\n", (string)@shell_exec('lpstat -p 2>/dev/null')) as $line) {
            if (preg_match('/^printer\s+(\S+)\s+(.*)$/', $line, $m)) {
                $printers[] = ['name' => $m[1], 'status' => stripos($m[2], 'idle') !== false ? 'Normal' : trim($m[2])];
            }
        }
        return $printers;
    }

    /** Just the names — handy for validation. */
    public static function printerNames(): array
    {
        return array_column(self::listPrinters(), 'name');
    }
}
