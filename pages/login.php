<?php
require_once __DIR__ . '/../app/config.php';
start_session();

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    redirect(is_admin() ? 'admin.php' : 'dashboard.php');
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($user = authenticate($username, $password)) {
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['phone'] = $user['phone'];
        $_SESSION['role'] = $user['role'];

        redirect(is_admin() ? 'admin.php' : 'dashboard.php');
    } else {
        flash('error', 'Invalid username or password');
        redirect('index.php');
    }
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['register'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($fullName) || empty($username) || empty($email) || empty($phone) || empty($password)) {
        flash('error', 'All fields are required');
        redirect('index.php');
    }

    $pdo = db();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ? OR phone = ?");
    $stmt->execute([$username, $email, $phone]);
    if ($stmt->fetchColumn() > 0) {
        flash('error', 'Username, email, or phone already exists');
        redirect('index.php');
    }

    create_user($fullName, $username, $email, $phone, $password);
    flash('success', 'Registration successful! Please login.');
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSlope - Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-body">
                        <h2 class="text-center mb-4">SmartSlope</h2>
                        <p class="text-center text-muted mb-4">Barangay Irisan, Baguio City</p>

                        <?php if ($msg = flash('error')): ?>
                            <div class="alert alert-danger"><?= e($msg) ?></div>
                        <?php endif; ?>

                        <?php if ($msg = flash('success')): ?>
                            <div class="alert alert-success"><?= e($msg) ?></div>
                        <?php endif; ?>

                        <h3 class="h5 mb-3">Login</h3>
                        <form method="post" class="mb-4">
                            <input type="hidden" name="login" value="1">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>

                        <hr>

                        <h3 class="h5 mb-3">Register</h3>
                        <form method="post">
                            <input type="hidden" name="register" value="1">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-outline-secondary w-100">Register</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
