<?php

class BidService
{
    private BidRepository $bids;
    private AuditService $audit;

    public function __construct()
    {
        $this->bids = new BidRepository();
        $this->audit = new AuditService();
    }

    public function trackVersion(int $jobId, int $freelancerId, float $amount, string $proposalText, ?string $expiresAt): int
    {
        $latest = $this->bids->getLatestVersion($jobId, $freelancerId);
        $version = $latest ? ((int) $latest['version']) + 1 : 1;
        $bidId = $this->bids->createBid([
            'job_id' => $jobId,
            'freelancer_id' => $freelancerId,
            'amount' => $amount,
            'proposal_text' => $proposalText,
            'version' => $version,
            'expires_at' => $expiresAt ?: date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);
        $this->audit->log($freelancerId, 'bid_submitted', 'bid', $bidId, $latest, ['amount' => $amount, 'proposal_text' => $proposalText, 'version' => $version]);
        return $bidId;
    }

    public function handleExpiry(): void
    {
        $expired = $this->bids->expireOldBids();
        foreach ($expired as $bid) {
            $this->audit->log((int) $bid['freelancer_id'], 'bid_expired', 'bid', (int) $bid['id'], ['status' => 'pending'], ['status' => 'expired']);
        }
    }

    public function withdraw(int $bidId, int $freelancerId): void
    {
        $bid = $this->bids->find($bidId);
        if (!$bid || (int) $bid['freelancer_id'] !== $freelancerId || $bid['status'] !== 'pending') {
            Response::error('Bid cannot be withdrawn', 400);
        }
        $this->bids->updateStatus($bidId, 'withdrawn');
        $this->audit->log($freelancerId, 'bid_withdrawn', 'bid', $bidId, ['status' => 'pending'], ['status' => 'withdrawn']);
    }

    public function filterBids(int $jobId, array $filters): array
    {
        $this->handleExpiry();
        $bids = $this->bids->listJobBids($jobId);
        $latestByFreelancer = [];
        foreach ($bids as $bid) {
            $freelancerId = (int) $bid['freelancer_id'];
            if (!isset($latestByFreelancer[$freelancerId])) {
                $latestByFreelancer[$freelancerId] = $bid;
            }
        }
        $rows = array_values($latestByFreelancer);
        $rows = array_filter($rows, function ($bid) use ($filters) {
            if (!empty($filters['min_success_rate']) && (float) $bid['composite_score'] < (float) $filters['min_success_rate']) {
                return false;
            }
            if (!empty($filters['cert_required']) && !(int) $bid['is_verified']) {
                return false;
            }
            if (!empty($filters['max_amount']) && (float) $bid['amount'] > (float) $filters['max_amount']) {
                return false;
            }
            return true;
        });
        $sort = $filters['sort_by'] ?? 'reputation_desc';
        usort($rows, function ($a, $b) use ($sort) {
            return match ($sort) {
                'price_asc' => $a['amount'] <=> $b['amount'],
                'price_desc' => $b['amount'] <=> $a['amount'],
                default => $b['composite_score'] <=> $a['composite_score'],
            };
        });

        foreach ($rows as &$row) {
            $history = array_values(array_filter($bids, fn ($item) => (int) $item['freelancer_id'] === (int) $row['freelancer_id']));
            $row['version_history'] = array_map(function ($item) {
                return [
                    'id' => $item['id'],
                    'version' => $item['version'],
                    'amount' => $item['amount'],
                    'proposal_text' => $item['proposal_text'],
                    'status' => $item['status'],
                ];
            }, $history);
        }

        return array_values($rows);
    }
}
