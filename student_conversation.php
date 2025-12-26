<?php
require 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header('Location: login.php'); exit; }
$student_id = $_SESSION['user_id'];
$post_id = intval($_GET['post_id'] ?? $_POST['post_id'] ?? 0);
if (!$post_id) die('Post not specified.');

// Handle sending a message (student)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $content = trim($_POST['content'] ?? '');
    if ($content) {
        $ps = $mysqli->prepare("SELECT owner_id FROM posts WHERE id = ?");
        $ps->bind_param('i', $post_id); $ps->execute(); $post = $ps->get_result()->fetch_assoc(); $ps->close();
        if ($post) {
            $ins = $mysqli->prepare("INSERT INTO messages (post_id, student_id, owner_id, content, student_read, owner_read) VALUES (?, ?, ?, ?, 1, 0)");
            $ins->bind_param('iiis', $post_id, $student_id, $post['owner_id'], $content);
            $ins->execute(); $ins->close();
        }
    }
    // Redirect to avoid resubmission
    header('Location: student_conversation.php?post_id=' . intval($post_id)); exit;
}

// Mark owner replies for this post as read
$upd = $mysqli->prepare("UPDATE messages SET student_read = 1 WHERE student_id = ? AND post_id = ? AND owner_reply IS NOT NULL AND owner_reply <> '' AND student_read = 0");
$upd->bind_param('ii', $student_id, $post_id); $upd->execute(); $upd->close();

// Fetch conversation messages
$stmt = $mysqli->prepare("SELECT m.*, u.name AS owner_name FROM messages m JOIN users u ON m.owner_id = u.id WHERE m.student_id = ? AND m.post_id = ? ORDER BY m.created_at ASC");
$stmt->bind_param('ii', $student_id, $post_id); $stmt->execute(); $res = $stmt->get_result();

// Fetch post title
$ps = $mysqli->prepare("SELECT title FROM posts WHERE id = ?"); $ps->bind_param('i',$post_id); $ps->execute(); $post = $ps->get_result()->fetch_assoc(); $ps->close();
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Conversation</title><link rel="stylesheet" href="css/bootstrap.min.css"></head>
<body class="p-4">
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Conversation about: <?= esc($post['title'] ?? '') ?></h3>
    <div><a href="student_inbox.php" class="btn btn-sm btn-secondary">Back to Inbox</a></div>
  </div>

  <div class="mb-3">
    <?php while($m = $res->fetch_assoc()): ?>
      <div class="mb-3 p-3 border rounded <?=($m['is_resolved'] ? '' : 'bg-light')?>">
        <div><strong>You</strong> <small class="text-muted"><?=esc($m['created_at'])?></small></div>
        <div class="mt-2"><?=nl2br(esc($m['content']))?></div>
        <?php if(!empty($m['owner_reply'])): ?>
          <div class="mt-3 p-2 border-top">
            <div><strong><?=esc($m['owner_name'])?> (owner)</strong> <small class="text-muted"><?=esc($m['created_at'])?></small></div>
            <div class="mt-2"><?=nl2br(esc($m['owner_reply']))?></div>
          </div>
        <?php endif; ?>
      </div>
    <?php endwhile; ?>
  </div>

  <div>
    <h5>Send a message</h5>
    <form method="post">
      <input type="hidden" name="post_id" value="<?=intval($post_id)?>">
      <div class="mb-3"><textarea name="content" class="form-control" rows="4" required></textarea></div>
      <button class="btn btn-primary">Send</button>
    </form>
  </div>
</div>
</body>
</html>