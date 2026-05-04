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
    $search = '%' . $query . '%';
    $stmt = $mysqli->prepare("
        SELECT l.lot_no, l.survey_no, b.name AS barangay_name, l.area_sqm, l.status, l.current_claimant, l.tax_declarant
        FROM lots l
        INNER JOIN barangay b ON b.id = l.barangay_id
        WHERE l.lot_no LIKE ? OR l.survey_no LIKE ? OR l.current_claimant LIKE ? OR l.tax_declarant LIKE ?
        ORDER BY l.id DESC
    ");
    $stmt->bind_param('ssss', $search, $search, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    $results = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $summaryStmt = $mysqli->prepare("
        SELECT COUNT(*) AS total_lots, COALESCE(SUM(l.area_sqm), 0) AS total_area_sqm
        FROM lots l
        WHERE l.lot_no LIKE ? OR l.survey_no LIKE ? OR l.current_claimant LIKE ? OR l.tax_declarant LIKE ?
    ");
    $summaryStmt->bind_param('ssss', $search, $search, $search, $search);
    $summaryStmt->execute();
    $summaryResult = $summaryStmt->get_result();
    $searchSummary = $summaryResult->fetch_assoc();
    $summaryStmt->close();
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="panel">
        <h2 class="panel-title">Search by Lot No, Survey No, or Claimant</h2>
    <form method="get">
        <div class="search-box">
            <input type="text" name="q" value="<?= h($query); ?>" placeholder="Enter lot number, survey number, or claimant name" required>
            <button class="btn btn-primary" type="submit">Search</button>
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
        <div class="empty-state">Enter a lot number, survey number, or claimant name to search.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
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
