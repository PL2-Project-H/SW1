<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$user = require_role('student');
$ctrl = new AssessmentController();
[$ok,$data,$error] = $ctrl->transcript($user);
json_response($ok,$data,$error);
