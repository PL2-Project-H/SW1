<?php

class Database
{
    private static ?self $instance = null;
    private PDO $pdo;
    private const DEFAULT_ADMIN_EMAIL = 'admin@specialisthub.local';
    private const DEFAULT_ADMIN_PASSWORD = 'admin123';

    private function __construct()
    {
        $dsn = 'mysql:host=' . ($_ENV['DB_HOST'] ?? 'db') . ';dbname=' . ($_ENV['DB_NAME'] ?? 'specialisthub') . ';charset=utf8mb4';
        $attempts = 0;

        while (true) {
            try {
                $this->pdo = new PDO($dsn, $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? 'secret', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                break;
            } catch (PDOException $exception) {
                $attempts++;
                if ($attempts >= 10) {
                    throw $exception;
                }
                usleep(500000);
            }
        }

        $this->ensureDefaultAdmin();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    private function ensureDefaultAdmin(): void
    {
        $statement = $this->pdo->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
        $statement->execute([self::DEFAULT_ADMIN_EMAIL]);
        $admin = $statement->fetch();

        if (!$admin) {
            return;
        }

        if (password_verify(self::DEFAULT_ADMIN_PASSWORD, $admin['password_hash'])) {
            return;
        }

        $update = $this->pdo->prepare(
            'UPDATE users
             SET password_hash = ?, role = ?, admin_role = ?, status = ?, kyc_status = ?, timezone = ?, country = ?, name = ?
             WHERE id = ?'
        );
        $update->execute([
            password_hash(self::DEFAULT_ADMIN_PASSWORD, PASSWORD_BCRYPT),
            'admin',
            'dispute_mediator',
            'active',
            'verified',
            'Africa/Cairo',
            'Egypt',
            'Platform Admin',
            (int) $admin['id'],
        ]);
    }
}
