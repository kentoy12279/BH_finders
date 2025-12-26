<?php
require 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header('Location: login.php'); exit; }
$student_id = $_SESSION['user_id'];
$info = '';

// Handle reply from student (creates a new message record)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_post_id'])) {
    $post_id = intval($_POST['reply_post_id']);
    $content = trim($_POST['content'] ?? '');
    if ($content) {
        $ps = $mysqli->prepare("SELECT owner_id FROM posts WHERE id = ?");
        $ps->bind_param('i', $post_id); $ps->execute(); $post = $ps->get_result()->fetch_assoc(); $ps->close();
        if ($post) {
            $ins = $mysqli->prepare("INSERT INTO messages (post_id, student_id, owner_id, content, student_read, owner_read) VALUES (?, ?, ?, ?, 1, 0)");
            $ins->bind_param('iiis', $post_id, $student_id, $post['owner_id'], $content);
            if ($ins->execute()) $info = 'Message sent.'; else $info = 'Error sending message.';
            $ins->close();
        } else {
            $info = 'Post not found.';
        }
    } else {
        $info = 'Message cannot be empty.';
    }
}

// (No auto-marking here; replies are marked read when viewing a conversation)

// Get filter
$filter = ($_GET['filter'] ?? '') === 'unread' ? 'unread' : '';

// Fetch threads grouped by post
$sql = "SELECT p.id AS post_id, p.title, u.name AS owner_name, MAX(m.created_at) AS last_activity, " .
       "(SELECT m2.content FROM messages m2 WHERE m2.post_id = p.id AND m2.student_id = ? ORDER BY m2.created_at DESC LIMIT 1) AS last_student_msg, " .
       "(SELECT m3.owner_reply FROM messages m3 WHERE m3.post_id = p.id AND m3.student_id = ? AND m3.owner_reply IS NOT NULL AND m3.owner_reply <> '' ORDER BY m3.created_at DESC LIMIT 1) AS last_owner_reply, " .
       "SUM(m.owner_reply IS NOT NULL AND m.owner_reply <> '' AND m.student_read = 0) AS unread_count, " .
       "SUM(m.is_resolved = 0) AS unresolved_count " .
       "FROM posts p JOIN messages m ON m.post_id = p.id JOIN users u ON p.owner_id = u.id " .
       "WHERE m.student_id = ? GROUP BY p.id " .
       "ORDER BY last_activity DESC";

if ($filter === 'unread') {
    // We'll apply HAVING unread_count > 0 after the group by - easier to do by wrapping in a subquery, but for simplicity fetch all and filter in PHP
}

$stmt = $mysqli->prepare($sql);
// bind params for the two subqueries and the main WHERE
$stmt->bind_param('iii', $student_id, $student_id, $student_id);
$stmt->execute(); $res = $stmt->get_result();

// Unread threads count (for badge)
$uc = $mysqli->prepare("SELECT COUNT(*) AS c FROM messages WHERE student_id = ? AND owner_reply IS NOT NULL AND owner_reply <> '' AND student_read = 0");
$uc->bind_param('i', $student_id); $uc->execute(); $student_unread = intval($uc->get_result()->fetch_assoc()['c'] ?? 0);
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Inbox</title><link rel="stylesheet" href="css/bootstrap.min.css"></head>
<body class="p-4">
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Inbox <?php if($student_unread>0): ?><span class="badge bg-danger ms-2"><?= $student_unread ?></span><?php endif; ?></h3>
    <div><a href="student-dashboard.php" class="btn btn-sm btn-secondary">Back</a></div>
  </div>

  <?php if($info): ?><div class="alert alert-info"><?=esc($info)?></div><?php endif; ?>

  <div class="mb-3 d-flex align-items-center">
    <div class="me-3">Filter:</div>
    <div>
      <a class="btn btn-sm btn-outline-secondary me-1 <?=($filter==='' ? 'active' : '')?>" href="student_inbox.php">All</a>
      <a class="btn btn-sm btn-outline-secondary <?=($filter==='unread' ? 'active' : '')?>" href="student_inbox.php?filter=unread">Unread only</a>
    </div>
  </div>

  <table class="table table-bordered">
    <thead><tr><th>Post</th><th>Owner</th><th>Last Student</th><th>Last Owner Reply</th><th>Last Activity</th><th>Action</th></tr></thead>
    <tbody>
      <?php while($row = $res->fetch_assoc()): ?>
        <?php if($filter === 'unread' && intval($row['unread_count']) === 0) continue; ?>
      <tr class="<?=($row['unresolved_count']>0 ? 'table-warning' : '')?>">
        <td><a href="view_post.php?id=<?=intval($row['post_id'])?>"><?=esc($row['title'])?></a></td>
        <td><?=esc($row['owner_name'])?></td>
        <td><?=nl2br(esc($row['last_student_msg'] ?? ''))?></td>
        <td>
          <?php if(!empty($row['last_owner_reply'])): ?>
            <?=nl2br(esc($row['last_owner_reply']))?>
            <?php if(intval($row['unread_count'])>0): ?> <span class="badge bg-danger ms-1">new</span><?php endif; ?>
          <?php else: ?>
            <div class="text-muted">No reply yet.</div>
          <?php endif; ?>
        </td>
        <td><?=esc($row['last_activity'])?></td>
        <td>
          <a href="student_conversation.php?post_id=<?=intval($row['post_id'])?>" class="btn btn-sm btn-outline-primary mb-1">View Conversation</a>
          <form method="post" class="mb-1">
            <input type="hidden" name="reply_post_id" value="<?=intval($row['post_id'])?>">
            <textarea name="content" class="form-control mb-1" rows="2" placeholder="Quick reply..." required></textarea>
            <button class="btn btn-sm btn-primary">Send</button>
          </form>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>