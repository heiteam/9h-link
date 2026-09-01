<?php
header("Content-Type: text/plain; charset=utf-8");
header("X-Content-Type-Options: nosniff");
header("HTTP/1.1 204 No Content");

$raw = file_get_contents("php://input");
if ($raw) {
    $logDir = __DIR__ . '/data/logs';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    $logFile = $logDir . '/csp-violations.log';
    $log = date("Y-m-d H:i:s") . " " . $raw . "\n";
    file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);
}
