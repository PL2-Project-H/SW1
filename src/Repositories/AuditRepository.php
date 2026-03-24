<?php

class AuditRepository extends BaseRepository
{
    public function log(?int $userId, string $action, string $entityType, ?int $entityId, $oldValue, $newValue, ?string $ipAddress): int
    {
        return $this->insert(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $action,
                $entityType,
                $entityId,
                $oldValue !== null ? json_encode($oldValue) : null,
                $newValue !== null ? json_encode($newValue) : null,
                $ipAddress,
            ]
        );
    }

    public function listLogs(array $filters = []): array
    {
        $sql = 'SELECT * FROM audit_logs WHERE 1=1';
        $params = [];
        if (!empty($filters['action'])) {
            $sql .= ' AND action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['entity_type'])) {
            $sql .= ' AND entity_type = ?';
            $params[] = $filters['entity_type'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= ' AND created_at >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND created_at <= ?';
            $params[] = $filters['date_to'];
        }
        return $this->fetchAllRows($sql . ' ORDER BY created_at DESC LIMIT 500', $params);
    }
}
