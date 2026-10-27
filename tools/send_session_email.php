<?php
/**
 * tools/send_session_email.php <session_id> [--dry]
 * Emails one accepted session (run in the background by api/strip_accept.php).
 * Records the outcome in the session's metadata.json. --dry builds the
 * message and prints its headers without sending (for checking).
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
@ini_set('memory_limit', '512M');
require_once __DIR__ . '/../includes/bootstrap.php';

$sessionId = sanitize_id($argv[1] ?? '');
if ($sessionId === '' || !is_dir(SessionManager::dir($sessionId))) {
    fwrite(STDERR, "Unknown session\n");
    exit(2);
}
if (in_array('--dry', $argv, true)) {
    $msg = Mailer::sendSession($sessionId, true);
    echo substr($msg, 0, strpos($msg, "\r\n\r\n")), "\n", strlen($msg), " bytes\n";
    exit(0);
}
exit(Mailer::sendAndRecord($sessionId) ? 0 : 1);
