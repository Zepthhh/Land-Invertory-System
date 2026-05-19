<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Search Records';
$pageDescription = 'Search lot records by lot number, survey number, or claimant name.';

$query = trim($_GET['q'] ?? '');
$results = [];
$searchSummary = null;

if ($query !== '') {
    $search = $query . '%';
    $stmt = $mysqli->prepare("
        SELECT l.lot_no, l.survey_no, b.name AS barangay_name, l.area_sqm, l.status, l.current_claimant, l.tax_declarant
        FROM lots l
        INNER JOIN barangay b ON b.id = l.barangay_id
        WHERE l.status LIKE ?
        ORDER BY l.id DESC
    ");
    $stmt->bind_param('s', $search);
    $stmt->execute();
    $result = $stmt->get_result();
    $results = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $summaryStmt = $mysqli->prepare("
        SELECT COUNT(*) AS total_lots, COALESCE(SUM(l.area_sqm), 0) AS total_area_sqm
        FROM lots l
        INNER JOIN barangay b ON b.id = l.barangay_id
        WHERE l.status LIKE ?
    ");
    $summaryStmt->bind_param('s', $search);
    $summaryStmt->execute();
    $summaryResult = $summaryStmt->get_result();
    $searchSummary = $summaryResult->fetch_assoc();
    $summaryStmt->close();
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="panel">
        <h2 class="panel-title">Search by Lot Status</h2>
    <form method="get">
        <div class="search-box" style="display: flex; gap: 10px; max-width: 600px;">
            <select name="q" required style="flex: 1; padding: 12px 20px; font-size: 1rem; border-radius: 12px; background: rgba(20, 27, 45, 0.8); border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 4px 15px rgba(0,0,0,0.1); color: #fff; appearance: auto; cursor: pointer;">
                <option value="" disabled <?= $query === '' ? 'selected' : ''; ?>>Select a status to search...</option>
                <?php foreach (lot_statuses() as $status): ?>
                    <option value="<?= h($status); ?>" <?= $query === $status ? 'selected' : ''; ?>><?= h(get_status_label($status)); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit" style="border-radius: 12px; padding: 12px 25px;">Search</button>
        </div>
    </form>
</section>

<?php if ($query !== '' && $searchSummary): ?>
    <section class="cards">
        <div class="card">
            <div class="card-label">Matching Lots</div>
            <div class="card-value"><?= h((string) $searchSummary['total_lots']); ?></div>
        </div>
        <div class="card">
            <div class="card-label">Matching Area (sqm)</div>
            <div class="card-value"><?= h(format_number((float) $searchSummary['total_area_sqm'])); ?></div>
        </div>
    </section>
<?php endif; ?>

<section class="panel">
    <h2 class="panel-title">Search Results</h2>
    <?php if ($query === ''): ?>
        <div class="empty-state">Enter a lot status (e.g., Titled, Conflict, Applied) to search.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-compact">
                <thead>
                    <tr>
                        <th>Lot No</th>
                        <th>Survey No</th>
                        <th>Barangay</th>
                        <th>Current Claimant</th>
                        <th>Area (sqm)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($results): ?>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?= h($row['lot_no']); ?></td>
                                <td><?= h($row['survey_no']); ?></td>
                                <td><?= h($row['barangay_name']); ?></td>
                                <td><?= h($row['current_claimant'] ?: $row['tax_declarant']); ?></td>
                                <td><?= format_number((float) $row['area_sqm']); ?></td>
                                <td><span class="<?= h(get_status_badge_class($row['status'])); ?>"><?= h(get_status_label($row['status'])); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No matching records found for "<?= h($query); ?>".</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
