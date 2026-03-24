<?php

require_once __DIR__ . '/../Core/bootstrap.php';

$controller = new EscrowController();
$action = Router::action();
$body = Router::body();

match ($action) {
    'lock' => $controller->lock($body),
    'release' => $controller->release($body),
    'partial-release' => $controller->partialRelease($body),
    'balance' => $controller->balance(),
    'refund' => $controller->refund($body),
    'ledger' => $controller->ledger(),
    'payout-schedule' => $controller->payoutSchedule(),
    'fees' => $controller->fees(),
    'tax' => $controller->tax(),
    default => Response::error('Unknown action', 404),
};
