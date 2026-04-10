<?php
require_once __DIR__ . '/../Core/bootstrap.php';



(new NotificationService())->checkDeadlines();
(new BidService())->handleExpiry();
Response::json(['message' => 'Cron tasks completed', 'time' => date('Y-m-d H:i:s')]);
