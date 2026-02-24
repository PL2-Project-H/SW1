<?php
require_once __DIR__ . '/../Core/bootstrap.php';
require_role('admin');
$ctrl = new CourseController();
[$ok,$data,$error] = $ctrl->update(body());
json_response($ok,$data,$error);
