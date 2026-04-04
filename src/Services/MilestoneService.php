<?php

class MilestoneService
{
    private MilestoneRepository $milestones;
    private ContractRepository $contracts;
    private EscrowService $escrow;
    private AuditService $audit;
    private NotificationService $notifications;
    private ReputationService $reputation;

    public function __construct()
    {
        $this->milestones = new MilestoneRepository();
        $this->contracts = new ContractRepository();
        $this->escrow = new EscrowService();
        $this->audit = new AuditService();
        $this->notifications = new NotificationService();
        $this->reputation = new ReputationService();
    }

    public function buildMilestones(int $contractId, array $items): array
    {
        $contract = $this->contracts->getContract($contractId);

        $sum = 0;
        foreach ($items as $item) {
            $sum += (float) ($item['amount'] ?? 0);
        }
        if (round($sum, 2) !== round((float) $contract['total_amount'], 2)) {
            Response::error('Milestone amounts must sum to contract total amount: ' . $contract['total_amount'], 422);
        }

        $pdo = Database::getInstance()->getConnection();
        $created = [];
        $index = 1;
        try {
            $pdo->beginTransaction();
            foreach ($items as $item) {
                $created[] = $this->milestones->createMilestone([
                    'contract_id' => $contractId,
                    'title' => $item['title'],
                    'amount' => $item['amount'],
                    'order_index' => $item['order_index'] ?? $index++,
                    'due_date' => $item['due_date'],
                    'dependency_milestone_id' => $item['dependency_milestone_id'] ?? null,
                ]);
            }
            $this->audit->log((int) ($_SESSION['user_id'] ?? null), 'milestones_built', 'contract', $contractId, null, ['count' => count($created), 'contract_total' => $contract['total_amount']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $created;
    }

    public function startMilestone(int $milestoneId): void
    {
        $milestone = $this->milestones->getMilestone($milestoneId);
        if (!$this->escrow->hasLock($milestoneId)) {
            Response::error('Escrow must be locked before milestone starts', 422);
        }
        if (!empty($milestone['dependency_milestone_id'])) {
            $dependency = $this->milestones->getMilestone((int) $milestone['dependency_milestone_id']);
            if (!in_array($dependency['status'], ['approved', 'auto_approved', 'complete'], true)) {
                Response::error('Dependency milestone is not approved yet', 422);
            }
        }
        $this->milestones->updateStatus($milestoneId, 'in_progress');
        $this->audit->log((int) $_SESSION['user_id'], 'milestone_started', 'milestone', $milestoneId, ['status' => $milestone['status']], ['status' => 'in_progress']);
    }

    public function enforceQaChecklist(int $milestoneId): void
    {
        $milestone = $this->milestones->getMilestone($milestoneId);
        if (empty($milestone['qa_submission'])) {
            Response::error('QA checklist must be submitted before deliverable upload', 422);
        }
    }

    public function submitQaChecklist(int $milestoneId, int $freelancerId, array $checklist): void
    {
        foreach (['files_complete', 'requirements_met', 'no_placeholder_data', 'formats_match'] as $key) {
            if (empty($checklist[$key])) {
                Response::error('All QA checklist items must be true', 422);
            }
        }
        $this->milestones->saveQaChecklist($milestoneId, $freelancerId, $checklist);
        $this->audit->log($freelancerId, 'qa_checklist_submitted', 'milestone', $milestoneId, null, $checklist);
    }

    public function handleDeliverable(int $milestoneId, string $filePath): int
    {
        $milestone = $this->milestones->getMilestone($milestoneId);
        if (($milestone['status'] ?? null) !== 'in_progress') {
            Response::error('Milestone must be in progress before submitting deliverable', 422);
        }
        $this->enforceQaChecklist($milestoneId);
        $contractId = (int) $milestone['contract_id'];
        $contract = $this->contracts->getContract($contractId);
        $deliverableId = $this->milestones->createDeliverable($milestoneId, $filePath, (int) $contract['free_revisions_per_milestone']);
        $this->milestones->updateStatus($milestoneId, 'submitted');
        $this->snapshot($milestoneId, $filePath);
        $this->audit->log((int) $_SESSION['user_id'], 'deliverable_submitted', 'deliverable', $deliverableId, null, ['milestone_id' => $milestoneId]);
        $this->notifications->send((int) $contract['client_id'], 'deliverable_submitted', 'A new deliverable has been submitted.');
        return $deliverableId;
    }

    public function checkRevisionLimit(int $milestoneId): array
    {
        $deliverable = $this->milestones->getLatestDeliverable($milestoneId);
        if (!$deliverable) {
            Response::error('Deliverable not found', 404);
        }
        return $deliverable;
    }

    public function requestRevision(int $milestoneId): array
    {
        $deliverable = $this->checkRevisionLimit($milestoneId);
        $paidRevisionRequired = (int) $deliverable['revision_count'] >= (int) $deliverable['free_revisions_allowed'];
        $paidRevisionFee = 0.0;
        if ($paidRevisionRequired) {
            $milestone = $this->milestones->getMilestone($milestoneId);
            $paidRevisionFee = round(((float) $milestone['amount']) * 0.1, 2);
            $this->milestones->markPaidRevision((int) $deliverable['id'], $paidRevisionFee);
        } else {
            $this->milestones->updateDeliverableForRevision((int) $deliverable['id']);
        }
        $this->milestones->updateStatus($milestoneId, 'revision');
        $this->audit->log((int) $_SESSION['user_id'], 'revision_requested', 'deliverable', (int) $deliverable['id'], ['revision_count' => $deliverable['revision_count']], ['revision_count' => ((int) $deliverable['revision_count']) + 1, 'paid_revision_required' => $paidRevisionRequired]);
        return [
            'paid_revision_required' => $paidRevisionRequired,
            'paid_revision_fee' => $paidRevisionFee,
        ];
    }

    public function approve(int $milestoneId, bool $auto = false): void
    {
        $deliverable = $this->milestones->getLatestDeliverable($milestoneId);
        if (!$deliverable) {
            Response::error('No deliverable found', 404);
        }
        $this->milestones->approveDeliverable((int) $deliverable['id']);
        $status = $auto ? 'auto_approved' : 'approved';
        $this->milestones->updateStatus($milestoneId, $status);
        $milestone = $this->milestones->getMilestone($milestoneId);
        $contract = $this->contracts->getContract((int) $milestone['contract_id']);
        $this->escrow->releaseForMilestone($contract, $milestone, $auto);
        $this->reputation->calculate((int) $contract['freelancer_id']);
        $this->audit->log((int) $_SESSION['user_id'], $auto ? 'auto_approval' : 'milestone_approved', 'milestone', $milestoneId, ['status' => 'submitted'], ['status' => $status]);
    }

    public function confirmCompletion(int $milestoneId): void
    {
        $deliverable = $this->milestones->getLatestDeliverable($milestoneId);
        if (!$deliverable || empty($deliverable['client_approved_at'])) {
            Response::error('Client approval is required first', 422);
        }
        $this->milestones->confirmDeliverable((int) $deliverable['id']);
        $this->milestones->updateStatus($milestoneId, 'complete');
        $this->audit->log((int) $_SESSION['user_id'], 'milestone_completed', 'milestone', $milestoneId, ['status' => 'approved'], ['status' => 'complete']);
    }

    public function snapshot(int $milestoneId, string $filePath): void
    {
        $snapshotId = $this->milestones->addSnapshot($milestoneId, $filePath);
        $this->audit->log((int) $_SESSION['user_id'], 'snapshot_created', 'wip_snapshot', $snapshotId, null, ['milestone_id' => $milestoneId]);
    }

    public function autoApprove(): void
    {
        $rows = $this->milestones->listSubmittedPastWindow();
        foreach ($rows as $row) {
            $this->approve((int) $row['id'], true);
            $contract = $this->contracts->getContract((int) $row['contract_id']);
            $this->notifications->send((int) $contract['client_id'], 'auto_approval', 'A milestone was auto-approved after 5 days.');
            $this->notifications->send((int) $contract['freelancer_id'], 'auto_approval', 'Your milestone was auto-approved after 5 days.');
        }
    }
}
