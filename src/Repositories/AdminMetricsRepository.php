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
        $rows = $this->fetchAllRows('SELECT DISTINCT niche FROM job_posts ORDER BY niche ASC');
        foreach ($rows as &$row) {
            $niche = $row['niche'];
            $row['active_jobs'] = (int) ($this->fetch(
                'SELECT COUNT(*) AS c FROM job_posts WHERE niche = ? AND status IN ("open", "private", "awarded")',
                [$niche]
            )['c'] ?? 0);
            $row['active_contracts'] = (int) ($this->fetch(
                'SELECT COUNT(*) AS c
                 FROM contracts c
                 JOIN job_posts jp ON jp.id = c.job_id
                 WHERE jp.niche = ? AND c.status = "active"',
                [$niche]
            )['c'] ?? 0);
            $row['average_contract_value'] = (float) ($this->fetch(
                'SELECT COALESCE(AVG(c.total_amount), 0) AS avg_value
                 FROM contracts c
                 JOIN job_posts jp ON jp.id = c.job_id
                 WHERE jp.niche = ?',
                [$niche]
            )['avg_value'] ?? 0);
            $row['total_revenue'] = (float) ($this->fetch(
                'SELECT COALESCE(SUM(et.amount), 0) AS total_revenue
                 FROM escrow_transactions et
                 JOIN contracts c ON c.id = et.contract_id
                 JOIN job_posts jp ON jp.id = c.job_id
                 WHERE jp.niche = ? AND et.type = "release"',
                [$niche]
            )['total_revenue'] ?? 0);
            $row['dispute_rate'] = (float) ($this->fetch(
                'SELECT COALESCE((COUNT(DISTINCT d.id) / NULLIF(COUNT(DISTINCT c.id), 0)) * 100, 0) AS dispute_rate
                 FROM contracts c
                 JOIN job_posts jp ON jp.id = c.job_id
                 LEFT JOIN disputes d ON d.contract_id = c.id
                 WHERE jp.niche = ?',
                [$niche]
            )['dispute_rate'] ?? 0);
            $row['top_rated_freelancers'] = $this->fetchAllRows(
                'SELECT u.name, rs.composite_score
                 FROM freelancer_profiles fp
                 JOIN users u ON u.id = fp.user_id
                 LEFT JOIN reputation_scores rs ON rs.user_id = u.id
                 WHERE fp.niche = ?
                 ORDER BY rs.composite_score DESC, u.name ASC
                 LIMIT 3',
                [$niche]
            );
        }
        usort($rows, fn ($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);
        return $rows;
    }
}
