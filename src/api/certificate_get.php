<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$user = require_role('student');
$ctrl = new AssessmentController();
$in = $_GET;
[$ok,$data,$error] = $ctrl->certificate($in, $user);
json_response($ok,$data,$error);
