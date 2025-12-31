<?php
require 'db.php';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['background'])) {
    $file = $_FILES['background'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($ext), $allowed)) {
            $newName = 'bg_' . time() . '.' . $ext;
            $path = 'uploads/' . $newName;
            if (move_uploaded_file($file['tmp_name'], $path)) {
                $_SESSION['background'] = $path;
                $msg = 'Background updated successfully.';
            } else {
                $msg = 'Failed to upload file.';
            }
        } else {
            $msg = 'Invalid file type. Only JPG, PNG, GIF allowed.';
        }
    } else {
        $msg = 'Upload error.';
    }
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Change Background</title><link rel="stylesheet" href="css/bootstrap.min.css"></head>
<body class="p-4">
<div class="container col-md-5">
  <a href="login.php" class="btn btn-sm btn-link">Back to Login</a>
  <h4>Change Background Photo</h4>
  <?php if($msg): ?><div class="alert alert-info"><?=esc($msg)?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <div class="mb-3">
      <label>Upload Background Image</label>
      <input type="file" name="background" class="form-control" accept="image/*" required>
    </div>
    <button class="btn btn-primary">Upload</button>
  </form>
</div>
</body>
</html>
