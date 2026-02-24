<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$user = require_login();
$ctrl = new CommunicationController();
[$ok,$data,$error] = $ctrl->notifications($user);
json_response($ok,$data,$error);
