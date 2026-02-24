<?php
require_once __DIR__ . '/../Core/bootstrap.php';
require_login();
$ctrl = new CommunicationController();
$in = $_GET;
[$ok,$data,$error] = $ctrl->announcements($in);
json_response($ok,$data,$error);
