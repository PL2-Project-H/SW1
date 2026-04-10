<?php

class NotificationService
{
    private ?NotificationRepository $notificationsRepo = null;
    private ?MilestoneRepository $milestonesRepo = null;
    private ?ContractRepository $contractsRepo = null;
    private ?JobRepository $jobsRepo = null;
    private ?FreelancerRepository $freelancersRepo = null;
    private ?AuditService $auditService = null;
    private ?Database $database = null;

    public function __construct()
    {
    }

    public function send(int $userId, string $type, string $message, ?array $payload = null): void
    {
        $id = $this->notifications()->create($userId, $type, $message, $payload);
        $this->audit()->log($userId, 'notification_created', 'notification', $id, null, ['type' => $type, 'message' => $message]);
    }

    public function listForUser(int $userId): array
    {
        return $this->notifications()->unreadForUser($userId);
    }

    public function clearForUser(int $userId): void
    {
        $this->notifications()->markAllAsRead($userId);
    }

    public function checkDeadlines(): void
    {
        $rows = $this->milestones()->listInProgressForDeadlineChecks();
        foreach ($rows as $milestone) {
            $contract = $this->contracts()->getContract((int) $milestone['contract_id']);
            $due = strtotime($milestone['due_date']);
            $now = time();
            $days = ($due - $now) / 86400;
            if ($days <= 3 && $days > 1) {
                $this->sendDeadlineNotification((int) $contract['freelancer_id'], 'deadline_warning', 'Milestone "' . $milestone['title'] . '" is due within 3 days.');
            } elseif ($days <= 1 && $days >= 0) {
                $this->sendDeadlineNotification((int) $contract['freelancer_id'], 'deadline_urgent', 'Milestone "' . $milestone['title'] . '" is due within 24 hours.');
                $this->sendDeadlineNotification((int) $contract['client_id'], 'deadline_urgent', 'A freelancer milestone is due within 24 hours.');
            } elseif ($days < 0) {
                $this->sendDeadlineNotification((int) $contract['freelancer_id'], 'deadline_overdue', 'Milestone "' . $milestone['title'] . '" is overdue.');
                $this->sendDeadlineNotification((int) $contract['client_id'], 'deadline_overdue', 'A milestone on your contract is overdue.');
            }
        }
    }

    public function generateWeeklyDigest(): int
    {
        $freelancers = $this->freelancers()->listFreelancers();
        $count = 0;
        $pdo = $this->database()->getConnection();
        foreach ($freelancers as $freelancer) {
            if (!(int) ($freelancer['digest_opt_in'] ?? 1)) {
                continue;
            }
            $skillsStmt = $pdo->prepare('SELECT s.name FROM freelancer_skills fs JOIN skills s ON s.id = fs.skill_id WHERE fs.freelancer_id = ?');
            $skillsStmt->execute([$freelancer['id']]);
            $skills = array_map(fn ($row) => $row['name'], $skillsStmt->fetchAll());
            $jobs = $this->jobs()->listJobs(['niche' => $freelancer['niche']], (int) $freelancer['id']);
            $matches = [];
            foreach ($jobs as $job) {
                $haystack = strtolower($job['title'] . ' ' . $job['description']);
                foreach ($skills as $skill) {
                    if ($skill !== '' && str_contains($haystack, strtolower($skill))) {
                        $matches[] = ['id' => $job['id'], 'title' => $job['title']];
                        break;
                    }
                }
            }
            $matches = array_slice($matches, 0, 5);
            $this->send((int) $freelancer['id'], 'weekly_digest', 'Weekly job digest is ready.', ['jobs' => $matches]);
            $pdo->prepare('INSERT INTO weekly_digest_log (user_id, job_recommendations) VALUES (?, ?)')->execute([$freelancer['id'], json_encode($matches)]);
            $count++;
        }
        return $count;
    }

    private function sendDeadlineNotification(int $userId, string $type, string $message): void
    {
        if ($this->notifications()->existsRecentByType($userId, $type, 12)) {
            return;
        }

        $this->send($userId, $type, $message);
    }

    private function notifications(): NotificationRepository
    {
        return $this->notificationsRepo ??= new NotificationRepository();
    }

    private function milestones(): MilestoneRepository
    {
        return $this->milestonesRepo ??= new MilestoneRepository();
    }

    private function contracts(): ContractRepository
    {
        return $this->contractsRepo ??= new ContractRepository();
    }

    private function jobs(): JobRepository
    {
        return $this->jobsRepo ??= new JobRepository();
    }

    private function freelancers(): FreelancerRepository
    {
        return $this->freelancersRepo ??= new FreelancerRepository();
    }

    private function audit(): AuditService
    {
        return $this->auditService ??= new AuditService();
    }

    private function database(): Database
    {
        return $this->database ??= Database::getInstance();
    }
}
