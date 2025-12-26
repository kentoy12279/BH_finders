<?php
require 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header('Location: login.php'); exit; }
$student_id = $_SESSION['user_id'];
$uc = $mysqli->prepare("SELECT COUNT(*) AS c FROM messages WHERE student_id = ? AND owner_reply IS NOT NULL AND owner_reply <> '' AND student_read = 0");
$uc->bind_param('i', $student_id); $uc->execute(); $student_unread = intval($uc->get_result()->fetch_assoc()['c'] ?? 0);
$stmt = $mysqli->prepare("SELECT p.id,p.title,p.description,p.price,p.amenities, (SELECT file_path FROM post_images WHERE post_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS image, u.name AS owner_name FROM posts p JOIN users u ON p.owner_id = u.id WHERE p.status='active' ORDER BY p.created_at DESC");
$stmt->execute(); $res = $stmt->get_result();
?>
<!doctype html><html><head><link rel="stylesheet" href="css/bootstrap.min.css"></head><body class="p-4">
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Available Boarding Houses</h3>
    <div>
      <a href="student_inbox.php" class="btn btn-sm btn-secondary">Inbox <?php if($student_unread>0): ?><span class="badge bg-danger ms-1"><?= $student_unread ?></span><?php endif; ?></a>
      <a href="student_bookings.php" class="btn btn-sm btn-info ms-2">My Bookings</a>
      <a href="logout.php" class="btn btn-sm btn-outline-secondary ms-2">Logout</a>
    </div>
  </div>
  <div class="row">
    <?php while($post = $res->fetch_assoc()): ?>
      <div class="col-md-6 mb-3">
        <div class="card">
          <?php if(!empty($post['image'])): ?><img src="<?=esc($post['image'])?>" class="card-img-top" style="max-height:220px;object-fit:cover;"><?php endif; ?>
          <div class="card-body">
            <h5 class="card-title"><?=esc($post['title'])?></h5>
            <h6 class="card-subtitle mb-2 text-muted">Owner: <?=esc($post['owner_name'])?></h6>
            <p class="card-text"><?=nl2br(esc($post['description']))?></p>
            <p class="fw-bold">₱<?=number_format($post['price'],2)?></p>

            <?php
              $amenitiesArr = [];
              if (!empty($post['amenities'])) {
                $amenitiesArr = json_decode($post['amenities'], true) ?: [];
              }
            ?>
            <?php if($amenitiesArr): ?>
              <div class="mb-2">
                <?php foreach($amenitiesArr as $am): ?>
                  <span class="badge bg-secondary me-1"><?=esc(ucwords(str_replace('_',' ',$am)))?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <a href="view_post.php?id=<?=intval($post['id'])?>" class="btn btn-outline-secondary btn-sm me-1">View</a>
            <a href="message.php?post_id=<?=intval($post['id'])?>" class="btn btn-primary btn-sm">Message Owner</a>
            <?php if(!empty($post['latitude']) && !empty($post['longitude'])): ?>
              <a class="btn btn-sm btn-link" href="https://www.google.com/maps?q=<?=urlencode($post['latitude'].','.$post['longitude'])?>" target="_blank">Open in Maps</a>
            <?php elseif(!empty($post['location'])): ?>
              <a class="btn btn-sm btn-link" href="https://www.google.com/maps?q=<?=urlencode($post['location'])?>" target="_blank">Open in Maps</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
</div>
</body></html>