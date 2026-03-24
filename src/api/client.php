<?php

require_once __DIR__ . '/../Core/bootstrap.php';

$controller = new ClientController();
$action = Router::action();
$body = Router::body();

match ($action) {
    'jobs/create' => $controller->createJob($body),
    'jobs/mine' => $controller->myJobs(),
    'jobs/browse' => $controller->browseJobs(),
    'jobs/invite' => $controller->createPrivateJob((int) $body['job_id'], $body['invitees']),
    'bids' => $controller->bids(),
    'bids/accept' => $controller->acceptBid($body),
    'bids/reject' => $controller->rejectBid($body),
    'contracts/nda/sign' => $controller->signNda($body),
    'interviews' => $controller->interviews(),
    'interviews/schedule' => $controller->scheduleInterview($body),
    'interviews/update' => $controller->updateInterview($body),
    default => Response::error('Unknown action', 404),
};
