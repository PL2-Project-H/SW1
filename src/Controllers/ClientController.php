<?php

class ClientController extends BaseController
{
    private JobRepository $jobs;
    private BidRepository $bids;
    private BidService $bidService;
    private ContractService $contracts;
    private InterviewRepository $interviews;

    public function __construct()
    {
        parent::__construct();
        $this->jobs = new JobRepository();
        $this->bids = new BidRepository();
        $this->bidService = new BidService();
        $this->contracts = new ContractService();
        $this->interviews = new InterviewRepository();
    }

    public function createJob(array $data): void
    {
        $clientId = $this->requireAuth('client');
        $niche = $this->enumField($data, 'niche', ['data_science', 'legal', 'translation', 'other']);
        $visibility = $this->enumField($data, 'visibility', ['public', 'invitation'], false) ?? 'public';
        $metadata = [];
        if ($niche === 'translation') {
            $metadata = [
                'source_language' => $this->stringField($data, 'source_language', 120, false) ?? '',
                'target_language' => $this->stringField($data, 'target_language', 120, false) ?? '',
                'word_count' => $this->stringField($data, 'word_count', 40, false) ?? '',
            ];
        } elseif ($niche === 'data_science') {
            $metadata = [
                'data_stack' => $this->stringField($data, 'data_stack', 120, false) ?? '',
                'dataset_size' => $this->stringField($data, 'dataset_size', 120, false) ?? '',
                'deliverable_format' => $this->stringField($data, 'deliverable_format', 120, false) ?? '',
            ];
        } elseif ($niche === 'legal') {
            $metadata = [
                'jurisdiction' => $this->stringField($data, 'jurisdiction', 120, false) ?? '',
                'case_type' => $this->stringField($data, 'case_type', 120, false) ?? '',
                'confidentiality_required' => $this->boolField($data, 'confidentiality_required'),
            ];
        }
        $jobId = $this->jobs->createJob($clientId, array_merge($data, [
            'title' => $this->stringField($data, 'title', 190),
            'description' => $this->stringField($data, 'description', 4000),
            'niche' => $niche,
            'budget' => $this->floatField($data, 'budget', 0),
            'deadline' => $this->dateTimeField($data, 'deadline'),
            'visibility' => $visibility,
            'niche_metadata' => $metadata,
        ]));
        if ($visibility === 'invitation' && !empty($data['invitees'])) {
            $this->createPrivateJob($jobId, $this->stringField($data, 'invitees', 1000));
        }
        Response::json(['id' => $jobId]);
    }

    public function createPrivateJob(int $jobId, string $invitees): void
    {
        $pairs = array_filter(array_map('trim', explode(',', $invitees)));
        $users = new UserRepository();
        foreach ($pairs as $entry) {
            $freelancer = is_numeric($entry) ? $users->findById((int) $entry) : $users->findByEmail($entry);
            if ($freelancer) {
                $this->jobs->inviteFreelancer($jobId, (int) $freelancer['id']);
                $this->notifications->send((int) $freelancer['id'], 'job_invitation', 'You have been invited to a private job.');
            }
        }
    }

    public function myJobs(): void
    {
        $clientId = $this->requireAuth('client');
        $jobs = $this->jobs->listJobs(['client_id' => $clientId], $clientId);
        Response::json($jobs);
    }

    public function browseJobs(): void
    {
        $userId = $this->requireAuth();
        $filters = [
            'niche' => $this->queryString('niche', 80),
            'keyword' => $this->queryString('keyword', 120),
            'max_budget' => $this->queryString('max_budget', 40),
        ];
        Response::json($this->jobs->listJobs($filters, $userId));
    }

    public function bids(): void
    {
        $this->requireAuth('client');
        $jobId = $this->queryInt('job_id', 1);
        if ($jobId === null) {
            Response::error('Query parameter job_id is required', 422);
        }
        Response::json($this->bidService->filterBids($jobId, [
            'min_success_rate' => $this->queryString('min_success_rate', 20),
            'cert_required' => $this->queryString('cert_required', 10),
            'max_amount' => $this->queryString('max_amount', 20),
            'sort_by' => $this->queryString('sort_by', 40),
        ]));
    }

    public function acceptBid(array $data): void
    {
        $clientId = $this->requireAuth('client');
        $bid = $this->bids->find($this->intField($data, 'bid_id', 1));
        if (!$bid) {
            Response::error('Bid not found', 404);
        }
        $job = $this->jobs->getJob((int) $bid['job_id']);
        if ((int) $job['client_id'] !== $clientId) {
            Response::error('Forbidden', 403);
        }
        $this->bids->updateStatus((int) $bid['id'], 'accepted');
        $contractId = $this->contracts->createContractFromBid($bid);
        Response::json(['contract_id' => $contractId]);
    }

    public function rejectBid(array $data): void
    {
        $this->requireAuth('client');
        $this->bids->updateStatus($this->intField($data, 'bid_id', 1), 'rejected');
        Response::json(['message' => 'Bid rejected']);
    }

    public function signNda(array $data): void
    {
        $clientId = $this->requireAuth('client');
        (new ContractService())->signNdaByClientAndActivate($this->intField($data, 'job_id', 1), $clientId);
        Response::json(['message' => 'Client NDA signature recorded']);
    }

    public function scheduleInterview(array $data): void
    {
        $clientId = $this->requireAuth('client');
        $freelancerId = $this->intField($data, 'freelancer_id', 1);
        $interviewId = $this->interviews->create([
            'job_id' => $this->intField($data, 'job_id', 1),
            'client_id' => $clientId,
            'freelancer_id' => $freelancerId,
            'scheduled_at' => $this->dateTimeField($data, 'scheduled_at'),
            'timezone' => $this->stringField($data, 'timezone', 120, false) ?? 'UTC',
            'proposed_by' => $clientId,
            'notes' => $this->stringField($data, 'notes', 1000, false),
        ]);
        $this->notifications->send($freelancerId, 'interview_scheduled', 'A technical interview has been scheduled.');
        Response::json(['id' => $interviewId]);
    }

    public function updateInterview(array $data): void
    {
        $clientId = $this->requireAuth('client');
        $this->interviews->updateForClient(
            $this->intField($data, 'interview_id', 1),
            $clientId,
            $this->enumField($data, 'status', ['pending', 'completed', 'canceled']),
            $this->stringField($data, 'notes', 1000, false)
        );
        Response::json(['message' => 'Interview updated']);
    }

    public function interviews(): void
    {
        $clientId = $this->requireAuth('client');
        Response::json($this->interviews->listForClient($clientId));
    }
}
