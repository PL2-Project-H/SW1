<?php
class AssessmentService {
    private $repo;
    private $comm;

    public function __construct() {
        $this->repo = new AssessmentRepository();
        $this->comm = new CommunicationRepository();
    }

    public function createAssessment($courseId, $title, $openAt, $closeAt, $totalPoints) {
        $id = $this->repo->createAssessment($courseId, $title, $openAt, $closeAt, $totalPoints);
        return [true, ['id' => $id], null];
    }

    public function addQuestion($assessmentId, $type, $prompt, $choices, $correctAnswer) {
        $choicesJson = $choices ? json_encode($choices) : null;
        $id = $this->repo->addQuestion($assessmentId, $type, $prompt, $choicesJson, $correctAnswer);
        return [true, ['id' => $id], null];
    }

    public function listByCourse($courseId) {
        return [true, $this->repo->listByCourse($courseId), null];
    }

    public function listForStudent($studentId) {
        return [true, $this->repo->listForStudent($studentId), null];
    }

    public function getAssessmentWithQuestions($assessmentId) {
        $a = $this->repo->assessment($assessmentId);
        $q = $this->repo->questions($assessmentId);
        foreach ($q as &$item) {
            if ($item['choices_json']) {
                $item['choices'] = json_decode($item['choices_json'], true);
            } else {
                $item['choices'] = [];
            }
            unset($item['choices_json']);
            unset($item['correct_answer']);
        }
        return [true, ['assessment' => $a, 'questions' => $q], null];
    }

    public function submit($assessmentId, $studentId, $answers) {
        $a = $this->repo->assessment($assessmentId);
        $now = date('Y-m-d H:i:s');
        if ($now < $a['open_at'] || $now > $a['close_at']) {
            return [false, null, 'Assessment window closed'];
        }
        $questions = $this->repo->questions($assessmentId);
        $submissionId = $this->repo->upsertSubmission($assessmentId, $studentId);
        $score = 0;
        foreach ($questions as $q) {
            $qid = $q['id'];
            $ans = $answers[$qid] ?? '';
            $points = 0;
            if ($q['type'] === 'mcq') {
                if ((string)$ans === (string)$q['correct_answer']) {
                    $points = 1;
                }
                $score += $points;
            }
            $this->repo->addSubmissionAnswer($submissionId, $qid, $ans, $points);
        }
        $this->repo->setSubmissionScore($submissionId, $score, 'submitted');
        return [true, ['submission_id' => $submissionId, 'auto_score' => $score], null];
    }

    public function submissions($assessmentId) {
        return [true, $this->repo->submissionsByAssessment($assessmentId), null];
    }

    public function submissionDetails($submissionId) {
        $submission = $this->repo->submissionById($submissionId);
        $answers = $this->repo->submissionAnswers($submissionId);
        return [true, ['submission' => $submission, 'answers' => $answers], null];
    }

    public function gradeShortAnswers($submissionId, $grades) {
        foreach ($grades as $answerId => $points) {
            $this->repo->updateAnswerPoints($answerId, $points);
        }
        $score = $this->repo->recalcSubmissionScore($submissionId);
        $sub = $this->repo->submissionById($submissionId);
        $assessment = $this->repo->assessment($sub['assessment_id']);
        $this->repo->updateFinalGrade($assessment['course_id'], $sub['student_id'], $score);
        $this->repo->maybeIssueCertificate($assessment['course_id'], $sub['student_id'], $score);
        $this->comm->notify($sub['student_id'], 'assessment_published', $sub['assessment_id'], 'Your submission was graded');
        return [true, ['score' => $score], null];
    }

    public function publishAssessment($assessmentId, $isPublished) {
        $this->repo->publishAssessment($assessmentId, $isPublished);
        return [true, ['updated' => true], null];
    }

    public function transcript($studentId) {
        $courses = $this->repo->transcript($studentId);
        foreach ($courses as &$course) {
            $course['assessments'] = $this->repo->assessmentBreakdown($course['course_id'], $studentId);
        }
        return [true, $courses, null];
    }

    public function certificate($courseId, $studentId) {
        $cert = $this->repo->certificate($courseId, $studentId);
        if (!$cert) {
            return [false, null, 'No certificate'];
        }
        return [true, $cert, null];
    }

    public function report() {
        return [true, $this->repo->reportCoursePerformance(), null];
    }
}
