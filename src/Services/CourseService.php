<?php
class CourseService {
    private $courses;
    private $events;
    private $comm;

    public function __construct() {
        $this->courses = new CourseRepository();
        $this->events = new EventRepository();
        $this->comm = new CommunicationRepository();
    }

    public function listAll($user) {
        $courses = $this->courses->all();
        $enrolled = [];
        if ($user && $user['role'] === 'student') {
            $enrolled = $this->courses->enrolledCourseIds($user['id']);
        }
        return [true, ['courses' => $courses, 'enrolled_ids' => $enrolled], null];
    }

    public function create($code, $title, $description, $facultyId) {
        $id = $this->courses->create($code, $title, $description, $facultyId);
        return [true, ['id' => $id], null];
    }

    public function update($id, $code, $title, $description, $facultyId) {
        $this->courses->update($id, $code, $title, $description, $facultyId);
        return [true, ['updated' => true], null];
    }

    public function delete($id) {
        $this->courses->delete($id);
        return [true, ['deleted' => true], null];
    }

    public function enroll($courseId, $studentId) {
        $this->courses->enroll($courseId, $studentId);
        return [true, ['enrolled' => true], null];
    }

    public function unenroll($courseId, $studentId) {
        $this->courses->unenroll($courseId, $studentId);
        return [true, ['unenrolled' => true], null];
    }

    public function facultyCourses($facultyId) {
        return [true, $this->courses->byFaculty($facultyId), null];
    }

    public function studentCourses($studentId) {
        return [true, $this->courses->byStudent($studentId), null];
    }

    public function dashboard($courseId) {
        $course = $this->courses->byId($courseId);
        $roster = $this->courses->roster($courseId);
        $events = $this->events->byCourse($courseId);
        $announcements = $this->comm->announcementsByCourse($courseId);
        $assessments = (new AssessmentRepository())->listByCourse($courseId);
        return [true, ['course' => $course, 'roster' => $roster, 'events' => $events, 'announcements' => $announcements, 'assessments' => $assessments], null];
    }
}
