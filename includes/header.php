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
    <title><?= h($pageTitle ?? 'Land Inventory System'); ?></title>
    <link rel="stylesheet" href="<?= h(app_url('/assets/css/style.css')); ?>">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-wrapper">
                    <img src="<?= h(app_url('/assets/img/logo.png')); ?>" alt="Lungsod Ng Digos Logo" class="sidebar-logo">
                </div>
                <div class="brand">Land Inventory</div>
                <p class="brand-subtitle">Offline Barangay Lot Monitoring</p>
            </div>
            <nav class="nav-menu">
                <a class="<?= $currentPath === app_url('/index.php') || $currentPath === app_url('') || $currentPath === '/Land Inventory System/index.php' || $currentPath === '/Land Inventory System/' ? 'active' : ''; ?>" href="<?= h(app_url('/index.php')); ?>">Dashboard</a>
                <a class="<?= is_active('/barangay/', $currentPath); ?>" href="<?= h(app_url('/barangay/index.php')); ?>">Barangay</a>
                <a class="<?= is_active('/lots/', $currentPath); ?>" href="<?= h(app_url('/lots/index.php')); ?>">Lots</a>
                <a class="<?= is_active('/land_use/', $currentPath); ?>" href="<?= h(app_url('/land_use/index.php')); ?>">Land Use</a>
                <a class="<?= is_active('/imports/', $currentPath); ?>" href="<?= h(app_url('/imports/index.php')); ?>">Import Excel</a>
                <a class="<?= is_active('/reports/', $currentPath); ?>" href="<?= h(app_url('/reports/index.php')); ?>">Reports</a>
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
                <div class="alert <?= h($flash['type']); ?>"><?= h($flash['message']); ?></div>
            <?php endif; ?>
