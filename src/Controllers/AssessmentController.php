<?php
class AssessmentController {
    private $service;

    public function __construct() {
        $this->service = new AssessmentService();
    }

    public function create($in) {
        return $this->service->createAssessment($in['course_id'] ?? 0, $in['title'] ?? '', $in['open_at'] ?? '', $in['close_at'] ?? '', $in['total_points'] ?? 0);
    }

    public function addQuestion($in) {
        return $this->service->addQuestion($in['assessment_id'] ?? 0, $in['type'] ?? 'mcq', $in['prompt'] ?? '', $in['choices'] ?? [], $in['correct_answer'] ?? null);
    }

    public function listByCourse($in) {
        return $this->service->listByCourse($in['course_id'] ?? 0);
    }

    public function listForStudent($user) {
        return $this->service->listForStudent($user['id']);
    }

    public function detail($in) {
        return $this->service->getAssessmentWithQuestions($in['assessment_id'] ?? 0);
    }

    public function submit($in, $user) {
        return $this->service->submit($in['assessment_id'] ?? 0, $user['id'], $in['answers'] ?? []);
    }

    public function submissions($in) {
        return $this->service->submissions($in['assessment_id'] ?? 0);
    }

    public function submissionDetails($in) {
        return $this->service->submissionDetails($in['submission_id'] ?? 0);
    }

    public function grade($in) {
        return $this->service->gradeShortAnswers($in['submission_id'] ?? 0, $in['grades'] ?? []);
    }

    public function publish($in) {
        return $this->service->publishAssessment($in['assessment_id'] ?? 0, $in['is_published'] ?? 0);
    }

    public function transcript($user) {
        return $this->service->transcript($user['id']);
    }

    public function certificate($in, $user) {
        return $this->service->certificate($in['course_id'] ?? 0, $user['id']);
    }

    public function report() {
        return $this->service->report();
    }
}
