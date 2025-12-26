<?php
require 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') { header('Location: login.php'); exit; }
$owner_id = $_SESSION['user_id'];
$info = '';
// Handle replies
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_to'])) {
    $id = intval($_POST['reply_to']);
    $reply = trim($_POST['owner_reply'] ?? '');
    $resolve = isset($_POST['resolve']) ? 1 : 0;
    $upd = $mysqli->prepare("UPDATE messages SET owner_reply = ?, is_resolved = ?, owner_read = 1, student_read = 0 WHERE id = ? AND owner_id = ?");
    $upd->bind_param('siii', $reply, $resolve, $id, $owner_id);
    if ($upd->execute()) $info = 'Reply saved.'; else $info = 'Error saving reply.';
}

// If post_id filter provided
$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : null;
if ($post_id) {
    $stmt = $mysqli->prepare("SELECT m.*, p.title, u.name AS student_name FROM messages m JOIN posts p ON m.post_id = p.id JOIN users u ON m.student_id = u.id WHERE p.owner_id = ? AND p.id = ? ORDER BY m.created_at DESC");
    $stmt->bind_param('ii', $owner_id, $post_id);
} else {
    $stmt = $mysqli->prepare("SELECT m.*, p.title, u.name AS student_name FROM messages m JOIN posts p ON m.post_id = p.id JOIN users u ON m.student_id = u.id WHERE p.owner_id = ? ORDER BY m.created_at DESC");
    $stmt->bind_param('i', $owner_id);
}
$stmt->execute(); $res = $stmt->get_result();
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Inbox</title><link rel="stylesheet" href="css/bootstrap.min.css"></head>
<body class="p-4">
<div class="container">
  <?php
// unread count for owner
$uc = $mysqli->prepare("SELECT COUNT(*) AS c FROM messages m JOIN posts p ON m.post_id = p.id WHERE p.owner_id = ? AND (m.owner_reply IS NULL OR m.owner_reply = '') AND m.is_resolved = 0");
$uc->bind_param('i',$owner_id); $uc->execute(); $ucn = $uc->get_result()->fetch_assoc(); $unread_count = intval($ucn['c'] ?? 0);
?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Owner Inbox <?php if($unread_count>0): ?><span class="badge bg-danger ms-2"><?= $unread_count ?></span><?php endif; ?></h3>
    <div><a href="owner-dashboard.php" class="btn btn-sm btn-secondary">Back</a></div>
  </div>
  <?php if($info): ?><div class="alert alert-info"><?=esc($info)?></div><?php endif; ?>
  <table class="table table-bordered">
    <thead><tr><th>Post</th><th>Student</th><th>Message</th><th>Owner Reply</th><th>When</th><th>Action</th></tr></thead>
    <tbody>
      <?php while($m = $res->fetch_assoc()): ?>
      <tr class="<?=($m['is_resolved'] ? '' : 'table-warning')?>">
        <td><a href="view_post.php?id=<?=intval($m['post_id'])?>"><?=esc($m['title'])?></a></td>
        <td><?=esc($m['student_name'])?></td>
        <td><?=nl2br(esc($m['content']))?></td>
        <td><?=nl2br(esc($m['owner_reply'] ?? ''))?></td>
        <td><?=esc($m['created_at'])?></td>
        <td>
          <form method="post" class="mb-1">
            <input type="hidden" name="reply_to" value="<?=intval($m['id'])?>">
            <textarea name="owner_reply" class="form-control mb-1" rows="3"><?=esc($m['owner_reply'] ?? '')?></textarea>
            <div class="form-check mb-1"><input class="form-check-input" type="checkbox" id="res<?=intval($m['id'])?>" name="resolve" value="1" <?=($m['is_resolved'] ? 'checked' : '')?>><label class="form-check-label" for="res<?=intval($m['id'])?>">Mark resolved</label></div>
            <button class="btn btn-sm btn-primary">Save reply</button>
          </form>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>