<?php

class AuditService
{
    private const ARCHIVE_PREFIX = 'ENC1:';

    private AuditRepository $auditRepository;
    private Database $database;

    public function __construct()
    {
        $this->auditRepository = new AuditRepository();
        $this->database = Database::getInstance();
    }

    public function log(?int $userId, string $action, string $entityType, ?int $entityId, $oldValue = null, $newValue = null): void
    {
        $this->auditRepository->log($userId, $action, $entityType, $entityId, $oldValue, $newValue, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    public static function decodeArchivedMessage(string $stored): string
    {
        $key = hash('sha256', $_ENV['MESSAGE_ARCHIVE_KEY'] ?? 'development-message-archive-key', true);
        if (str_starts_with($stored, self::ARCHIVE_PREFIX)) {
            $raw = base64_decode(substr($stored, strlen(self::ARCHIVE_PREFIX)), true);
            if ($raw !== false && strlen($raw) > 16) {
                $iv = substr($raw, 0, 16);
                $cipher = substr($raw, 16);
                $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                if ($plain !== false) {
                    return $plain;
                }
            }

            return '[archive: could not decrypt]';
        }
        $legacy = base64_decode($stored, true);

        return $legacy !== false ? $legacy : $stored;
    }

    public function archiveCommunication(string $entityType, int $entityId): void
    {
        $pdo = $this->database->getConnection();
        if ($entityType === 'dispute') {
            $stmt = $pdo->prepare('SELECT id, message FROM dispute_messages WHERE dispute_id = ? AND archived = 0');
            $stmt->execute([$entityId]);
            foreach ($stmt->fetchAll() as $message) {
                $sealed = $this->sealForArchive((string) $message['message']);
                $pdo->prepare('UPDATE dispute_messages SET message = ?, archived = 1 WHERE id = ?')->execute([$sealed, $message['id']]);
            }

            return;
        }
        if ($entityType === 'contract') {
            $stmt = $pdo->prepare('SELECT id, message FROM contract_messages WHERE contract_id = ? AND archived = 0');
            $stmt->execute([$entityId]);
            foreach ($stmt->fetchAll() as $message) {
                $sealed = $this->sealForArchive((string) $message['message']);
                $pdo->prepare('UPDATE contract_messages SET message = ?, archived = 1 WHERE id = ?')->execute([$sealed, $message['id']]);
            }
        }
    }

    private function sealForArchive(string $plain): string
    {
        $key = hash('sha256', $_ENV['MESSAGE_ARCHIVE_KEY'] ?? 'development-message-archive-key', true);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return self::ARCHIVE_PREFIX . base64_encode($plain);
        }

        return self::ARCHIVE_PREFIX . base64_encode($iv . $cipher);
    }
}
