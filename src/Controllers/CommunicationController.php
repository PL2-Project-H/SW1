<?php
class CommunicationController {
    private $service;

    public function __construct() {
        $this->service = new CommunicationService();
    }

    public function postAnnouncement($in, $user) {
        return $this->service->postAnnouncement($in['course_id'] ?? 0, $user['id'], $in['title'] ?? '', $in['body'] ?? '');
    }

    public function announcements($in) {
        return $this->service->announcements($in['course_id'] ?? 0);
    }

    public function sendMessage($in, $user) {
        return $this->service->sendMessage($in['course_id'] ?? 0, $user['id'], $in['to_user_id'] ?? 0, $in['body'] ?? '');
    }

    public function thread($in, $user) {
        return $this->service->thread($in['course_id'] ?? 0, $user['id'], $in['other_user_id'] ?? 0);
    }

    public function inbox($user) {
        return $this->service->inbox($user['id']);
    }

    public function notifications($user) {
        return $this->service->notifications($user['id']);
    }

    public function markRead($in, $user) {
        return $this->service->markRead($in['id'] ?? 0, $user['id'], $in['is_read'] ?? 1);
    }
}
