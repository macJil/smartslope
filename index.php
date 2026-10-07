<?php
// Simple Index - Login/Registration Page
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/User.php';

start_session();

// Initialize database and models first
try {
    $database = new Database();
    $pdo = $database->connect();
    $user = new User($pdo);
} catch (PDOException $e) {
    error_log('Database connection error in index.php: ' . $e->getMessage());
    die("Database connection error. Please check the server configuration.");
}

// If already logged in, redirect to appropriate page
// Only redirect if we have a valid, complete session with proper user data
if (is_logged_in() && 
    !empty($_SESSION['user_id']) && 
    !empty($_SESSION['username']) && 
    !empty($_SESSION['role']) &&
    isset($_SESSION['full_name'])) {
    redirect(is_admin() ? 'admin.php' : 'dashboard.php');
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['login'])) {
    
    $username = trim(post('username'));
    $password = post('password');
    
    if ($authenticatedUser = $user->authenticate($username, $password)) {
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['user_id'] = $authenticatedUser['id'];
        $_SESSION['full_name'] = $authenticatedUser['full_name'];
        $_SESSION['username'] = $authenticatedUser['username'];
        $_SESSION['email'] = $authenticatedUser['email'];
        $_SESSION['phone'] = $authenticatedUser['phone'];
        $_SESSION['role'] = $authenticatedUser['role'];
        
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
    
    // Simple validation
    if ($fullName === '' || $username === '' || $email === '' || $phone === '' || $password === '') {
        flash('error', 'All fields are required.');
        redirect('index.php');
    }
    
    // Validate username (3-50 chars, alphanumeric + underscore)
    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        flash('error', 'Username must be 3-50 characters (letters, numbers, underscore only).');
        redirect('index.php');
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Invalid email address.');
        redirect('index.php');
    }
    
    // Validate phone
    if (!preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
        flash('error', 'Phone must be 10-15 digits, optionally starting with +.');
        redirect('index.php');
    }
    
    // Validate password length
    if (strlen($password) < 8) {
        flash('error', 'Password must be at least 8 characters.');
        redirect('index.php');
    }
    
    // Check if user exists
    if ($user->exists($username, $email, $phone)) {
        flash('error', 'Username, email, or phone already exists');
        redirect('index.php');
    }
    
    // Create user
    try {
        $user->create($fullName, $username, $email, $phone, $password);
        flash('success', 'Registration successful! Please login.');
    } catch (PDOException $error) {
        error_log('Registration failed: ' . $error->getMessage());
        flash('error', 'Registration failed. Please try again.');
    }
    
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
    <link rel="stylesheet" href="<?= e(url('assets/css/frontend.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/assets/css/frontend.css') ?>">
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
                <!-- Login Section -->
                <section class="auth-section" aria-labelledby="login-title">
                    <h2 id="login-title" class="h4 mb-2">Log in</h2>
                    <p class="page-subtitle mb-3">Continue to the Irisan dashboard.</p>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="login" value="1">
                        <div class="form-floating mb-3">
                            <input type="text" name="username" id="login_username" class="form-control" 
                                   placeholder="Username" autocomplete="username" required autofocus>
                            <label for="login_username">Username</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="password" name="password" id="login_password" class="form-control" 
                                   placeholder="Password" autocomplete="current-password" required>
                            <label for="login_password">Password</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Log in</button>
                    </form>
                </section>
                
                <!-- Registration Section -->
                <section class="auth-section auth-register" aria-labelledby="register-title">
                    <h2 id="register-title" class="h4 mb-2">Create an account</h2>
                    <p class="page-subtitle mb-3">Register as a resident to submit observations.</p>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="register" value="1">
                        <div class="d-flex flex-column gap-3">
                            <div class="form-floating">
                                <input type="text" name="full_name" id="register_name" class="form-control" 
                                       placeholder="Full name" maxlength="100" autocomplete="name" required>
                                <label for="register_name">Full name</label>
                            </div>
                            <div class="form-floating">
                                <input type="text" name="username" id="register_username" class="form-control" 
                                       placeholder="Username" minlength="3" maxlength="50" 
                                       pattern="[A-Za-z0-9_]{3,50}" autocomplete="username" required>
                                <label for="register_username">Username</label>
                            </div>
                            <div class="form-floating">
                                <input type="email" name="email" id="register_email" class="form-control" 
                                       placeholder="Email" maxlength="254" autocomplete="email" required>
                                <label for="register_email">Email</label>
                            </div>
                            <div class="form-floating">
                                <input type="tel" name="phone" id="register_phone" class="form-control" 
                                       placeholder="Phone" maxlength="16" pattern="\+?[0-9]{10,15}" 
                                       autocomplete="tel" required>
                                <label for="register_phone">Phone</label>
                            </div>
                            <div>
                                <div class="form-floating">
                                    <input type="password" name="password" id="register_password" class="form-control" 
                                           placeholder="Password" minlength="8" maxlength="72" 
                                           autocomplete="new-password" required>
                                    <label for="register_password">Password</label>
                                </div>
                                <div class="form-text">Use 8-72 characters. Phone numbers use 10-15 digits, optionally starting with +.</div>
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
