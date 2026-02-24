<?php
class EvaluationRepository {
    private $db;

    public function __construct() {
        $this->db = DB::conn();
    }

    public function questions() {
        return $this->db->query('SELECT * FROM eval_questions ORDER BY id')->fetchAll();
    }

    public function addQuestion($text, $active) {
        $st = $this->db->prepare('INSERT INTO eval_questions (text,active) VALUES (?,?)');
        $st->execute([$text, $active]);
        return $this->db->lastInsertId();
    }

    public function updateQuestion($id, $text, $active) {
        $st = $this->db->prepare('UPDATE eval_questions SET text=?,active=? WHERE id=?');
        return $st->execute([$text, $active, $id]);
    }

    public function deleteQuestion($id) {
        $st = $this->db->prepare('DELETE FROM eval_questions WHERE id=?');
        return $st->execute([$id]);
    }

    public function createEvaluation($courseId, $facultyId, $evaluatorId, $role) {
        $st = $this->db->prepare('INSERT INTO evaluations (course_id,faculty_id,evaluator_user_id,evaluator_role,created_at) VALUES (?,?,?,?,NOW())');
        $st->execute([$courseId, $facultyId, $evaluatorId, $role]);
        return $this->db->lastInsertId();
    }

    public function existingEvaluation($courseId, $facultyId, $evaluatorId) {
        $st = $this->db->prepare('SELECT * FROM evaluations WHERE course_id=? AND faculty_id=? AND evaluator_user_id=?');
        $st->execute([$courseId, $facultyId, $evaluatorId]);
        return $st->fetch();
    }

    public function addAnswer($evaluationId, $questionId, $rating, $comment) {
        $st = $this->db->prepare('INSERT INTO evaluation_answers (evaluation_id,question_id,rating,comment) VALUES (?,?,?,?)');
        return $st->execute([$evaluationId, $questionId, $rating, $comment]);
    }

    public function ratingsForFaculty($facultyId) {
        $sql = 'SELECT e.evaluator_role,AVG(a.rating) avg_rating,COUNT(*) total_answers FROM evaluations e JOIN evaluation_answers a ON a.evaluation_id=e.id WHERE e.faculty_id=? GROUP BY e.evaluator_role';
        $st = $this->db->prepare($sql);
        $st->execute([$facultyId]);
        return $st->fetchAll();
    }

    public function overallFacultyRating($facultyId) {
        $st = $this->db->prepare('SELECT COALESCE(AVG(a.rating),0) avg_rating,COUNT(*) total_answers FROM evaluations e JOIN evaluation_answers a ON a.evaluation_id=e.id WHERE e.faculty_id=?');
        $st->execute([$facultyId]);
        return $st->fetch();
    }

    public function commentsForFaculty($facultyId) {
        $sql = 'SELECT a.comment,e.course_id,c.title course_title,e.evaluator_role,u.name evaluator_name FROM evaluation_answers a JOIN evaluations e ON e.id=a.evaluation_id JOIN courses c ON c.id=e.course_id JOIN users u ON u.id=e.evaluator_user_id WHERE e.faculty_id=? AND a.comment IS NOT NULL AND a.comment<>"" ORDER BY a.id DESC';
        $st = $this->db->prepare($sql);
        $st->execute([$facultyId]);
        return $st->fetchAll();
    }
}
