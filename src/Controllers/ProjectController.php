<?php

class ProjectController extends BaseController
{
    private BidService $bidService;
    private BidRepository $bids;
    private JobRepository $jobs;
    private ContractRepository $contracts;
    private MilestoneService $milestones;

    public function __construct()
    {
        parent::__construct();
        $this->bidService = new BidService();
        $this->bids = new BidRepository();
        $this->jobs = new JobRepository();
        $this->contracts = new ContractRepository();
        $this->milestones = new MilestoneService();
    }

    public function submitBid(array $data): void
    {
        $freelancerId = $this->requireAuth('freelancer');
        $job = $this->jobs->getJob((int) $data['job_id']);
        if (!$job) {
            Response::error('Job not found', 404);
        }
        if ($job['visibility'] === 'invitation' && !$this->jobs->isInvited((int) $job['id'], $freelancerId)) {
            Response::error('You are not invited to bid on this private job', 403);
        }
        $bidId = $this->bidService->trackVersion((int) $data['job_id'], $freelancerId, (float) $data['amount'], $data['proposal_text'], !empty($data['validity_days']) ? date('Y-m-d H:i:s', strtotime('+' . (int) $data['validity_days'] . ' days')) : null);
        Response::json(['id' => $bidId]);
    }

    public function withdrawBid(array $data): void
    {
        $freelancerId = $this->requireAuth('freelancer');
        $this->bidService->withdraw((int) $data['bid_id'], $freelancerId);
        Response::json(['message' => 'Bid withdrawn']);
    }

    public function myBids(): void
    {
        $freelancerId = $this->requireAuth('freelancer');
        Response::json($this->bids->listFreelancerBids($freelancerId));
    }

    public function activeContracts(): void
    {
        $userId = $this->requireAuth();
        Response::json($this->contracts->listActiveContracts($userId));
    }

    public function contractDetail(int $contractId): void
    {
        $this->requireAuth();
        Response::json($this->contracts->getContract($contractId));
    }

    public function signNda(array $data): void
    {
        $freelancerId = $this->requireAuth('freelancer');
        (new ContractService())->signNdaAndActivate((int) $data['job_id'], $freelancerId);
        Response::json(['message' => 'NDA signed and contract activated']);
    }

    public function buildMilestones(array $data): void
    {
        $this->requireAuth();
        Response::json(['ids' => $this->milestones->buildMilestones((int) $data['contract_id'], $data['milestones'] ?? [])]);
    }

    public function startMilestone(array $data): void
    {
        $this->requireAuth();
        $this->milestones->startMilestone((int) $data['milestone_id']);
        Response::json(['message' => 'Milestone started']);
    }

    public function milestoneDetail(int $milestoneId): void
    {
        $this->requireAuth();
        Response::json((new MilestoneRepository())->getMilestone($milestoneId));
    }

    public function submitChecklist(array $data): void
    {
        $freelancerId = $this->requireAuth('freelancer');
        $this->milestones->submitQaChecklist((int) $data['milestone_id'], $freelancerId, $data['checklist'] ?? []);
        Response::json(['message' => 'QA checklist submitted']);
    }

    public function qaChecklist(): void
    {
        $this->requireAuth();
        Response::json([
            ['key' => 'files_complete', 'label' => 'All deliverable files are complete and named correctly'],
            ['key' => 'requirements_met', 'label' => 'Work meets all requirements stated in the contract'],
            ['key' => 'no_placeholder_data', 'label' => 'No placeholder or test data included'],
            ['key' => 'formats_match', 'label' => 'File formats match what was agreed'],
        ]);
    }

    public function submitDeliverable(): void
    {
        $this->requireAuth('freelancer');
        $milestoneId = (int) ($_POST['milestone_id'] ?? 0);
        $path = $this->uploadFile('deliverables', (int) $_SESSION['user_id'], ['pdf', 'jpg', 'jpeg', 'png', 'ipynb', 'docx', 'zip']);
        $id = $this->milestones->handleDeliverable($milestoneId, $path);
        Response::json(['id' => $id, 'file_path' => $path]);
    }

    public function requestRevision(array $data): void
    {
        $this->requireAuth('client');
        $result = $this->milestones->requestRevision((int) $data['milestone_id']);
        Response::json(array_merge(['message' => 'Revision requested'], $result));
    }

    public function approveMilestone(array $data): void
    {
        $this->requireAuth('client');
        $this->milestones->approve((int) $data['milestone_id']);
        Response::json(['message' => 'Milestone approved']);
    }

    public function confirmMilestone(array $data): void
    {
        $this->requireAuth('freelancer');
        $this->milestones->confirmCompletion((int) $data['milestone_id']);
        Response::json(['message' => 'Milestone marked complete']);
    }

    public function respondInterview(array $data): void
    {
        $freelancerId = $this->requireAuth('freelancer');
        $status = $this->enumField($data, 'status', ['accepted', 'countered', 'canceled']);
        (new InterviewRepository())->updateForFreelancer(
            $this->intField($data, 'interview_id', 1),
            $freelancerId,
            $status,
            $status === 'countered' ? $this->dateTimeField($data, 'scheduled_at') : null,
            $status === 'countered' ? ($this->stringField($data, 'timezone', 120, false) ?? 'UTC') : null,
            $this->stringField($data, 'notes', 1000, false)
        );
        Response::json(['message' => 'Interview response saved']);
    }

    public function myInterviews(): void
    {
        $freelancerId = $this->requireAuth('freelancer');
        Response::json((new InterviewRepository())->listForFreelancer($freelancerId));
    }

    public function listSnapshots(): void
    {
        $this->requireAuth();
        $milestone = (new MilestoneRepository())->getMilestone((int) $_GET['milestone_id']);
        Response::json($milestone['snapshots'] ?? []);
    }

    public function createSnapshot(): void
    {
        $this->requireAuth('freelancer');
        $milestoneId = (int) ($_POST['milestone_id'] ?? 0);
        $path = $this->uploadFile('snapshots', (int) $_SESSION['user_id'], ['pdf', 'jpg', 'jpeg', 'png', 'ipynb', 'docx', 'zip']);
        $this->milestones->snapshot($milestoneId, $path);
        Response::json(['file_path' => $path]);
    }

    public function amend(array $data): void
    {
        $userId = $this->requireAuth();
        $id = (new ContractService())->proposeAmendment((int) $data['contract_id'], $userId, $data['change_description']);
        Response::json(['id' => $id]);
    }

    public function respondAmendment(array $data): void
    {
        $userId = $this->requireAuth();
        (new ContractService())->respondToAmendment((int) $data['amendment_id'], $userId, $data['response']);
        Response::json(['message' => 'Amendment updated']);
    }
}
