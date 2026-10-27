<?php
/**
 * includes/Mailer.php
 * ------------------------------------------------------------------
 * Emails every accepted session (final strip, side-by-side print file and
 * raw photos) over plain SMTP — no Composer. Configure in settings.json:
 *
 *   "email": { "enabled": true, "subject": "Natibari Photobooth",
 *              "to": [...], "smtp_host": "smtp.gmail.com", "smtp_port": 465,
 *              "smtp_user": "you@gmail.com", "smtp_pass": "<app password>" }
 *
 * Threading: every email of one day has the SAME subject
 * ("Natibari Photobooth - 2026-10-26") and references a fixed per-day
 * root Message-ID, so mail clients stack them in one thread per day.
 * ------------------------------------------------------------------
 */
class Mailer
{
    private const DOMAIN = 'natibari-photobooth.local';

    public static function enabled(): bool
    {
        return (bool)Settings::get('email.enabled', false)
            && self::recipients() !== []
            && trim((string)Settings::get('email.smtp_user', '')) !== ''
            && (string)Settings::get('email.smtp_pass', '') !== '';
    }

    public static function recipients(): array
    {
        $to = Settings::get('email.to', []);
        $to = is_array($to) ? $to : [$to];
        return array_values(array_filter(array_map('trim', $to), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
    }

    /**
     * Email a session without making the guest wait: spawn the CLI sender
     * detached. $wait = true runs it inline (needed before raw photos are purged).
     */
    public static function dispatch(string $sessionId, bool $wait = false): void
    {
        if (!self::enabled()) {
            return;
        }
        if ($wait) {
            self::sendAndRecord($sessionId);
            return;
        }
        $isWin = PrintManager::isWindows();
        $php = $isWin ? dirname(PHP_BINARY) . '\\php.exe' : 'php';
        if ($isWin && !is_file($php)) {
            $php = 'C:\\xampp\\php\\php.exe';
        }
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg(ROOT_PATH . '/tools/send_session_email.php') . ' ' . escapeshellarg($sessionId);
        if ($isWin) {
            pclose(popen('start /B "" ' . $cmd . ' >NUL 2>&1', 'r'));
        } else {
            exec($cmd . ' >/dev/null 2>&1 &');
        }
    }

    /** Send and note the outcome in metadata.json. Returns success. */
    public static function sendAndRecord(string $sessionId): bool
    {
        try {
            self::sendSession($sessionId);
            $result = ['email_status' => 'sent', 'email_sent_at' => date('c')];
        } catch (Throwable $e) {
            error_log('Photobooth email failed: ' . $e->getMessage());
            $result = ['email_status' => 'failed', 'email_error' => $e->getMessage()];
        }
        $meta = SessionManager::getMetadata($sessionId);
        SessionManager::saveMetadata($sessionId, $result + $meta);
        return $result['email_status'] === 'sent';
    }

    /** Send the session's files. Throws on failure. */
    public static function sendSession(string $sessionId, bool $dryRun = false): string
    {
        $meta = SessionManager::getMetadata($sessionId);
        $files = [];
        foreach (['strip', 'strip_print'] as $key) {
            if (!empty($meta[$key]) && is_file(SessionManager::abs($meta[$key]))) {
                $files[] = SessionManager::abs($meta[$key]);
            }
        }
        $raw = glob(SessionManager::rawDir($sessionId) . '/photo*.jpg') ?: [];
        sort($raw, SORT_NATURAL);
        $files = array_merge($files, $raw);
        if (!$files) {
            throw new RuntimeException('Nothing to email for this session');
        }

        $day = substr($sessionId, 0, 10);                       // YYYY-MM-DD
        $time = str_replace('-', ':', substr($sessionId, 11));  // HH:MM:SS
        $subject = trim((string)Settings::get('email.subject', 'Natibari Photobooth')) . ' - ' . $day;
        $body = "New photobooth session accepted.\r\n\r\nSession: {$sessionId}\r\nTime: {$day} {$time}\r\n"
            . 'Template: ' . ($meta['template'] ?? '-') . "\r\n\r\nAttached: final strip, print layout (double strip) and raw photos.\r\n";

        $rootId = '<natibari-photobooth-' . $day . '@' . self::DOMAIN . '>';
        $stateFile = CONFIG_PATH . '/mail_threads.json';
        $state = read_json_file($stateFile, []);
        $isRoot = empty($state[$day]);
        $msgId = $isRoot ? $rootId : '<' . bin2hex(random_bytes(8)) . '@' . self::DOMAIN . '>';

        $msg = self::buildMessage($subject, $body, $files, $msgId, $isRoot ? null : $rootId);
        if ($dryRun) {
            return $msg;
        }
        self::deliver($msg);

        if ($isRoot) {
            $state[$day] = $rootId;
            write_json_file($stateFile, $state);
        }
        return $msg;
    }

    private static function buildMessage(string $subject, string $text, array $files, string $msgId, ?string $rootId): string
    {
        $user = (string)Settings::get('email.smtp_user');
        $fromName = (string)Settings::get('email.from_name', 'Natibari Photobooth');
        $b = 'pb' . bin2hex(random_bytes(10));
        $h = [
            'Date: ' . date('r'),
            'From: ' . self::encodeWord($fromName) . " <{$user}>",
            'To: ' . implode(', ', self::recipients()),
            'Subject: ' . self::encodeWord($subject),
            "Message-ID: {$msgId}",
        ];
        if ($rootId !== null) {
            $h[] = "In-Reply-To: {$rootId}";
            $h[] = "References: {$rootId}";
        }
        $h[] = 'MIME-Version: 1.0';
        $h[] = "Content-Type: multipart/mixed; boundary=\"{$b}\"";

        $m = implode("\r\n", $h) . "\r\n\r\n"
            . "--{$b}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text)) . "\r\n";
        foreach ($files as $f) {
            $type = str_ends_with(strtolower($f), '.png') ? 'image/png' : 'image/jpeg';
            $name = basename($f);
            if (preg_match('/^photo\d+\.jpg$/', $name)) {
                $name = basename(dirname($f, 2)) . '_' . $name; // unique raw names across sessions
            }
            $m .= "--{$b}\r\nContent-Type: {$type}; name=\"{$name}\"\r\nContent-Transfer-Encoding: base64\r\n"
                . "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n"
                . chunk_split(base64_encode(file_get_contents($f))) . "\r\n";
        }
        return $m . "--{$b}--\r\n";
    }

    private static function encodeWord(string $s): string
    {
        return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private static function deliver(string $message): void
    {
        $host = (string)Settings::get('email.smtp_host', 'smtp.gmail.com');
        $port = (int)Settings::get('email.smtp_port', 465);
        $user = (string)Settings::get('email.smtp_user');
        $pass = (string)Settings::get('email.smtp_pass');

        $fp = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . "{$host}:{$port}", $errno, $err, 20);
        if (!$fp) {
            throw new RuntimeException("SMTP connect failed: {$err}");
        }
        stream_set_timeout($fp, 60);
        $read = function () use ($fp): string {
            $out = '';
            while (($line = fgets($fp, 1024)) !== false) {
                $out .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') {
                    break;
                }
            }
            return $out;
        };
        $cmd = function (string $c, string $expect, string $label = '') use ($fp, $read): void {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if (strpos($r, $expect) !== 0) {
                throw new RuntimeException('SMTP error after ' . ($label ?: $c) . ': ' . trim($r));
            }
        };

        try {
            $r = $read();
            if (strpos($r, '220') !== 0) {
                throw new RuntimeException('SMTP greeting: ' . trim($r));
            }
            $cmd('EHLO ' . self::DOMAIN, '250');
            if ($port !== 465) {
                $cmd('STARTTLS', '220');
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS failed');
                }
                $cmd('EHLO ' . self::DOMAIN, '250');
            }
            $cmd('AUTH LOGIN', '334');
            $cmd(base64_encode($user), '334', 'username');
            $cmd(base64_encode($pass), '235', 'password (check the Gmail app password)');
            $cmd("MAIL FROM:<{$user}>", '250');
            foreach (self::recipients() as $to) {
                $cmd("RCPT TO:<{$to}>", '250');
            }
            $cmd('DATA', '354');
            fwrite($fp, preg_replace('/^\./m', '..', $message) . "\r\n.\r\n");
            $r = $read();
            if (strpos($r, '250') !== 0) {
                throw new RuntimeException('SMTP rejected message: ' . trim($r));
            }
            @fwrite($fp, "QUIT\r\n");
        } finally {
            fclose($fp);
        }
    }
}
