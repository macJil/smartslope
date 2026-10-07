<?php

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/repositories.php';
start_session();

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    redirect(is_admin() ? 'admin.php' : 'dashboard.php');
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['login'])) {
    $username = trim(post('username'));
    $password = post('password');

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
    $fullName = trim(post('full_name'));
    $username = trim(post('username'));
    $email = trim(post('email'));
    $phone = trim(post('phone'));
    $password = post('password');

    if (
        $fullName === '' || $username === '' || $email === '' || $phone === '' || $password === '' ||
        strlen($fullName) > 100 || strlen($username) > 50 || strlen($email) > 254 ||
        strlen($phone) > 20 || strlen($password) > 72
    ) {
        flash('error', 'Fill all fields and keep them within the form limits.');
        redirect('index.php');
    }

    try {
        create_user($fullName, $username, $email, $phone, $password);
    } catch (PDOException $error) {
        error_log('Registration failed: ' . $error->getMessage());
        flash('error', $error->getCode() === '23000' ? 'Username, email, or phone already exists' : 'Registration is temporarily unavailable. Please try again.');
        redirect('index.php');
    }
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
    <link rel="stylesheet" href="<?= e(url('assets/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/frontend.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/frontend.css') ?>">
</head>
<body>
    <main class="container auth-shell py-4 py-md-5">
        <div class="text-center mb-4 auth-intro">
            <h1 class="h2 mb-2">SmartSlope</h1>
            <p class="page-subtitle mb-0">Landslide awareness for Barangay Irisan, Baguio City</p>
        </div>
        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-danger" role="alert"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success" role="status"><?= e($msg) ?></div>
        <?php endif; ?>
        <div class="card auth-card">
            <div class="auth-layout">
                <section class="auth-section" aria-labelledby="login-title">
                    <h2 id="login-title" class="h4 mb-2">Log in</h2>
                    <p class="page-subtitle mb-3">Continue to the Irisan dashboard.</p>
                    <form method="post">

                        <input type="hidden" name="login" value="1">
                        <div class="form-floating mb-3">
                            <input type="text" name="username" id="login_username" class="form-control" placeholder="Username" autocomplete="username" required autofocus>
                            <label for="login_username">Username</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="password" name="password" id="login_password" class="form-control" placeholder="Password" autocomplete="current-password" required>
                            <label for="login_password">Password</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Log in</button>
                    </form>
                </section>
                <section class="auth-section auth-register" aria-labelledby="register-title">
                    <h2 id="register-title" class="h4 mb-2">Create an account</h2>
                    <p class="page-subtitle mb-3">Register as a resident to submit observations.</p>
                    <form method="post">

                        <input type="hidden" name="register" value="1">
                        <div class="d-flex flex-column gap-3">
                            <div class="form-floating">
                                <input type="text" name="full_name" id="register_name" class="form-control" placeholder="Full name" maxlength="100" autocomplete="name" required>
                                <label for="register_name">Full name</label>
                            </div>
                            <div class="form-floating">
                                <input type="text" name="username" id="register_username" class="form-control" placeholder="Username" minlength="3" maxlength="50" pattern="[A-Za-z0-9_]{3,50}" autocomplete="username" required>
                                <label for="register_username">Username</label>
                            </div>
                            <div class="form-floating">
                                <input type="email" name="email" id="register_email" class="form-control" placeholder="Email" maxlength="254" autocomplete="email" required>
                                <label for="register_email">Email</label>
                            </div>
                            <div class="form-floating">
                                <input type="tel" name="phone" id="register_phone" class="form-control" placeholder="Phone" maxlength="16" pattern="\+?[0-9]{10,15}" autocomplete="tel" required>
                                <label for="register_phone">Phone</label>
                            </div>
                            <div>
                                <div class="form-floating">
                                    <input type="password" name="password" id="register_password" class="form-control" placeholder="Password" minlength="8" maxlength="72" autocomplete="new-password" required>
                                    <label for="register_password">Password</label>
                                </div>
                                <div class="form-text">Use 8–72 characters. Phone numbers use 10–15 digits, optionally starting with +.</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Create account</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
        <p class="text-center small page-subtitle mt-4">SmartSlope is an academic prototype; see the sources and methodology after signing in.</p>
    </main>
    <script src="<?= e(url('assets/js/bootstrap.bundle.js')) ?>"></script>
</body>
</html>
