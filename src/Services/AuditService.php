<?php

class AuditService
{
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

    public function archiveCommunication(string $entityType, int $entityId): void
    {
        $pdo = $this->database->getConnection();
        if ($entityType === 'dispute') {
            $stmt = $pdo->prepare('SELECT id, message FROM dispute_messages WHERE dispute_id = ? AND archived = 0');
            $stmt->execute([$entityId]);
            foreach ($stmt->fetchAll() as $message) {
                $encoded = base64_encode($message['message']);
                $pdo->prepare('UPDATE dispute_messages SET message = ?, archived = 1 WHERE id = ?')->execute([$encoded, $message['id']]);
            }
            return;
        }
        if ($entityType === 'contract') {
            $stmt = $pdo->prepare('SELECT id, message FROM contract_messages WHERE contract_id = ? AND archived = 0');
            $stmt->execute([$entityId]);
            foreach ($stmt->fetchAll() as $message) {
                $encoded = base64_encode($message['message']);
                $pdo->prepare('UPDATE contract_messages SET message = ?, archived = 1 WHERE id = ?')->execute([$encoded, $message['id']]);
            }
        }
    }
}
