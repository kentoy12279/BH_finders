<?php
require 'db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') { echo json_encode(['error'=>'Unauthorized']); exit; }
$owner_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? ($_GET['action'] ?? 'fetch');
if ($action === 'fetch') {
    // fetch recent messages
    $limit = intval($_GET['limit'] ?? 12);
    $stmt = $mysqli->prepare("SELECT m.*, p.title, u.name AS student_name FROM messages m JOIN posts p ON m.post_id = p.id JOIN users u ON m.student_id = u.id WHERE p.owner_id = ? ORDER BY m.created_at DESC LIMIT ?");
    $stmt->bind_param('ii', $owner_id, $limit);
    $stmt->execute(); $res = $stmt->get_result();
    $messages = [];
    while ($r = $res->fetch_assoc()) {
        $messages[] = $r;
    }
    // unread count
    $uc = $mysqli->prepare("SELECT COUNT(*) AS c FROM messages m JOIN posts p ON m.post_id = p.id WHERE p.owner_id = ? AND (m.owner_reply IS NULL OR m.owner_reply = '') AND m.is_resolved = 0");
    $uc->bind_param('i',$owner_id); $uc->execute(); $ur = $uc->get_result()->fetch_assoc(); $unread = intval($ur['c'] ?? 0);
    echo json_encode(['messages'=>$messages,'unread'=>$unread]);
    exit;
} elseif ($action === 'reply') {
    // CSRF check
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) { echo json_encode(['error'=>'Invalid CSRF']); exit; }
    $id = intval($_POST['id'] ?? 0);
    $reply = trim($_POST['reply'] ?? '');
    $resolve = isset($_POST['resolve']) && $_POST['resolve'] ? 1 : 0;
    $upd = $mysqli->prepare("UPDATE messages SET owner_reply = ?, is_resolved = ?, owner_read = 1, student_read = 0 WHERE id = ? AND owner_id = ?");
    $upd->bind_param('siii', $reply, $resolve, $id, $owner_id);
    if ($upd->execute()) {
        // return updated message and unread count
        $mstmt = $mysqli->prepare("SELECT m.*, p.title, u.name AS student_name FROM messages m JOIN posts p ON m.post_id = p.id JOIN users u ON m.student_id = u.id WHERE m.id = ? AND p.owner_id = ?");
        $mstmt->bind_param('ii',$id,$owner_id); $mstmt->execute(); $mres = $mstmt->get_result(); $m = $mres->fetch_assoc();
        $uc = $mysqli->prepare("SELECT COUNT(*) AS c FROM messages m JOIN posts p ON m.post_id = p.id WHERE p.owner_id = ? AND m.owner_read = 0 AND m.is_resolved = 0");
        $uc->bind_param('i',$owner_id); $uc->execute(); $ur = $uc->get_result()->fetch_assoc(); $unread = intval($ur['c'] ?? 0);
        echo json_encode(['success'=>true,'message'=>$m,'unread'=>$unread]);
        exit;
    } else {
        echo json_encode(['error'=>'Update failed']); exit;
    }
} else {
    echo json_encode(['error'=>'Unknown action']); exit;
}
