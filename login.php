<?php
require 'db.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) $err = 'Fill all fields.';
    else {
        $stmt = $mysqli->prepare("SELECT id,name,password,role FROM users WHERE email = ?");
        $stmt->bind_param('s',$email); $stmt->execute(); $res = $stmt->get_result();
        if ($user = $res->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                // redirect by role
                header('Location: '.($user['role'] === 'owner' ? 'owner-dashboard.php' : 'student-dashboard.php'));
                exit;
            } else $err = 'Invalid credentials.';
        } else $err = 'Invalid credentials.';
    }
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Login</title><link rel="stylesheet" href="css/bootstrap.min.css"></head>
<body class="p-4">
<div class="container col-md-5">
  <h2>Login</h2>
  <?php if(isset($_GET['registered'])): ?><div class="alert alert-success">Registered. You can log in.</div><?php endif; ?>
  <?php if($err): ?><div class="alert alert-danger"><?=esc($err)?></div><?php endif; ?>
  <form method="post">
    <div class="mb-3"><label>Email</label><input name="email" type="email" class="form-control" required></div>
    <div class="mb-3"><label>Password</label><input name="password" type="password" class="form-control" required></div>
    <button class="btn btn-primary">Login</button>
    <a href="register.php" class="btn btn-link">Register</a>
  </form>
</div>
</body>
</html>
