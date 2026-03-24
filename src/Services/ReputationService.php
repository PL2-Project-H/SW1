<?php

class ReputationService
{
    private Database $database;

    public function __construct()
    {
        $this->database = Database::getInstance();
    }

    public function calculate(int $freelancerId): array
    {
        $pdo = $this->database->getConnection();

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN approved_at IS NOT NULL AND approved_at <= due_date THEN 1 ELSE 0 END) AS on_time,
                    SUM(COALESCE(d.revision_count, 0)) AS revisions
             FROM milestones m
             JOIN contracts c ON c.id = m.contract_id
             LEFT JOIN deliverables d ON d.milestone_id = m.id
             WHERE c.freelancer_id = ? AND m.status IN ("approved", "auto_approved")'
        );
        $stmt->execute([$freelancerId]);
        $stats = $stmt->fetch() ?: ['total' => 0, 'on_time' => 0, 'revisions' => 0];

        $ratings = $pdo->prepare('SELECT AVG(score) AS avg_rating FROM ratings WHERE rated_user_id = ?');
        $ratings->execute([$freelancerId]);
        $ratingRow = $ratings->fetch() ?: ['avg_rating' => 0];

        $total = max((int) $stats['total'], 1);
        $punctuality = round((((int) $stats['on_time']) / $total) * 100, 2);
        $quality = round((1 - (((int) $stats['revisions']) / $total)) * 100, 2);
        $authority = round((((float) $ratingRow['avg_rating']) / 5) * 100, 2);
        $composite = round(($punctuality * 0.3) + ($quality * 0.4) + ($authority * 0.3), 2);

        $exists = $pdo->prepare('SELECT id FROM reputation_scores WHERE user_id = ?');
        $exists->execute([$freelancerId]);
        if ($exists->fetch()) {
            $pdo->prepare('UPDATE reputation_scores SET punctuality_score = ?, quality_score = ?, authority_score = ?, composite_score = ? WHERE user_id = ?')
                ->execute([$punctuality, $quality, $authority, $composite, $freelancerId]);
        } else {
            $pdo->prepare('INSERT INTO reputation_scores (user_id, punctuality_score, quality_score, authority_score, composite_score) VALUES (?, ?, ?, ?, ?)')
                ->execute([$freelancerId, $punctuality, $quality, $authority, $composite]);
        }

        return [
            'punctuality_score' => $punctuality,
            'quality_score' => $quality,
            'authority_score' => $authority,
            'composite_score' => $composite,
        ];
    }
}
