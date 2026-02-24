<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$user = require_role('student');
$ctrl = new CourseController();
[$ok,$data,$error] = $ctrl->unenroll(body(), $user);
json_response($ok,$data,$error);
