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
    // Simulate payment processing
    $provider = $_POST['provider'] ?? 'test';
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
        $msg = 'Payment successful. Booking confirmed.';
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
<head><meta charset="utf-8"><title>Pay Booking</title><link rel="stylesheet" href="css/bootstrap.min.css"></head>
<body class="p-4">
<div class="container col-md-6">
  <a href="student_bookings.php" class="btn btn-sm btn-link">Back</a>
  <h4>Pay for Booking: <?=esc($booking['title'])?></h4>
  <p class="text-muted">Owner: <?=esc($booking['owner_name'])?></p>
  <p>Amount: <strong>₱<?=number_format($booking['total_price'],2)?></strong></p>

  <?php if($msg): ?><div class="alert alert-info"><?=esc($msg)?></div><?php endif; ?>

  <form method="post" id="paymentForm">
    <input type="hidden" name="booking_id" value="<?=intval($booking_id)?>">
    <div class="mb-3">
      <label>Payment provider</label>
      <select name="provider" class="form-select" id="providerSelect">
        <option value="gcash">GCash</option>
        <option value="card">Card</option>
        <option value="test">Test (simulate)</option>
      </select>
    </div>
    <button type="button" class="btn btn-primary" onclick="handlePayment()">Pay Now</button>
  </form>
  <script>
    function handlePayment() {
      const provider = document.getElementById('providerSelect').value;
      const bookingId = <?=intval($booking_id)?>;
      if (provider === 'gcash') {
        // Redirect to GCash app or web
        window.location.href = 'gcash://pay?amount=<?=number_format($booking['total_price'],2)?>&booking=' + bookingId;
      } else if (provider === 'card') {
        // Redirect to card payment page
        window.location.href = 'card_payment.php?id=' + bookingId;
      } else {
        // Submit form for test
        document.getElementById('paymentForm').submit();
      }
    }
  </script>
</div>
</body>
</html>