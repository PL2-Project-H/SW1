<?php

class FreelancerController extends BaseController
{
    private FreelancerRepository $freelancers;
    private SkillMatchingService $matcher;

    public function __construct()
    {
        parent::__construct();
        $this->freelancers = new FreelancerRepository();
        $this->matcher = new SkillMatchingService();
    }

    public function profile(): void
    {
        $userId = $this->requireAuth('freelancer');
        Response::json($this->freelancers->getProfile($userId));
    }

    public function updateProfile(array $data): void
    {
        $userId = $this->requireAuth('freelancer');
        $this->freelancers->updateProfile($userId, $data);
        $skills = array_filter(array_map('trim', explode(',', $data['skills'] ?? '')));
        $this->freelancers->replaceSkills($userId, $skills, $data['niche'] ?? 'other');
        Response::json(['message' => 'Profile updated']);
    }

    public function submitCredential(array $data): void
    {
        $userId = $this->requireAuth('freelancer');
        $path = $this->uploadFile('credentials', $userId, ['pdf', 'jpg', 'jpeg', 'png', 'docx']);
        $id = $this->freelancers->addCredential($userId, $data['type'] ?? 'certification', $path, $data);
        Response::json(['id' => $id, 'file_path' => $path]);
    }

    public function submitKyc(array $data): void
    {
        $userId = $this->requireAuth('freelancer');
        $path = $this->uploadFile('credentials', $userId, ['pdf', 'jpg', 'jpeg', 'png', 'docx']);
        $users = new UserRepository();
        $id = $users->createKycSubmission(
            $userId,
            $data['account_type'] ?? 'individual',
            $data['document_kind'] ?? 'national_id',
            $path
        );
        $users->updateKycStatus($userId, 'submitted');
        Response::json(['id' => $id, 'file_path' => $path]);
    }

    public function kycStatus(): void
    {
        $userId = $this->requireAuth('freelancer');
        Response::json((new UserRepository())->listUserKycSubmissions($userId));
    }

    public function credentialsStatus(): void
    {
        $userId = $this->requireAuth('freelancer');
        $profile = $this->freelancers->getProfile($userId);
        Response::json($profile['credentials'] ?? []);
    }

    public function addPortfolio(array $data): void
    {
        $userId = $this->requireAuth('freelancer');
        $niche = $data['niche'] ?? 'other';
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'ipynb', 'docx', 'zip'];
        $path = $this->uploadFile('portfolio', $userId, $allowed);
        $metadata = match ($niche) {
            'data_science' => ['dataset_description' => $data['dataset_description'] ?? '', 'tools_used' => $data['tools_used'] ?? ''],
            'legal' => ['case_type' => $data['case_type'] ?? '', 'jurisdiction' => $data['jurisdiction'] ?? ''],
            'translation' => ['language_pair' => $data['language_pair'] ?? ''],
            default => [],
        };
        $metadata['client_name'] = trim((string) ($data['client_name'] ?? ''));
        $metadata['project_outcome'] = trim((string) ($data['project_outcome'] ?? ''));
        $id = $this->freelancers->addPortfolioItem($userId, [
            'title' => $data['title'] ?? 'Portfolio item',
            'file_path' => $path,
            'niche' => $niche,
            'metadata' => $metadata,
            'is_public' => !empty($data['is_public']) ? 1 : 0,
            'is_confidential' => !empty($data['is_confidential']) ? 1 : 0,
        ]);
        Response::json(['id' => $id, 'file_path' => $path]);
    }

    public function renderPortfolio(): void
    {
        $userId = $this->requireAuth('freelancer');
        $profile = $this->freelancers->getProfile($userId);
        $portfolio = array_map(function ($item) {
            $item['metadata'] = $item['metadata_json'] ? json_decode($item['metadata_json'], true) : [];
            return $item;
        }, $profile['portfolio'] ?? []);
        Response::json($portfolio);
    }

    public function getPublicProfile(): void
    {
        $userId = (int) ($_GET['user_id'] ?? 0);
        $profile = $this->freelancers->getProfile($userId);
        if (!$profile) {
            Response::error('Profile not found', 404);
        }
        $profile['portfolio'] = array_map(function ($item) {
            $metadata = $item['metadata_json'] ? json_decode($item['metadata_json'], true) : [];
            $outcome = $metadata['project_outcome']
                ?? $metadata['dataset_description']
                ?? $metadata['case_type']
                ?? $metadata['language_pair']
                ?? 'Project outcome not provided';
            if ((int) $item['is_confidential'] === 1) {
                return [
                    'id' => $item['id'],
                    'niche' => $item['niche'],
                    'project_outcome' => $outcome,
                    'date' => $item['created_at'],
                    'title' => $item['title'],
                    'client_name' => 'Hidden',
                    'metadata' => array_merge($metadata, ['client_name' => 'Hidden', 'project_outcome' => $outcome]),
                ];
            }
            $item['metadata'] = $metadata;
            $item['client_name'] = $metadata['client_name'] ?? 'Private client';
            $item['project_outcome'] = $outcome;
            return $item;
        }, array_filter($profile['portfolio'] ?? [], fn ($item) => (int) $item['is_public'] === 1));
        Response::json($profile);
    }

    public function togglePortfolioPrivacy(array $data): void
    {
        $userId = $this->requireAuth('freelancer');
        $this->freelancers->setPortfolioPrivacy((int) $data['portfolio_id'], $userId, !empty($data['is_public']), !empty($data['is_confidential']));
        Response::json(['message' => 'Privacy updated']);
    }

    public function availability(): void
    {
        $userId = (int) ($_GET['freelancer_id'] ?? $this->requireAuth('freelancer'));
        Response::json($this->syncAvailability($userId));
    }

    public function setAvailability(array $data): void
    {
        $userId = $this->requireAuth('freelancer');
        $slots = is_array($data['slots'] ?? null) ? $data['slots'] : [];
        $this->freelancers->replaceAvailability($userId, $slots);
        Response::json(['message' => 'Availability updated']);
    }

    public function syncAvailability(int $freelancerId): array
    {
        $slots = $this->freelancers->getAvailability($freelancerId);
        $viewerId = $_SESSION['user_id'] ?? null;
        $viewer = $viewerId ? (new UserRepository())->findById((int) $viewerId) : null;
        $timezone = $viewer['timezone'] ?? 'UTC';
        $viewerTz = new DateTimeZone($timezone);
        $utc = new DateTimeZone('UTC');
        $weekStartSunday = new DateTimeImmutable('1970-01-04', $utc);

        return array_map(function ($slot) use ($viewerTz, $utc, $weekStartSunday) {
            $dow = max(0, min(6, (int) $slot['day_of_week']));
            $day = $weekStartSunday->modify('+' . $dow . ' days');
            $datePart = $day->format('Y-m-d');
            $startUtc = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $datePart . ' ' . self::normalizeTimeForUtc($slot['start_time_utc']),
                $utc
            );
            $endUtc = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $datePart . ' ' . self::normalizeTimeForUtc($slot['end_time_utc']),
                $utc
            );
            if (!$startUtc || !$endUtc) {
                return [
                    'day_of_week' => $slot['day_of_week'],
                    'start' => '00:00',
                    'end' => '00:00',
                    'viewer_timezone' => $viewerTz->getName(),
                ];
            }
            $startLocal = $startUtc->setTimezone($viewerTz);
            $endLocal = $endUtc->setTimezone($viewerTz);

            return [
                'day_of_week' => $slot['day_of_week'],
                'start' => $startLocal->format('H:i'),
                'end' => $endLocal->format('H:i'),
                'viewer_timezone' => $viewerTz->getName(),
            ];
        }, $slots);
    }

    private static function normalizeTimeForUtc(mixed $time): string
    {
        $s = trim((string) $time);
        if ($s === '') {
            return '00:00:00';
        }
        if (preg_match('/^\d{1,2}:\d{2}$/', $s)) {
            return $s . ':00';
        }

        return $s;
    }

    public function search(): void
    {
        $niche = $_GET['niche'] ?? 'other';
        $keywords = array_filter(array_map('trim', explode(',', $_GET['keywords'] ?? '')));
        $required = !empty($_GET['certification_required']);
        Response::json($this->matcher->rankFreelancers($niche, $keywords, $required));
    }

    public function reputation(): void
    {
        $userId = (int) ($_GET['freelancer_id'] ?? $this->requireAuth('freelancer'));
        Response::json((new ReputationService())->calculate($userId));
    }
}
