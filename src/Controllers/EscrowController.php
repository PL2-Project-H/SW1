<?php

class EscrowController extends BaseController
{
    private EscrowService $escrow;
    private ContractRepository $contracts;
    private MilestoneRepository $milestones;

    public function __construct()
    {
        parent::__construct();
        $this->escrow = new EscrowService();
        $this->contracts = new ContractRepository();
        $this->milestones = new MilestoneRepository();
    }

    public function lock(array $data): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('escrow/lock');
        $milestone = $this->milestones->getMilestone((int) $data['milestone_id']);
        $contract = $this->contracts->getContract((int) $milestone['contract_id']);
        Response::json(['id' => $this->escrow->lockFunds($contract, $milestone)]);
    }

    public function release(array $data): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('escrow/release');
        $milestone = $this->milestones->getMilestone((int) $data['milestone_id']);
        $contract = $this->contracts->getContract((int) $milestone['contract_id']);
        Response::json(['id' => $this->escrow->releaseForMilestone($contract, $milestone)]);
    }

    public function partialRelease(array $data): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('escrow/partial-release');
        $milestone = $this->milestones->getMilestone((int) $data['milestone_id']);
        $contract = $this->contracts->getContract((int) $milestone['contract_id']);
        Response::json(['id' => $this->escrow->partialRelease($contract, $milestone)]);
    }

    public function balance(): void
    {
        $this->requireAuth();
        Response::json($this->escrow->balance((int) $_GET['contract_id']));
    }

    public function refund(array $data): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('escrow/refund');
        $milestone = $this->milestones->getMilestone((int) $data['milestone_id']);
        $contract = $this->contracts->getContract((int) $milestone['contract_id']);
        $completionPercentage = isset($data['completion_percentage']) ? (float) $data['completion_percentage'] : null;
        Response::json($this->escrow->settleCancellation($contract, $milestone, $completionPercentage));
    }

    public function ledger(): void
    {
        $this->requireAuth();
        Response::json($this->escrow->getLedger((int) $_GET['contract_id']));
    }

    public function payoutSchedule(): void
    {
        $this->requireAuth();
        $balance = $this->escrow->balance((int) $_GET['contract_id']);
        Response::json([
            'cooling_off_days' => 3,
            'pending_balance' => $balance['pending_balance'],
            'cleared_balance' => $balance['cleared_balance'],
        ]);
    }

    public function fees(): void
    {
        $this->requireAuth();
        $contract = $this->contracts->getContract((int) $_GET['contract_id']);
        Response::json($this->escrow->calculateFee((int) $contract['client_id'], (int) $contract['freelancer_id']));
    }

    public function tax(): void
    {
        $this->requireAuth();
        $contract = $this->contracts->getContract((int) $_GET['contract_id']);
        Response::json($this->escrow->calculateTax($contract, (float) ($_GET['gross_amount'] ?? $contract['total_amount'])));
    }
}
