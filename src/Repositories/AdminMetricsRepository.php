<?php

class AdminMetricsRepository extends BaseRepository
{
    public function dashboardMetrics(): array
    {
        $active = (int) ($this->fetch('SELECT COUNT(*) AS c FROM contracts WHERE status = "active"')['c'] ?? 0);
        $activeValue = (float) ($this->fetch('SELECT COALESCE(SUM(total_amount), 0) AS s FROM contracts WHERE status = "active"')['s'] ?? 0);
        $escrowed = (float) ($this->fetch('SELECT COALESCE(SUM(amount),0) AS s FROM escrow_transactions WHERE type = "lock" AND status = "pending"')['s'] ?? 0);
        $openDisputes = (int) ($this->fetch('SELECT COUNT(*) AS c FROM disputes WHERE status IN ("open", "in_mediation")')['c'] ?? 0);
        $newUsers7 = (int) ($this->fetch('SELECT COUNT(*) AS c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')['c'] ?? 0);
        $newUsers30 = (int) ($this->fetch('SELECT COUNT(*) AS c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')['c'] ?? 0);
        $completed = (int) ($this->fetch('SELECT COUNT(*) AS c FROM milestones WHERE approved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')['c'] ?? 0);

        return [
            'active_contracts' => $active,
            'active_contract_value' => $activeValue,
            'total_escrowed' => $escrowed,
            'open_disputes' => $openDisputes,
            'dispute_rate' => $active > 0 ? round(($openDisputes / $active) * 100, 2) : 0,
            'new_users_last_7_days' => $newUsers7,
            'new_users_last_30_days' => $newUsers30,
            'completed_milestones_this_week' => $completed,
        ];
    }

    public function nicheReport(): array
    {
        $rows = $this->fetchAllRows(
            'SELECT jp.niche,
                    SUM(CASE WHEN c.status = "active" THEN 1 ELSE 0 END) AS active_contracts,
                    SUM(CASE WHEN c.status IN ("completed", "final_resolved") THEN 1 ELSE 0 END) AS completed_contracts,
                    COALESCE(SUM(CASE WHEN et.type = "release" THEN et.amount ELSE 0 END), 0) AS total_revenue,
                    AVG(c.total_amount) AS average_contract_value,
                    (SUM(CASE WHEN d.id IS NOT NULL THEN 1 ELSE 0 END) / NULLIF(COUNT(c.id), 0)) * 100 AS dispute_rate
             FROM job_posts jp
             LEFT JOIN contracts c ON c.job_id = jp.id
             LEFT JOIN disputes d ON d.contract_id = c.id
             LEFT JOIN escrow_transactions et ON et.contract_id = c.id
             GROUP BY jp.niche
             ORDER BY total_revenue DESC'
        );
        foreach ($rows as &$row) {
            $row['top_rated_freelancers'] = $this->fetchAllRows(
                'SELECT u.name, rs.composite_score
                 FROM freelancer_profiles fp
                 JOIN users u ON u.id = fp.user_id
                 LEFT JOIN reputation_scores rs ON rs.user_id = u.id
                 WHERE fp.niche = ?
                 ORDER BY rs.composite_score DESC, u.name ASC
                 LIMIT 3',
                [$row['niche']]
            );
        }
        return $rows;
    }
}
