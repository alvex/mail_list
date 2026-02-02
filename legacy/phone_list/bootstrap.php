<?php
session_start();

$timeout = 30 * 60; // 30 minutes

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

$_SESSION['last_activity'] = time();

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function phone_list_require_csrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    if ($token === '' || !hash_equals((string)$_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        echo 'Bad request';
        exit();
    }
}
