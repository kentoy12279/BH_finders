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
<head>
  <meta charset="utf-8">
  <title>Inbox</title>
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/owner-dashboard.css">
  <style>
    .chat-container { max-height: 600px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background-color: #f8f9fa; }
    .message { margin-bottom: 15px; }
    .message.sent { text-align: right; }
    .message.received { text-align: left; }
    .message-bubble { display: inline-block; max-width: 70%; padding: 10px; border-radius: 10px; }
    .message.sent .message-bubble { background-color: #007bff; color: white; }
    .message.received .message-bubble { background-color: #e9ecef; color: black; }
    .message-time { font-size: 0.8em; color: #666; margin-top: 5px; }
    .conversation { margin-bottom: 30px; border-bottom: 1px solid #ddd; padding-bottom: 20px; }
    .conversation-header { font-weight: bold; margin-bottom: 10px; }
    .reply-form { margin-top: 10px; }
  </style>
</head>
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

  <?php
  // Group messages by post and student
  $conversations = [];
  $res->data_seek(0); // Reset result pointer
  while($m = $res->fetch_assoc()) {
    $key = $m['post_id'] . '-' . $m['student_id'];
    if (!isset($conversations[$key])) {
      $conversations[$key] = [
        'post_title' => $m['title'],
        'student_name' => $m['student_name'],
        'messages' => []
      ];
    }
    $conversations[$key]['messages'][] = $m;
  }
  ?>

  <?php foreach($conversations as $conv): ?>
    <div class="conversation">
      <div class="conversation-header">
        Conversation with <?= esc($conv['student_name']) ?> about: <a href="view_post.php?id=<?= intval($conv['messages'][0]['post_id']) ?>"><?= esc($conv['post_title']) ?></a>
      </div>
      <div class="chat-container">
        <?php foreach($conv['messages'] as $msg): ?>
          <div class="message received">
            <div class="message-bubble">
              <strong>Student:</strong> <?= nl2br(esc($msg['content'])) ?>
            </div>
            <div class="message-time"><?= esc($msg['created_at']) ?></div>
          </div>
          <?php if (!empty($msg['owner_reply'])): ?>
            <div class="message sent">
              <div class="message-bubble">
                <strong>You:</strong> <?= nl2br(esc($msg['owner_reply'])) ?>
              </div>
              <div class="message-time"><?= esc($msg['created_at']) ?></div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <div class="reply-form">
        <form method="post">
          <input type="hidden" name="reply_to" value="<?= intval($conv['messages'][0]['id']) ?>">
          <div class="input-group">
            <textarea name="owner_reply" class="form-control" rows="2" placeholder="Type your reply..." required></textarea>
            <div class="input-group-append">
              <div class="form-check d-flex align-items-center me-2">
                <input class="form-check-input" type="checkbox" id="res<?= intval($conv['messages'][0]['id']) ?>" name="resolve" value="1">
                <label class="form-check-label ms-1" for="res<?= intval($conv['messages'][0]['id']) ?>">Resolve</label>
              </div>
              <button class="btn btn-primary" type="submit">Send</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
</body>
</html>
