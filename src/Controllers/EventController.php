<?php
class EventController {
    private $service;

    public function __construct() {
        $this->service = new EventService();
    }

    public function create($in) {
        return $this->service->create($in['course_id'] ?? 0, $in['title'] ?? '', $in['starts_at'] ?? '', $in['location'] ?? '', $in['description'] ?? '');
    }

    public function byCourse($in) {
        return $this->service->byCourse($in['course_id'] ?? 0);
    }

    public function upcomingForStudent($user) {
        return $this->service->upcomingForStudent($user['id']);
    }
}
