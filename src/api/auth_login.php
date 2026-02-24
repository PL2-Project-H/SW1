<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$ctrl = new AuthController();
[$ok,$data,$error] = $ctrl->login(body());
json_response($ok,$data,$error);
