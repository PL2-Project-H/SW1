<?php

class AdminController extends BaseController
{
    private UserRepository $users;
    private FreelancerRepository $freelancers;
    private AuditRepository $auditRepo;
    private AdminMetricsRepository $metrics;

    public function __construct()
    {
        parent::__construct();
        $this->users = new UserRepository();
        $this->freelancers = new FreelancerRepository();
        $this->auditRepo = new AuditRepository();
        $this->metrics = new AdminMetricsRepository();
    }

    public function getDashboard(): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('dashboard');
        Response::json($this->metrics->dashboardMetrics());
    }

    public function getNicheReport(): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('reports/niche');
        Response::json($this->metrics->nicheReport());
    }

    public function users(): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('users');
        Response::json($this->users->listUsers([
            'role' => $this->queryString('role', 40),
            'status' => $this->queryString('status', 40),
            'q' => $this->queryString('q', 120),
        ]));
    }

    public function flagUser(array $data): void
    {
        $adminId = $this->requireAuth('admin');
        (new AuthService())->checkRole('users/flag');
        $userId = $this->intField($data, 'user_id', 1);
        $reason = $this->stringField($data, 'reason', 500);
        $this->users->addFlag($userId, $adminId, $reason, 'flag');
        Response::json(['message' => 'User flagged']);
    }

    public function sanctionUser(array $data): void
    {
        $adminId = $this->requireAuth('admin');
        (new AuthService())->checkRole('users/sanction');
        $userId = $this->intField($data, 'user_id', 1);
        $reason = $this->stringField($data, 'reason', 500);
        $level = $this->enumField($data, 'level', ['warn', 'limit', 'ban']);
        $this->users->addFlag($userId, $adminId, $reason, $level);
        if ($level === 'limit') {
            $this->users->setStatus($userId, 'limited');
        } elseif ($level === 'ban') {
            $this->users->setStatus($userId, 'banned');
        }
        if ($level === 'warn') {
            $this->notifications->send($userId, 'warning', 'Your account received a warning.');
        }
        Response::json(['message' => 'Sanction applied']);
    }

    public function flaggedUsers(): void
    {
        $this->requireAuth('admin');
        Response::json($this->users->listFlaggedUsers());
    }

    public function auditLog(): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('audit-log');
        Response::json($this->auditRepo->listLogs([
            'action' => $this->queryString('action', 120),
            'entity_type' => $this->queryString('entity_type', 120),
            'date_from' => $this->queryString('date_from', 32),
            'date_to' => $this->queryString('date_to', 32),
        ]));
    }

    public function credentialQueue(): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('credential/review');
        Response::json($this->freelancers->listPendingCredentials());
    }

    public function reviewCredential(array $data): void
    {
        $adminId = $this->requireAuth('admin');
        (new CredentialVerificationService())->processCredential(
            $this->intField($data, 'credential_id', 1),
            $adminId,
            $this->enumField($data, 'decision', ['verified', 'rejected'])
        );
        Response::json(['message' => 'Credential reviewed']);
    }

    public function kycQueue(): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('credential/review');
        Response::json((new UserRepository())->listKycSubmissions('pending'));
    }

    public function reviewKyc(array $data): void
    {
        $adminId = $this->requireAuth('admin');
        (new AuthService())->checkRole('credential/review');
        $users = new UserRepository();
        $submission = $users->getKycSubmission($this->intField($data, 'submission_id', 1));
        if (!$submission) {
            Response::error('KYC submission not found', 404);
        }
        $decision = $this->enumField($data, 'decision', ['verified', 'rejected']);
        $users->updateKycSubmission((int) $submission['id'], $decision, $adminId);
        $users->updateKycStatus((int) $submission['user_id'], $decision === 'verified' ? 'verified' : 'unverified');
        Response::json(['message' => 'KYC reviewed']);
    }

    public function rebuildSearchIndex(): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('search-index/rebuild');
        $service = new SkillMatchingService();
        $freelancers = $this->freelancers->listFreelancers();
        foreach ($freelancers as $freelancer) {
            (new ReputationService())->calculate((int) $freelancer['id']);
            $service->rankFreshAndPopulateCache($freelancer['niche'], explode(',', str_replace('_', ' ', $freelancer['niche'])), false);
        }
        Response::json(['message' => 'Search cache rebuilt', 'count' => count($freelancers)]);
    }

    public function digestPreview(): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('weekly-digest/preview');
        Response::json(['message' => 'Weekly digest generation uses live freelancer niche and skill matching.', 'sample_count' => count($this->freelancers->listFreelancers())]);
    }

    public function sendWeeklyDigest(): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('weekly-digest/send');
        Response::json(['generated' => (new NotificationService())->generateWeeklyDigest()]);
    }

    public function roles(): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('roles');
        Response::json([
            ['name' => 'financial_admin'],
            ['name' => 'dispute_mediator'],
            ['name' => 'tech_support'],
        ]);
    }

    public function assignRole(array $data): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('roles/assign');
        $this->users->setAdminRole(
            $this->intField($data, 'user_id', 1),
            $this->enumField($data, 'admin_role', ['financial_admin', 'dispute_mediator', 'tech_support'])
        );
        Response::json(['message' => 'Role assigned']);
    }

    public function archivedMessages(): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('audit-log');
        Response::json((new DisputeRepository())->archivedMessages([
            'q' => $this->queryString('q', 120),
            'page' => $this->queryInt('page', 1) ?? 1,
            'limit' => $this->queryInt('limit', 1) ?? 20,
        ]));
    }
}
