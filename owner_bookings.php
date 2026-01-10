<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header('Location: login.php');
    exit;
}

$owner_id = $_SESSION['user_id'];
$msg = '';

// Confirm / Cancel actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['booking_id'])) {
    $bid = intval($_POST['booking_id']);

    if ($_POST['action'] === 'confirm') {
        $upd = $mysqli->prepare("UPDATE booking SET status='confirmed' WHERE id = ? AND owner_id = ?");
        $upd->bind_param('ii', $bid, $owner_id);
        $upd->execute();
        $upd->close();
        $msg = 'Booking confirmed.';
    }

    if ($_POST['action'] === 'cancel') {
        $upd = $mysqli->prepare("UPDATE booking SET status='cancelled' WHERE id = ? AND owner_id = ?");
        $upd->bind_param('ii', $bid, $owner_id);
        $upd->execute();
        $upd->close();
        $msg = 'Booking cancelled.';
    }
}

// Fetch bookings for owner
$stmt = $mysqli->prepare("
    SELECT
        b.*,
        p.title,
        u.name AS student_name
    FROM booking b
    JOIN posts p ON b.post_id = p.id
    JOIN users u ON b.student_id = u.id
    WHERE b.owner_id = ?
    ORDER BY b.created_at DESC
");
$stmt->bind_param('i', $owner_id);
$stmt->execute();
$res = $stmt->get_result();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bookings</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/owner-dashboard.css">
</head>
<body class="p-4">
<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Bookings</h3>
        <a href="owner-dashboard.php" class="back-btn">Back</a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-info"><?= esc($msg) ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-striped">
        <thead class="table-light">
        <tr>
            <th>Post</th>
            <th>Student</th>
            <th>Date</th>
            <th>Guest Name</th>
            <th>Email</th>
            <th>Contact</th>
            <th>Amount</th>
            <th>Payment</th>
            <th>Note</th>
            <th>Status</th>
            <th>Requested</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>

        <?php while ($b = $res->fetch_assoc()): ?>
            <tr>
                <td>
                    <a href="view_post.php?id=<?= intval($b['post_id']) ?>">
                        <?= esc($b['title']) ?>
                    </a>
                </td>

                <td><?= esc($b['student_name']) ?></td>

                <td><?= date('Y-m-d', strtotime($b['created_at'])) ?></td>

                <td><?= esc($b['guest_name']) ?></td>
                <td><?= esc($b['guest_email']) ?></td>
                <td><?= esc($b['guest_contact']) ?></td>

                <td>₱<?= number_format(floatval($b['total_price']), 2) ?></td>

                <td>
                    <?= esc(ucfirst($b['payment_status'])) ?>
                    <?= !empty($b['payment_method']) ? ' (' . esc($b['payment_method']) . ')' : '' ?>
                </td>

                <td><?= esc($b['note'] ?: '-') ?></td>

                <td><?= esc(ucfirst($b['status'])) ?></td>

                <td><?= esc($b['created_at']) ?></td>

                <td>
                    <?php if ($b['status'] === 'pending'): ?>
                        <div class="btn-group-vertical" role="group">
                            <form method="post" style="margin-bottom: 5px;">
                                <input type="hidden" name="booking_id" value="<?= intval($b['id']) ?>">
                                <input type="hidden" name="action" value="confirm">
                                <button class="btn btn-sm btn-success w-100">Confirm</button>
                            </form>

                            <form method="post">
                                <input type="hidden" name="booking_id" value="<?= intval($b['id']) ?>">
                                <input type="hidden" name="action" value="cancel">
                                <button class="btn btn-sm btn-danger w-100">Cancel</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">No actions</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>

        </tbody>
    </table>
    </div>
</div>
</body>
</html>
