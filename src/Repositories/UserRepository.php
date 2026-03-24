<?php

class UserRepository extends BaseRepository
{
    public function create(array $data): int
    {
        return $this->insert(
            'INSERT INTO users (email, password_hash, role, name, country, timezone, kyc_status, id_type, admin_role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['email'],
                $data['password_hash'],
                $data['role'],
                $data['name'],
                $data['country'],
                $data['timezone'] ?? 'UTC',
                $data['kyc_status'] ?? 'submitted',
                $data['id_type'] ?? null,
                $data['admin_role'] ?? null,
            ]
        );
    }

    public function createFreelancerProfile(int $userId, array $data): void
    {
        $this->insert(
            'INSERT INTO freelancer_profiles (user_id, bio, niche, hourly_rate, availability_status, timezone, linkedin_url, digest_opt_in) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $data['bio'] ?? '',
                $data['niche'] ?? 'other',
                $data['hourly_rate'] ?? 0,
                $data['availability_status'] ?? 'open',
                $data['timezone'] ?? 'UTC',
                $data['linkedin_url'] ?? null,
                array_key_exists('digest_opt_in', $data) ? (!empty($data['digest_opt_in']) ? 1 : 0) : 1,
            ]
        );
        $this->execute('INSERT INTO reputation_scores (user_id) VALUES (?)', [$userId]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->fetch('SELECT * FROM users WHERE TRIM(LOWER(email)) = ? LIMIT 1', [trim(strtolower($email))]);
    }

    public function findById(int $id): ?array
    {
        return $this->fetch('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public function getSessionUser(int $id): ?array
    {
        $user = $this->fetch('SELECT id, email, role, admin_role, name, country, timezone, kyc_status, status, created_at FROM users WHERE id = ?', [$id]);
        if (!$user) {
            return null;
        }
        if ($user['role'] === 'freelancer') {
            $user['profile'] = $this->fetch('SELECT * FROM freelancer_profiles WHERE user_id = ?', [$id]);
            $user['reputation'] = $this->fetch('SELECT * FROM reputation_scores WHERE user_id = ?', [$id]);
        }
        $user['csrf_token'] = $_SESSION['csrf_token'] ?? null;
        return $user;
    }

    public function listUsers(array $filters = []): array
    {
        $sql = 'SELECT id, email, role, admin_role, name, country, timezone, status, kyc_status, created_at FROM users WHERE 1=1';
        $params = [];
        if (!empty($filters['role'])) {
            $sql .= ' AND role = ?';
            $params[] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (name LIKE ? OR email LIKE ?)';
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }
        $sql .= ' ORDER BY created_at DESC';
        return $this->fetchAllRows($sql, $params);
    }

    public function setStatus(int $userId, string $status): void
    {
        $this->execute('UPDATE users SET status = ? WHERE id = ?', [$status, $userId]);
    }

    public function setAdminRole(int $userId, string $adminRole): void
    {
        $this->execute('UPDATE users SET admin_role = ?, role = ? WHERE id = ?', [$adminRole, 'admin', $userId]);
    }

    public function addFlag(int $userId, int $adminId, string $reason, string $level): void
    {
        $this->execute(
            'INSERT INTO user_flags (user_id, admin_id, reason, level) VALUES (?, ?, ?, ?)',
            [$userId, $adminId, $reason, $level]
        );
    }

    public function listFlaggedUsers(): array
    {
        return $this->fetchAllRows(
            'SELECT u.id, u.name, u.email, u.status, f.reason, f.level, f.created_at
             FROM user_flags f
             JOIN users u ON u.id = f.user_id
             ORDER BY f.created_at DESC'
        );
    }

    public function createKycSubmission(int $userId, string $accountType, string $documentKind, string $filePath): int
    {
        return $this->insert(
            'INSERT INTO kyc_submissions (user_id, account_type, document_kind, file_path) VALUES (?, ?, ?, ?)',
            [$userId, $accountType, $documentKind, $filePath]
        );
    }

    public function listKycSubmissions(?string $status = null): array
    {
        $sql = 'SELECT ks.*, u.name, u.email, u.role
                FROM kyc_submissions ks
                JOIN users u ON u.id = ks.user_id';
        $params = [];
        if ($status !== null) {
            $sql .= ' WHERE ks.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY ks.created_at DESC';
        return $this->fetchAllRows($sql, $params);
    }

    public function listUserKycSubmissions(int $userId): array
    {
        return $this->fetchAllRows('SELECT * FROM kyc_submissions WHERE user_id = ? ORDER BY created_at DESC', [$userId]);
    }

    public function getKycSubmission(int $submissionId): ?array
    {
        return $this->fetch('SELECT * FROM kyc_submissions WHERE id = ?', [$submissionId]);
    }

    public function updateKycSubmission(int $submissionId, string $status, int $reviewerId): void
    {
        $this->execute(
            'UPDATE kyc_submissions SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?',
            [$status, $reviewerId, $submissionId]
        );
    }

    public function updateKycStatus(int $userId, string $status): void
    {
        $this->execute('UPDATE users SET kyc_status = ? WHERE id = ?', [$status, $userId]);
    }
}
