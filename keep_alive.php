<?php
// Session keep-alive script
session_start();

if (isset($_SESSION['user_id'])) {
    // Refresh session expiration
    $_SESSION['last_activity'] = time();
    echo json_encode(['ok' => true]);
} else {
    http_response_code(401);
    echo json_encode(['ok' => false]);
}