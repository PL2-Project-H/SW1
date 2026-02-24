<?php
class CommunicationRepository {
    private $db;

    public function __construct() {
        $this->db = DB::conn();
    }

    public function addAnnouncement($courseId, $userId, $title, $body) {
        $st = $this->db->prepare('INSERT INTO announcements (course_id,user_id,title,body,created_at) VALUES (?,?,?,?,NOW())');
        $st->execute([$courseId, $userId, $title, $body]);
        return $this->db->lastInsertId();
    }

    public function announcementsByCourse($courseId) {
        $st = $this->db->prepare('SELECT a.*,u.name FROM announcements a JOIN users u ON u.id=a.user_id WHERE course_id=? ORDER BY created_at DESC');
        $st->execute([$courseId]);
        return $st->fetchAll();
    }

    public function addMessage($courseId, $from, $to, $body) {
        $st = $this->db->prepare('INSERT INTO messages (course_id,from_user_id,to_user_id,body,created_at) VALUES (?,?,?,?,NOW())');
        $st->execute([$courseId, $from, $to, $body]);
        return $this->db->lastInsertId();
    }

    public function thread($courseId, $a, $b) {
        $st = $this->db->prepare('SELECT m.*,u1.name from_name,u2.name to_name FROM messages m JOIN users u1 ON u1.id=m.from_user_id JOIN users u2 ON u2.id=m.to_user_id WHERE m.course_id=? AND ((m.from_user_id=? AND m.to_user_id=?) OR (m.from_user_id=? AND m.to_user_id=?)) ORDER BY m.created_at');
        $st->execute([$courseId, $a, $b, $b, $a]);
        return $st->fetchAll();
    }

    public function inbox($userId) {
        $st = $this->db->prepare('SELECT m.*,c.title course_title,u1.name from_name,u2.name to_name FROM messages m JOIN courses c ON c.id=m.course_id JOIN users u1 ON u1.id=m.from_user_id JOIN users u2 ON u2.id=m.to_user_id WHERE m.from_user_id=? OR m.to_user_id=? ORDER BY m.created_at DESC');
        $st->execute([$userId, $userId]);
        return $st->fetchAll();
    }

    public function notify($userId, $type, $refId, $text) {
        $st = $this->db->prepare('INSERT INTO notifications (user_id,type,ref_id,text,is_read,created_at) VALUES (?,?,?,?,0,NOW())');
        return $st->execute([$userId, $type, $refId, $text]);
    }

    public function notifications($userId) {
        $st = $this->db->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC');
        $st->execute([$userId]);
        return $st->fetchAll();
    }

    public function markRead($id, $userId, $isRead) {
        $st = $this->db->prepare('UPDATE notifications SET is_read=? WHERE id=? AND user_id=?');
        return $st->execute([$isRead, $id, $userId]);
    }
}
