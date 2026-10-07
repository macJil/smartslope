<?php
/**
 * SmartSlope - Navigation Bar Partial
 */
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark site-nav">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo url('index.php'); ?>">
            <strong>SmartSlope</strong>
            <span class="brand-place d-block small">Barangay Irisan, Baguio City</span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('dashboard.php'); ?>">
                            <i class="bi bi-house me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('readings.php'); ?>">
                            <i class="bi bi-graph-up me-1"></i> Readings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('report.php'); ?>">
                            <i class="bi bi-plus-circle me-1"></i> Submit Report
                        </a>
                    </li>
                    <?php if (is_admin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo url('admin.php'); ?>">
                                <i class="bi bi-shield-check me-1"></i> Admin
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('methodology.php'); ?>">
                            <i class="bi bi-info-circle me-1"></i> Methodology
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav">
                <?php if (is_logged_in()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> 
                            <?php echo e($_SESSION['username'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <span class="dropdown-item-text text-muted small">
                                    <?php echo e($_SESSION['role'] === 'admin' ? 'Administrator' : 'Resident'); ?>
                                </span>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo url('logout.php'); ?>">
                                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('index.php'); ?>">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
