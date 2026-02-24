<?php
require_once __DIR__ . '/../Core/bootstrap.php';
require_role('admin');
$ctrl = new UserController();
[$ok,$data,$error] = $ctrl->deleteUser(body());
json_response($ok,$data,$error);
