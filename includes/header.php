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
    
    <!-- PWA Manifest & Icons -->
    <link rel="manifest" href="<?= h(app_url('/manifest.json')); ?>">
    <link rel="apple-touch-icon" href="<?= h(app_url('/assets/img/logo.png')); ?>">
    
    <link rel="stylesheet" href="<?= h(app_url('/assets/css/style.css?v=' . time())); ?>">
    
    <!-- Register Service Worker for PWA -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?= h(app_url('/sw.js')); ?>')
                    .then(registration => console.log('ServiceWorker registered successfully'))
                    .catch(err => console.error('ServiceWorker registration failed: ', err));
            });
        }
    </script>
</head>
<body>
    <!-- Mobile Sidebar Overlay -->
    <div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileMenu()"></div>

    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-wrapper" id="logoBtn" title="Click to view logo" style="cursor:pointer; background:#fff; border-radius:50%; overflow:hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.5), 0 0 0 3px rgba(16,185,129,0.4);">
                    <img src="<?= h(app_url('/assets/img/logo.png?v=' . (time()+2))); ?>" alt="DENR Logo" class="sidebar-logo" style="object-fit:cover; padding:0; transform:scale(1.55) translateX(-1.5%); border-radius:50%;">
                </div>

                <!-- Logo Modal -->
                <div id="logoModal" role="dialog" aria-modal="true" aria-label="DENR Logo" style="
                    display:none; position:fixed; inset:0; z-index:9999;
                    background:rgba(0,0,0,0.82); backdrop-filter:blur(14px);
                    -webkit-backdrop-filter:blur(14px);
                    align-items:center; justify-content:center; cursor:pointer;
                ">
                    <div id="logoModalInner" style="
                        position:relative;
                        width: min(420px, 88vw);
                        height: min(420px, 88vw);
                        border-radius:50%;
                        overflow:hidden;
                        box-shadow: 0 0 0 6px rgba(16,185,129,0.35), 0 0 60px rgba(16,185,129,0.25), 0 30px 80px rgba(0,0,0,0.6);
                        animation: logoModalZoomIn 0.4s cubic-bezier(0.175,0.885,0.32,1.275) forwards;
                        cursor:default;
                    ">
                        <img src="<?= h(app_url('/assets/img/logo.png?v=' . (time()+2))); ?>" alt="DENR Logo" style="
                            display:block; width:100%; height:100%;
                            object-fit:cover; border-radius:50%; transform:scale(1.55) translateX(-1.5%); background:#fff; padding:0;
                        ">
                        <!-- Shine overlay -->
                        <div style="
                            position:absolute; top:0; left:0; width:100%; height:100%;
                            border-radius:50%; pointer-events:none; overflow:hidden;
                        ">
                            <div style="
                                position:absolute; top:0; left:-150%; width:60%; height:100%;
                                background:linear-gradient(90deg,rgba(255,255,255,0) 0%,rgba(255,255,255,0.45) 50%,rgba(255,255,255,0) 100%);
                                transform:skewX(-25deg);
                                animation: logoShine 2.5s infinite ease-in-out;
                            "></div>
                        </div>
                    </div>
                    <p style="
                        position:fixed; bottom:40px; left:0; right:0;
                        text-align:center; color:rgba(255,255,255,0.45);
                        font-size:0.88rem; font-family:inherit; pointer-events:none;
                    ">Click anywhere to close &nbsp;·&nbsp; Press Esc</p>
                </div>

                <style>
                @keyframes logoModalZoomIn {
                    from { opacity:0; transform:scale(0.35); }
                    to   { opacity:1; transform:scale(1); }
                }
                @keyframes logoModalZoomOut {
                    from { opacity:1; transform:scale(1); }
                    to   { opacity:0; transform:scale(0.35); }
                }
                </style>

                <script>
                (function(){
                    var btn   = document.getElementById('logoBtn');
                    var modal = document.getElementById('logoModal');
                    var inner = document.getElementById('logoModalInner');

                    // Move modal to <body> so it escapes the sidebar's
                    // backdrop-filter stacking context — otherwise position:fixed
                    // is relative to the sidebar, not the viewport.
                    document.body.appendChild(modal);

                    function openModal() {
                        modal.style.display = 'flex';
                        inner.style.animation = 'logoModalZoomIn 0.4s cubic-bezier(0.175,0.885,0.32,1.275) forwards';
                        document.body.style.overflow = 'hidden';
                    }

                    function closeModal() {
                        inner.style.animation = 'logoModalZoomOut 0.3s ease forwards';
                        setTimeout(function(){ modal.style.display = 'none'; document.body.style.overflow = ''; }, 280);
                    }

                    btn.addEventListener('click', openModal);

                    modal.addEventListener('click', function(e){
                        if (e.target !== inner && !inner.contains(e.target)) closeModal();
                    });

                    document.addEventListener('keydown', function(e){
                        if (e.key === 'Escape' && modal.style.display === 'flex') closeModal();
                    });
                })();
                </script>
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
                <!-- <a class="<?= is_active('/land_use/', $currentPath); ?>" href="<?= h(app_url('/land_use/index.php')); ?>">
                    <span class="nav-icon">🏗️</span><span>Land Use</span>
                </a> -->
                <a class="<?= is_active('/imports/', $currentPath); ?>" href="<?= h(app_url('/imports/index.php')); ?>">
                    <span class="nav-icon">📥</span><span>Import Excel</span>
                </a>
                <!-- <a class="<?= is_active('/reports/', $currentPath); ?>" href="<?= h(app_url('/reports/index.php')); ?>">
                    <span class="nav-icon">📊</span><span>Reports</span>
                </a> -->
            </nav>
        </aside>
        <main class="main-content">
            <!-- Mobile Header Bar -->
            <div class="mobile-header">
                <div style="font-weight: 800; font-size: 1.25rem; color: #fff; display: flex; align-items: center; gap: 10px;">
                    <img src="<?= h(app_url('/assets/img/logo.png?v=' . (time()+3))); ?>" alt="Logo" style="width: 32px; height: 32px; object-fit: cover; border-radius: 50%; transform: scale(1.45);">
                    DENR CENRO
                </div>
                <button onclick="toggleMobileMenu()" style="background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer;">
                    ☰
                </button>
            </div>

            <script>
            function toggleMobileMenu() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('mobileOverlay');
                
                sidebar.classList.toggle('mobile-active');
                overlay.classList.toggle('active');
                
                // Prevent background scrolling when menu is open
                if (sidebar.classList.contains('mobile-active')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }
            </script>

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

