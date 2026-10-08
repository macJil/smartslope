<?php
if (empty($_SESSION['user_id'])) {
    http_response_code(404);
    exit;
}
    $links = [
        'dashboard' => ['dashboard.php', 'Dashboard'],
        'readings' => ['readings.php', 'Readings'],
        'methodology' => ['methodology.php', 'Sources & methodology'],
    ];
    if (($_SESSION['role'] ?? '') === 'admin') {
        $links = ['dashboard' => $links['dashboard'], 'admin' => ['admin.php', 'Admin'],
            'readings' => $links['readings'], 'methodology' => $links['methodology']];
    } else {
        $links = ['dashboard' => $links['dashboard'], 'report' => ['report.php', 'Submit report'],
            'readings' => $links['readings'], 'methodology' => $links['methodology']];
    }
?>
    <nav class="navbar navbar-expand-lg site-nav" aria-label="Main navigation">
        <div class="container-fluid px-3 px-lg-4">
            <a class="navbar-brand fw-semibold" href="dashboard.php">SmartSlope <span class="brand-place">Irisan</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNavigation"
                    aria-controls="siteNavigation" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="siteNavigation">
                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <?php foreach ($links as $key => [$path, $label]): ?>
                        <a class="nav-link<?= $active === $key ? ' active' : '' ?>" href="<?= e($path) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                    <?php endforeach; ?>
                    <form method="post" action="logout.php" class="nav-logout">
                        <button class="nav-link btn btn-link" type="submit">Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
