<?php
require 'db.php';
// Only allow students
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header('Location: login.php'); exit; }
$student_id = $_SESSION['user_id'];
$post_id = intval($_GET['post_id'] ?? 0);
$msg = '';

// Fetch post to get owner info
$stmt = $mysqli->prepare("SELECT owner_id, title FROM posts WHERE id = ? AND status='active'");
$stmt->bind_param('i', $post_id);
$stmt->execute();
$result = $stmt->get_result();
$post = $result->fetch_assoc();
$stmt->close();

if (!$post) {
    die('Post not found or inactive.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message_content = trim($_POST['content'] ?? '');
    if ($message_content) {
        $ins = $mysqli->prepare("INSERT INTO messages (post_id, student_id, owner_id, content, student_read, owner_read) VALUES (?,?,?,?,1,0)");
        $ins->bind_param('iiis', $post_id, $student_id, $post['owner_id'], $message_content);
        if ($ins->execute()) {
            $msg = 'Message sent successfully.';
        } else {
            $msg = 'Error sending message.';
        }
        $ins->close();
    } else {
        $msg = 'Message cannot be empty.';
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Message Owner</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container col-md-6">
    <h4>Message Owner about: <?= esc($post['title']) ?></h4>
    <?php if ($msg): ?>
        <div class="alert alert-info"><?= esc($msg) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="mb-3">
            <textarea name="content" class="form-control" rows="6" required></textarea>
        </div>
        <button class="btn btn-primary">Send</button>
        <a href="student-dashboard.php" class="btn btn-link">Back</a>
    </form>
</div>
</body>
</html>
