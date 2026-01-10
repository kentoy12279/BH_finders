<?php
require 'db.php';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $err = 'Fill all fields.';
    } else {
        $stmt = $mysqli->prepare("SELECT id,name,password,role FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($user = $res->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                header('Location: ' . ($user['role'] === 'owner'
                    ? 'owner-dashboard.php'
                    : 'student-dashboard.php'));
                exit;
            } else {
                $err = 'Invalid credentials.';
            }
        } else {
            $err = 'Invalid credentials.';
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>BH Houses | Login</title>
<link rel="stylesheet" href="css/auth.css">
</head>

<body>

<div class="login-card">

    <div class="logo">
        <!-- Replace with your actual logo -->
        <img src="../BH/uploads/bhlogo.jpg" alt="BH Houses">
    </div>

    <h2>Login</h2>
    <div class="subtitle">Find safe and affordable boarding houses</div>

    <?php if(isset($_GET['registered'])): ?>
        <div class="alert alert-success">Registered successfully. You may log in.</div>
    <?php endif; ?>

    <?php if($err): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="email" name="email" class="form-control" placeholder="Email" required>
        <input type="password" name="password" class="form-control" placeholder="Password" required>

        <button class="btn-primary">Sign In</button>
    </form>



    <div class="footer-links">
        Don’t have an account?
        <a href="register.php">Create Account</a>
    </div>
</div>

<script>
function loginWithGoogle() {
    // Redirect to Google OAuth
    window.location.href = 'social_login.php?provider=google';
}

function loginWithFacebook() {
    // Redirect to Facebook OAuth
    window.location.href = 'social_login.php?provider=facebook';
}
</script>

</body>
</html>
