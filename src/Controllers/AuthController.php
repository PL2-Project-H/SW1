<?php
class AuthController {
    private $service;

    public function __construct() {
        $this->service = new AuthService();
    }

    public function register($in) {
        return $this->service->register($in['name'] ?? '', $in['email'] ?? '', $in['password'] ?? '', $in['role'] ?? 'student');
    }

    public function login($in) {
        return $this->service->login($in['email'] ?? '', $in['password'] ?? '');
    }

    public function logout() {
        return $this->service->logout();
    }

    public function me() {
        return $this->service->me();
    }
}
