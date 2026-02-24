<?php
class CourseRepository {
    private $db;

    public function __construct() {
        $this->db = DB::conn();
    }

    public function all() {
        $sql = 'SELECT c.*, u.name faculty_name FROM courses c JOIN users u ON u.id=c.faculty_id ORDER BY c.id';
        return $this->db->query($sql)->fetchAll();
    }

    public function byId($id) {
        $st = $this->db->prepare('SELECT c.*, u.name faculty_name FROM courses c JOIN users u ON u.id=c.faculty_id WHERE c.id=?');
        $st->execute([$id]);
        return $st->fetch();
    }

    public function create($code, $title, $description, $facultyId) {
        $st = $this->db->prepare('INSERT INTO courses (code,title,description,faculty_id) VALUES (?,?,?,?)');
        $st->execute([$code, $title, $description, $facultyId]);
        return $this->db->lastInsertId();
    }

    public function update($id, $code, $title, $description, $facultyId) {
        $st = $this->db->prepare('UPDATE courses SET code=?, title=?, description=?, faculty_id=? WHERE id=?');
        return $st->execute([$code, $title, $description, $facultyId, $id]);
    }

    public function delete($id) {
        $st = $this->db->prepare('DELETE FROM courses WHERE id=?');
        return $st->execute([$id]);
    }

    public function enroll($courseId, $studentId) {
        $st = $this->db->prepare('INSERT IGNORE INTO enrollments (course_id,student_id) VALUES (?,?)');
        return $st->execute([$courseId, $studentId]);
    }

    public function unenroll($courseId, $studentId) {
        $st = $this->db->prepare('DELETE FROM enrollments WHERE course_id=? AND student_id=?');
        return $st->execute([$courseId, $studentId]);
    }

    public function enrolledCourseIds($studentId) {
        $st = $this->db->prepare('SELECT course_id FROM enrollments WHERE student_id=?');
        $st->execute([$studentId]);
        return array_map(fn($r) => (int)$r['course_id'], $st->fetchAll());
    }

    public function byStudent($studentId) {
        $st = $this->db->prepare('SELECT c.*,u.name faculty_name FROM enrollments e JOIN courses c ON c.id=e.course_id JOIN users u ON u.id=c.faculty_id WHERE e.student_id=? ORDER BY c.title');
        $st->execute([$studentId]);
        return $st->fetchAll();
    }

    public function byFaculty($facultyId) {
        $st = $this->db->prepare('SELECT DISTINCT c.*,u.name faculty_name FROM courses c JOIN users u ON u.id=c.faculty_id LEFT JOIN co_teaching ct ON ct.course_id=c.id WHERE c.faculty_id=? OR ct.faculty_id=? ORDER BY c.title');
        $st->execute([$facultyId, $facultyId]);
        return $st->fetchAll();
    }

    public function roster($courseId) {
        $st = $this->db->prepare('SELECT u.id,u.name,u.email FROM enrollments e JOIN users u ON u.id=e.student_id WHERE e.course_id=? ORDER BY u.name');
        $st->execute([$courseId]);
        return $st->fetchAll();
    }

    public function facultyAssociated($facultyId) {
        $st = $this->db->prepare('SELECT DISTINCT c.id,c.title FROM courses c LEFT JOIN co_teaching ct ON ct.course_id=c.id WHERE c.faculty_id=? OR ct.faculty_id=?');
        $st->execute([$facultyId, $facultyId]);
        return $st->fetchAll();
    }

    public function otherFacultyInCourse($courseId, $facultyId) {
        $st = $this->db->prepare("SELECT DISTINCT u.id,u.name FROM users u WHERE u.role='faculty' AND u.id IN (
            SELECT faculty_id FROM courses WHERE id=?
            UNION SELECT faculty_id FROM co_teaching WHERE course_id=?
        ) AND u.id<>?");
        $st->execute([$courseId, $courseId, $facultyId]);
        return $st->fetchAll();
    }
}
