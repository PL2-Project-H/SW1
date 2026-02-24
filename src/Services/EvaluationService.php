<?php
class EvaluationService {
    private $repo;
    private $courses;
    private $comm;

    public function __construct() {
        $this->repo = new EvaluationRepository();
        $this->courses = new CourseRepository();
        $this->comm = new CommunicationRepository();
    }

    public function questions() {
        return [true, $this->repo->questions(), null];
    }

    public function addQuestion($text, $active) {
        $id = $this->repo->addQuestion($text, $active);
        return [true, ['id' => $id], null];
    }

    public function updateQuestion($id, $text, $active) {
        $this->repo->updateQuestion($id, $text, $active);
        return [true, ['updated' => true], null];
    }

    public function deleteQuestion($id) {
        $this->repo->deleteQuestion($id);
        return [true, ['deleted' => true], null];
    }

    public function submitEvaluation($courseId, $facultyId, $evaluatorId, $role, $answers) {
        $exists = $this->repo->existingEvaluation($courseId, $facultyId, $evaluatorId);
        if ($exists) {
            return [false, null, 'Already evaluated'];
        }
        $evalId = $this->repo->createEvaluation($courseId, $facultyId, $evaluatorId, $role);
        foreach ($answers as $a) {
            $this->repo->addAnswer($evalId, $a['question_id'], $a['rating'], $a['comment'] ?? '');
        }
        $this->comm->notify($facultyId, 'evaluation_published', $evalId, 'A new evaluation was submitted');
        return [true, ['id' => $evalId], null];
    }

    public function evaluationTargets($user) {
        if ($user['role'] === 'student') {
            $courses = $this->courses->byStudent($user['id']);
            $targets = [];
            foreach ($courses as $c) {
                $targets[] = ['course_id' => $c['id'], 'course_title' => $c['title'], 'faculty_id' => $c['faculty_id'], 'faculty_name' => $c['faculty_name']];
            }
            return [true, $targets, null];
        }
        if ($user['role'] === 'admin') {
            $courses = $this->courses->all();
            $targets = [];
            foreach ($courses as $c) {
                $targets[] = ['course_id' => $c['id'], 'course_title' => $c['title'], 'faculty_id' => $c['faculty_id'], 'faculty_name' => $c['faculty_name']];
            }
            return [true, $targets, null];
        }
        $courses = $this->courses->byFaculty($user['id']);
        $targets = [];
        foreach ($courses as $c) {
            $others = $this->courses->otherFacultyInCourse($c['id'], $user['id']);
            foreach ($others as $f) {
                $targets[] = ['course_id' => $c['id'], 'course_title' => $c['title'], 'faculty_id' => $f['id'], 'faculty_name' => $f['name']];
            }
        }
        return [true, $targets, null];
    }

    public function facultyRatings($facultyId) {
        $overall = $this->repo->overallFacultyRating($facultyId);
        $byRole = $this->repo->ratingsForFaculty($facultyId);
        $comments = $this->repo->commentsForFaculty($facultyId);
        return [true, ['overall' => $overall, 'by_role' => $byRole, 'comments' => $comments], null];
    }
}
