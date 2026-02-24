<?php
require_once __DIR__ . '/../Core/bootstrap.php';
require_login();
$ctrl = new AssessmentController();
$in = $_GET;
[$ok,$data,$error] = $ctrl->listByCourse($in);
json_response($ok,$data,$error);
