<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$user = require_role('student');
$ctrl = new AssessmentController();
[$ok,$data,$error] = $ctrl->listForStudent($user);
json_response($ok,$data,$error);
