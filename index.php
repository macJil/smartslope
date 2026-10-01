<?php
declare(strict_types=1);

require_once __DIR__ . '/configs/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['user_id'])
    && in_array($_SESSION['role'] ?? '', ['user', 'admin'], true)) {
    redirect_to(($_SESSION['role'] ?? '') === 'admin' ? 'admin/index.php' : 'resident/index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSlope | Barangay Irisan</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>">
    <script src="<?= e(app_url('assets/js/bootstrap.bundle.js')) ?>" defer></script>
</head>
<body>
<header class="p-3" style="background-color: aliceblue">
    <h1 class="h4 mb-0">Barangay Irisan (Baguio City) — SmartSlope</h1>
</header>
<main class="container my-4">
    <section class="card mx-auto" style="max-width: 420px" aria-labelledby="login-heading">
        <div class="card-body">
            <h2 id="login-heading" class="h5">Sign in</h2>
            <?php if ($message = flash('login_error')): ?>
                <div class="alert alert-danger" role="alert"><?= e($message) ?></div>
            <?php endif; ?>
            <?php if ($message = flash('register_success')): ?>
                <div class="alert alert-success" role="status"><?= e($message) ?></div>
            <?php endif; ?>
            <form action="<?= e(app_url('index.php')) ?>" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input class="form-control" type="text" id="username" name="username" maxlength="50" autocomplete="username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required>
                </div>
                <button class="btn btn-primary" type="submit" name="login" value="1">Sign in</button>
                <a class="btn btn-outline-secondary" href="<?= e(app_url('configs/register.php')) ?>">Register</a>
            </form>
        </div>
    </section>

</main>
<?php include __DIR__ . '/components/footer.html'; ?>
</body>
</html>
