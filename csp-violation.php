<?php
header("Content-Type: text/plain; charset=utf-8");
$raw = file_get_contents("php://input");
if ($raw) {
    $log = date("Y-m-d H:i:s") . " " . $raw . "\n";
    file_put_contents("/www/logs/csp-violations.log", $log, FILE_APPEND | LOCK_EX);
}
http_response_code(204);
