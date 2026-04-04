<?php

require_once __DIR__ . '/../Core/bootstrap.php';

$controller = new ProjectController();
$action = Router::action();
$body = Router::body();

if (preg_match('#^contracts/(\d+)$#', $action, $matches)) {
    $controller->contractDetail((int) $matches[1]);
    exit;
}

if (preg_match('#^milestones/(\d+)$#', $action, $matches)) {
    $controller->milestoneDetail((int) $matches[1]);
    exit;
}

if (preg_match('#^contracts/(\d+)/messages$#', $action, $matches)) {
    $controller->contractMessages((int) $matches[1]);
    exit;
}

match ($action) {
    'bids/submit' => $controller->submitBid($body),
    'bids/withdraw' => $controller->withdrawBid($body),
    'bids/mine' => $controller->myBids(),
    'interviews/mine' => $controller->myInterviews(),
    'interviews/respond' => $controller->respondInterview($body),
    'contracts/active' => $controller->activeContracts(),
    'contracts/nda/sign' => $controller->signNda($body),
    'contracts/amend' => $controller->amend($body),
    'contracts/amend/respond' => $controller->respondAmendment($body),
    'contracts/milestones/build' => $controller->buildMilestones($body),
    'milestones/start' => $controller->startMilestone($body),
    'milestones/submit' => $controller->submitDeliverable(),
    'milestones/revision' => $controller->requestRevision($body),
    'milestones/approve' => $controller->approveMilestone($body),
    'milestones/confirm' => $controller->confirmMilestone($body),
    'milestones/snapshots' => $controller->listSnapshots(),
    'milestones/snapshot' => $controller->createSnapshot(),
    'contracts/qa-checklist' => $controller->qaChecklist(),
    'contracts/qa-checklist/submit' => $controller->submitChecklist($body),
    'contracts/message' => $controller->sendContractMessage($body),
    default => Response::error('Unknown action', 404),
};
