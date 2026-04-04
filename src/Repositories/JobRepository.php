<?php

class JobRepository extends BaseRepository
{
    public function createJob(int $clientId, array $data): int
    {
        $status = ($data['visibility'] ?? 'public') === 'invitation' ? 'private' : 'open';
        return $this->insert(
            'INSERT INTO job_posts (client_id, title, description, niche, budget, deadline, status, visibility, niche_metadata, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $clientId,
                $data['title'],
                $data['description'],
                $data['niche'],
                $data['budget'],
                $data['deadline'],
                $status,
                $data['visibility'] ?? 'public',
                json_encode($data['niche_metadata'] ?? []),
                $data['currency'] ?? 'USD',
            ]
        );
    }

    public function getJob(int $jobId): ?array
    {
        $job = $this->fetch(
            'SELECT jp.*, u.name AS client_name, u.country AS client_country
             FROM job_posts jp
             JOIN users u ON u.id = jp.client_id
             WHERE jp.id = ?',
            [$jobId]
        );
        if ($job) {
            $job['niche_metadata'] = $job['niche_metadata'] ? json_decode($job['niche_metadata'], true) : [];
        }
        return $job;
    }

    public function listJobs(array $filters = [], ?int $viewerId = null): array
    {
        $sql = 'SELECT jp.*, u.name AS client_name
                FROM job_posts jp
                JOIN users u ON u.id = jp.client_id
                WHERE jp.status IN ("open", "private", "awarded")';
        $params = [];
        if (!empty($filters['client_id'])) {
            $sql .= ' AND jp.client_id = ?';
            $params[] = $filters['client_id'];
        }
        if (!empty($filters['niche'])) {
            $sql .= ' AND jp.niche = ?';
            $params[] = $filters['niche'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= ' AND (jp.title LIKE ? OR jp.description LIKE ?)';
            $params[] = '%' . $filters['keyword'] . '%';
            $params[] = '%' . $filters['keyword'] . '%';
        }
        if (!empty($filters['max_budget'])) {
            $sql .= ' AND jp.budget <= ?';
            $params[] = $filters['max_budget'];
        }
        $rows = $this->fetchAllRows($sql . ' ORDER BY jp.created_at DESC', $params);
        return array_values(array_filter($rows, function ($row) use ($viewerId) {
            if ($row['visibility'] === 'public' || $viewerId === null || (int) $row['client_id'] === $viewerId) {
                return true;
            }
            $invite = $this->fetch('SELECT id FROM job_invitations WHERE job_id = ? AND freelancer_id = ?', [$row['id'], $viewerId]);
            return (bool) $invite;
        }));
    }

    public function inviteFreelancer(int $jobId, int $freelancerId): void
    {
        $this->execute('INSERT IGNORE INTO job_invitations (job_id, freelancer_id) VALUES (?, ?)', [$jobId, $freelancerId]);
    }

    public function isInvited(int $jobId, int $freelancerId): bool
    {
        return (bool) $this->fetch('SELECT id FROM job_invitations WHERE job_id = ? AND freelancer_id = ?', [$jobId, $freelancerId]);
    }

    public function markAwarded(int $jobId): void
    {
        $this->execute('UPDATE job_posts SET status = ? WHERE id = ?', ['awarded', $jobId]);
    }
}
