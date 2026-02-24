<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$ctrl = new AuthController();
[$ok,$data,$error] = $ctrl->register(body());
json_response($ok,$data,$error);
