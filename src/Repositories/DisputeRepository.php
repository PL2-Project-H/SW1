<?php

class DisputeRepository extends BaseRepository
{
    public function create(array $data): int
    {
        return $this->insert(
            'INSERT INTO disputes (contract_id, filed_by, reason, status, evidence_path, assigned_admin) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['contract_id'],
                $data['filed_by'],
                $data['reason'],
                $data['status'] ?? 'open',
                $data['evidence_path'] ?? null,
                $data['assigned_admin'] ?? null,
            ]
        );
    }

    public function get(int $id): ?array
    {
        return $this->fetch('SELECT * FROM disputes WHERE id = ?', [$id]);
    }

    public function listMine(int $userId): array
    {
        return $this->fetchAllRows(
            'SELECT DISTINCT d.*
             FROM disputes d
             JOIN contracts c ON c.id = d.contract_id
             WHERE d.filed_by = ? OR c.client_id = ? OR c.freelancer_id = ? OR d.assigned_admin = ?
             ORDER BY d.created_at DESC',
            [$userId, $userId, $userId, $userId]
        );
    }

    public function addMessage(int $disputeId, int $senderId, string $message): int
    {
        return $this->insert('INSERT INTO dispute_messages (dispute_id, sender_id, message) VALUES (?, ?, ?)', [$disputeId, $senderId, $message]);
    }

    public function listMessages(int $disputeId): array
    {
        return $this->fetchAllRows('SELECT * FROM dispute_messages WHERE dispute_id = ? ORDER BY sent_at ASC', [$disputeId]);
    }

    public function updateAssignment(int $disputeId, ?int $adminId, string $status): void
    {
        $this->execute('UPDATE disputes SET assigned_admin = ?, status = ? WHERE id = ?', [$adminId, $status, $disputeId]);
    }

    public function updateVerdict(int $disputeId, string $status, string $verdict, int $clientPct, int $freelancerPct): void
    {
        $this->execute(
            'UPDATE disputes SET status = ?, verdict = ?, client_pct = ?, freelancer_pct = ? WHERE id = ?',
            [$status, $verdict, $clientPct, $freelancerPct, $disputeId]
        );
    }

    public function setEvidence(int $disputeId, string $path): void
    {
        $this->execute('UPDATE disputes SET evidence_path = ? WHERE id = ?', [$path, $disputeId]);
    }

    public function listArbitrators(?int $excludeAdminId = null): array
    {
        $sql = 'SELECT u.id, u.name, COUNT(d.id) AS open_disputes
                FROM users u
                LEFT JOIN disputes d ON d.assigned_admin = u.id AND d.status IN ("open", "in_mediation", "appealed")
                WHERE u.role = "admin" AND u.admin_role = "dispute_mediator"';
        $params = [];
        if ($excludeAdminId) {
            $sql .= ' AND u.id != ?';
            $params[] = $excludeAdminId;
        }
        $sql .= ' GROUP BY u.id ORDER BY open_disputes ASC, u.id ASC';
        return $this->fetchAllRows($sql, $params);
    }

    public function archivedMessages(array $filters = []): array
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $limit = max(1, min((int) ($filters['limit'] ?? 20), 100));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $rows = $this->fetchAllRows(
            'SELECT "dispute" AS source_type, id, dispute_id AS parent_id, sender_id, message, sent_at
             FROM dispute_messages
             WHERE archived = 1
             UNION ALL
             SELECT "contract" AS source_type, id, contract_id AS parent_id, sender_id, message, sent_at
             FROM contract_messages
             WHERE archived = 1
             ORDER BY sent_at DESC'
        );

        $rows = array_values(array_filter($rows, function ($row) use ($search) {
            if ($search === '') {
                return true;
            }
            $plain = AuditService::decodeArchivedMessage((string) $row['message']);

            return str_contains(strtolower($plain), strtolower($search));
        }));

        $total = count($rows);
        $slice = array_slice($rows, $offset, $limit);
        $slice = array_map(function ($row) {
            $row['decoded_message'] = AuditService::decodeArchivedMessage((string) $row['message']);

            return $row;
        }, $slice);

        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'items' => $slice,
        ];
    }
}
