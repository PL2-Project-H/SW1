<?php

class AuthService
{
    private UserRepository $users;
    private AuditService $audit;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->audit = new AuditService();
    }

    public function register(array $data): array
    {
        $email = $this->normalizeEmail($data['email'] ?? '');
        $adminRole = $this->normalizeAdminRole($data['role'] ?? '', $data['admin_role'] ?? null);
        if ($email === '') {
            Response::error('Email is required', 422);
        }
        if (empty($data['password'])) {
            Response::error('Password is required', 422);
        }
        if ($this->users->findByEmail($email)) {
            Response::error('Email already exists', 409);
        }

        $userId = $this->users->create([
            'email' => $email,
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role' => $data['role'],
            'name' => $data['name'],
            'country' => $data['country'],
            'timezone' => $data['timezone'] ?? 'UTC',
            'kyc_status' => $this->kycCheck($data),
            'id_type' => $data['id_type'] ?? null,
            'admin_role' => $adminRole,
        ]);

        if ($data['role'] === 'freelancer') {
            $this->users->createFreelancerProfile($userId, $data);
        }

        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = $data['role'];
        $_SESSION['admin_role'] = $adminRole;

        $this->audit->log($userId, 'user_registration', 'user', $userId, null, ['email' => $email, 'role' => $data['role']]);
        return $this->users->getSessionUser($userId);
    }

    public function login(string $email, string $password): array
    {
        $email = $this->normalizeEmail($email);
        $user = $this->users->findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::error('Invalid credentials', 401);
        }
        if ($user['status'] === 'banned') {
            session_destroy();
            Response::error('Account is banned', 403);
        }
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['admin_role'] = $user['admin_role'] ?? null;
        $this->audit->log((int) $user['id'], 'login', 'user', (int) $user['id']);
        return $this->users->getSessionUser((int) $user['id']);
    }

    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            $this->audit->log((int) $userId, 'logout', 'user', (int) $userId);
        }
        session_destroy();
    }

    public function me(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return $this->users->getSessionUser((int) $_SESSION['user_id']);
    }

    public function requireAuth(?string $role = null): int
    {
        if (empty($_SESSION['user_id'])) {
            Response::error('Authentication required', 401);
        }
        $user = $this->users->findById((int) $_SESSION['user_id']);
        if (!$user) {
            Response::error('User not found', 404);
        }
        if ($user['status'] === 'banned') {
            session_destroy();
            Response::error('Account is banned', 403);
        }
        if ($user['status'] === 'limited' && in_array($_SESSION['role'], ['client', 'freelancer'], true)) {
            Response::error('Your account is limited and cannot perform this action', 403);
        }
        if ($role !== null && ($_SESSION['role'] ?? null) !== $role) {
            Response::error('Forbidden', 403);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->enforceCsrf();
        }
        return (int) $_SESSION['user_id'];
    }

    public function kycCheck(array $data): string
    {
        if (!empty($data['id_type']) && !empty($data['country']) && !empty($data['name'])) {
            return 'submitted';
        }
        return 'unverified';
    }

    public function checkRole(string $action): void
    {
        $role = $_SESSION['role'] ?? null;
        $adminRole = $_SESSION['admin_role'] ?? null;
        if ($role !== 'admin') {
            Response::error('Admin access required', 403);
        }

        $matrix = [
            'dashboard' => ['financial_admin', 'dispute_mediator', 'tech_support'],
            'reports/niche' => ['financial_admin', 'tech_support'],
            'users' => ['tech_support', 'dispute_mediator'],
            'users/flagged' => ['tech_support', 'dispute_mediator'],
            'users/flag' => ['tech_support', 'dispute_mediator'],
            'users/sanction' => ['tech_support', 'dispute_mediator'],
            'audit-log' => ['tech_support'],
            'search-index/rebuild' => ['tech_support'],
            'weekly-digest/preview' => ['tech_support'],
            'weekly-digest/send' => ['tech_support'],
            'roles' => ['tech_support'],
            'roles/assign' => ['tech_support'],
            'credential/review' => ['tech_support', 'dispute_mediator'],
            'dispute/verdict' => ['dispute_mediator'],
            'dispute/assign' => ['dispute_mediator'],
            'escrow/*' => ['financial_admin'],
        ];

        foreach ($matrix as $key => $allowed) {
            if ($key === $action || ($key === 'escrow/*' && str_starts_with($action, 'escrow'))) {
                if (!in_array($adminRole, $allowed, true)) {
                    Response::error('Admin role forbidden', 403);
                }
                return;
            }
        }
    }

    private function enforceCsrf(): void
    {
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($header !== ($_SESSION['csrf_token'] ?? null)) {
            Response::error('Invalid CSRF token', 419);
        }
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function normalizeAdminRole(string $role, mixed $adminRole): ?string
    {
        if ($role !== 'admin') {
            return null;
        }

        if (!is_scalar($adminRole)) {
            return null;
        }

        $normalized = trim((string) $adminRole);
        return $normalized === '' ? null : $normalized;
    }
}
