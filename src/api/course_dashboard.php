<?php
require_once __DIR__ . '/../Core/bootstrap.php';
require_login();
$ctrl = new CourseController();
$in = $_GET;
[$ok,$data,$error] = $ctrl->dashboard($in);
json_response($ok,$data,$error);
