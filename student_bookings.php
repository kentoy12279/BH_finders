<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user_id'];

// Fetch student bookings
$stmt = $mysqli->prepare("
    SELECT 
        b.*,
        p.title,
        u.name AS owner_name
    FROM booking b
    JOIN posts p ON b.post_id = p.id
    JOIN users u ON b.owner_id = u.id
    WHERE b.student_id = ?
    ORDER BY b.created_at DESC
");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$res = $stmt->get_result();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>My Bookings</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/student-dashboard.css">
</head>
<body class="p-4">
<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>My Bookings</h3>
        <a href="student-dashboard.php" class="back-btn">Back</a>
    </div>

    <div style="overflow-x: auto;">
        <table class="table table-bordered table-striped">
            <thead class="table-light">
            <tr>
                <th>Post</th>
                <th>Owner</th>
                <th>Date</th>
                <th>Name</th>
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

                    <td><?= esc($b['owner_name']) ?></td>

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
                        <?php if ($b['payment_status'] !== 'paid' && $b['payment_method'] !== 'cash'): ?>
                            <a href="pay_booking.php?id=<?= intval($b['id']) ?>" class="btn btn-sm btn-primary">
                                Pay
                            </a>
                        <?php else: ?>
                            <span class="text-muted">No action</span>
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
