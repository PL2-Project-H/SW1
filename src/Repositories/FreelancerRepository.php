<?php

class FreelancerRepository extends BaseRepository
{
    public function getProfile(int $userId): ?array
    {
        $profile = $this->fetch(
            'SELECT fp.*, u.name, u.email, u.country, u.timezone AS user_timezone, u.kyc_status
             FROM freelancer_profiles fp
             JOIN users u ON u.id = fp.user_id
             WHERE fp.user_id = ?',
            [$userId]
        );
        if (!$profile) {
            return null;
        }

        $profile['skills'] = $this->fetchAllRows(
            'SELECT s.* FROM freelancer_skills fs JOIN skills s ON s.id = fs.skill_id WHERE fs.freelancer_id = ? ORDER BY s.name',
            [$userId]
        );
        $profile['credentials'] = $this->fetchAllRows('SELECT * FROM credentials WHERE freelancer_id = ? ORDER BY submitted_at DESC', [$userId]);
        $profile['portfolio'] = $this->fetchAllRows('SELECT * FROM portfolio_items WHERE freelancer_id = ? ORDER BY created_at DESC', [$userId]);
        $profile['availability'] = $this->fetchAllRows('SELECT * FROM freelancer_availability WHERE freelancer_id = ? ORDER BY day_of_week, start_time_utc', [$userId]);
        $profile['reputation'] = $this->fetch('SELECT * FROM reputation_scores WHERE user_id = ?', [$userId]);
        $profile['kyc_submissions'] = $this->fetchAllRows('SELECT * FROM kyc_submissions WHERE user_id = ? ORDER BY created_at DESC', [$userId]);
        return $profile;
    }

    public function updateProfile(int $userId, array $data): void
    {
        $this->execute(
            'UPDATE freelancer_profiles SET bio = ?, niche = ?, hourly_rate = ?, timezone = ?, linkedin_url = ?, availability_status = ? WHERE user_id = ?',
            [
                $data['bio'] ?? '',
                $data['niche'] ?? 'other',
                $data['hourly_rate'] ?? 0,
                $data['timezone'] ?? 'UTC',
                $data['linkedin_url'] ?? null,
                $data['availability_status'] ?? 'open',
                $userId,
            ]
        );
        if (array_key_exists('digest_opt_in', $data)) {
            $this->execute('UPDATE freelancer_profiles SET digest_opt_in = ? WHERE user_id = ?', [!empty($data['digest_opt_in']) ? 1 : 0, $userId]);
        }
    }

    public function replaceSkills(int $userId, array $skills, string $niche): void
    {
        $this->execute('DELETE FROM freelancer_skills WHERE freelancer_id = ?', [$userId]);
        foreach ($skills as $skillName) {
            $skillName = trim($skillName);
            if ($skillName === '') {
                continue;
            }
            $skill = $this->fetch('SELECT id FROM skills WHERE name = ?', [$skillName]);
            $skillId = $skill ? (int) $skill['id'] : $this->insert('INSERT INTO skills (name, niche) VALUES (?, ?)', [$skillName, $niche]);
            $this->execute('INSERT IGNORE INTO freelancer_skills (freelancer_id, skill_id) VALUES (?, ?)', [$userId, $skillId]);
        }
    }

    public function addCredential(int $freelancerId, string $type, string $filePath, array $metadata = []): int
    {
        return $this->insert(
            'INSERT INTO credentials (freelancer_id, type, file_path, metadata_json) VALUES (?, ?, ?, ?)',
            [$freelancerId, $type, $filePath, json_encode($metadata)]
        );
    }

    public function updateCredentialStatus(int $credentialId, string $status, int $reviewerId): void
    {
        $this->execute('UPDATE credentials SET status = ?, reviewed_at = NOW(), reviewer_id = ? WHERE id = ?', [$status, $reviewerId, $credentialId]);
    }

    public function markCredentialUnderReview(int $credentialId, int $reviewerId): void
    {
        $this->execute('UPDATE credentials SET status = ?, reviewer_id = ? WHERE id = ?', ['under_review', $reviewerId, $credentialId]);
    }

    public function setVerifiedFlag(int $freelancerId, bool $value): void
    {
        $this->execute('UPDATE freelancer_profiles SET is_verified = ? WHERE user_id = ?', [$value ? 1 : 0, $freelancerId]);
    }

    public function listPendingCredentials(): array
    {
        return $this->fetchAllRows(
            'SELECT c.*, u.name AS freelancer_name, u.email
             FROM credentials c
             JOIN users u ON u.id = c.freelancer_id
             WHERE c.status IN ("pending", "under_review")
             ORDER BY c.submitted_at ASC'
        );
    }

    public function addPortfolioItem(int $freelancerId, array $data): int
    {
        return $this->insert(
            'INSERT INTO portfolio_items (freelancer_id, title, file_path, niche, metadata_json, is_public, is_confidential) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $freelancerId,
                $data['title'],
                $data['file_path'],
                $data['niche'],
                json_encode($data['metadata'] ?? []),
                $data['is_public'] ?? 1,
                $data['is_confidential'] ?? 0,
            ]
        );
    }

    public function setPortfolioPrivacy(int $itemId, int $freelancerId, bool $isPublic, bool $isConfidential): void
    {
        $this->execute('UPDATE portfolio_items SET is_public = ?, is_confidential = ? WHERE id = ? AND freelancer_id = ?', [$isPublic ? 1 : 0, $isConfidential ? 1 : 0, $itemId, $freelancerId]);
    }

    public function replaceAvailability(int $freelancerId, array $slots): void
    {
        $this->execute('DELETE FROM freelancer_availability WHERE freelancer_id = ?', [$freelancerId]);
        foreach ($slots as $slot) {
            $this->insert(
                'INSERT INTO freelancer_availability (freelancer_id, day_of_week, start_time_utc, end_time_utc) VALUES (?, ?, ?, ?)',
                [$freelancerId, $slot['day_of_week'], $slot['start_time_utc'], $slot['end_time_utc']]
            );
        }
    }

    public function getAvailability(int $freelancerId): array
    {
        return $this->fetchAllRows('SELECT * FROM freelancer_availability WHERE freelancer_id = ? ORDER BY day_of_week, start_time_utc', [$freelancerId]);
    }

    public function listFreelancers(): array
    {
        return $this->fetchAllRows(
            'SELECT u.id, u.name, u.country, u.timezone, fp.niche, fp.bio, fp.hourly_rate, fp.is_verified, rs.composite_score,
                    fp.digest_opt_in,
                    COALESCE(project_stats.completed_projects, 0) AS completed_projects,
                    COALESCE(rs.composite_score, 0) AS search_reputation_score
             FROM users u
             JOIN freelancer_profiles fp ON fp.user_id = u.id
             LEFT JOIN reputation_scores rs ON rs.user_id = u.id
             LEFT JOIN (
                 SELECT c.freelancer_id, COUNT(*) AS completed_projects
                 FROM contracts c
                 WHERE c.status IN ("completed", "final_resolved")
                 GROUP BY c.freelancer_id
             ) AS project_stats ON project_stats.freelancer_id = u.id
             WHERE u.role = "freelancer" AND u.status = "active"'
        );
    }

    public function getSearchCache(): array
    {
        return $this->fetchAllRows('SELECT * FROM search_cache');
    }

    public function listSearchCacheByNiche(string $niche): array
    {
        return $this->fetchAllRows(
            'SELECT sc.freelancer_id AS id, sc.niche, sc.keyword_blob, sc.skills_blob, sc.completed_projects,
                    sc.reputation_score, sc.score AS cached_score, u.name, u.country, u.timezone,
                    fp.bio, fp.is_verified, fp.hourly_rate, fp.digest_opt_in,
                    COALESCE(rs.composite_score, 0) AS composite_score
             FROM search_cache sc
             INNER JOIN users u ON u.id = sc.freelancer_id
             INNER JOIN freelancer_profiles fp ON fp.user_id = u.id
             LEFT JOIN reputation_scores rs ON rs.user_id = u.id
             WHERE sc.niche = ? AND u.role = "freelancer" AND u.status = "active"
             ORDER BY sc.score DESC',
            [$niche]
        );
    }

    public function upsertSearchCache(int $freelancerId, string $niche, string $keywords, string $skills, int $completedProjects, float $reputationScore, float $score): void
    {
        $existing = $this->fetch('SELECT id FROM search_cache WHERE freelancer_id = ?', [$freelancerId]);
        if ($existing) {
            $this->execute(
                'UPDATE search_cache SET niche = ?, keyword_blob = ?, skills_blob = ?, completed_projects = ?, reputation_score = ?, score = ?, updated_at = NOW() WHERE freelancer_id = ?',
                [$niche, $keywords, $skills, $completedProjects, $reputationScore, $score, $freelancerId]
            );
            return;
        }
        $this->insert(
            'INSERT INTO search_cache (freelancer_id, niche, keyword_blob, skills_blob, completed_projects, reputation_score, score) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$freelancerId, $niche, $keywords, $skills, $completedProjects, $reputationScore, $score]
        );
    }
}
