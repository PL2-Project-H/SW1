<?php
require_once __DIR__ . '/../Core/bootstrap.php';
require_role('admin');
$ctrl = new EvaluationController();
[$ok,$data,$error] = $ctrl->updateQuestion(body());
json_response($ok,$data,$error);
