<?php
require_once __DIR__ . '/../Core/bootstrap.php';
require_role(['faculty','admin']);
$ctrl = new AssessmentController();
[$ok,$data,$error] = $ctrl->addQuestion(body());
json_response($ok,$data,$error);
