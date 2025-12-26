<?php
require 'db.php';

// Define esc only if not defined in db.php
if (!function_exists('esc')) {
    function esc($str) {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

// Ensure uploads directory exists and is writable
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Amenities list for the form
$amenitiesList = [
    'wifi' => 'Wi-Fi',
    'water' => 'Water',
    'air_conditioning' => 'Air Conditioning',
    'hot_water' => 'Hot Water',
    'laundry' => 'Laundry',
    'parking' => 'Parking',
    'tv' => 'TV',
    'cctv' => 'CCTV',
    'guard' => 'Security Guard',
    'furnished' => 'Furnished',
    'kitchen' => 'Kitchen',
    'pet_friendly' => 'Pet Friendly',
    'balcony' => 'Balcony',
    'pool' => 'Pool',
    'gym' => 'Gym',
];

// CSRF token
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header('Location: login.php');
    exit;
}

$owner_id = $_SESSION['user_id'];
$err = '';
$msg = '';

// Check if schema supports amenities and post_images table
$hasAmenities = $mysqli->query("SHOW COLUMNS FROM posts LIKE 'amenities'") && $mysqli->query("SHOW COLUMNS FROM posts LIKE 'amenities'")->num_rows > 0;
$hasPostImages = $mysqli->query("SHOW TABLES LIKE 'post_images'") && $mysqli->query("SHOW TABLES LIKE 'post_images'")->num_rows > 0;

// initialize form values
$title = '';
$description = '';
$price = '';
$status = 'inactive';
$methods = [];
$location = '';
$room_count = '';
$room_type = '';
$contact = '';
$amenities = [];

// Handle owner replies inline (from inbox sidebar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_to']) && isset($_POST['owner_reply'])) {
    // CSRF check
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        $info = 'Invalid form submission.';
    } else {
        $id = intval($_POST['reply_to']);
        $reply = trim($_POST['owner_reply'] ?? '');
        $resolve = isset($_POST['resolve']) ? 1 : 0;
        $upd = $mysqli->prepare("UPDATE messages SET owner_reply = ?, is_resolved = ?, owner_read = 1, student_read = 0 WHERE id = ? AND owner_id = ?");
        $upd->bind_param('siii', $reply, $resolve, $id, $owner_id);
        if ($upd->execute()) $info = 'Reply saved.'; else $info = 'Error saving reply.';
        $upd->close();
    }
}

// Regular create post handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['reply_to']))) {
    // CSRF check
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        $err = 'Invalid form submission.';
    } else {
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
        $amenities = $hasAmenities ? ($_POST['amenities'] ?? []) : [];

        if (!$title || !$description || $price <= 0) {
            $err = 'Title, description and monthly price are required.';
        } else {
            $mysqli->begin_transaction();
            try {
                $methodsCsv = is_array($methods) ? implode(',', $methods) : '';
                $amenJson = $hasAmenities ? json_encode(array_values($amenities)) : null;

                // Prepare columns and values (include latitude & longitude)
                $cols = ['owner_id','title','description','price','status','payment_methods','location','latitude','longitude','room_count','room_type','contact'];
                $values = [$owner_id, $title, $description, $price, $status, $methodsCsv, $location, $latitude, $longitude, $room_count, $room_type, $contact];
                // types: i=owner, s=title, s=desc, d=price, s=status, s=payment_methods, s=location, d=lat, d=lng, i=room_count, s=room_type, s=contact
                $types = 'issdsssddiss';

                if ($hasAmenities) {
                    // insert amenities after location (index 7)
                    array_splice($cols, 7, 0, ['amenities']);
                    array_splice($values, 7, 0, [$amenJson]);
                    // add an extra 's' in types for amenities
                    $types = 'issdssssddiss';
                }

                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $sql = 'INSERT INTO posts (' . implode(',', $cols) . ') VALUES (' . $placeholders . ')';
                $stmt = $mysqli->prepare($sql);
                if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);

                // bind params (build array of references)
                $bindParams = [];
                $bindParams[] = $types;
                foreach ($values as $k => $v) {
                    $bindParams[] = &$values[$k];
                }
                call_user_func_array([$stmt,'bind_param'],$bindParams);

                if (!$stmt->execute()) throw new Exception('Insert post failed: ' . $stmt->error);
                $post_id = $mysqli->insert_id;
                $stmt->close();

                // Handle multiple images (limit 10)
                if ($hasPostImages && !empty($_FILES['images']['name'][0])) {
                    $allowed = ['image/jpeg','image/png','image/gif'];
                    $insImg = $mysqli->prepare("INSERT INTO post_images (post_id,file_path,is_primary,sort_order) VALUES (?,?,?,?)");
                    if (!$insImg) throw new Exception('Prepare image insert failed: ' . $mysqli->error);
                    $sort = 0;

                    $filesCount = min(12, count($_FILES['images']['name']));
                    for ($i = 0; $i < $filesCount; $i++) {
                        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                        $tmp = $_FILES['images']['tmp_name'][$i];
                        $mime = mime_content_type($tmp);
                        $size = $_FILES['images']['size'][$i] ?? 0;
                        if (!in_array($mime,$allowed) || $size>2*1024*1024) continue;
                        $ext = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                        $newName = time().'_'.bin2hex(random_bytes(6)).'.'.$ext;
                        $dest = __DIR__.'/uploads/'.$newName;
                        if (move_uploaded_file($tmp,$dest)) {
                            $filePath = 'uploads/'.$newName;
                            $isPrimary = $sort===0 ? 1 : 0;
                            $insImg->bind_param('isii',$post_id,$filePath,$isPrimary,$sort);
                            $insImg->execute();
                            $sort++;
                        }
                    }
                    $insImg->close();
                }

                $mysqli->commit();
                $msg = 'Post created successfully.';
                header('Location: owner-dashboard.php?created=1');
                exit;

            } catch (Exception $e) {
                $mysqli->rollback();
                $err = $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8"><title>Create Post</title>
<link rel="stylesheet" href="css/bootstrap.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-sA+e2Xk24wQvNfXyp9+O2Yf8Z1bCq1xD5s3wT0iPsv0=" crossorigin="" />
<style>#map{height:300px;margin-bottom:12px}</style>
</head>
<body class="p-4">
<div class="container">
  <div class="row">
    <div class="col-md-8">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Create Boarding House Post</h2>
        <a href="owner-dashboard.php" class="btn btn-sm btn-secondary">Back</a>
      </div>
<?php if(!empty($info)): ?><div class="alert alert-info"><?=esc($info)?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><?=esc($err)?></div><?php endif; ?>
<?php if($msg): ?><div class="alert alert-success"><?=esc($msg)?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<!-- Add CSRF -->
<input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>">

<!-- Title & Description -->
<div class="mb-3"><label class="form-label">Title</label><input name="title" class="form-control" required value="<?=esc($title)?>"></div>
<div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="5" required><?=esc($description)?></textarea></div>

<!-- Price, Status, Location -->
<div class="mb-3 row">
<div class="col-md-3"><label class="form-label">Monthly Price (₱)</label><input required name="price" type="number" step="0.01" class="form-control" value="<?=esc($price)?>"></div>
<div class="col-md-3"><label class="form-label">Status</label>
<select name="status" class="form-select"><option value="active" <?= $status==='active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $status==='inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
<div class="col-md-6"><label class="form-label">Location</label><input name="location" class="form-control" value="<?=esc($location)?>"></div>
</div>

<!-- Room Info & Contact -->
<div class="mb-3 row">
<div class="col-md-3"><label class="form-label">Room Count</label><input name="room_count" type="number" class="form-control" value="<?=esc($room_count)?>"></div>
<div class="col-md-3"><label class="form-label">Room Type</label><input name="room_type" class="form-control" value="<?=esc($room_type)?>"></div>
<div class="col-md-6"><label class="form-label">Contact (phone or email)</label><input name="contact" class="form-control" value="<?=esc($contact)?>"></div>
</div>

<!-- Amenities -->
<div class="mb-3">
<label class="form-label">Amenities</label>
<?php foreach($amenitiesList as $key=>$val): ?>
  <div class="form-check form-check-inline">
    <input class="form-check-input" type="checkbox" name="amenities[]" value="<?=esc($key)?>" id="am_<?=esc($key)?>" <?= in_array($key,$amenities) ? 'checked' : '' ?>>
    <label class="form-check-label" for="am_<?=esc($key)?>"><?=esc($val)?></label>
  </div>
<?php endforeach; ?>
</div>

<!-- Payment Methods -->
<div class="mb-3">
<label class="form-label">Payment Methods</label>
<?php
$gcashSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M7 11h10M7 15h6"/></svg> GCash';
$paymentList = ['cash'=>'💵 Cash','gcash'=>$gcashSVG,'card'=>'💳 Card'];
foreach($paymentList as $key=>$val): ?>
  <div class="form-check">
    <input class="form-check-input" type="checkbox" name="payment_methods[]" value="<?=esc($key)?>" id="pm_<?=esc($key)?>" <?= in_array($key,$methods) ? 'checked' : '' ?> >
    <label class="form-check-label" for="pm_<?=esc($key)?>"><?=$val?></label>
  </div>
<?php endforeach; ?>
</div>

<!-- Location (map) -->
<div class="mb-3">
  <label class="form-label">Location on map (optional)</label>
  <div id="map"></div>
  <input type="hidden" name="latitude" id="latitude" value="<?=esc($latitude ?? '')?>">
  <input type="hidden" name="longitude" id="longitude" value="<?=esc($longitude ?? '')?>">
  <small class="form-text text-muted">Click map to drop a marker for your exact location.</small>
</div>

<!-- Images -->
<div class="mb-3"><label class="form-label">Images (jpg/png/gif, max 2MB each, up to 12)</label><input type="file" name="images[]" accept="image/*" class="form-control" multiple></div>

<button class="btn btn-primary">Create Post</button>
</form>
    </div>

    <!-- Inbox Sidebar -->
    <div class="col-md-4">
      <div class="card sticky-top" style="top:20px;">
        <div class="card-body">
          <h5 class="card-title">Inbox <span id="inbox-unread-badge"></span></h5>
          <p class="text-muted small mb-2">Recent messages from students (click to reply)</p>
          <div id="inbox-list" style="max-height:520px;overflow:auto;">
            <div class="text-muted">Loading...</div>
          </div>
          <div class="mt-2 text-end"><a href="owner_inbox.php" class="btn btn-sm btn-link">Open full inbox</a></div>
        </div>
        <script>
          async function fetchInbox(){
            try{
              const res = await fetch('ajax_inbox.php?action=fetch');
              const data = await res.json();
              const listEl = document.getElementById('inbox-list');
              const badgeEl = document.getElementById('inbox-unread-badge');
              if(data.unread && data.unread>0){
                badgeEl.innerHTML = '<span class="badge bg-danger ms-2">'+data.unread+'</span>';
              } else badgeEl.innerHTML = '';
              if(!data.messages || data.messages.length===0){ listEl.innerHTML = '<div class="text-muted">No messages yet.</div>'; return; }
              let html='';
              data.messages.forEach(function(mm){
                const unresolved = (mm.is_resolved == 0 && (!mm.owner_reply || mm.owner_reply===''));
                html += '<div class="mb-3 p-2 border rounded '+(unresolved? 'border-warning':'')+'">';
                html += '<div class="d-flex justify-content-between align-items-start"><div><strong style="font-size:0.95rem">'+escapeHtml(mm.student_name)+'</strong>';
                html += '<div class="text-muted small">on <a href="view_post.php?id='+parseInt(mm.post_id)+'">'+escapeHtml(mm.title)+'</a></div></div>';
                html += '<div class="small text-muted">'+escapeHtml(mm.created_at)+'</div></div>';
                html += '<div class="mt-2 mb-2">'+nl2br(escapeHtml(mm.content))+'</div>';
                html += '<form class="mb-0 inbox-reply-form" data-id="'+parseInt(mm.id)+'">';
                html += '<input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>">';
                html += '<div class="mb-2"><textarea name="owner_reply" class="form-control form-control-sm" rows="2">'+escapeHtml(mm.owner_reply||'')+'</textarea></div>';
                html += '<div class="d-flex justify-content-between align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="resolve" value="1" '+(mm.is_resolved? 'checked':'')+'> <label class="form-check-label small ms-1">Resolve</label></div>';
                html += '<button class="btn btn-sm btn-primary">Reply</button></div></form></div>';
              });
              listEl.innerHTML = html;
              document.querySelectorAll('.inbox-reply-form').forEach(function(f){
                f.addEventListener('submit', async function(e){
                  e.preventDefault();
                  const id = this.dataset.id; const reply = this.querySelector('textarea[name="owner_reply"]').value; const resolve = this.querySelector('input[name="resolve"]').checked?1:0; const csrf = this.querySelector('input[name="csrf"]').value;
                  const fd = new FormData(); fd.append('action','reply'); fd.append('id',id); fd.append('reply',reply); fd.append('resolve',resolve); fd.append('csrf',csrf);
                  const r = await fetch('ajax_inbox.php',{method:'POST',body:fd}); const j = await r.json();
                  if(j.success){ fetchInbox(); updateInboxBadge(j.unread); } else { alert('Error saving reply'); }
                });
              });
            }catch(err){ console.error(err); }
          }
          function updateInboxBadge(n){ const badgeEl = document.getElementById('inbox-unread-badge'); if(n && n>0) badgeEl.innerHTML = '<span class="badge bg-danger ms-2">'+n+'</span>'; else badgeEl.innerHTML = ''; }
          function escapeHtml(s){ return (s+'').replace(/[&<>\"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c];}); }
          function nl2br(s){ return s.replace(/\n/g,'<br>'); }
          fetchInbox(); setInterval(fetchInbox,20000);
        </script>
      </div>
    </div>

  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-o9N1j7kG2Qf5x1Q+0gk0fA6rN1Aj2c7qkG4X6qk/0+M=" crossorigin=""></script>
<script>
  // initialize map
  var latEl = document.getElementById('latitude');
  var lngEl = document.getElementById('longitude');
  var lat = parseFloat(latEl.value) || 14.5995; // default Manila
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