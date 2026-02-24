<?php
class EvaluationController {
    private $service;

    public function __construct() {
        $this->service = new EvaluationService();
    }

    public function questions() {
        return $this->service->questions();
    }

    public function addQuestion($in) {
        return $this->service->addQuestion($in['text'] ?? '', $in['active'] ?? 1);
    }

    public function updateQuestion($in) {
        return $this->service->updateQuestion($in['id'] ?? 0, $in['text'] ?? '', $in['active'] ?? 1);
    }

    public function deleteQuestion($in) {
        return $this->service->deleteQuestion($in['id'] ?? 0);
    }

    public function submit($in, $user) {
        return $this->service->submitEvaluation($in['course_id'] ?? 0, $in['faculty_id'] ?? 0, $user['id'], $user['role'], $in['answers'] ?? []);
    }

    public function targets($user) {
        return $this->service->evaluationTargets($user);
    }

    public function facultyRatings($user) {
        return $this->service->facultyRatings($user['id']);
    }
}
