<?php
require_once __DIR__ . '/../Core/bootstrap.php';
require_login();
$ctrl = new UserController();
[$ok,$data,$error] = $ctrl->facultyList();
json_response($ok,$data,$error);
