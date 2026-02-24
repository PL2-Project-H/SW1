<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$user = current_user();
$ctrl = new CourseController();
[$ok,$data,$error] = $ctrl->listAll($user);
json_response($ok,$data,$error);
