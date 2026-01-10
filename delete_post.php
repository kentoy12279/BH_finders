<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header('Location: login.php');
    exit;
}

$owner_id = $_SESSION['user_id'];
$post_id  = intval($_GET['id'] ?? 0);

if (!$post_id) {
    die('Invalid post.');
}

/**
 * Ensure CSRF token exists
 */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/**
 * Verify ownership
 */
$stmt = $mysqli->prepare("
    SELECT id, title
    FROM posts
    WHERE id = ? AND owner_id = ?
");
$stmt->bind_param('ii', $post_id, $owner_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$post) {
    die('Post not found or access denied.');
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF validation
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        $err = 'Invalid request token.';
    } else {

        $mysqli->begin_transaction();

        try {

            /**
             * Delete image files from disk
             */
            $imgStmt = $mysqli->prepare("
                SELECT file_path
                FROM post_images
                WHERE post_id = ?
            ");
            $imgStmt->bind_param('i', $post_id);
            $imgStmt->execute();
            $imgs = $imgStmt->get_result();

            while ($img = $imgs->fetch_assoc()) {
                $path = __DIR__ . '/' . $img['file_path'];
                if (is_file($path)) {
                    unlink($path);
                }
            }
            $imgStmt->close();

            /**
             * Delete post
             * (Bookings, images, etc. should be ON DELETE CASCADE)
             */
            $del = $mysqli->prepare("
                DELETE FROM posts
                WHERE id = ? AND owner_id = ?
            ");
            $del->bind_param('ii', $post_id, $owner_id);

            if (!$del->execute()) {
                throw new Exception('Database delete failed.');
            }

            $del->close();
            $mysqli->commit();

            header('Location: owner-dashboard.php?deleted=1');
            exit;

        } catch (Exception $e) {
            $mysqli->rollback();
            $err = 'Failed to delete post. Please try again.';
        }
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delete Post</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/owner-dashboard.css">
</head>
<body class="p-4">
<div class="container col-md-6">

    <h4 class="mb-3">Delete Post</h4>
    <p class="fw-bold"><?= esc($post['title']) ?></p>

    <?php if ($err): ?>
        <div class="alert alert-danger"><?= esc($err) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>">

        <p class="text-danger">
            Are you sure you want to delete this post?<br>
            <strong>This action cannot be undone.</strong>
        </p>

        <button class="btn btn-danger">Yes, Delete</button>
        <a href="owner-dashboard.php" class="btn btn-secondary">Cancel</a>
    </form>

</div>
</body>
</html>
