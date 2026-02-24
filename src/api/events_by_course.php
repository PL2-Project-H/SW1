<?php
require_once __DIR__ . '/../Core/bootstrap.php';
require_login();
$ctrl = new EventController();
$in = $_GET;
[$ok,$data,$error] = $ctrl->byCourse($in);
json_response($ok,$data,$error);
