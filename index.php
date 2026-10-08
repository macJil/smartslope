<?php
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/RiskAnalyzer.php';
require_once __DIR__ . '/app/assessment.php';
require_once __DIR__ . '/app/repositories.php';

start_session();
// ... rest of code (unchanged)

start_session();

// An authenticated visitor goes straight to their page.
if (is_logged_in()) {
    if (is_admin()) {
        redirect('admin.php');
    }
    redirect('dashboard.php');
}

$errorMessage = flash('error') ?? '';
$successMessage = flash('success') ?? '';
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

// Login: find the account, check its password, then set the session.
if ($isPost && !empty($_POST['login'])) {
    $username = trim(post('username'));
    $password = post('password');

    try {
        $statement = db()->prepare('SELECT id, full_name, username, email, phone, password, role FROM users WHERE username = ? LIMIT 1');
        $statement->execute([$username]);
        $user = $statement->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION = [];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['phone'] = $user['phone'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                redirect('admin.php');
            }
            redirect('dashboard.php');
        }
        $errorMessage = 'Invalid username or password';
    } catch (PDOException $error) {
        error_log('SmartSlope login: ' . $error->getMessage());
        $errorMessage = database_error_message($error);
    }
} elseif ($isPost && !empty($_POST['register'])) {
    // Registration always creates a resident; the browser cannot choose a role.
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
        $errorMessage = 'Fill all fields and keep them within the form limits.';
    } else {
        try {
            create_user($fullName, $username, $email, $phone, $password);
            flash('success', 'Registration successful! Please login.');
            redirect('index.php');
        } catch (PDOException $error) {
            error_log('Registration failed: ' . $error->getMessage());
            if ($error->getCode() === '23000') {
                $errorMessage = 'Username, email, or phone already exists';
            } else {
                $errorMessage = database_error_message($error);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSlope - Login</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/frontend.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/frontend.css') ?>">
</head>
<body>
    <main class="container auth-shell py-4 py-md-5">
        <div class="text-center mb-4 auth-intro">
            <h1 class="h2 mb-2">SmartSlope</h1>
            <p class="page-subtitle mb-0">Landslide awareness for Barangay Irisan, Baguio City</p>
        </div>
        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= e($errorMessage) ?></div>
        <?php endif; ?>
        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success" role="status"><?= e($successMessage) ?></div>
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
    <script src="assets/js/bootstrap.bundle.js"></script>
</body>
</html>
