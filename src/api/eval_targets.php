<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$user = require_role(['admin','student','faculty']);
$ctrl = new EvaluationController();
[$ok,$data,$error] = $ctrl->targets($user);
json_response($ok,$data,$error);
