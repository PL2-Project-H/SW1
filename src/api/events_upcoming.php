<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$user = require_role('student');
$ctrl = new EventController();
[$ok,$data,$error] = $ctrl->upcomingForStudent($user);
json_response($ok,$data,$error);
