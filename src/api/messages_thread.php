<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$user = require_login();
$ctrl = new CommunicationController();
$in = $_GET;
[$ok,$data,$error] = $ctrl->thread($in, $user);
json_response($ok,$data,$error);
