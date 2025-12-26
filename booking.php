<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user_id'];
$post_id = intval($_GET['post_id'] ?? $_POST['post_id'] ?? 0);
if (!$post_id) die('Post not specified.');

// Fetch post info
$ps = $mysqli->prepare("
    SELECT p.id, p.title, p.price, p.owner_id, u.name AS owner_name
    FROM posts p
    JOIN users u ON p.owner_id = u.id
    WHERE p.id = ? AND p.status = 'active'
");
$ps->bind_param('i', $post_id);
$ps->execute();
$post = $ps->get_result()->fetch_assoc();
$ps->close();

if (!$post) die('Post not found.');

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $note = trim($_POST['note'] ?? '');
    $guest_name = trim($_POST['guest_name'] ?? '');
    $guest_email = trim($_POST['guest_email'] ?? '');
    $guest_contact = trim($_POST['guest_contact'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $occupants = 1; // Default occupants

    if (!$guest_name || !$guest_email || !$guest_contact) {
        $msg = 'Please provide name, email, and contact.';
    } else {
        // Check if already booked today
        $today = date('Y-m-d');
        $chk = $mysqli->prepare("
            SELECT COUNT(*) AS c
            FROM booking
            WHERE post_id = ?
              AND status IN ('pending', 'confirmed')
              AND DATE(created_at) = ?
        ");
        $chk->bind_param('is', $post_id, $today);
        $chk->execute();
        $cnt = intval($chk->get_result()->fetch_assoc()['c'] ?? 0);
        $chk->close();

        if ($cnt > 0) {
            $msg = 'This unit is already booked for today.';
        } else {
            $total_price = round(floatval($post['price']), 2);

            $ins = $mysqli->prepare("
                INSERT INTO booking
                (post_id, student_id, owner_id, occupants,
                 total_price, guest_name, guest_email, guest_contact, note,
                 payment_method, payment_status, status, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?, 'pending', NOW())
            ");

            $payment_status = 'unpaid';

            $ins->bind_param(
                'iiiidssssss',
                $post_id,
                $student_id,
                $post['owner_id'],
                $occupants,
                $total_price,
                $guest_name,
                $guest_email,
                $guest_contact,
                $note,
                $payment_method,
                $payment_status
            );

            if ($ins->execute()) {
                $booking_id = $ins->insert_id;

                if ($payment_method !== 'cash') {
                    header('Location: pay_booking.php?id=' . intval($booking_id));
                    exit;
                }

                $msg = 'Booking request submitted successfully.';
            } else {
                $msg = 'Error creating booking: ' . $ins->error;
            }
            $ins->close();
        }
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Book <?= esc($post['title']) ?></title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container col-md-6">

    <a href="view_post.php?id=<?= intval($post_id) ?>" class="btn btn-sm btn-link">Back</a>

    <h4>Book: <?= esc($post['title']) ?></h4>
    <p class="text-muted">Owner: <?= esc($post['owner_name']) ?></p>

    <?php if ($msg): ?>
        <div class="alert alert-info"><?= esc($msg) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="post_id" value="<?= intval($post_id) ?>">

        <div class="mb-3">
            <label>Your name</label>
            <input type="text" name="guest_name" class="form-control"
                   value="<?= esc($_SESSION['name'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label>Your email</label>
            <input type="email" name="guest_email" class="form-control"
                   value="<?= esc($_SESSION['email'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label>Contact number</label>
            <input type="text" name="guest_contact" class="form-control"
                   value="<?= esc($_SESSION['contact'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label>Payment method</label>
            <select name="payment_method" class="form-select">
                <option value="cash">Cash</option>
                <option value="gcash">GCash</option>
                <option value="card">Card</option>
            </select>
        </div>

        <div class="mb-3">
            <label>Note (optional)</label>
            <textarea name="note" class="form-control" rows="3"></textarea>
        </div>

        <button class="btn btn-primary">Request Booking</button>
    </form>
</div>
</body>
</html>
