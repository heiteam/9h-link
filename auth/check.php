<?php
require __DIR__ . '/session_init.php';
header('Content-Type: application/json; charset=utf-8');
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user'])) {
    echo json_encode([
        'logged_in' => true,
        'user' => $_SESSION['user']
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['logged_in' => false]);
}