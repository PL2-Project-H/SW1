<?php

class DisputeService
{
    private DisputeRepository $disputes;
    private ContractRepository $contracts;
    private EscrowService $escrow;
    private NotificationService $notifications;
    private AuditService $audit;
    private Database $database;

    public function __construct()
    {
        $this->disputes = new DisputeRepository();
        $this->contracts = new ContractRepository();
        $this->escrow = new EscrowService();
        $this->notifications = new NotificationService();
        $this->audit = new AuditService();
        $this->database = Database::getInstance();
    }

    public function assembleEvidence(int $disputeId): string
    {
        $dispute = $this->disputes->get($disputeId);
        if (!$dispute) {
            Response::error('Dispute not found', 404);
        }
        $pdo = $this->database->getConnection();
        $contractId = (int) $dispute['contract_id'];
        $messages = $this->disputes->listMessages($disputeId);
        $deliverables = $pdo->prepare('SELECT d.* FROM deliverables d JOIN milestones m ON m.id = d.milestone_id WHERE m.contract_id = ?');
        $deliverables->execute([$contractId]);
        $amendments = $pdo->prepare('SELECT * FROM contract_amendments WHERE contract_id = ?');
        $amendments->execute([$contractId]);
        $snapshots = $pdo->prepare('SELECT w.* FROM wip_snapshots w JOIN milestones m ON m.id = w.milestone_id WHERE m.contract_id = ?');
        $snapshots->execute([$contractId]);
        $auditLogs = $pdo->prepare('SELECT * FROM audit_logs WHERE entity_type IN ("contract", "milestone", "deliverable", "escrow_transaction") AND entity_id IN (?, ?, ?)');
        $auditLogs->execute([$contractId, $contractId, $contractId]);

        $payload = [
            'dispute' => $dispute,
            'messages' => $messages,
            'deliverables' => $deliverables->fetchAll(),
            'amendments' => $amendments->fetchAll(),
            'snapshots' => $snapshots->fetchAll(),
            'audit_logs' => $auditLogs->fetchAll(),
        ];
        $dir = dirname(__DIR__) . '/uploads/evidence';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir . '/dispute_' . $disputeId . '.json';
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT));
        $relative = 'uploads/evidence/dispute_' . $disputeId . '.json';
        $this->disputes->setEvidence($disputeId, $relative);
        return $relative;
    }

    public function assignArbitrator(int $disputeId, ?int $excludeAdminId = null): ?int
    {
        $arbitrators = $this->disputes->listArbitrators($excludeAdminId);
        if (!$arbitrators) {
            $admins = (new UserRepository())->listUsers(['role' => 'admin']);
            foreach ($admins as $admin) {
                $this->notifications->send((int) $admin['id'], 'dispute_unassigned', 'A dispute needs an arbitrator assignment.');
            }
            return null;
        }
        $adminId = (int) $arbitrators[0]['id'];
        $this->disputes->updateAssignment($disputeId, $adminId, 'in_mediation');
        $this->notifications->send($adminId, 'dispute_assigned', 'A new dispute has been assigned to you.');
        $this->audit->log((int) $_SESSION['user_id'], 'dispute_assigned', 'dispute', $disputeId, null, ['assigned_admin' => $adminId]);
        return $adminId;
    }

    public function executeVerdict(int $disputeId, int $clientPct, int $freelancerPct, string $verdict): void
    {
        if (($clientPct + $freelancerPct) !== 100) {
            Response::error('Split must sum to 100', 422);
        }
        $dispute = $this->disputes->get($disputeId);
        $contract = $this->contracts->getContract((int) $dispute['contract_id']);
        $milestone = $contract['milestones'][0] ?? null;
        $locked = $milestone ? (new EscrowRepository())->getLockedForMilestone((int) $milestone['id']) : (float) $contract['total_amount'];
        $clientShare = round($locked * ($clientPct / 100), 2);
        $freelancerShare = round($locked * ($freelancerPct / 100), 2);
        if ($clientShare > 0) {
            (new EscrowRepository())->createTransaction([
                'contract_id' => $contract['id'],
                'milestone_id' => $milestone['id'] ?? null,
                'amount' => $clientShare,
                'currency' => $contract['currency'],
                'type' => 'refund',
                'status' => 'completed',
            ]);
        }
        if ($freelancerShare > 0) {
            (new EscrowRepository())->createTransaction([
                'contract_id' => $contract['id'],
                'milestone_id' => $milestone['id'] ?? null,
                'amount' => $freelancerShare,
                'currency' => $contract['currency'],
                'type' => 'release',
                'status' => 'completed',
            ]);
        }
        $status = $dispute['status'] === 'appealed' ? 'final_resolved' : 'resolved';
        $this->disputes->updateVerdict($disputeId, $status, $verdict, $clientPct, $freelancerPct);
        $this->contracts->updateContractStatus((int) $contract['id'], $status === 'final_resolved' ? 'final_resolved' : 'disputed', true);
        $this->notifications->send((int) $contract['client_id'], 'dispute_verdict', 'A dispute verdict has been issued.');
        $this->notifications->send((int) $contract['freelancer_id'], 'dispute_verdict', 'A dispute verdict has been issued.');
        $this->audit->log((int) $_SESSION['user_id'], 'verdict', 'dispute', $disputeId, ['status' => $dispute['status']], ['status' => $status, 'verdict' => $verdict]);
        $this->audit->archiveCommunication('dispute', $disputeId);
        $this->audit->archiveCommunication('contract', (int) $contract['id']);
    }

    public function fileAppeal(int $disputeId, int $userId, string $reason): void
    {
        $dispute = $this->disputes->get($disputeId);
        if (!$dispute || $dispute['status'] !== 'resolved') {
            Response::error('Dispute cannot be appealed', 422);
        }
        $contract = $this->contracts->getContract((int) $dispute['contract_id']);
        if (empty($contract['verdict_at'])) {
            Response::error('Appeal window is unavailable until a verdict is issued', 422);
        }
        if (strtotime((string) $contract['verdict_at']) < strtotime('-48 hours')) {
            Response::error('Appeal window has expired', 422);
        }
        $this->disputes->updateVerdict($disputeId, 'appealed', $reason, (int) $dispute['client_pct'], (int) $dispute['freelancer_pct']);
        $this->assignArbitrator($disputeId, (int) $dispute['assigned_admin']);
        $this->audit->log($userId, 'appeal', 'dispute', $disputeId, ['status' => 'resolved'], ['status' => 'appealed', 'reason' => $reason]);
    }
}
