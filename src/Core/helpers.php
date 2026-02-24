<?php
function json_response($ok, $data = null, $error = null) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok, 'data' => $data, 'error' => $error]);
    exit;
}

function body() {
    $raw = file_get_contents('php://input');
    $parsed = json_decode($raw, true);
    return is_array($parsed) ? $parsed : $_POST;
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function require_login() {
    $u = current_user();
    if (!$u) {
        json_response(false, null, 'Unauthorized');
    }
    return $u;
}

function require_role($roles) {
    $u = require_login();
    $list = is_array($roles) ? $roles : [$roles];
    if (!in_array($u['role'], $list, true)) {
        json_response(false, null, 'Forbidden');
    }
    return $u;
}
