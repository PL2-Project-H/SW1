<?php
class CourseController {
    private $service;

    public function __construct() {
        $this->service = new CourseService();
    }

    public function listAll($user) {
        return $this->service->listAll($user);
    }

    public function create($in) {
        return $this->service->create($in['code'] ?? '', $in['title'] ?? '', $in['description'] ?? '', $in['faculty_id'] ?? 0);
    }

    public function update($in) {
        return $this->service->update($in['id'] ?? 0, $in['code'] ?? '', $in['title'] ?? '', $in['description'] ?? '', $in['faculty_id'] ?? 0);
    }

    public function delete($in) {
        return $this->service->delete($in['id'] ?? 0);
    }

    public function enroll($in, $user) {
        return $this->service->enroll($in['course_id'] ?? 0, $user['id']);
    }

    public function unenroll($in, $user) {
        return $this->service->unenroll($in['course_id'] ?? 0, $user['id']);
    }

    public function facultyCourses($user) {
        return $this->service->facultyCourses($user['id']);
    }

    public function studentCourses($user) {
        return $this->service->studentCourses($user['id']);
    }

    public function dashboard($in) {
        return $this->service->dashboard($in['course_id'] ?? 0);
    }
}
