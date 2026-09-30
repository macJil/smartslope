<?php


require_once __DIR__ . '/configs/auth.php';



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landslide</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>">
    <script src="<?= e(app_url('assets/js/bootstrap.bundle.js')) ?>"></script>
</head>
<header class="nav" style="background-color: aliceblue; display: flex; justify-content: space-between; align-items: center; padding: 10px 20px;">
    <h1>BARANGAY IRISAN (Baguio City) - Landslide Warning System</h1>
    
</header>

<body>
    
    <div class="container" style="width: 300px; align-items:center;text-align:center">
        <h4>Login Page</h4>
        <?php if ($message = flash('login_error')): ?>
            <div class="alert alert-danger" role="alert"><?= e($message) ?></div>
        <?php endif; ?>
        <?php if ($message = flash('register_success')): ?>
            <div class="alert alert-success" role="alert"><?= e($message) ?></div>
        <?php endif; ?>
        <form action="<?= e(app_url('index.php')) ?>" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="card" >
                <div class="card-header">
                    <p>Enter credentials</p>
                </div>
                <div class="body">
                    <div class="form-control ">
                        <label for="username">Username:</label><br>
                        <input type="text" id="username" name="username" class="form-control"
                            maxlength="50" autocomplete="username" required><br>
                        <label for="password" class="form">Password:</label><br>
                        <input type="password" id="password" name="password" class="form-control"
                            autocomplete="current-password" required><br>
                      
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-outline-success" name="login">Login</button>
                    <a href="<?= e(app_url('configs/register.php')) ?>" class="btn btn-outline-primary">Register</a>
                </div>

            </div>
        
        </form>
    </div>
<?php include __DIR__ . '/components/footer.html';?>
</body>
</html>