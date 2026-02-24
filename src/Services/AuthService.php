<?php
class AuthService {
    private $users;

    public function __construct() {
        $this->users = new UserRepository();
    }

    public function register($name, $email, $password, $role) {
        if (!in_array($role, ['student', 'faculty'], true)) {
            return [false, null, 'Invalid role'];
        }
        $id = $this->users->create($name, $email, $password, $role);
        return [true, ['id' => $id], null];
    }

    public function login($email, $password) {
        $u = $this->users->byEmail($email);
        if (!$u || $u['password_hash'] !== $password) {
            return [false, null, 'Invalid credentials'];
        }
        if ((int)$u['active'] !== 1) {
            return [false, null, 'User inactive'];
        }
        $_SESSION['user'] = [
            'id' => (int)$u['id'],
            'name' => $u['name'],
            'email' => $u['email'],
            'role' => $u['role']
        ];
        return [true, $_SESSION['user'], null];
    }

    public function logout() {
        $_SESSION = [];
        session_destroy();
        return [true, ['logged_out' => true], null];
    }

    public function me() {
        return [true, current_user(), null];
    }
}
