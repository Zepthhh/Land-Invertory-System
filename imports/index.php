<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/importer.php';

$pageTitle = 'Import Excel';
$pageDescription = 'Upload an Excel workbook to rebuild barangays and lots, then calculate barangay totals, lot totals, and grouped deductions automatically.';

$importOutput = '';
$inlineAlert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['excel_file']) || !is_array($_FILES['excel_file'])) {
        $inlineAlert = ['type' => 'error', 'message' => 'Please choose an Excel file to import.'];
    }

    if ($inlineAlert === null) {
        $file = $_FILES['excel_file'];
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $inlineAlert = ['type' => 'error', 'message' => 'Upload failed. Please try again with a valid .xlsx file.'];
        }
    }

    if ($inlineAlert === null) {
        $originalName = (string) ($file['name'] ?? '');
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'xlsx') {
            $inlineAlert = ['type' => 'error', 'message' => 'Only .xlsx Excel files are supported.'];
        }
    }

    if ($inlineAlert === null) {
        $tempUpload = tempnam(sys_get_temp_dir(), 'rlta_');
        if ($tempUpload === false) {
            $inlineAlert = ['type' => 'error', 'message' => 'Unable to prepare a temporary import file.'];
        }
    }

    if ($inlineAlert === null) {
        $xlsxPath = $tempUpload . '.xlsx';
        rename($tempUpload, $xlsxPath);

        if (!move_uploaded_file((string) $file['tmp_name'], $xlsxPath)) {
            @unlink($xlsxPath);
            $inlineAlert = ['type' => 'error', 'message' => 'Unable to move the uploaded file for import.'];
        }
    }

    if ($inlineAlert === null) {
        $importResult = run_excel_import($xlsxPath, [
            'mysql_exe' => 'C:\\xampp\\mysql\\bin\\mysql.exe',
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'database' => $dbname,
        ]);

        @unlink($xlsxPath);
        $importOutput = $importResult['output'];

        if ($importResult['exit_code'] !== 0) {
            $inlineAlert = ['type' => 'error', 'message' => 'Import failed. Review the output below.'];
        } else {
            $inlineAlert = ['type' => 'success', 'message' => 'Excel import completed successfully. Barangays, lots, and calculations were refreshed.'];
        }
    }
}

$barangayCount = count_table_rows($mysqli, 'barangay');
$lotCount = count_table_rows($mysqli, 'lots');
$landUseCount = count_table_rows($mysqli, 'land_use');

$barangaySummaryResult = $mysqli->query("
    SELECT
        b.name,
        b.total_area_sqm,
        COALESCE(l.total_lots, 0) AS total_lots,
        COALESCE(u.infrastructure_area, 0) AS infrastructure_area,
        COALESCE(u.community_area, 0) AS community_area,
        (b.total_area_sqm - COALESCE(u.total_land_use, 0)) AS area_after_deductions
    FROM barangay b
    LEFT JOIN (
        SELECT barangay_id, COUNT(*) AS total_lots
        FROM lots
        GROUP BY barangay_id
    ) l ON l.barangay_id = b.id
    LEFT JOIN (
        SELECT
            barangay_id,
            SUM(CASE WHEN type IN ('Alley', 'Road', 'Irrigation', 'Canal') THEN area_sqm ELSE 0 END) AS infrastructure_area,
            SUM(CASE WHEN type IN ('Church', 'School', 'School Site', 'Plaza') THEN area_sqm ELSE 0 END) AS community_area,
            SUM(area_sqm) AS total_land_use
        FROM land_use
        GROUP BY barangay_id
    ) u ON u.barangay_id = b.id
    ORDER BY b.name ASC
");
$barangaySummary = $barangaySummaryResult ? $barangaySummaryResult->fetch_all(MYSQLI_ASSOC) : [];

require_once __DIR__ . '/../includes/header.php';
?>
<?php if ($inlineAlert): ?>
    <div class="alert <?= h($inlineAlert['type']); ?>"><?= h($inlineAlert['message']); ?></div>
<?php endif; ?>
<section class="cards">
    <div class="card">
        <div class="card-label">Barangays</div>
        <div class="card-value"><?= h((string) $barangayCount); ?></div>
    </div>
    <div class="card">
        <div class="card-label">Lots</div>
        <div class="card-value"><?= h((string) $lotCount); ?></div>
    </div>
    <div class="card">
        <div class="card-label">Land Use Entries</div>
        <div class="card-value"><?= h((string) $landUseCount); ?></div>
    </div>
</section>

<section class="panel">
    <h2 class="panel-title">Import RLTA Excel File</h2>
    <p class="section-text">Upload an `.xlsx` file with barangay sheets. The system will rebuild the barangay and lot records, then your reports will automatically show total area per barangay, total lots, grouped deductions for `Alley/Road/Irrigation/Canal`, grouped deductions for `Church/School Site/Plaza`, and the remaining square meters.</p>
    <form method="post" enctype="multipart/form-data">
        <div class="upload-box">
            <label for="excel_file">Excel File (.xlsx)</label>
            <input type="file" id="excel_file" name="excel_file" accept=".xlsx" required>
        </div>
        <div class="actions">
            <button class="btn btn-primary" type="submit">Import Excel</button>
            <a class="btn btn-secondary" href="<?= h(app_url('/reports/index.php')); ?>">Open Reports</a>
        </div>
    </form>
</section>

<?php if ($importOutput !== ''): ?>
    <section class="panel">
        <h2 class="panel-title">Import Output</h2>
        <pre class="output-box"><?= h($importOutput); ?></pre>
    </section>
<?php endif; ?>

<section class="panel">
    <h2 class="panel-title">Barangay Calculation Preview</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Barangay</th>
                    <th>Total Area (sqm)</th>
                    <th>Total Lots</th>
                    <th>Alley/Road/Irrigation/Canal (sqm)</th>
                    <th>Church/School Site/Plaza (sqm)</th>
                    <th>Area After Deductions (sqm)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($barangaySummary): ?>
                    <?php foreach ($barangaySummary as $row): ?>
                        <tr>
                            <td><?= h($row['name']); ?></td>
                            <td><?= format_number((float) $row['total_area_sqm']); ?></td>
                            <td><?= h((string) $row['total_lots']); ?></td>
                            <td><?= format_number((float) $row['infrastructure_area']); ?></td>
                            <td><?= format_number((float) $row['community_area']); ?></td>
                            <td><?= format_number((float) $row['area_after_deductions']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No barangay data available yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
