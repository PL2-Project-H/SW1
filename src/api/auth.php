<?php

require_once __DIR__ . '/../Core/bootstrap.php';

$controller = new AuthController();
$action = Router::action();
$body = Router::body();

match ($action) {
    'register' => $controller->register($body),
    'login' => $controller->login($body),
    'logout' => $controller->logout(),
    'me' => $controller->me(),
    'notifications/clear' => $controller->clearNotifications(),
    default => Response::error('Unknown action', 404),
};
