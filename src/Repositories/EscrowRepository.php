<?php

class EscrowRepository extends BaseRepository
{
    public function createTransaction(array $data): int
    {
        return $this->insert(
            'INSERT INTO escrow_transactions (contract_id, milestone_id, amount, currency, type, status, cleared_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['contract_id'],
                $data['milestone_id'] ?? null,
                $data['amount'],
                $data['currency'] ?? 'USD',
                $data['type'],
                $data['status'] ?? 'pending',
                $data['cleared_at'] ?? null,
            ]
        );
    }

    public function hasLockForMilestone(int $milestoneId): bool
    {
        return (bool) $this->fetch('SELECT id FROM escrow_transactions WHERE milestone_id = ? AND type = "lock"', [$milestoneId]);
    }

    public function getContractTransactions(int $contractId): array
    {
        return $this->fetchAllRows('SELECT * FROM escrow_transactions WHERE contract_id = ? ORDER BY created_at DESC', [$contractId]);
    }

    public function clearMaturePending(): array
    {
        $rows = $this->fetchAllRows('SELECT * FROM escrow_transactions WHERE status = "pending" AND type IN ("release", "partial_release") AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)');
        $this->execute('UPDATE escrow_transactions SET status = "cleared", cleared_at = NOW() WHERE status = "pending" AND type IN ("release", "partial_release") AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)');
        return $rows;
    }

    public function getLockedForMilestone(int $milestoneId): float
    {
        $row = $this->fetch('SELECT COALESCE(SUM(amount), 0) AS total FROM escrow_transactions WHERE milestone_id = ? AND type = "lock"', [$milestoneId]);
        return (float) ($row['total'] ?? 0);
    }
}
