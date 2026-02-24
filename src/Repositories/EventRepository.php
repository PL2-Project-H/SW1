<?php
class EventRepository {
    private $db;

    public function __construct() {
        $this->db = DB::conn();
    }

    public function create($courseId, $title, $startsAt, $location, $description) {
        $st = $this->db->prepare('INSERT INTO events (course_id,title,starts_at,location,description) VALUES (?,?,?,?,?)');
        $st->execute([$courseId, $title, $startsAt, $location, $description]);
        return $this->db->lastInsertId();
    }

    public function byCourse($courseId) {
        $st = $this->db->prepare('SELECT * FROM events WHERE course_id=? ORDER BY starts_at');
        $st->execute([$courseId]);
        return $st->fetchAll();
    }

    public function upcomingByStudent($studentId) {
        $sql = 'SELECT e.*, c.title course_title FROM events e JOIN courses c ON c.id=e.course_id JOIN enrollments en ON en.course_id=e.course_id WHERE en.student_id=? ORDER BY e.starts_at';
        $st = $this->db->prepare($sql);
        $st->execute([$studentId]);
        return $st->fetchAll();
    }
}
