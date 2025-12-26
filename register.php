<?php
require 'db.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (!$name || !$email || !$password || !in_array($role,['owner','student'])) {
        $err = 'All fields required.';
    } else {
        // check email
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s',$email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows) { $err = 'Email already registered.'; }
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $mysqli->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
            $ins->bind_param('ssss',$name,$email,$hash,$role);
            $ins->execute();
            header('Location: login.php?registered=1'); exit;
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"><title>Register</title>
  <link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container col-md-6">
  <h2>Register</h2>
  <?php if($err): ?><div class="alert alert-danger"><?=esc($err)?></div><?php endif; ?>
  <form method="post">
    <div class="mb-3"><label class="form-label">Name</label><input required name="name" class="form-control"></div>
    <div class="mb-3"><label class="form-label">Email</label><input required type="email" name="email" class="form-control"></div>
    <div class="mb-3"><label class="form-label">Password</label><input required type="password" name="password" class="form-control"></div>
    <div class="mb-3"><label class="form-label">Role</label>
      <select name="role" class="form-select" required>
        <option value="">Select role</option>
        <option value="owner">Owner</option>
        <option value="student">Student</option>
      </select>
    </div>
    <button class="btn btn-primary">Register</button>
    <a href="login.php" class="btn btn-link">Login</a>
  </form>
</div>
<script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>