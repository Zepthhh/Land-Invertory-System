<?php
declare(strict_types=1);

$flash = get_flash();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

function is_active(string $needle, string $currentPath): string
{
    return str_contains($currentPath, $needle) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= h($pageDescription ?? 'DENR CENRO Land Inventory & RLTA Management System — Barangay land records, lot tracking, conflict monitoring, and reports.'); ?>">
    <meta name="theme-color" content="#10b981">
    <title><?= h($pageTitle ?? 'Land Inventory System'); ?> — DENR CENRO</title>
    <link rel="stylesheet" href="<?= h(app_url('/assets/css/style.css?v=2026061102')); ?>">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-wrapper">
                    <img src="<?= h(app_url('/assets/img/logo.png')); ?>" alt="DENR Logo" class="sidebar-logo">
                </div>
                <div class="brand">DENR CENRO</div>
                <p class="brand-subtitle">Land Inventory &amp; RLTA</p>
            </div>
            <nav class="nav-menu" aria-label="Main Navigation">
                <a class="<?= $currentPath === app_url('/index.php') || $currentPath === app_url('') || $currentPath === '/Land-Invertory-System/index.php' || $currentPath === '/Land-Invertory-System/' ? 'active' : ''; ?>" href="<?= h(app_url('/index.php')); ?>">
                    <span class="nav-icon">🏠</span><span>Dashboard</span>
                </a>
                <a class="<?= is_active('/barangay/', $currentPath); ?>" href="<?= h(app_url('/barangay/index.php')); ?>">
                    <span class="nav-icon">🗺️</span><span>Barangay</span>
                </a>
                <a class="<?= is_active('/lots/', $currentPath); ?>" href="<?= h(app_url('/lots/index.php')); ?>">
                    <span class="nav-icon">📋</span><span>Lots</span>
                </a>
                <a class="<?= is_active('/land_use/', $currentPath); ?>" href="<?= h(app_url('/land_use/index.php')); ?>">
                    <span class="nav-icon">🏗️</span><span>Land Use</span>
                </a>
                <a class="<?= is_active('/imports/', $currentPath); ?>" href="<?= h(app_url('/imports/index.php')); ?>">
                    <span class="nav-icon">📥</span><span>Import Excel</span>
                </a>
                <a class="<?= is_active('/reports/', $currentPath); ?>" href="<?= h(app_url('/reports/index.php')); ?>">
                    <span class="nav-icon">📊</span><span>Reports</span>
                </a>
            </nav>
        </aside>
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1><?= h($pageTitle ?? 'Land Inventory System'); ?></h1>
                    <?php if (!empty($pageDescription)): ?>
                        <p><?= h($pageDescription); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="alert <?= h($flash['type']); ?>" role="alert"><?= h($flash['message']); ?></div>
            <?php endif; ?>

