<?php
/**
 * SmartSlope - Login/Registration Page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

start_session();

// Redirect if already logged in
if (is_logged_in() && !empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    redirect(is_admin() ? 'admin.php' : 'dashboard.php');
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim(post('username'));
    $password = post('password');
    
    if ($user = authenticate_user($username, $password)) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $fullName = trim(post('full_name'));
    $username = trim(post('username'));
    $email = trim(post('email'));
    $phone = trim(post('phone'));
    $password = post('password');
    
    // Validation
    if ($fullName === '' || $username === '' || $email === '' || $phone === '' || $password === '') {
        flash('error', 'All fields are required.');
        redirect('index.php');
    }
    
    if (!validate_username($username)) {
        flash('error', 'Username must be 3-50 characters (letters, numbers, underscore only).');
        redirect('index.php');
    }
    
    if (!validate_email($email)) {
        flash('error', 'Invalid email address.');
        redirect('index.php');
    }
    
    if (!validate_phone($phone)) {
        flash('error', 'Phone must be 10-15 digits, optionally starting with +.');
        redirect('index.php');
    }
    
    if (!validate_password($password)) {
        flash('error', 'Password must be at least 8 characters.');
        redirect('index.php');
    }
    
    if (user_exists($username, $email, $phone)) {
        flash('error', 'Username, email, or phone already exists');
        redirect('index.php');
    }
    
    if (create_user($fullName, $username, $email, $phone, $password)) {
        flash('success', 'Registration successful! Please login.');
        redirect('index.php');
    } else {
        flash('error', 'Registration failed. Please try again.');
        redirect('index.php');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSlope - Login</title>
    <link rel="stylesheet" href="<?php echo url('assets/css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/frontend.css'); ?>">
    <style>
        .auth-shell { max-width: 500px; margin: 2rem auto; }
        .auth-intro h1 { color: var(--slope-green); font-weight: 700; letter-spacing: -.025em; }
        .auth-intro .page-subtitle { line-height: 1.5; }
        .auth-card { border-top: 4px solid var(--slope-green); }
        .auth-layout { display: block; }
        .auth-section { flex: 1 1 0; min-width: 0; padding: clamp(1.25rem, 4vw, 2rem); }
        .auth-section > h2, .auth-section > p { text-align: center; }
        .auth-section .form-control { text-align: left; }
        .auth-section .form-floating > label { width: calc(100% - 2rem); text-align: left; }
        .auth-section h2 { color: var(--slope-green); }
        .auth-register { border-top: 1px solid var(--slope-border); background: #f7f8f3; }
        .auth-register h2 { color: var(--slope-brown); }
        .auth-section .btn { min-height: 2.75rem; font-weight: 600; }
        .auth-section .form-text { line-height: 1.5; margin-top: .5rem; }
        .auth-toggle { text-align: center; margin: 1rem 0; }
        .auth-toggle a { text-decoration: none; }
    </style>
</head>
<body>
    <main class="auth-shell">
        <div class="auth-card card shadow">
            <div class="auth-layout">
                <!-- Login Section -->
                <div class="auth-section" id="login-section">
                    <div class="auth-intro text-center mb-4">
                        <h1>SmartSlope</h1>
                        <p class="page-subtitle">Rainfall Monitoring for Barangay Irisan, Baguio City</p>
                    </div>
                    
                    <?php if ($error = flash('error')): ?>
                        <div class="alert alert-danger"><?php echo e($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success = flash('success')): ?>
                        <div class="alert alert-success"><?php echo e($success); ?></div>
                    <?php endif; ?>
                    
                    <h2>Sign In</h2>
                    <form method="POST" action="index.php">
                        <input type="hidden" name="login" value="1">
                        
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Username" required autocomplete="username">
                            <label for="username">Username</label>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Password" required autocomplete="current-password">
                            <label for="password">Password</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Sign In</button>
                    </form>
                    
                    <div class="auth-toggle">
                        <p>Don't have an account? <a href="#" onclick="showRegister(); return false;">Register here</a></p>
                    </div>
                </div>
                
                <!-- Registration Section (Hidden by default) -->
                <div class="auth-section auth-register" id="register-section" style="display: none;">
                    <h2>Create Account</h2>
                    <form method="POST" action="index.php">
                        <input type="hidden" name="register" value="1">
                        
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   placeholder="Full Name" required autocomplete="name">
                            <label for="full_name">Full Name</label>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="reg_username" name="username" 
                                   placeholder="Username" required autocomplete="username">
                            <label for="reg_username">Username (3-50 chars, letters, numbers, underscore)</label>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="Email" required autocomplete="email">
                            <label for="email">Email Address</label>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   placeholder="Phone" required autocomplete="tel">
                            <label for="phone">Phone Number (10-15 digits, optional +)</label>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="reg_password" name="password" 
                                   placeholder="Password" required autocomplete="new-password" minlength="8">
                            <label for="reg_password">Password (min 8 characters)</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Create Account</button>
                    </form>
                    
                    <div class="auth-toggle">
                        <p>Already have an account? <a href="#" onclick="showLogin(); return false;">Sign in here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        function showRegister() {
            document.getElementById('login-section').style.display = 'none';
            document.getElementById('register-section').style.display = 'block';
        }
        
        function showLogin() {
            document.getElementById('register-section').style.display = 'none';
            document.getElementById('login-section').style.display = 'block';
        }
    </script>
</body>
</html>
