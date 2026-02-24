<?php
require_once __DIR__ . '/../Core/bootstrap.php';
require_role(['faculty','admin']);
$ctrl = new AssessmentController();
$in = $_GET;
[$ok,$data,$error] = $ctrl->submissionDetails($in);
json_response($ok,$data,$error);
