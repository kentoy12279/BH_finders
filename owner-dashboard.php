<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header('Location: login.php');
    exit;
}

$owner_id = $_SESSION['user_id'];

/* Pending bookings for ALL posts (top badge only) */
$pc = $mysqli->prepare("
    SELECT COUNT(*) AS c
    FROM bookings b
    JOIN posts p ON b.post_id = p.id
    WHERE p.owner_id = ?
      AND b.status = 'pending'
");
$pc->bind_param('i', $owner_id);
$pc->execute();
$pending_count = intval($pc->get_result()->fetch_assoc()['c'] ?? 0);
$pc->close();

/* Posts list (NO booking count per post) */
$stmt = $mysqli->prepare("
    SELECT 
        p.id,
        p.title,
        p.price,
        p.status,
        p.created_at,
        p.amenities,
        (SELECT file_path
            FROM post_images
            WHERE post_id = p.id
            ORDER BY is_primary DESC, sort_order ASC
            LIMIT 1) AS image,
        (SELECT COUNT(*)
            FROM messages m
            WHERE m.post_id = p.id
              AND (m.owner_reply IS NULL OR m.owner_reply = '')
              AND m.is_resolved = 0) AS unread_count
    FROM posts p
    WHERE p.owner_id = ?
    ORDER BY p.created_at DESC
");
$stmt->bind_param('i', $owner_id);
$stmt->execute();
$res = $stmt->get_result();
?>

<!doctype html>
<html>
<head>
    <link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body class="p-4">

<div class="container">

    <div class="d-flex justify-content-between align-items-center">
        <h3>Owner Dashboard — <?= esc($_SESSION['name']) ?></h3>
        <a href="logout.php" class="btn btn-sm btn-outline-secondary">Logout</a>
    </div>

    <hr>

    <div class="mb-3">
        <a href="create_post.php" class="btn btn-success">Create Post</a>

        <a href="owner_bookings.php" class="btn btn-info ms-2">
            Bookings
            <?php if ($pending_count > 0): ?>
                <span class="badge bg-danger ms-1"><?= $pending_count ?></span>
            <?php endif; ?>
        </a>
    </div>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>Title</th>
                <th>Price</th>
                <th>Status</th>
                <th>Created</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>

        <?php while ($row = $res->fetch_assoc()): ?>
            <tr>
                <td>
                    <?php if (!empty($row['image'])): ?>
                        <img src="<?= esc($row['image']) ?>"
                             style="width:64px;height:48px;object-fit:cover;margin-right:8px;"
                             class="rounded">
                    <?php endif; ?>

                    <?= esc($row['title']) ?>

                    <?php
                        $ams = [];
                        if (!empty($row['amenities'])) {
                            $ams = json_decode($row['amenities'], true) ?: [];
                        }
                    ?>

                    <?php if ($ams): ?>
                        <div class="mt-1">
                            <?php foreach ($ams as $a): ?>
                                <span class="badge bg-secondary me-1">
                                    <?= esc(ucwords(str_replace('_', ' ', $a))) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>

                <td><?= number_format($row['price'], 2) ?></td>
                <td><?= esc(ucfirst($row['status'])) ?></td>
                <td><?= esc($row['created_at']) ?></td>

                <td>
                    <a class="btn btn-sm btn-primary"
                       href="view_post.php?id=<?= intval($row['id']) ?>">View</a>

                    <a class="btn btn-sm btn-warning"
                       href="edit_post.php?id=<?= intval($row['id']) ?>">Edit</a>

                    <a class="btn btn-sm btn-danger"
                       href="delete_post.php?id=<?= intval($row['id']) ?>"
                       onclick="return confirm('Are you sure you want to delete this post?')">
                        Delete
                    </a>

                    <a class="btn btn-sm btn-secondary"
                       href="owner_inbox.php?post_id=<?= intval($row['id']) ?>">
                        Messages
                        <?php if (!empty($row['unread_count'])): ?>
                            <span class="badge bg-danger ms-1">
                                <?= intval($row['unread_count']) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>

        </tbody>
    </table>
</div>

</body>
</html>
