<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$user = require_role(['admin','faculty']);
$ctrl = new CommunicationController();
[$ok,$data,$error] = $ctrl->postAnnouncement(body(), $user);
json_response($ok,$data,$error);
