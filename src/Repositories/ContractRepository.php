<?php

class ContractRepository extends BaseRepository
{
    public function createNda(int $jobId, int $freelancerId, string $content): int
    {
        return $this->insert('INSERT INTO ndas (job_id, freelancer_id, content) VALUES (?, ?, ?)', [$jobId, $freelancerId, $content]);
    }

    public function getNdaForJob(int $jobId, int $freelancerId): ?array
    {
        return $this->fetch('SELECT * FROM ndas WHERE job_id = ? AND freelancer_id = ?', [$jobId, $freelancerId]);
    }

    public function signNda(int $ndaId): void
    {
        $this->execute('UPDATE ndas SET freelancer_signed_at = NOW() WHERE id = ?', [$ndaId]);
    }

    public function signNdaByClient(int $ndaId): void
    {
        $this->execute('UPDATE ndas SET client_signed_at = NOW() WHERE id = ?', [$ndaId]);
    }

    public function createContract(array $data): int
    {
        return $this->insert(
            'INSERT INTO contracts (job_id, client_id, freelancer_id, total_amount, status, scope_text, free_revisions_per_milestone, partial_release_pct, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'],
                $data['client_id'],
                $data['freelancer_id'],
                $data['total_amount'],
                $data['status'] ?? 'pending_nda',
                $data['scope_text'],
                $data['free_revisions_per_milestone'] ?? 2,
                $data['partial_release_pct'] ?? 0,
                $data['currency'] ?? 'USD',
            ]
        );
    }

    public function activateContract(int $contractId): void
    {
        $this->execute('UPDATE contracts SET status = "active", started_at = NOW() WHERE id = ?', [$contractId]);
    }

    public function getContract(int $contractId): ?array
    {
        $contract = $this->fetch('SELECT * FROM contracts WHERE id = ?', [$contractId]);
        if (!$contract) {
            return null;
        }
        $contract['milestones'] = $this->fetchAllRows('SELECT * FROM milestones WHERE contract_id = ? ORDER BY order_index', [$contractId]);
        $contract['amendments'] = $this->fetchAllRows('SELECT * FROM contract_amendments WHERE contract_id = ? ORDER BY created_at DESC', [$contractId]);
        
        $nda = $this->fetch('SELECT content, client_signed_at, freelancer_signed_at FROM ndas WHERE job_id = ? ORDER BY id DESC LIMIT 1', [$contract['job_id']]);
        if ($nda) {
            $contract['nda_content'] = $nda['content'];
            $contract['nda_client_signed'] = !empty($nda['client_signed_at']);
            $contract['nda_freelancer_signed'] = !empty($nda['freelancer_signed_at']);
        }
        return $contract;
    }

    public function listActiveContracts(int $userId): array
    {
        return $this->fetchAllRows(
            'SELECT * FROM contracts WHERE status IN ("pending_nda", "active", "disputed", "appealed") AND (client_id = ? OR freelancer_id = ?) ORDER BY started_at DESC',
            [$userId, $userId]
        );
    }

    public function addAmendment(int $contractId, int $proposedBy, string $changeDescription): int
    {
        return $this->insert(
            'INSERT INTO contract_amendments (contract_id, proposed_by, change_description, requester_approved_at) VALUES (?, ?, ?, NOW())',
            [$contractId, $proposedBy, $changeDescription]
        );
    }

    public function getAmendment(int $amendmentId): ?array
    {
        return $this->fetch('SELECT * FROM contract_amendments WHERE id = ?', [$amendmentId]);
    }

    public function updateAmendmentStatus(int $amendmentId, string $status, ?string $approvalColumn = null, ?int $rejectedBy = null): void
    {
        if ($status === 'rejected') {
            $this->execute('UPDATE contract_amendments SET status = ?, rejected_by = ? WHERE id = ?', [$status, $rejectedBy, $amendmentId]);
            return;
        }
        if ($approvalColumn !== null) {
            $this->execute("UPDATE contract_amendments SET {$approvalColumn} = NOW(), status = ? WHERE id = ?", [$status, $amendmentId]);
            return;
        }

        $this->execute('UPDATE contract_amendments SET status = ? WHERE id = ?', [$status, $amendmentId]);
    }

    public function updateContractStatus(int $contractId, string $status, bool $stampVerdictAt = false): void
    {
        if ($stampVerdictAt) {
            $this->execute('UPDATE contracts SET status = ?, verdict_at = NOW() WHERE id = ?', [$status, $contractId]);
            return;
        }
        $this->execute('UPDATE contracts SET status = ? WHERE id = ?', [$status, $contractId]);
    }

    public function updateContractAmount(int $contractId, float $totalAmount): void
    {
        $this->execute('UPDATE contracts SET total_amount = ? WHERE id = ?', [$totalAmount, $contractId]);
    }

    public function listContractMessages(int $contractId, bool $includeArchived = false): array
    {
        $sql = 'SELECT cm.*, u.name AS sender_name
                FROM contract_messages cm
                JOIN users u ON u.id = cm.sender_id
                WHERE cm.contract_id = ?';
        if (!$includeArchived) {
            $sql .= ' AND cm.archived = 0';
        }
        $sql .= ' ORDER BY cm.sent_at ASC';

        return $this->fetchAllRows($sql, [$contractId]);
    }

    public function addContractMessage(int $contractId, int $senderId, string $message): int
    {
        return $this->insert(
            'INSERT INTO contract_messages (contract_id, sender_id, message) VALUES (?, ?, ?)',
            [$contractId, $senderId, $message]
        );
    }
}
