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
<html lang="en">
<head>
<meta charset="UTF-8">
<title>BH Houses | Register</title>
<link rel="stylesheet" href="css/auth.css">
</head>

<body>

<div class="login-card">

    <div class="logo">
        <!-- Replace with your actual logo -->
        <img src="../BH/uploads/bhlogo.jpg" alt="BH Houses">
    </div>

    <h2>Register</h2>
    <div class="subtitle">Create your account to get started</div>

    <?php if($err): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="text" name="name" class="form-control" placeholder="Full Name" required>
        <input type="email" name="email" class="form-control" placeholder="Email" required>
        <input type="password" name="password" class="form-control" placeholder="Password" required>
        <select name="role" class="form-control" required>
            <option value="">Select Role</option>
            <option value="owner">Owner</option>
            <option value="student">Student</option>
        </select>

        <button class="btn-primary">Create Account</button>
    </form>

    <div class="footer-links">
        Already have an account?
        <a href="login.php">Sign In</a>
    </div>
</div>

</body>
</html>
