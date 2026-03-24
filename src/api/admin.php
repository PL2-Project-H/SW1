<?php

require_once __DIR__ . '/../Core/bootstrap.php';

$controller = new AdminController();
$action = Router::action();
$body = Router::body();

match ($action) {
    'dashboard' => $controller->getDashboard(),
    'reports/niche' => $controller->getNicheReport(),
    'users' => $controller->users(),
    'users/sanction' => $controller->sanctionUser($body),
    'users/flagged' => $controller->flaggedUsers(),
    'users/flag' => $controller->flagUser($body),
    'audit-log' => $controller->auditLog(),
    'credentials/queue' => $controller->credentialQueue(),
    'credential/review' => $controller->reviewCredential($body),
    'kyc/queue' => $controller->kycQueue(),
    'kyc/review' => $controller->reviewKyc($body),
    'search-index/rebuild' => $controller->rebuildSearchIndex(),
    'weekly-digest/preview' => $controller->digestPreview(),
    'weekly-digest/send' => $controller->sendWeeklyDigest(),
    'roles' => $controller->roles(),
    'roles/assign' => $controller->assignRole($body),
    'messages/archive' => $controller->archivedMessages(),
    default => Response::error('Unknown action', 404),
};
