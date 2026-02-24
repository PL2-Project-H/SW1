<?php
class UserController {
    private $service;

    public function __construct() {
        $this->service = new UserService();
    }

    public function profile($id) {
        return $this->service->profile($id);
    }

    public function updateProfile($id, $in) {
        return $this->service->updateProfile($id, $in['name'] ?? '', $in['email'] ?? '');
    }

    public function changePassword($id, $in) {
        return $this->service->changePassword($id, $in['password'] ?? '');
    }

    public function listUsers() {
        return $this->service->listUsers();
    }

    public function addUser($in) {
        return $this->service->addUser($in['name'] ?? '', $in['email'] ?? '', $in['password'] ?? '', $in['role'] ?? 'student');
    }

    public function deleteUser($in) {
        return $this->service->deleteUser($in['id'] ?? 0);
    }

    public function setRole($in) {
        return $this->service->setRole($in['id'] ?? 0, $in['role'] ?? 'student');
    }

    public function setActive($in) {
        return $this->service->setActive($in['id'] ?? 0, $in['active'] ?? 0);
    }

    public function facultyList() {
        return $this->service->facultyList();
    }
}
