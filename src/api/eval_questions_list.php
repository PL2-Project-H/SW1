<?php
require_once __DIR__ . '/../Core/bootstrap.php';
require_login();
$ctrl = new EvaluationController();
[$ok,$data,$error] = $ctrl->questions();
json_response($ok,$data,$error);
