<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$user = require_role('faculty');
$ctrl = new EvaluationController();
[$ok,$data,$error] = $ctrl->facultyRatings($user);
json_response($ok,$data,$error);
