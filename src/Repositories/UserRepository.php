<?php
class UserRepository {
    private $db;

    public function __construct() {
        $this->db = DB::conn();
    }

    public function create($name, $email, $password, $role) {
        $st = $this->db->prepare('INSERT INTO users (name,email,password_hash,role,active) VALUES (?,?,?,?,1)');
        $st->execute([$name, $email, $password, $role]);
        return $this->db->lastInsertId();
    }

    public function byEmail($email) {
        $st = $this->db->prepare('SELECT * FROM users WHERE email=?');
        $st->execute([$email]);
        return $st->fetch();
    }

    public function byId($id) {
        $st = $this->db->prepare('SELECT id,name,email,role,active FROM users WHERE id=?');
        $st->execute([$id]);
        return $st->fetch();
    }

    public function all() {
        return $this->db->query('SELECT id,name,email,role,active FROM users ORDER BY id')->fetchAll();
    }

    public function updateProfile($id, $name, $email) {
        $st = $this->db->prepare('UPDATE users SET name=?, email=? WHERE id=?');
        return $st->execute([$name, $email, $id]);
    }

    public function updatePassword($id, $password) {
        $st = $this->db->prepare('UPDATE users SET password_hash=? WHERE id=?');
        return $st->execute([$password, $id]);
    }

    public function delete($id) {
        $st = $this->db->prepare('DELETE FROM users WHERE id=?');
        return $st->execute([$id]);
    }

    public function setRole($id, $role) {
        $st = $this->db->prepare('UPDATE users SET role=? WHERE id=?');
        return $st->execute([$role, $id]);
    }

    public function setActive($id, $active) {
        $st = $this->db->prepare('UPDATE users SET active=? WHERE id=?');
        return $st->execute([$active, $id]);
    }

    public function facultyList() {
        $st = $this->db->query("SELECT id,name,email FROM users WHERE role='faculty' AND active=1 ORDER BY name");
        return $st->fetchAll();
    }

    public function studentsInCourse($courseId) {
        $st = $this->db->prepare('SELECT u.id,u.name,u.email FROM enrollments e JOIN users u ON u.id=e.student_id WHERE e.course_id=? ORDER BY u.name');
        $st->execute([$courseId]);
        return $st->fetchAll();
    }
}
