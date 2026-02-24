<?php
class EventService {
    private $events;

    public function __construct() {
        $this->events = new EventRepository();
    }

    public function create($courseId, $title, $startsAt, $location, $description) {
        $id = $this->events->create($courseId, $title, $startsAt, $location, $description);
        return [true, ['id' => $id], null];
    }

    public function byCourse($courseId) {
        return [true, $this->events->byCourse($courseId), null];
    }

    public function upcomingForStudent($studentId) {
        return [true, $this->events->upcomingByStudent($studentId), null];
    }
}
