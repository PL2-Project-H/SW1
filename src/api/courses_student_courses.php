<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$user = require_role('student');
$ctrl = new CourseController();
[$ok,$data,$error] = $ctrl->studentCourses($user);
json_response($ok,$data,$error);
