<?php

class EscrowService
{
    private EscrowRepository $escrow;
    private AuditService $audit;

    public function __construct()
    {
        $this->escrow = new EscrowRepository();
        $this->audit = new AuditService();
    }

    public function hasLock(int $milestoneId): bool
    {
        return $this->escrow->hasLockForMilestone($milestoneId);
    }

    public function lockFunds(array $contract, array $milestone): int
    {
        $id = $this->escrow->createTransaction([
            'contract_id' => $contract['id'],
            'milestone_id' => $milestone['id'],
            'amount' => $milestone['amount'],
            'currency' => $contract['currency'],
            'type' => 'lock',
            'status' => 'pending',
        ]);
        $this->audit->log((int) $_SESSION['user_id'], 'escrow_lock', 'escrow_transaction', $id, null, ['milestone_id' => $milestone['id'], 'amount' => $milestone['amount']]);
        return $id;
    }

    public function partialRelease(array $contract, array $milestone): int
    {
        $pct = (int) $contract['partial_release_pct'];
        if ($pct <= 0) {
            Response::error('Partial release is not enabled for this contract', 422);
        }
        $amount = round(((float) $milestone['amount']) * ($pct / 100), 2);
        $id = $this->escrow->createTransaction([
            'contract_id' => $contract['id'],
            'milestone_id' => $milestone['id'],
            'amount' => $amount,
            'currency' => $contract['currency'],
            'type' => 'partial_release',
            'status' => 'pending',
        ]);
        $this->audit->log((int) $_SESSION['user_id'], 'escrow_partial_release', 'escrow_transaction', $id, null, ['amount' => $amount]);
        return $id;
    }

    public function releaseForMilestone(array $contract, array $milestone, bool $auto = false): int
    {
        $transactions = $this->escrow->getContractTransactions((int) $contract['id']);
        $alreadyReleased = 0.0;
        foreach ($transactions as $tx) {
            if ((int) $tx['milestone_id'] === (int) $milestone['id'] && in_array($tx['type'], ['partial_release', 'release'], true)) {
                $alreadyReleased += (float) $tx['amount'];
            }
        }
        $amount = max(0, (float) $milestone['amount'] - $alreadyReleased);
        $id = $this->escrow->createTransaction([
            'contract_id' => $contract['id'],
            'milestone_id' => $milestone['id'],
            'amount' => $amount,
            'currency' => $contract['currency'],
            'type' => 'release',
            'status' => 'pending',
        ]);
        $this->audit->log((int) $_SESSION['user_id'], $auto ? 'escrow_auto_release' : 'escrow_release', 'escrow_transaction', $id, null, ['amount' => $amount]);
        $this->updateFee((int) $contract['client_id'], (int) $contract['freelancer_id'], $amount);
        return $id;
    }

    public function calculateFee(int $clientId, int $freelancerId): array
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM platform_fees WHERE client_id = ? AND freelancer_id = ?');
        $stmt->execute([$clientId, $freelancerId]);
        $row = $stmt->fetch();
        $lifetime = (float) ($row['lifetime_value'] ?? 0);
        $fee = 20.0;
        if ($lifetime >= 5000) {
            $fee = 5.0;
        } elseif ($lifetime >= 500) {
            $fee = 10.0;
        }
        if ($row) {
            $pdo->prepare('UPDATE platform_fees SET fee_percentage = ? WHERE id = ?')->execute([$fee, $row['id']]);
        } else {
            $pdo->prepare('INSERT INTO platform_fees (client_id, freelancer_id, lifetime_value, fee_percentage) VALUES (?, ?, 0, ?)')->execute([$clientId, $freelancerId, $fee]);
        }
        return ['lifetime_value' => $lifetime, 'fee_percentage' => $fee];
    }

    public function updateFee(int $clientId, int $freelancerId, float $releasedAmount): void
    {
        $data = $this->calculateFee($clientId, $freelancerId);
        $pdo = Database::getInstance()->getConnection();
        $pdo->prepare('UPDATE platform_fees SET lifetime_value = lifetime_value + ? WHERE client_id = ? AND freelancer_id = ?')->execute([$releasedAmount, $clientId, $freelancerId]);
        $this->calculateFee($clientId, $freelancerId);
    }

    public function calculateTax(array $contract, float $grossAmount): array
    {
        $pdo = Database::getInstance()->getConnection();
        $client = $pdo->prepare('SELECT country FROM users WHERE id = ?');
        $client->execute([$contract['client_id']]);
        $clientCountry = strtolower(($client->fetch()['country'] ?? ''));
        $freelancer = $pdo->prepare('SELECT country FROM users WHERE id = ?');
        $freelancer->execute([$contract['freelancer_id']]);
        $freelancerCountry = strtolower(($freelancer->fetch()['country'] ?? ''));

        $eu = ['germany', 'france', 'spain', 'italy', 'netherlands', 'belgium', 'sweden', 'poland', 'ireland', 'portugal', 'greece', 'austria'];
        $feeData = $this->calculateFee((int) $contract['client_id'], (int) $contract['freelancer_id']);
        $platformFee = round($grossAmount * ($feeData['fee_percentage'] / 100), 2);

        if (in_array($clientCountry, $eu, true) && in_array($freelancerCountry, $eu, true)) {
            $taxRate = 0.20;
        } elseif ($clientCountry === 'united states' || $clientCountry === 'usa' || $clientCountry === 'us') {
            $taxRate = 0.0;
        } elseif ($freelancerCountry === 'egypt') {
            $taxRate = 0.0;
        } else {
            $taxRate = 0.15;
        }
        $taxOnFee = round($platformFee * $taxRate, 2);
        return [
            'gross_amount' => $grossAmount,
            'platform_fee' => $platformFee,
            'tax_on_fee' => $taxOnFee,
            'freelancer_net' => round($grossAmount - $platformFee - $taxOnFee, 2),
            'client_total' => round($grossAmount + $taxOnFee, 2),
        ];
    }

    public function schedulePayout(): void
    {
        $cleared = $this->escrow->clearMaturePending();
        foreach ($cleared as $tx) {
            $this->audit->log((int) $_SESSION['user_id'], 'escrow_cleared', 'escrow_transaction', (int) $tx['id'], ['status' => 'pending'], ['status' => 'cleared']);
        }
    }

    public function settleCancellation(array $contract, array $milestone, ?float $completionPercentage = null): array
    {
        $deliverable = (new MilestoneRepository())->getLatestDeliverable((int) $milestone['id']);
        $clientShare = (float) $milestone['amount'];
        $freelancerShare = 0.0;
        if ($completionPercentage !== null) {
            $completionPercentage = max(0, min(100, $completionPercentage));
            $freelancerShare = round((float) $milestone['amount'] * ($completionPercentage / 100), 2);
            $clientShare = round((float) $milestone['amount'] - $freelancerShare, 2);
        } elseif ($deliverable && $milestone['status'] === 'submitted') {
            $clientShare = round((float) $milestone['amount'] * 0.5, 2);
            $freelancerShare = round((float) $milestone['amount'] * 0.5, 2);
        } elseif ($deliverable && $milestone['status'] === 'revision') {
            $clientShare = round((float) $milestone['amount'] * 0.7, 2);
            $freelancerShare = round((float) $milestone['amount'] * 0.3, 2);
        }
        $transactions = [];
        if ($clientShare > 0) {
            $transactions[] = $this->escrow->createTransaction([
                'contract_id' => $contract['id'],
                'milestone_id' => $milestone['id'],
                'amount' => $clientShare,
                'currency' => $contract['currency'],
                'type' => 'refund',
                'status' => 'completed',
            ]);
        }
        if ($freelancerShare > 0) {
            $transactions[] = $this->escrow->createTransaction([
                'contract_id' => $contract['id'],
                'milestone_id' => $milestone['id'],
                'amount' => $freelancerShare,
                'currency' => $contract['currency'],
                'type' => 'release',
                'status' => 'completed',
            ]);
        }
        $this->audit->log((int) $_SESSION['user_id'], 'cancellation_settlement', 'contract', (int) $contract['id'], null, ['client_share' => $clientShare, 'freelancer_share' => $freelancerShare, 'completion_percentage' => $completionPercentage]);
        return ['client_refund' => $clientShare, 'freelancer_payout' => $freelancerShare, 'transaction_ids' => $transactions];
    }

    public function getLedger(int $contractId): array
    {
        $transactions = $this->escrow->getContractTransactions($contractId);
        $rates = ['USD' => 1, 'EUR' => 1 / 0.92, 'GBP' => 1 / 0.79];
        $grouped = [];
        foreach ($transactions as $tx) {
            $currency = $tx['currency'];
            if (!isset($grouped[$currency])) {
                $grouped[$currency] = ['transactions' => [], 'total' => 0];
            }
            $grouped[$currency]['transactions'][] = array_merge($tx, [
                'usd_equivalent' => round(((float) $tx['amount']) * $rates[$currency], 2),
            ]);
            $grouped[$currency]['total'] += (float) $tx['amount'];
        }
        return $grouped;
    }

    public function balance(int $contractId): array
    {
        $this->schedulePayout();
        $transactions = $this->escrow->getContractTransactions($contractId);
        $pending = 0;
        $cleared = 0;
        foreach ($transactions as $tx) {
            if (in_array($tx['type'], ['release', 'partial_release'], true)) {
                if ($tx['status'] === 'pending') {
                    $pending += (float) $tx['amount'];
                }
                if ($tx['status'] === 'cleared' || $tx['status'] === 'completed') {
                    $cleared += (float) $tx['amount'];
                }
            }
        }
        return ['pending_balance' => $pending, 'cleared_balance' => $cleared];
    }
}
