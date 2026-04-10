<?php

class MilestoneService
{
    private MilestoneRepository $milestones;
    private ContractRepository $contracts;
    private EscrowService $escrow;
    private AuditService $audit;
    private NotificationService $notifications;
    private ReputationService $reputation;
    private FreelancerSearchRepository $search;

    public function __construct()
    {
        $this->milestones = new MilestoneRepository();
        $this->contracts = new ContractRepository();
        $this->escrow = new EscrowService();
        $this->audit = new AuditService();
        $this->notifications = new NotificationService();
        $this->reputation = new ReputationService();
        $this->search = new FreelancerSearchRepository();
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

        $this->ensureNoDependencyCycles($items);

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

    private function ensureNoDependencyCycles(array $items): void
    {
        $graph = [];
        $aliases = [];

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $nodeId = 'item_' . $index;
            $graph[$nodeId] = [];

            foreach ($this->milestoneAliases($item, $index) as $alias) {
                $aliases[$alias] = $nodeId;
            }
        }

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $dependency = $item['dependency_milestone_id'] ?? null;
            if ($dependency === null || $dependency === '') {
                continue;
            }

            $alias = (string) $dependency;
            if (isset($aliases[$alias])) {
                $graph['item_' . $index][] = $aliases[$alias];
            }
        }

        $state = [];
        foreach (array_keys($graph) as $nodeId) {
            if ($this->hasCycle($nodeId, $graph, $state)) {
                Response::error('Milestone dependencies cannot contain cycles', 422);
            }
        }
    }

    private function milestoneAliases(array $item, int $index): array
    {
        $aliases = [(string) ($index + 1)];

        foreach (['id', 'milestone_id', 'temp_id', 'order_index'] as $field) {
            $value = $item[$field] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $aliases[] = (string) $value;
        }

        return array_values(array_unique($aliases));
    }

    private function hasCycle(string $nodeId, array $graph, array &$state): bool
    {
        $status = $state[$nodeId] ?? 0;
        if ($status === 1) {
            return true;
        }
        if ($status === 2) {
            return false;
        }

        $state[$nodeId] = 1;
        foreach ($graph[$nodeId] ?? [] as $neighbor) {
            if ($this->hasCycle($neighbor, $graph, $state)) {
                return true;
            }
        }
        $state[$nodeId] = 2;

        return false;
    }

    public function startMilestone(int $milestoneId): void
    {
        $milestone = $this->milestones->getMilestone($milestoneId);
        if (!$this->escrow->hasLock($milestoneId)) {
            Response::error('Escrow funds must be locked before milestone starts', 422);
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
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $this->audit->log($userId, $auto ? 'auto_approval' : 'milestone_approved', 'milestone', $milestoneId, ['status' => 'submitted'], ['status' => $status]);
    }

    public function confirmCompletion(int $milestoneId): void
    {
        $deliverable = $this->milestones->getLatestDeliverable($milestoneId);
        if (!$deliverable || empty($deliverable['client_approved_at'])) {
            Response::error('Client approval is required first', 422);
        }
        $this->milestones->confirmDeliverable((int) $deliverable['id']);
        $this->milestones->updateStatus($milestoneId, 'complete');

        $milestone = $this->milestones->getMilestone($milestoneId);
        $contract = $this->contracts->getContract((int) $milestone['contract_id']);
        $freelancerId = (int) $contract['freelancer_id'];
        $profile = (new FreelancerRepository())->getProfile($freelancerId);
        $reputation = $profile['reputation'] ?? ['composite_score' => 0];

        $completedProjects = Database::getInstance()->getConnection()->prepare('SELECT COUNT(*) FROM contracts WHERE freelancer_id = ? AND status IN ("completed", "final_resolved")');
        $completedProjects->execute([$freelancerId]);
        $count = (int) $completedProjects->fetchColumn();

        $this->search->upsertSearchCache(
            $freelancerId,
            $profile['niche'] ?? 'other',
            $profile['bio'] ?? '',
            implode(', ', array_column($profile['skills'] ?? [], 'name')),
            $count,
            (float) ($reputation['composite_score'] ?? 0),
            (float) ($reputation['composite_score'] ?? 0)
        );

        $this->audit->log((int) ($_SESSION['user_id'] ?? 0), 'milestone_completed', 'milestone', $milestoneId, ['status' => 'approved'], ['status' => 'complete']);
    }

    public function snapshot(int $milestoneId, string $filePath): void
    {
        $snapshotId = $this->milestones->addSnapshot($milestoneId, $filePath);
        $this->audit->log((int) $_SESSION['user_id'], 'snapshot_created', 'wip_snapshot', $snapshotId, null, ['milestone_id' => $milestoneId]);
    }

    public function checkDeadlines(): void
    {
        $milestones = $this->milestones->listInProgressForDeadlineChecks();
        foreach ($milestones as $milestone) {
            $dueDate = strtotime((string) $milestone['due_date']);
            $now = time();
            $diff = $dueDate - $now;

            $contract = $this->contracts->getContract((int) $milestone['contract_id']);

            if ($diff > 0 && $diff <= 86400) { // Within 24 hours
                $this->notifications->send((int) $contract['freelancer_id'], 'deadline_approaching', "Milestone '{$milestone['title']}' is due within 24 hours.");
            } elseif ($diff < 0) { // Overdue
                $this->audit->log(null, 'milestone_overdue', 'milestone', (int) $milestone['id'], null, ['due_date' => $milestone['due_date']]);
                $this->contracts->updateContractStatus((int) $milestone['contract_id'], 'active'); // Ensure status is active or alert
                $this->notifications->send((int) $contract['client_id'], 'milestone_overdue', "Milestone '{$milestone['title']}' is overdue.");
            }
        }
    }

    public function autoApprove(): void
    {
        $rows = $this->milestones->listSubmittedPastWindow();
        $sessionUserIdChanged = false;
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['user_id'] = null;
            $sessionUserIdChanged = true;
        }
        foreach ($rows as $row) {
            $this->approve((int) $row['id'], true);
            $contract = $this->contracts->getContract((int) $row['contract_id']);
            $this->notifications->send((int) $contract['client_id'], 'auto_approval', 'A milestone was auto-approved after 5 days.');
            $this->notifications->send((int) $contract['freelancer_id'], 'auto_approval', 'Your milestone was auto-approved after 5 days.');
        }
        if ($sessionUserIdChanged) {
            unset($_SESSION['user_id']);
        }
    }
}
