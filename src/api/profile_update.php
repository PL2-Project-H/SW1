<?php
require_once __DIR__ . '/../Core/bootstrap.php';
$user = require_login();
$ctrl = new UserController();
[$ok,$data,$error] = $ctrl->updateProfile($user['id'], body());
if ($ok) {
    $_SESSION['user']['name'] = $data['name'];
    $_SESSION['user']['email'] = $data['email'];
}
json_response($ok,$data,$error);
