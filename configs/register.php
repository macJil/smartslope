<?php
require_once __DIR__ . '/add_user.php';


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Landslide Warning System</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>">
    <script src="<?= e(app_url('assets/js/bootstrap.bundle.js')) ?>" defer></script>
</head>
<body>
    <header class="nav" style="background-color: aliceblue; display:flex; justify-content:space-between; align-items:center; padding:10px 20px;">
        <h1>BARANGAY IRISAN (Baguio City) - Landslide Warning System</h1>
    </header>
    <div class="container" style="width: 900px; align-items:center; text-align: center" >
        <h4>Register Page</h4>
        <?php if ($message = flash('register_error')): ?>
            <div class="alert alert-danger" role="alert"><?= e($message) ?></div>
        <?php endif; ?>
        <?php if ($message = flash('register_success')): ?>
            <div class="alert alert-success" role="alert"><?= e($message) ?></div>
        <?php endif; ?>
        <form action="<?= e(app_url('configs/register.php')) ?>" method="post" class="form-control">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <table class="table table-borderless">
                <tr>
                    <th>Enter Username</th>
                    <td>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </td>
                </tr>
                <tr>
                    <th>Enter Full Name</th>
                    <td>
                        <input type="text" name="full_name" id="full_name" class="form-control" required>
                    </td>
                </tr>
              
                <tr>
                    <th>Enter Email</th>
                    <td>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </td>
                </tr>
                <tr>
                    <th>Enter Password</th>
                    <td>
                        <input type="password" name="password" id="password" class="form-control" required minlength="8">
                    </td>
                </tr>
            </table>
            <button class="btn btn-outline-success" name="register">Register</button>
            <a href="<?= e(app_url('index.php')) ?>" class="btn btn-outline-danger">Cancel</a>
        </form>
    </div>
</body>
<?php include __DIR__ . '/../components/footer.html';?>
</html>
