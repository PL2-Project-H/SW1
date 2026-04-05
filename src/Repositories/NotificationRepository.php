<?php

class NotificationRepository extends BaseRepository
{
    public function create(int $userId, string $type, string $message, ?array $payload = null): int
    {
        return $this->insert('INSERT INTO notifications (user_id, type, message, payload_json) VALUES (?, ?, ?, ?)', [$userId, $type, $message, $payload ? json_encode($payload) : null]);
    }

    public function unreadForUser(int $userId): array
    {
        return $this->fetchAllRows('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30', [$userId]);
    }

    public function existsRecentByType(int $userId, string $type, int $hours): bool
    {
        $row = $this->fetch(
            'SELECT id FROM notifications WHERE user_id = ? AND type = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR) ORDER BY created_at DESC LIMIT 1',
            [$userId, $type, $hours]
        );

        return $row !== null;
    }
}
