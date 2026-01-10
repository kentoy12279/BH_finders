<?php
require 'db.php';
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') { header('Location: login.php'); exit; }
$owner_id = $_SESSION['user_id'];
$post_id = intval($_GET['id'] ?? 0);
if (!$post_id) die('Invalid post');
// fetch post
$stmt = $mysqli->prepare("SELECT * FROM posts WHERE id = ? AND owner_id = ?");
$stmt->bind_param('ii',$post_id,$owner_id); $stmt->execute(); $res = $stmt->get_result(); $post = $res->fetch_assoc(); $stmt->close();
if (!$post) die('Post not found or access denied.');
// get images
$images = [];
$imgStmt = $mysqli->prepare("SELECT id,file_path FROM post_images WHERE post_id = ? ORDER BY is_primary DESC, sort_order ASC");
$imgStmt->bind_param('i',$post_id); $imgStmt->execute(); $ir = $imgStmt->get_result(); while($r = $ir->fetch_assoc()) $images[] = $r; $imgStmt->close();

$err=''; $msg='';
// amenities list
$amenitiesList = [
    'wifi'=>'Wi-Fi','water'=>'Water','air_conditioning'=>'Air Conditioning','hot_water'=>'Hot Water','laundry'=>'Laundry','parking'=>'Parking','tv'=>'TV','cctv'=>'CCTV','guard'=>'Security Guard','furnished'=>'Furnished','kitchen'=>'Kitchen','pet_friendly'=>'Pet Friendly','balcony'=>'Balcony','pool'=>'Pool','gym'=>'Gym'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) { $err='Invalid form submission.'; }
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

        if (!$title || !$description || $price <= 0) { $err='Title, description and monthly price are required.'; }
        else {
            $mysqli->begin_transaction();
            try {
                // update post
                $methodsCsv = is_array($methods) ? implode(',', $methods) : '';
                $amenJson = json_encode(array_values($amenities));
                $sql = $mysqli->real_escape_string($title);
                $sqlD = $mysqli->real_escape_string($description);
                $sqlMethods = $mysqli->real_escape_string($methodsCsv);
                $sqlLocation = $mysqli->real_escape_string($location);
                $sqlAmen = $mysqli->real_escape_string($amenJson);
                $sqlRoomType = $mysqli->real_escape_string($room_type);
                $sqlContact = $mysqli->real_escape_string($contact);

                $latStr = $latitude === null ? 'NULL' : $latitude;
                $lngStr = $longitude === null ? 'NULL' : $longitude;

                $q = "UPDATE posts SET title='$sql', description='$sqlD', price=$price, status='".$mysqli->real_escape_string($status)."', payment_methods='".$sqlMethods."', location='".$sqlLocation."', latitude=$latStr, longitude=$lngStr, amenities='".$sqlAmen."', room_count=$room_count, room_type='".$sqlRoomType."', contact='".$sqlContact."' WHERE id=$post_id AND owner_id=$owner_id";
                if (!$mysqli->query($q)) throw new Exception('Update failed: '.$mysqli->error);

                // handle deletions of existing images
                if (!empty($_POST['delete_image']) && is_array($_POST['delete_image'])) {
                    $delIds = array_map('intval', $_POST['delete_image']);
                    if ($delIds) {
                        $in = implode(',', $delIds);
                        $rows = $mysqli->query("SELECT id,file_path FROM post_images WHERE id IN ($in) AND post_id = $post_id");
                        while ($r = $rows->fetch_assoc()) {
                            @unlink(dirname(__DIR__).'/'. $r['file_path']);
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
                if ($toAdd>0 && !empty($_FILES['images']['name'][0])) {
                    $allowed = ['image/jpeg','image/png','image/gif'];
                    $insImg = $mysqli->prepare("INSERT INTO post_images (post_id,file_path,is_primary,sort_order) VALUES (?,?,?,?)");
                    $sort = $current;
                    for ($i=0;$i<$filesCount;$i++){
                        if ($toAdd<=0) break;
                        if ($_FILES['images']['error'][$i]!==UPLOAD_ERR_OK) continue;
                        $tmp = $_FILES['images']['tmp_name'][$i]; $mime = mime_content_type($tmp); $size = $_FILES['images']['size'][$i] ?? 0;
                        if (!in_array($mime,$allowed) || $size>2*1024*1024) continue;
                        $ext = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                        $newName = time().'_'.bin2hex(random_bytes(6)).'.'.$ext;
                        $dest = __DIR__.'/uploads/'.$newName;
                        if (move_uploaded_file($tmp,$dest)){
                            $filePath = 'uploads/'.$newName; $isPrimary = 0;
                            $insImg->bind_param('isii',$post_id,$filePath,$isPrimary,$sort);
                            $insImg->execute(); $sort++; $toAdd--;
                        }
                    }
                    $insImg->close();
                }

                $mysqli->commit();
                $msg='Post updated.';
                // reload post
                header('Location: edit_post.php?id='.$post_id.'&updated=1'); exit;

            } catch (Exception $e) {
                $mysqli->rollback(); $err = $e->getMessage();
            }
        }
    }
}

// show form with $post values
$csrf = $_SESSION['csrf'];
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8"><title>Edit Post</title>
<link rel="stylesheet" href="css/bootstrap.min.css">
<link rel="stylesheet" href="css/owner-dashboard.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>#map{height:300px;margin-bottom:12px}</style>
</head>
<body class="p-4">
<div class="container col-md-8">
<div class="d-flex justify-content-between align-items-center mb-3">
<h2>Edit Post</h2>
<a href="owner-dashboard.php" class="back-btn">Back</a>
</div>
<?php if($err): ?><div class="alert alert-danger"><?=esc($err)?></div><?php endif; ?>
<?php if(isset($_GET['updated'])): ?><div class="alert alert-success">Updated.</div><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=esc($csrf)?>">
<div class="mb-3"><label class="form-label">Title</label><input name="title" class="form-control" required value="<?=esc($post['title'])?>"></div>
<div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="5" required><?=esc($post['description'])?></textarea></div>
<div class="mb-3 row">
<div class="col-md-3"><label class="form-label">Monthly Price (₱)</label><input required name="price" type="number" step="0.01" class="form-control" value="<?=esc($post['price'])?>"></div>
<div class="col-md-3"><label class="form-label">Status</label>
<select name="status" class="form-select"><option value="active" <?= $post['status']==='active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $post['status']==='inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
<div class="col-md-6"><label class="form-label">Location</label><input name="location" class="form-control" value="<?=esc($post['location'])?>"></div>
</div>
<div class="mb-3 row">
<div class="col-md-3"><label class="form-label">Room Count</label><input name="room_count" type="number" class="form-control" value="<?=esc($post['room_count'])?>"></div>
<div class="col-md-3"><label class="form-label">Room Type</label><input name="room_type" class="form-control" value="<?=esc($post['room_type'])?>"></div>
<div class="col-md-6"><label class="form-label">Contact (phone or email)</label><input name="contact" class="form-control" value="<?=esc($post['contact'])?>"></div>
</div>

<div class="mb-3">
<label class="form-label">Location on map (optional)</label>
<div id="map"></div>
<input type="hidden" name="latitude" id="latitude" value="<?=esc($post['latitude'])?>">
<input type="hidden" name="longitude" id="longitude" value="<?=esc($post['longitude'])?>">
<small class="form-text text-muted">Click map to drop a marker for your exact location.</small>
</div>

<!-- Payment Methods -->
<div class="mb-3">
<label class="form-label">Payment Methods</label>
<?php
$gcashSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M7 11h10M7 15h6"/></svg> GCash';
$paymentList = ['cash'=>'💵 Cash','gcash'=>$gcashSVG,'card'=>'💳 Card'];
$selectedMethods = array_filter(explode(',', $post['payment_methods'] ?? ''));
foreach($paymentList as $key=>$val): ?>
  <div class="form-check">
    <input class="form-check-input" type="checkbox" name="payment_methods[]" value="<?=esc($key)?>" id="pm_<?=esc($key)?>" <?= in_array($key,$selectedMethods) ? 'checked' : '' ?> >
    <label class="form-check-label" for="pm_<?=esc($key)?>"><?=$val?></label>
  </div>
<?php endforeach; ?>
</div>

<!-- Amenities -->
<div class="mb-3">
<label class="form-label">Amenities</label>
<?php foreach($amenitiesList as $key=>$val): ?>
  <div class="form-check form-check-inline">
    <input class="form-check-input" type="checkbox" name="amenities[]" value="<?=esc($key)?>" id="am_<?=esc($key)?>" <?= (in_array($key,json_decode($post['amenities']?:'[]',true)?:[])) ? 'checked' : '' ?>>
    <label class="form-check-label" for="am_<?=esc($key)?>"><?=esc($val)?></label>
  </div>
<?php endforeach; ?>
</div>

<!-- Existing images -->
<?php if($images): ?>
<div class="mb-3">
  <label class="form-label">Existing Images (check to delete)</label>
  <div class="d-flex flex-wrap">
    <?php foreach($images as $im): ?>
      <div class="me-2 mb-2" style="width:120px">
        <img src="<?=esc($im['file_path'])?>" style="width:100%;height:80px;object-fit:cover;display:block;margin-bottom:4px;">
        <div class="form-check"><input class="form-check-input" type="checkbox" name="delete_image[]" value="<?=intval($im['id'])?>" id="del<?=intval($im['id'])?>"><label class="form-check-label" for="del<?=intval($im['id'])?>">Delete</label></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Images -->
<div class="mb-3"><label class="form-label">Add Images (jpg/png/gif, max 2MB each) — max total images 12</label><input type="file" name="images[]" accept="image/*" class="form-control" multiple></div>

<button class="btn btn-primary">Update Post</button>
</form>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  var latEl = document.getElementById('latitude');
  var lngEl = document.getElementById('longitude');
  var lat = parseFloat(latEl.value) || 14.5995;
  var lng = parseFloat(lngEl.value) || 120.9842;
  var map = L.map('map').setView([lat,lng], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution: '© OpenStreetMap contributors'}).addTo(map);
  var marker = null;
  if (latEl.value && lngEl.value) {
    marker = L.marker([lat,lng], {draggable:true}).addTo(map);
    marker.on('dragend', function(e){ var p = marker.getLatLng(); latEl.value = p.lat; lngEl.value = p.lng; });
  }
  map.on('click', function(e){ if (marker) map.removeLayer(marker); marker = L.marker(e.latlng, {draggable:true}).addTo(map); latEl.value = e.latlng.lat; lngEl.value = e.latlng.lng; marker.on('dragend', function(){ var p=marker.getLatLng(); latEl.value = p.lat; lngEl.value = p.lng; }); });
</script>
</body>
</html>