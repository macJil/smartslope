<?php
declare(strict_types=1);

require_once __DIR__ . '/configs/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['user_id'])
    && in_array($_SESSION['role'] ?? '', ['user', 'admin'], true)) {
    redirect_to(($_SESSION['role'] ?? '') === 'admin' ? 'admin/index.php' : 'resident/index.php');
}
$publicLocations = (new LocationRepository($pdo))->activeForStudyArea();
$publicReadings = new ReadingRepository($pdo);
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
    <section class="card mx-auto mb-4" style="max-width: 420px" aria-labelledby="login-heading">
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

    <section aria-labelledby="public-heading">
        <h2 id="public-heading" class="h4">Barangay Irisan location overview</h2>
        <p class="text-muted">Baseline susceptibility and the latest rainfall indicator are separate. These prototype indicators are not official warnings. Observation time uses Philippine time.</p>
        <div class="row g-3">
            <?php foreach ($publicLocations as $location): ?>
                <?php
                $latest = $publicReadings->latestForActiveLocation((int) $location['location_id']);
                $age = $latest ? time() - (new DateTimeImmutable($latest['observed_at'], new DateTimeZone('UTC')))->getTimestamp() : null;
                $stale = $age === null || $age > 7200 || $age < -600;
                ?>
                <div class="col-md-6"><article class="card h-100"><div class="card-body">
                    <h3 class="h5"><?= e($location['location_name']) ?></h3>
                    <p>Baseline susceptibility: <strong><?= e(str_replace('_', ' ', $location['susceptibility_class'])) ?></strong>
                    <?php if ($location['hazard_source_name']): ?> · Source: <?= e($location['hazard_source_name']) ?><?php endif; ?></p>
                    <p class="mb-0">Latest rainfall indicator: <strong><?= $latest ? e(strtoupper($latest['risk_level'])) : 'Unavailable' ?></strong><?= $stale ? ' (stale or missing)' : '' ?>.
                    <?php if ($latest): ?> Observed <?= e(display_local_datetime($latest['observed_at'])) ?> PHT.<?php endif; ?></p>
                </div></article></div>
            <?php endforeach; ?>
            <?php if (!$publicLocations): ?><p>No active Irisan locations are available yet.</p><?php endif; ?>
        </div>
    </section>
</main>
<?php include __DIR__ . '/components/footer.html'; ?>
</body>
</html>
