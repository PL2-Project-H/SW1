<?php
class UserService {
    private $users;

    public function __construct() {
        $this->users = new UserRepository();
    }

    public function profile($id) {
        return [true, $this->users->byId($id), null];
    }

    public function updateProfile($id, $name, $email) {
        $this->users->updateProfile($id, $name, $email);
        return [true, $this->users->byId($id), null];
    }

    public function changePassword($id, $password) {
        $this->users->updatePassword($id, $password);
        return [true, ['updated' => true], null];
    }

    public function listUsers() {
        return [true, $this->users->all(), null];
    }

    public function addUser($name, $email, $password, $role) {
        $id = $this->users->create($name, $email, $password, $role);
        return [true, ['id' => $id], null];
    }

    public function deleteUser($id) {
        $this->users->delete($id);
        return [true, ['deleted' => true], null];
    }

    public function setRole($id, $role) {
        $this->users->setRole($id, $role);
        return [true, ['updated' => true], null];
    }

    public function setActive($id, $active) {
        $this->users->setActive($id, $active);
        return [true, ['updated' => true], null];
    }

    public function facultyList() {
        return [true, $this->users->facultyList(), null];
    }
}
