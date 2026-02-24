<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$user = require_login();
$ctrl = new UserController();
[$ok,$data,$error] = $ctrl->profile($user['id']);
json_response($ok,$data,$error);
