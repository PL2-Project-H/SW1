<?php

require_once __DIR__ . '/../Core/bootstrap.php';

$controller = new FreelancerController();
$action = Router::action();
$body = Router::body();

match ($action) {
    'profile' => $controller->profile(),
    'profile/update' => $controller->updateProfile($body),
    'credentials/submit' => $controller->submitCredential($_POST),
    'credentials/status' => $controller->credentialsStatus(),
    'kyc/submit' => $controller->submitKyc($_POST),
    'kyc/status' => $controller->kycStatus(),
    'portfolio/add' => $controller->addPortfolio($_POST),
    'portfolio' => $controller->renderPortfolio(),
    'profile/public' => $controller->getPublicProfile(),
    'profile/privacy' => $controller->togglePortfolioPrivacy($body),
    'availability' => $controller->availability(),
    'availability/set' => $controller->setAvailability($body),
    'search' => $controller->search(),
    'reputation' => $controller->reputation(),
    default => Response::error('Unknown action', 404),
};
