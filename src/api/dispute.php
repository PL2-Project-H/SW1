<?php

require_once __DIR__ . '/../Core/bootstrap.php';

$controller = new DisputeController();
$action = Router::action();
$body = Router::body();

if (preg_match('#^\d+$#', $action)) {
    $controller->detail((int) $action);
    exit;
}
if (preg_match('#^messages/(\d+)$#', $action, $matches)) {
    $controller->messages((int) $matches[1]);
    exit;
}

match ($action) {
    'file' => $controller->file($body),
    'mine' => $controller->mine(),
    'message' => $controller->safeRoomMessage($body),
    'verdict' => $controller->verdict($body),
    'appeal' => $controller->appeal($body),
    'arbitrators' => $controller->arbitrators(),
    'assign' => $controller->assign($body),
    default => Response::error('Unknown action', 404),
};
