<?php
class CommunicationService {
    private $repo;
    private $courses;

    public function __construct() {
        $this->repo = new CommunicationRepository();
        $this->courses = new CourseRepository();
    }

    public function postAnnouncement($courseId, $userId, $title, $body) {
        $id = $this->repo->addAnnouncement($courseId, $userId, $title, $body);
        $students = $this->courses->roster($courseId);
        foreach ($students as $s) {
            $this->repo->notify($s['id'], 'announcement_posted', $id, 'New announcement posted');
        }
        return [true, ['id' => $id], null];
    }

    public function announcements($courseId) {
        return [true, $this->repo->announcementsByCourse($courseId), null];
    }

    public function sendMessage($courseId, $from, $to, $body) {
        $id = $this->repo->addMessage($courseId, $from, $to, $body);
        $this->repo->notify($to, 'message_received', $id, 'You received a new message');
        return [true, ['id' => $id], null];
    }

    public function thread($courseId, $a, $b) {
        return [true, $this->repo->thread($courseId, $a, $b), null];
    }

    public function inbox($userId) {
        return [true, $this->repo->inbox($userId), null];
    }

    public function notifications($userId) {
        return [true, $this->repo->notifications($userId), null];
    }

    public function markRead($id, $userId, $isRead) {
        $this->repo->markRead($id, $userId, $isRead);
        return [true, ['updated' => true], null];
    }
}
