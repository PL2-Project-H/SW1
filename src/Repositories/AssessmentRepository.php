<?php
class AssessmentRepository {
    private $db;

    public function __construct() {
        $this->db = DB::conn();
    }

    public function createAssessment($courseId, $title, $openAt, $closeAt, $totalPoints) {
        $st = $this->db->prepare('INSERT INTO assessments (course_id,title,open_at,close_at,total_points,is_published) VALUES (?,?,?,?,?,0)');
        $st->execute([$courseId, $title, $openAt, $closeAt, $totalPoints]);
        return $this->db->lastInsertId();
    }

    public function addQuestion($assessmentId, $type, $prompt, $choicesJson, $correctAnswer) {
        $st = $this->db->prepare('INSERT INTO assessment_questions (assessment_id,type,prompt,choices_json,correct_answer) VALUES (?,?,?,?,?)');
        $st->execute([$assessmentId, $type, $prompt, $choicesJson, $correctAnswer]);
        return $this->db->lastInsertId();
    }

    public function listByCourse($courseId) {
        $st = $this->db->prepare('SELECT * FROM assessments WHERE course_id=? ORDER BY id DESC');
        $st->execute([$courseId]);
        return $st->fetchAll();
    }

    public function listForStudent($studentId) {
        $sql = 'SELECT a.*, c.title course_title FROM assessments a JOIN enrollments e ON e.course_id=a.course_id JOIN courses c ON c.id=a.course_id WHERE e.student_id=? AND a.is_published=1 ORDER BY a.id DESC';
        $st = $this->db->prepare($sql);
        $st->execute([$studentId]);
        return $st->fetchAll();
    }

    public function assessment($id) {
        $st = $this->db->prepare('SELECT * FROM assessments WHERE id=?');
        $st->execute([$id]);
        return $st->fetch();
    }

    public function questions($assessmentId) {
        $st = $this->db->prepare('SELECT * FROM assessment_questions WHERE assessment_id=? ORDER BY id');
        $st->execute([$assessmentId]);
        return $st->fetchAll();
    }

    public function createSubmission($assessmentId, $studentId) {
        $st = $this->db->prepare('INSERT INTO submissions (assessment_id,student_id,submitted_at,score,status) VALUES (?,?,NOW(),0,"submitted")');
        $st->execute([$assessmentId, $studentId]);
        return $this->db->lastInsertId();
    }

    public function upsertSubmission($assessmentId, $studentId) {
        $st = $this->db->prepare('SELECT id FROM submissions WHERE assessment_id=? AND student_id=?');
        $st->execute([$assessmentId, $studentId]);
        $row = $st->fetch();
        if ($row) {
            $st2 = $this->db->prepare('UPDATE submissions SET submitted_at=NOW(), status="submitted" WHERE id=?');
            $st2->execute([$row['id']]);
            $this->db->prepare('DELETE FROM submission_answers WHERE submission_id=?')->execute([$row['id']]);
            return $row['id'];
        }
        return $this->createSubmission($assessmentId, $studentId);
    }

    public function addSubmissionAnswer($submissionId, $questionId, $answerText, $awardedPoints) {
        $st = $this->db->prepare('INSERT INTO submission_answers (submission_id,question_id,answer_text,awarded_points) VALUES (?,?,?,?)');
        return $st->execute([$submissionId, $questionId, $answerText, $awardedPoints]);
    }

    public function setSubmissionScore($submissionId, $score, $status) {
        $st = $this->db->prepare('UPDATE submissions SET score=?, status=? WHERE id=?');
        return $st->execute([$score, $status, $submissionId]);
    }

    public function submissionsByAssessment($assessmentId) {
        $sql = 'SELECT s.*,u.name student_name,u.email student_email FROM submissions s JOIN users u ON u.id=s.student_id WHERE s.assessment_id=? ORDER BY s.submitted_at DESC';
        $st = $this->db->prepare($sql);
        $st->execute([$assessmentId]);
        return $st->fetchAll();
    }

    public function submissionAnswers($submissionId) {
        $sql = 'SELECT sa.*,q.prompt,q.type,q.correct_answer FROM submission_answers sa JOIN assessment_questions q ON q.id=sa.question_id WHERE sa.submission_id=?';
        $st = $this->db->prepare($sql);
        $st->execute([$submissionId]);
        return $st->fetchAll();
    }

    public function publishAssessment($assessmentId, $isPublished) {
        $st = $this->db->prepare('UPDATE assessments SET is_published=? WHERE id=?');
        return $st->execute([$isPublished, $assessmentId]);
    }

    public function updateAnswerPoints($answerId, $points) {
        $st = $this->db->prepare('UPDATE submission_answers SET awarded_points=? WHERE id=?');
        return $st->execute([$points, $answerId]);
    }

    public function submissionById($submissionId) {
        $st = $this->db->prepare('SELECT * FROM submissions WHERE id=?');
        $st->execute([$submissionId]);
        return $st->fetch();
    }

    public function recalcSubmissionScore($submissionId) {
        $st = $this->db->prepare('SELECT COALESCE(SUM(awarded_points),0) total FROM submission_answers WHERE submission_id=?');
        $st->execute([$submissionId]);
        $total = (float)$st->fetch()['total'];
        $this->setSubmissionScore($submissionId, $total, 'graded');
        return $total;
    }

    public function updateFinalGrade($courseId, $studentId, $score) {
        $st = $this->db->prepare('INSERT INTO final_grades (course_id,student_id,final_score) VALUES (?,?,?) ON DUPLICATE KEY UPDATE final_score=VALUES(final_score)');
        return $st->execute([$courseId, $studentId, $score]);
    }

    public function maybeIssueCertificate($courseId, $studentId, $score) {
        if ($score < 60) {
            return;
        }
        $st = $this->db->prepare('SELECT id FROM certificates WHERE course_id=? AND student_id=?');
        $st->execute([$courseId, $studentId]);
        if (!$st->fetch()) {
            $no = 'CERT-' . $courseId . '-' . $studentId . '-' . time();
            $st2 = $this->db->prepare('INSERT INTO certificates (course_id,student_id,certificate_no,issued_at) VALUES (?,?,?,NOW())');
            $st2->execute([$courseId, $studentId, $no]);
        }
    }

    public function transcript($studentId) {
        $sql = 'SELECT c.id course_id,c.code,c.title,COALESCE(fg.final_score,0) final_score FROM courses c JOIN enrollments e ON e.course_id=c.id LEFT JOIN final_grades fg ON fg.course_id=c.id AND fg.student_id=e.student_id WHERE e.student_id=? ORDER BY c.title';
        $st = $this->db->prepare($sql);
        $st->execute([$studentId]);
        return $st->fetchAll();
    }

    public function assessmentBreakdown($courseId, $studentId) {
        $sql = 'SELECT a.id assessment_id,a.title,COALESCE(s.score,0) score,a.total_points FROM assessments a LEFT JOIN submissions s ON s.assessment_id=a.id AND s.student_id=? WHERE a.course_id=?';
        $st = $this->db->prepare($sql);
        $st->execute([$studentId, $courseId]);
        return $st->fetchAll();
    }

    public function certificate($courseId, $studentId) {
        $st = $this->db->prepare('SELECT ct.*,c.title course_title,u.name student_name FROM certificates ct JOIN courses c ON c.id=ct.course_id JOIN users u ON u.id=ct.student_id WHERE ct.course_id=? AND ct.student_id=?');
        $st->execute([$courseId, $studentId]);
        return $st->fetch();
    }

    public function reportCoursePerformance() {
        $sql = 'SELECT c.id,c.code,c.title,COALESCE(AVG(fg.final_score),0) avg_grade,COALESCE(SUM(CASE WHEN fg.final_score>=60 THEN 1 ELSE 0 END)/NULLIF(COUNT(fg.id),0)*100,0) pass_rate FROM courses c LEFT JOIN final_grades fg ON fg.course_id=c.id GROUP BY c.id,c.code,c.title ORDER BY c.id';
        return $this->db->query($sql)->fetchAll();
    }
}
