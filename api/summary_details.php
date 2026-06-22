<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce login
require_login();

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';

if ($type === 'municipality') {
    $name = $_GET['name'] ?? '';
    if (!$name) {
        echo json_encode(['error' => 'Municipality name required']);
        exit;
    }

    // Get Municipality ID
    $stmt = $mysqli->prepare("SELECT id FROM municipality WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $res = $stmt->get_result();
    $mun = $res->fetch_assoc();
    
    if (!$mun) {
        echo json_encode(['error' => 'Municipality not found']);
        exit;
    }
    $munId = (int)$mun['id'];

    // Status breakdown for this municipality
    $statusQuery = "
        SELECT l.status, COUNT(*) AS count, COALESCE(SUM(l.area_sqm), 0) AS total_area
        FROM lots l
        JOIN barangay b ON l.barangay_id = b.id
        WHERE b.municipality_id = ?
        GROUP BY l.status
    ";
    $stmt = $mysqli->prepare($statusQuery);
    $stmt->bind_param("i", $munId);
    $stmt->execute();
    $statusRes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Top 3 Barangays by Lot Count
    $topBrgyQuery = "
        SELECT b.name, COUNT(l.id) as lot_count, COALESCE(SUM(l.area_sqm), 0) as total_area
        FROM barangay b
        LEFT JOIN lots l ON l.barangay_id = b.id
        WHERE b.municipality_id = ?
        GROUP BY b.id
        ORDER BY lot_count DESC, total_area DESC
        LIMIT 3
    ";
    $stmt = $mysqli->prepare($topBrgyQuery);
    $stmt->bind_param("i", $munId);
    $stmt->execute();
    $topBrgys = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    ob_start();
    ?>
    <div class="card-view-content">
        <h3 class="cv-title">Lot Status Breakdown</h3>
        <div class="conflict-stats-grid">
            <?php 
            $colors = ['Titled' => 'var(--primary)', 'Applied' => 'var(--accent)', 'Unapplied' => 'var(--warning)', 'Conflict' => 'var(--danger)'];
            foreach ($statusRes as $row): 
                $c = $colors[$row['status']] ?? '#94a3b8';
            ?>
                <div class="conflict-stat-card" style="border-left: 4px solid <?= $c ?>;">
                    <div class="cs-label"><?= h($row['status']) ?></div>
                    <div class="cs-value"><?= number_format($row['count']) ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 5px;">
                        <?= format_number((float)$row['total_area']) ?> sqm
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($statusRes)): ?>
                <div style="grid-column: 1 / -1; color: var(--text-muted); text-align: center; padding: 20px;">
                    No lots recorded for this municipality.
                </div>
            <?php endif; ?>
        </div>

        <h3 class="cv-title" style="margin-top: 25px;">Top Barangays</h3>
        <table class="table-compact" style="margin-bottom: 0;">
            <thead>
                <tr>
                    <th>Barangay</th>
                    <th>Lots</th>
                    <th>Area (sqm)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topBrgys as $b): ?>
                    <tr>
                        <td><strong><?= h($b['name']) ?></strong></td>
                        <td><?= number_format($b['lot_count']) ?></td>
                        <td><?= format_number((float)$b['total_area']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($topBrgys)): ?>
                    <tr><td colspan="3" style="text-align: center;">No barangays found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    $html = ob_get_clean();

    echo json_encode([
        'title' => h($name) . ' Municipality',
        'html' => $html
    ]);
    exit;
}

if ($type === 'barangay') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        echo json_encode(['error' => 'Barangay ID required']);
        exit;
    }

    // Get Barangay Details
    $stmt = $mysqli->prepare("SELECT b.name, m.name as mun_name, b.total_area_sqm FROM barangay b JOIN municipality m ON b.municipality_id = m.id WHERE b.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $brgy = $res->fetch_assoc();
    
    if (!$brgy) {
        echo json_encode(['error' => 'Barangay not found']);
        exit;
    }

    // Status breakdown
    $statusQuery = "
        SELECT status, COUNT(*) AS count, COALESCE(SUM(area_sqm), 0) AS total_area
        FROM lots
        WHERE barangay_id = ?
        GROUP BY status
    ";
    $stmt = $mysqli->prepare($statusQuery);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $statusRes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Recent lots
    $recentQuery = "
        SELECT lot_no, survey_no, status, area_sqm
        FROM lots
        WHERE barangay_id = ?
        ORDER BY id DESC
        LIMIT 5
    ";
    $stmt = $mysqli->prepare($recentQuery);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $recentLots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    ob_start();
    ?>
    <div class="card-view-content">
        <h3 class="cv-title">Lot Status Breakdown</h3>
        <div class="conflict-stats-grid">
            <?php 
            $colors = ['Titled' => 'var(--primary)', 'Applied' => 'var(--accent)', 'Unapplied' => 'var(--warning)', 'Conflict' => 'var(--danger)'];
            foreach ($statusRes as $row): 
                $c = $colors[$row['status']] ?? '#94a3b8';
            ?>
                <div class="conflict-stat-card" style="border-left: 4px solid <?= $c ?>;">
                    <div class="cs-label"><?= h($row['status']) ?></div>
                    <div class="cs-value"><?= number_format($row['count']) ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 5px;">
                        <?= format_number((float)$row['total_area']) ?> sqm
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($statusRes)): ?>
                <div style="grid-column: 1 / -1; color: var(--text-muted); text-align: center; padding: 20px;">
                    No lots recorded for this barangay.
                </div>
            <?php endif; ?>
        </div>

        <h3 class="cv-title" style="margin-top: 25px;">Recent Lots Added</h3>
        <table class="table-compact" style="margin-bottom: 0;">
            <thead>
                <tr>
                    <th>Lot No.</th>
                    <th>Survey No.</th>
                    <th>Status</th>
                    <th>Area (sqm)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLots as $l): 
                    $badge = 'amber';
                    if ($l['status'] === 'Titled') $badge = 'green';
                    if ($l['status'] === 'Applied') $badge = 'blue';
                    if ($l['status'] === 'Conflict') $badge = 'red';
                ?>
                    <tr>
                        <td><strong><?= h($l['lot_no']) ?></strong></td>
                        <td><?= h($l['survey_no'] ?: 'N/A') ?></td>
                        <td><span class="badge <?= $badge ?>"><?= h($l['status']) ?></span></td>
                        <td><?= format_number((float)$l['area_sqm']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recentLots)): ?>
                    <tr><td colspan="4" style="text-align: center;">No recent lots.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    $html = ob_get_clean();

    echo json_encode([
        'title' => h($brgy['name']) . ', ' . h($brgy['mun_name']),
        'html' => $html
    ]);
    exit;
}

echo json_encode(['error' => 'Invalid type']);
