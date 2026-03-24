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
            if ((int) $item['is_confidential'] === 1) {
                return [
                    'id' => $item['id'],
                    'niche' => $item['niche'],
                    'success_metric' => $metadata['dataset_description'] ?? $metadata['case_type'] ?? $metadata['language_pair'] ?? 'Confidential project',
                    'date' => $item['created_at'],
                    'title' => 'Confidential project',
                    'client' => 'Hidden',
                ];
            }
            $item['metadata'] = $metadata;
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
        $tz = new DateTimeZone($timezone);
        return array_map(function ($slot) use ($tz) {
            $baseStart = new DateTime('1970-01-0' . ((int) $slot['day_of_week'] + 4) . ' ' . $slot['start_time_utc'], new DateTimeZone('UTC'));
            $baseEnd = new DateTime('1970-01-0' . ((int) $slot['day_of_week'] + 4) . ' ' . $slot['end_time_utc'], new DateTimeZone('UTC'));
            $baseStart->setTimezone($tz);
            $baseEnd->setTimezone($tz);
            return [
                'day_of_week' => $slot['day_of_week'],
                'start' => $baseStart->format('H:i'),
                'end' => $baseEnd->format('H:i'),
                'viewer_timezone' => $tz->getName(),
            ];
        }, $slots);
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
