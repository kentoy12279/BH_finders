<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid = $_SESSION['user_id'];
$booking_id = intval($_GET['id'] ?? $_POST['booking_id'] ?? 0);
if (!$booking_id) die('Booking not specified.');

// Fetch booking
$stmt = $mysqli->prepare("SELECT b.*, p.title, p.price AS post_price, u.name AS owner_name FROM booking b JOIN posts p ON b.post_id = p.id JOIN users u ON b.owner_id = u.id WHERE b.id = ?");
$stmt->bind_param('i',$booking_id); $stmt->execute(); $booking = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$booking) die('Booking not found.');

// Only student who created booking can pay
if ($booking['student_id'] != $uid) die('Access denied.');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simulate card payment processing
    $provider = 'card';
    $amount = floatval($booking['total_price']);
    $currency = 'PHP';
    $txn = 'TXN' . time() . rand(1000,9999);

    // Insert payment record
    $ins = $mysqli->prepare("INSERT INTO booking_payments (booking_id, provider, amount, currency, transaction_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'completed', NOW())");
    $ins->bind_param('isdss', $booking_id, $provider, $amount, $currency, $txn);
    if ($ins->execute()) {
        // mark booking as paid and confirm the booking
        $upd = $mysqli->prepare("UPDATE booking SET payment_status='paid', payment_method = ?, status='confirmed', updated_at = NOW() WHERE id = ?");
        $upd->bind_param('si', $provider, $booking_id); $upd->execute(); $upd->close();
        $msg = 'Card payment successful. Booking confirmed.';
    } else {
        $msg = 'Payment failed.';
    }
    $ins->close();
    // refresh booking data
    $stmt = $mysqli->prepare("SELECT b.*, p.title, p.price AS post_price, u.name AS owner_name FROM booking b JOIN posts p ON b.post_id = p.id JOIN users u ON b.owner_id = u.id WHERE b.id = ?");
    $stmt->bind_param('i',$booking_id); $stmt->execute(); $booking = $stmt->get_result()->fetch_assoc(); $stmt->close();
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Card Payment</title><link rel="stylesheet" href="css/bootstrap.min.css"><link rel="stylesheet" href="css/student-dashboard.css"></head>
<body class="p-4">
<div class="container col-md-6">
  <a href="student_bookings.php" class="back-btn">Back</a>
  <h4>Card Payment for Booking: <?=esc($booking['title'])?></h4>
  <p class="text-muted">Owner: <?=esc($booking['owner_name'])?></p>
  <p>Amount: <strong>₱<?=number_format($booking['total_price'],2)?></strong></p>

  <?php if($msg): ?><div class="alert alert-info"><?=esc($msg)?></div><?php endif; ?>

  <form method="post">
    <input type="hidden" name="booking_id" value="<?=intval($booking_id)?>">
    <div class="mb-3">
      <label>Card Number</label>
      <input type="text" name="card_number" class="form-control" placeholder="1234 5678 9012 3456" required>
    </div>
    <div class="mb-3">
      <label>Expiry Date</label>
      <input type="text" name="expiry" class="form-control" placeholder="MM/YY" required>
    </div>
    <div class="mb-3">
      <label>CVV</label>
      <input type="text" name="cvv" class="form-control" placeholder="123" required>
    </div>
    <button class="btn btn-primary">Pay with Card</button>
  </form>
</div>
</body>
</html>
