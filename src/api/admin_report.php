<?php
require_once __DIR__ . '/../Core/bootstrap.php';
require_role('admin');
$ctrl = new AssessmentController();
[$ok,$data,$error] = $ctrl->report();
json_response($ok,$data,$error);
