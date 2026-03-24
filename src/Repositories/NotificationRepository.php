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
}
