<?php

class ContractService
{
    private ContractRepository $contracts;
    private JobRepository $jobs;
    private UserRepository $users;
    private AuditService $audit;
    private NotificationService $notifications;
    private MilestoneRepository $milestones;
    private EscrowService $escrow;

    public function __construct()
    {
        $this->contracts = new ContractRepository();
        $this->jobs = new JobRepository();
        $this->users = new UserRepository();
        $this->audit = new AuditService();
        $this->notifications = new NotificationService();
        $this->milestones = new MilestoneRepository();
        $this->escrow = new EscrowService();
    }

    public function generateNda(int $jobId, int $freelancerId): int
    {
        $job = $this->jobs->getJob($jobId);
        $client = $this->users->findById((int) $job['client_id']);
        $freelancer = $this->users->findById($freelancerId);
        $content = sprintf(
            'This Non-Disclosure Agreement is entered into on %s between %s (%s) and %s (%s) regarding the project: %s. Both parties agree not to disclose any confidential information shared during this engagement.',
            gmdate('Y-m-d'),
            $client['name'],
            $client['country'],
            $freelancer['name'],
            $freelancer['country'],
            $job['title']
        );
        $ndaId = $this->contracts->createNda($jobId, $freelancerId, $content);
        $this->audit->log((int) $job['client_id'], 'nda_generated', 'nda', $ndaId, null, ['job_id' => $jobId, 'freelancer_id' => $freelancerId]);
        return $ndaId;
    }

    public function createContractFromBid(array $bid, array $options = []): int
    {
        $job = $this->jobs->getJob((int) $bid['job_id']);
        $nda = $this->contracts->getNdaForJob((int) $bid['job_id'], (int) $bid['freelancer_id']);
        if (!$nda) {
            $this->generateNda((int) $bid['job_id'], (int) $bid['freelancer_id']);
        }
        $currency = $options['currency'] ?? ($job['currency'] ?? 'USD');
        if (!in_array($currency, ['USD', 'EUR', 'GBP'], true)) {
            $currency = 'USD';
        }
        $contractId = $this->contracts->createContract([
            'job_id' => $bid['job_id'],
            'client_id' => $job['client_id'],
            'freelancer_id' => $bid['freelancer_id'],
            'total_amount' => $bid['amount'],
            'scope_text' => $job['description'],
            'partial_release_pct' => (int) ($options['partial_release_pct'] ?? 0),
            'currency' => $currency,
        ]);
        $this->jobs->markAwarded((int) $bid['job_id']);
        $this->audit->log((int) $job['client_id'], 'contract_created', 'contract', $contractId, null, ['bid_id' => $bid['id']]);
        $this->notifications->send((int) $bid['freelancer_id'], 'nda_signature', 'A contract is ready. Sign the NDA to activate it.');
        $this->notifications->send((int) $job['client_id'], 'nda_signature', 'Please sign the NDA to fully activate the contract.');
        return $contractId;
    }

    public function signNdaAndActivate(int $jobId, int $freelancerId): void
    {
        $nda = $this->contracts->getNdaForJob($jobId, $freelancerId);
        if (!$nda) {
            Response::error('NDA not found', 404);
        }
        $this->contracts->signNda((int) $nda['id']);
        $this->audit->log($freelancerId, 'nda_signed_freelancer', 'nda', (int) $nda['id'], ['freelancer_signed_at' => $nda['freelancer_signed_at'] ?? null], ['freelancer_signed_at' => gmdate('c')]);
        $this->activateContractIfFullySigned($jobId, $freelancerId);
    }

    public function signNdaByClientAndActivate(int $jobId, int $clientId): void
    {
        $job = $this->jobs->getJob($jobId);
        if (!$job || (int) $job['client_id'] !== $clientId) {
            Response::error('Job not found', 404);
        }
        $contractStmt = Database::getInstance()->getConnection()->prepare('SELECT freelancer_id FROM contracts WHERE job_id = ? AND client_id = ? ORDER BY id DESC LIMIT 1');
        $contractStmt->execute([$jobId, $clientId]);
        $contract = $contractStmt->fetch();
        if (!$contract) {
            Response::error('Contract not found', 404);
        }
        $nda = $this->contracts->getNdaForJob($jobId, (int) $contract['freelancer_id']);
        if (!$nda) {
            Response::error('NDA not found', 404);
        }
        $this->contracts->signNdaByClient((int) $nda['id']);
        $this->audit->log($clientId, 'nda_signed_client', 'nda', (int) $nda['id'], ['client_signed_at' => $nda['client_signed_at'] ?? null], ['client_signed_at' => gmdate('c')]);
        $this->activateContractIfFullySigned($jobId, (int) $nda['freelancer_id']);
    }

    private function activateContractIfFullySigned(int $jobId, int $freelancerId): void
    {
        $contractData = Database::getInstance()->getConnection()->prepare('SELECT id FROM contracts WHERE job_id = ? AND freelancer_id = ? ORDER BY id DESC LIMIT 1');
        $contractData->execute([$jobId, $freelancerId]);
        $row = $contractData->fetch();
        $nda = $this->contracts->getNdaForJob($jobId, $freelancerId);
        if ($row && !empty($nda['client_signed_at']) && !empty($nda['freelancer_signed_at'])) {
            $contractId = (int) $row['id'];
            $this->contracts->activateContract($contractId);
            $contract = $this->contracts->getContract($contractId);
            
            
            $this->audit->log($freelancerId, 'nda_fully_signed', 'nda', (int) $nda['id'], null, ['contract_id' => $contractId]);
        }
    }

    public function proposeAmendment(int $contractId, int $userId, string $changeDescription): int
    {
        $amendmentId = $this->contracts->addAmendment($contractId, $userId, $changeDescription);
        $this->audit->log($userId, 'amendment_proposed', 'contract_amendment', $amendmentId, null, ['contract_id' => $contractId]);
        return $amendmentId;
    }

    public function respondToAmendment(int $amendmentId, int $userId, string $response): void
    {
        $amendment = $this->contracts->getAmendment($amendmentId);
        if (!$amendment || $amendment['status'] !== 'pending') {
            Response::error('Amendment not found', 404);
        }
        $contract = $this->contracts->getContract((int) $amendment['contract_id']);
        $participants = [(int) $contract['client_id'], (int) $contract['freelancer_id']];
        if (!in_array($userId, $participants, true)) {
            Response::error('Forbidden', 403);
        }
        if ($response === 'rejected') {
            $this->contracts->updateAmendmentStatus($amendmentId, 'rejected', null, $userId);
            $this->audit->log($userId, 'amendment_rejected', 'contract_amendment', $amendmentId, ['status' => 'pending'], ['status' => 'rejected']);
            return;
        }

        $approvalColumn = $userId === (int) $amendment['proposed_by'] ? 'requester_approved_at' : 'counterparty_approved_at';
        if (!empty($amendment[$approvalColumn])) {
            Response::error('You already responded to this amendment', 422);
        }

        $newStatus = 'pending';
        $otherApproved = $approvalColumn === 'requester_approved_at'
            ? !empty($amendment['counterparty_approved_at'])
            : !empty($amendment['requester_approved_at']);
        if ($otherApproved) {
            $newStatus = 'approved';
        }
        $this->contracts->updateAmendmentStatus($amendmentId, $newStatus, $approvalColumn);
        if ($newStatus === 'approved') {
            $change = json_decode((string) $amendment['change_description'], true);
            if ($change !== null) {
                if (isset($change['new_total'])) {
                    $this->contracts->updateContractAmount((int) $amendment['contract_id'], (float) $change['new_total']);
                }
                if (isset($change['milestones']) && is_array($change['milestones'])) {
                    foreach ($change['milestones'] as $mId => $mAmount) {
                        $this->milestones->updateMilestoneAmount((int) $mId, (float) $mAmount);
                    }
                }
            }

            if (!$this->milestoneTotalsAreValid((int) $amendment['contract_id'])) {
                $this->contracts->updateAmendmentStatus($amendmentId, 'pending');
                Response::error('Amendment approved but milestone totals are inconsistent with the contract total amount', 422);
            }
        }
        $this->audit->log($userId, 'amendment_approved', 'contract_amendment', $amendmentId, ['status' => $amendment['status']], ['status' => $newStatus, 'approval_column' => $approvalColumn]);
    }

    public function validateMilestoneTotals(int $contractId): void
    {
        $contract = $this->contracts->getContract($contractId);
        $sum = 0;
        foreach ($contract['milestones'] as $milestone) {
            $sum += (float) $milestone['amount'];
        }
        if (round($sum, 2) !== round((float) $contract['total_amount'], 2)) {
            Response::error('Milestone amounts must sum to contract total amount', 422);
        }
    }

    private function milestoneTotalsAreValid(int $contractId): bool
    {
        $contract = $this->contracts->getContract($contractId);
        $sum = 0;
        foreach ($contract['milestones'] as $milestone) {
            $sum += (float) $milestone['amount'];
        }

        return round($sum, 2) === round((float) $contract['total_amount'], 2);
    }
}
