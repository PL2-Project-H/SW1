<?php

class MilestoneRepository extends BaseRepository
{
    public function createMilestone(array $data): int
    {
        return $this->insert(
            'INSERT INTO milestones (contract_id, title, amount, order_index, status, due_date, dependency_milestone_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['contract_id'],
                $data['title'],
                $data['amount'],
                $data['order_index'],
                $data['status'] ?? 'locked',
                $data['due_date'],
                $data['dependency_milestone_id'] ?? null,
            ]
        );
    }

    public function getMilestone(int $milestoneId): ?array
    {
        $milestone = $this->fetch('SELECT * FROM milestones WHERE id = ?', [$milestoneId]);
        if (!$milestone) {
            return null;
        }
        $milestone['deliverables'] = $this->fetchAllRows('SELECT * FROM deliverables WHERE milestone_id = ? ORDER BY submitted_at DESC', [$milestoneId]);
        $milestone['snapshots'] = $this->fetchAllRows('SELECT * FROM wip_snapshots WHERE milestone_id = ? ORDER BY captured_at DESC', [$milestoneId]);
        $milestone['qa_submission'] = $this->fetch('SELECT * FROM qa_submissions WHERE milestone_id = ?', [$milestoneId]);
        return $milestone;
    }

    public function listByContract(int $contractId): array
    {
        return $this->fetchAllRows('SELECT * FROM milestones WHERE contract_id = ? ORDER BY order_index', [$contractId]);
    }

    public function updateStatus(int $milestoneId, string $status): void
    {
        $suffix = '';
        if ($status === 'in_progress') {
            $suffix = ', started_at = NOW()';
        }
        if (in_array($status, ['approved', 'auto_approved'], true)) {
            $suffix = ', approved_at = NOW()';
        }
        $this->execute("UPDATE milestones SET status = ?{$suffix} WHERE id = ?", [$status, $milestoneId]);
    }

    public function createDeliverable(int $milestoneId, string $filePath, int $freeRevisionsAllowed): int
    {
        return $this->insert(
            'INSERT INTO deliverables (milestone_id, file_path, free_revisions_allowed) VALUES (?, ?, ?)',
            [$milestoneId, $filePath, $freeRevisionsAllowed]
        );
    }

    public function getLatestDeliverable(int $milestoneId): ?array
    {
        return $this->fetch('SELECT * FROM deliverables WHERE milestone_id = ? ORDER BY submitted_at DESC LIMIT 1', [$milestoneId]);
    }

    public function updateDeliverableForRevision(int $deliverableId): void
    {
        $this->execute('UPDATE deliverables SET revision_count = revision_count + 1, status = "revision_requested" WHERE id = ?', [$deliverableId]);
    }

    public function markPaidRevision(int $deliverableId, float $fee): void
    {
        $this->execute(
            'UPDATE deliverables SET revision_count = revision_count + 1, status = "revision_requested", paid_revision_required = 1, paid_revision_fee = ? WHERE id = ?',
            [$fee, $deliverableId]
        );
    }

    public function approveDeliverable(int $deliverableId): void
    {
        $this->execute('UPDATE deliverables SET status = "approved", client_approved_at = NOW() WHERE id = ?', [$deliverableId]);
    }

    public function confirmDeliverable(int $deliverableId): void
    {
        $this->execute('UPDATE deliverables SET freelancer_confirmed_at = NOW() WHERE id = ?', [$deliverableId]);
    }

    public function addSnapshot(int $milestoneId, string $filePath): int
    {
        return $this->insert('INSERT INTO wip_snapshots (milestone_id, file_path) VALUES (?, ?)', [$milestoneId, $filePath]);
    }

    public function saveQaChecklist(int $milestoneId, int $freelancerId, array $checklist): void
    {
        $existing = $this->fetch('SELECT id FROM qa_submissions WHERE milestone_id = ?', [$milestoneId]);
        if ($existing) {
            $this->execute('UPDATE qa_submissions SET checklist_json = ?, submitted_at = NOW() WHERE milestone_id = ?', [json_encode($checklist), $milestoneId]);
            return;
        }
        $this->insert('INSERT INTO qa_submissions (milestone_id, freelancer_id, checklist_json) VALUES (?, ?, ?)', [$milestoneId, $freelancerId, json_encode($checklist)]);
    }

    public function listSubmittedPastWindow(): array
    {
        return $this->fetchAllRows(
            'SELECT m.*, d.id AS deliverable_id, d.submitted_at
             FROM milestones m
             JOIN deliverables d ON d.milestone_id = m.id
             WHERE m.status = "submitted" AND d.submitted_at < DATE_SUB(NOW(), INTERVAL 5 DAY)'
        );
    }

    public function listInProgressForDeadlineChecks(): array
    {
        return $this->fetchAllRows('SELECT * FROM milestones WHERE status = "in_progress"');
    }
}
