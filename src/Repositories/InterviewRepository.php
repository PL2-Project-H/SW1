<?php

class InterviewRepository extends BaseRepository
{
    public function create(array $data): int
    {
        return $this->insert(
            'INSERT INTO interviews (job_id, client_id, freelancer_id, scheduled_at, timezone, proposed_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'],
                $data['client_id'],
                $data['freelancer_id'],
                $data['scheduled_at'],
                $data['timezone'],
                $data['proposed_by'],
                $data['notes'],
            ]
        );
    }

    public function updateForClient(int $interviewId, int $clientId, string $status, ?string $notes): void
    {
        $this->execute(
            'UPDATE interviews SET status = ?, notes = ? WHERE id = ? AND client_id = ?',
            [$status, $notes, $interviewId, $clientId]
        );
    }

    public function listForClient(int $clientId, ?int $jobId = null): array
    {
        $sql = 'SELECT * FROM interviews WHERE client_id = ?';
        $params = [$clientId];
        if ($jobId !== null) {
            $sql .= ' AND job_id = ?';
            $params[] = $jobId;
        }
        $sql .= ' ORDER BY scheduled_at DESC';
        return $this->fetchAllRows($sql, $params);
    }

    public function listForFreelancer(int $freelancerId, ?int $jobId = null): array
    {
        $sql = 'SELECT * FROM interviews WHERE freelancer_id = ?';
        $params = [$freelancerId];
        if ($jobId !== null) {
            $sql .= ' AND job_id = ?';
            $params[] = $jobId;
        }
        $sql .= ' ORDER BY COALESCE(counter_scheduled_at, scheduled_at) DESC';
        return $this->fetchAllRows($sql, $params);
    }

    public function updateForFreelancer(int $interviewId, int $freelancerId, string $status, ?string $scheduledAt, ?string $timezone, ?string $notes): void
    {
        if ($status === 'countered') {
            $this->execute(
                'UPDATE interviews SET status = ?, counter_scheduled_at = ?, counter_timezone = ?, notes = ? WHERE id = ? AND freelancer_id = ?',
                [$status, $scheduledAt, $timezone, $notes, $interviewId, $freelancerId]
            );
            return;
        }
        $this->execute(
            'UPDATE interviews SET status = ?, notes = ? WHERE id = ? AND freelancer_id = ?',
            [$status, $notes, $interviewId, $freelancerId]
        );
    }

    public function clientAcceptFreelancerCounter(int $interviewId, int $clientId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE interviews SET status = "accepted",
                scheduled_at = COALESCE(counter_scheduled_at, scheduled_at),
                timezone = COALESCE(counter_timezone, timezone)
             WHERE id = ? AND client_id = ? AND status = "countered"'
        );
        $stmt->execute([$interviewId, $clientId]);
        if ($stmt->rowCount() < 1) {
            Response::error('Interview not found or not awaiting client confirmation', 422);
        }
    }
}
