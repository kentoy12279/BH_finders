<?php
require 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') { header('Location: login.php'); exit; }
$owner_id = $_SESSION['user_id'];
$post_id = intval($_GET['id'] ?? 0);
if (!$post_id) die('Invalid post');

// fetch post to verify ownership
$stmt = $mysqli->prepare("SELECT id FROM posts WHERE id = ? AND owner_id = ?");
$stmt->bind_param('ii', $post_id, $owner_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res->fetch_assoc()) die('Post not found or access denied.');
$stmt->close();

$err = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) { $err = 'Invalid form submission.'; }
    else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $status = $_POST['status'] ?? 'inactive';
        $methods = $_POST['payment_methods'] ?? [];
        $location = trim($_POST['location'] ?? '');
        $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
        $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
        $room_count = intval($_POST['room_count'] ?? 0);
        $room_type = trim($_POST['room_type'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $amenities = $_POST['amenities'] ?? [];

        if (!$title || !$description || $price <= 0) { $err = 'Title, description and monthly price are required.'; }
        else {
            $mysqli->begin_transaction();
            try {
                // update post using prepared statement
                $methodsCsv = is_array($methods) ? implode(',', $methods) : '';
                $amenJson = json_encode(array_values($amenities));
                $stmt = $mysqli->prepare("UPDATE posts SET title=?, description=?, price=?, status=?, payment_methods=?, location=?, latitude=?, longitude=?, amenities=?, room_count=?, room_type=?, contact=? WHERE id=? AND owner_id=?");
                $stmt->bind_param('ssdssssssissii', $title, $description, $price, $status, $methodsCsv, $location, $latitude, $longitude, $amenJson, $room_count, $room_type, $contact, $post_id, $owner_id);
                if (!$stmt->execute()) throw new Exception('Update failed: ' . $stmt->error);
                $stmt->close();

                // handle deletions of existing images
                if (!empty($_POST['delete_image']) && is_array($_POST['delete_image'])) {
                    $delIds = array_map('intval', $_POST['delete_image']);
                    if ($delIds) {
                        $in = implode(',', $delIds);
                        $rows = $mysqli->query("SELECT id,file_path FROM post_images WHERE id IN ($in) AND post_id = $post_id");
                        while ($r = $rows->fetch_assoc()) {
                            @unlink(dirname(__DIR__) . '/' . $r['file_path']);
                        }
                        $mysqli->query("DELETE FROM post_images WHERE id IN ($in) AND post_id = $post_id");
                    }
                }

                // handle adding new images
                $maxAllowed = 12;
                $cntRow = $mysqli->query("SELECT COUNT(*) AS c FROM post_images WHERE post_id = $post_id")->fetch_assoc();
                $current = intval($cntRow['c']);
                $filesCount = max(0, isset($_FILES['images']['name']) ? count($_FILES['images']['name']) : 0);
                $toAdd = max(0, min($maxAllowed - $current, $filesCount));
                if ($toAdd > 0 && !empty($_FILES['images']['name'][0])) {
                    $allowed = ['image/jpeg', 'image/png', 'image/gif'];
                    $insImg = $mysqli->prepare("INSERT INTO post_images (post_id,file_path,is_primary,sort_order) VALUES (?,?,?,?)");
                    $sort = $current;
                    for ($i = 0; $i < $filesCount; $i++) {
                        if ($toAdd <= 0) break;
                        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                        $tmp = $_FILES['images']['tmp_name'][$i];
                        $mime = mime_content_type($tmp);
                        $size = $_FILES['images']['size'][$i] ?? 0;
                        if (!in_array($mime, $allowed) || $size > 2 * 1024 * 1024) continue;
                        $ext = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                        $newName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                        $dest = __DIR__ . '/uploads/' . $newName;
                        if (move_uploaded_file($tmp, $dest)) {
                            $filePath = 'uploads/' . $newName;
                            $isPrimary = 0;
                            $insImg->bind_param('isii', $post_id, $filePath, $isPrimary, $sort);
                            $insImg->execute();
                            $sort++;
                            $toAdd--;
                        }
                    }
                    $insImg->close();
                }

                $mysqli->commit();
                $msg = 'Post updated.';
                // redirect back to edit_post.php with success
                header('Location: edit_post.php?id=' . $post_id . '&updated=1');
                exit;

            } catch (Exception $e) {
                $mysqli->rollback();
                $err = $e->getMessage();
            }
        }
    }
}

// If not POST or error, redirect back to edit_post.php with error
if ($err) {
    header('Location: edit_post.php?id=' . $post_id . '&error=' . urlencode($err));
} else {
    header('Location: edit_post.php?id=' . $post_id);
}
exit;
?>
