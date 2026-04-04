<?php

class SkillMatchingService
{
    private FreelancerRepository $freelancers;
    private Database $database;

    public function __construct()
    {
        $this->freelancers = new FreelancerRepository();
        $this->database = Database::getInstance();
    }

    public function rankFreelancers(string $niche, array $keywords = [], bool $certificationRequired = false): array
    {
        $cached = $this->freelancers->listSearchCacheByNiche($niche);
        if ($cached !== []) {
            return $this->rankFromCache($cached, $niche, $keywords, $certificationRequired);
        }

        return $this->rankFreshAndPopulateCache($niche, $keywords, $certificationRequired);
    }

    private function rankFromCache(array $cached, string $niche, array $keywords, bool $certificationRequired): array
    {
        $results = [];
        foreach ($cached as $freelancer) {
            if ($certificationRequired && !(int) $freelancer['is_verified']) {
                continue;
            }
            $skillNames = array_filter(array_map('trim', explode(',', strtolower((string) ($freelancer['skills_blob'] ?? '')))));
            $keywordOverlap = 0;
            foreach ($keywords as $keyword) {
                $kw = strtolower(trim((string) $keyword));
                if ($kw === '') {
                    continue;
                }
                if (in_array($kw, $skillNames, true) || str_contains(strtolower((string) ($freelancer['bio'] ?? '')), $kw)) {
                    $keywordOverlap++;
                }
            }
            $keywordScore = count($keywords) > 0 ? $keywordOverlap / count($keywords) : 0;
            $nicheMatch = ($freelancer['niche'] ?? '') === $niche ? 1 : 0;
            $reputation = ((float) $freelancer['composite_score']) / 100;
            $completedProjects = (int) ($freelancer['completed_projects'] ?? 0);
            $completionScore = min($completedProjects / 10, 1);
            $verified = (int) $freelancer['is_verified'] ? 1 : 0;
            $score = count($keywords) === 0
                ? (float) $freelancer['cached_score']
                : round(($nicheMatch * 0.35) + ($keywordScore * 0.25) + ($reputation * 0.2) + ($completionScore * 0.1) + ($verified * 0.1), 3);
            $freelancer['score'] = $score;
            $results[] = $freelancer;
        }
        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $results;
    }

    private function rankFreshAndPopulateCache(string $niche, array $keywords, bool $certificationRequired): array
    {
        $pdo = $this->database->getConnection();
        $freelancers = $this->freelancers->listFreelancers();
        $results = [];
        foreach ($freelancers as $freelancer) {
            if ($certificationRequired && !(int) $freelancer['is_verified']) {
                continue;
            }
            $skillStmt = $pdo->prepare('SELECT s.name FROM freelancer_skills fs JOIN skills s ON s.id = fs.skill_id WHERE fs.freelancer_id = ?');
            $skillStmt->execute([$freelancer['id']]);
            $skillNames = array_map(fn ($row) => strtolower($row['name']), $skillStmt->fetchAll());
            $keywordOverlap = 0;
            foreach ($keywords as $keyword) {
                if (in_array(strtolower($keyword), $skillNames, true) || str_contains(strtolower($freelancer['bio'] ?? ''), strtolower($keyword))) {
                    $keywordOverlap++;
                }
            }
            $keywordScore = count($keywords) > 0 ? $keywordOverlap / count($keywords) : 0;
            $nicheMatch = $freelancer['niche'] === $niche ? 1 : 0;
            $reputation = ((float) $freelancer['composite_score']) / 100;
            $completedProjects = (int) ($freelancer['completed_projects'] ?? 0);
            $completionScore = min($completedProjects / 10, 1);
            $verified = (int) $freelancer['is_verified'] ? 1 : 0;
            $score = round(($nicheMatch * 0.35) + ($keywordScore * 0.25) + ($reputation * 0.2) + ($completionScore * 0.1) + ($verified * 0.1), 3);
            $this->freelancers->upsertSearchCache(
                (int) $freelancer['id'],
                $freelancer['niche'],
                implode(',', array_merge($skillNames, $keywords)),
                implode(',', $skillNames),
                $completedProjects,
                (float) $freelancer['composite_score'],
                $score
            );
            $freelancer['score'] = $score;
            $results[] = $freelancer;
        }
        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $results;
    }
}
