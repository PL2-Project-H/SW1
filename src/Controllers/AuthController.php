<?php

class AuthController extends BaseController
{
    public function register(array $data): void
    {
        Response::json(['user' => $this->auth->register($data)]);
    }

    public function login(array $data): void
    {
        Response::json(['user' => $this->auth->login($data['email'] ?? '', $data['password'] ?? '')]);
    }

    public function logout(): void
    {
        $this->auth->logout();
        Response::json(['message' => 'Logged out']);
    }

    public function me(): void
    {
        $user = $this->auth->me();
        if (!$user) {
            Response::error('Unauthenticated', 401);
        }
        $user['notifications'] = $this->notifications->listForUser((int) $user['id']);
        Response::json($user);
    }

    public function clearNotifications(): void
    {
        $userId = $this->requireAuth();
        $this->notifications->clearForUser($userId);
        Response::json(['message' => 'Notifications cleared']);
    }
}
