<?php
session_start();
require 'db.php';
$post_id = intval($_GET['id'] ?? 0);
$stmt = $mysqli->prepare("SELECT p.*, u.name AS owner_name FROM posts p JOIN users u ON p.owner_id = u.id WHERE p.id = ? AND p.status='active'");
$stmt->bind_param('i',$post_id); $stmt->execute(); $res = $stmt->get_result();
$post = $res->fetch_assoc(); $stmt->close();
if (!$post) { die('Post not found.'); }
// Check if fully booked today
$today = date('Y-m-d');
$chkStmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM booking WHERE post_id = ? AND status IN ('pending', 'confirmed') AND DATE(created_at) = ?");
$chkStmt->bind_param('is', $post_id, $today);
$chkStmt->execute();
$booked_count = intval($chkStmt->get_result()->fetch_assoc()['c']);
$chkStmt->close();
$is_available = $booked_count < intval($post['room_count'] ?? 1);

$images = [];
$imgStmt = $mysqli->prepare("SELECT file_path FROM post_images WHERE post_id = ? ORDER BY is_primary DESC, sort_order ASC");
$imgStmt->bind_param('i',$post_id); $imgStmt->execute(); $ir = $imgStmt->get_result(); while($r = $ir->fetch_assoc()) $images[] = $r['file_path']; $imgStmt->close();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8"><title><?=esc($post['title'])?></title>
<link rel="stylesheet" href="css/bootstrap.min.css">
<link rel="stylesheet" href="css/student-dashboard.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>#map{height:320px}</style>
</head>
<body class="p-4">
<div class="container">
  <a href="student-dashboard.php" class="back-btn">Back</a>
  <h2><?=esc($post['title'])?></h2>
  <p class="text-muted">Owner: <?=esc($post['owner_name'])?></p>
  <div class="row">
    <div class="col-md-7">
      <?php if($images): ?>
        <div id="imgs" class="mb-3">
          <?php foreach($images as $i): ?>
            <img src="<?=esc($i)?>" style="width:100%;margin-bottom:8px;object-fit:cover;max-height:400px;">
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <p><?=nl2br(esc($post['description']))?></p>
      <p class="fw-bold">₱<?=number_format($post['price'],2)?></p>
      <?php if(!empty($post['amenities'])): $ams=json_decode($post['amenities'],true)?:[]; if($ams): ?>
        <div><?php foreach($ams as $a): ?><span class="badge bg-secondary me-1"><?=esc(ucwords(str_replace('_',' ',$a)))?></span><?php endforeach; ?></div>
      <?php endif; endif; ?>
      <p>Contact: <?=esc($post['contact'] ?? '')?></p>
      <div class="mt-3">
        <?php if ($is_available): ?>
          <a class="btn btn-success" href="booking.php?post_id=<?=intval($post['id'])?>">Book this BH</a>
        <?php else: ?>
          <button class="btn btn-secondary" disabled>Not Available (Booked for today)</button>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-md-5">
      <h5>Location</h5>
      <?php if($post['latitude'] && $post['longitude']): ?>
        <div class="mb-2">
          <input id="mapSearch" class="form-control form-control-sm" placeholder="Search location (try address or landmark)">
        </div>
        <div id="map"></div>
        <p class="mt-2"><a class="btn btn-sm btn-outline-primary" href="https://www.google.com/maps?q=<?=urlencode($post['latitude'].','.$post['longitude'])?>" target="_blank">Open in Google Maps</a></p>
      <?php else: ?>
        <p><?=esc($post['location'] ?? 'No location provided')?></p>
        <p><a class="btn btn-sm btn-outline-primary" href="https://www.google.com/maps?q=<?=urlencode($post['location'] ?? '')?>" target="_blank">Search in Maps</a></p>
      <?php endif; ?>

      <hr>
      <a class="btn btn-primary" href="message.php?post_id=<?=intval($post['id'])?>">Message Owner</a>
    </div>
  </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php if($post['latitude'] && $post['longitude']): ?>
<script>
  var map = L.map('map').setView([<?= $post['latitude'] ?>, <?= $post['longitude'] ?>], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution: '© OpenStreetMap contributors'}).addTo(map);
  var marker = L.marker([<?= $post['latitude'] ?>, <?= $post['longitude'] ?>]).addTo(map);

  // simple Nominatim search on input
  function nominatimSearch(q, cb){
    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=5&q=' + encodeURIComponent(q))
      .then(r=>r.json()).then(cb).catch(()=>cb([]));
  }
  var input = document.getElementById('mapSearch');
  if(input){
    var timeout;
    input.addEventListener('input', function(){
      clearTimeout(timeout);
      var q = this.value.trim();
      if(!q) return;
      timeout = setTimeout(function(){
        nominatimSearch(q, function(results){
          if(!results || results.length===0) return;
          var first = results[0];
          var lat = parseFloat(first.lat), lon = parseFloat(first.lon);
          map.setView([lat, lon], 16);
          marker.setLatLng([lat, lon]);
        });
      }, 500);
    });
  }
</script>
<?php endif; ?>
</body>
</html>