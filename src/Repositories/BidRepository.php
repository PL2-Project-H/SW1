<?php

class BidRepository extends BaseRepository
{
    public function createBid(array $data): int
    {
        return $this->insert(
            'INSERT INTO bids (job_id, freelancer_id, amount, proposal_text, version, status, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'],
                $data['freelancer_id'],
                $data['amount'],
                $data['proposal_text'],
                $data['version'],
                $data['status'] ?? 'pending',
                $data['expires_at'],
            ]
        );
    }

    public function getLatestVersion(int $jobId, int $freelancerId): ?array
    {
        return $this->fetch('SELECT * FROM bids WHERE job_id = ? AND freelancer_id = ? ORDER BY version DESC LIMIT 1', [$jobId, $freelancerId]);
    }

    public function listJobBids(int $jobId): array
    {
        return $this->fetchAllRows(
            'SELECT b.*, u.name AS freelancer_name, fp.niche, fp.is_verified, rs.composite_score
             FROM bids b
             JOIN users u ON u.id = b.freelancer_id
             LEFT JOIN freelancer_profiles fp ON fp.user_id = b.freelancer_id
             LEFT JOIN reputation_scores rs ON rs.user_id = b.freelancer_id
             WHERE b.job_id = ?
             ORDER BY b.version DESC, b.submitted_at DESC',
            [$jobId]
        );
    }

    public function listFreelancerBids(int $freelancerId): array
    {
        return $this->fetchAllRows(
            'SELECT b.*, jp.title, jp.niche, jp.client_id
             FROM bids b
             JOIN job_posts jp ON jp.id = b.job_id
             WHERE b.freelancer_id = ?
             ORDER BY b.submitted_at DESC',
            [$freelancerId]
        );
    }

    public function find(int $bidId): ?array
    {
        return $this->fetch('SELECT * FROM bids WHERE id = ?', [$bidId]);
    }

    public function updateStatus(int $bidId, string $status): void
    {
        $this->execute('UPDATE bids SET status = ? WHERE id = ?', [$status, $bidId]);
    }

    public function expireOldBids(): array
    {
        $expired = $this->fetchAllRows('SELECT * FROM bids WHERE status = "pending" AND expires_at < NOW()');
        $this->execute('UPDATE bids SET status = "expired" WHERE status = "pending" AND expires_at < NOW()');
        return $expired;
    }
}
