<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$user = require_role('faculty');
$ctrl = new CourseController();
[$ok,$data,$error] = $ctrl->facultyCourses($user);
json_response($ok,$data,$error);
