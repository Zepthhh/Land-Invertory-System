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
    $tempUpload = '';
    $xlsxPath = '';
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
        $municipalityId = (int)($_POST['municipality_id'] ?? 0);
        if ($municipalityId <= 0) {
            $inlineAlert = ['type' => 'error', 'message' => 'Please select a valid Municipality.'];
        }
    }

    if ($inlineAlert === null) {
        $importResult = run_excel_import($xlsxPath, $dbFile, $municipalityId);

        @unlink($xlsxPath);
        $importOutput = $importResult['output'];

        if ($importResult['exit_code'] !== 0) {
            $inlineAlert = ['type' => 'error', 'message' => 'Import failed. Review the output below for details.'];
        } else {
            // Parse counts from output
            $importedBarangays = 0;
            $importedLots = 0;
            foreach (explode(PHP_EOL, $importOutput) as $outLine) {
                if (preg_match('/imported barangays:\s*(\d+)/i', $outLine, $m)) $importedBarangays = (int)$m[1];
                if (preg_match('/imported lots:\s*(\d+)/i', $outLine, $m)) $importedLots = (int)$m[1];
            }
            $inlineAlert = [
                'type' => 'success',
                'message' => "Import successful! Imported {$importedBarangays} barangay(s) and {$importedLots} lot(s). All calculations have been refreshed."
            ];
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

$municipalitiesResult = $mysqli->query("SELECT * FROM municipality ORDER BY name ASC");
$municipalities = $municipalitiesResult ? $municipalitiesResult->fetch_all(MYSQLI_ASSOC) : [];

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
    <p class="section-text">Upload an <strong>.xlsx</strong> file with barangay sheets. The system will rebuild barangay and lot records automatically. Each worksheet = one barangay. Data columns must follow the standard RLTA layout.</p>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px; padding: 16px; background: rgba(0,0,0,0.15); border-radius: 12px; border: 1px solid var(--panel-border);">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.5rem;">1️⃣</span>
            <div><strong style="font-size: 0.85rem;">Select File</strong><br><span style="color: var(--text-muted); font-size: 0.8rem;">.xlsx RLTA workbook</span></div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.5rem;">2️⃣</span>
            <div><strong style="font-size: 0.85rem;">Click Import</strong><br><span style="color: var(--text-muted); font-size: 0.8rem;">System parses all sheets</span></div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.5rem;">3️⃣</span>
            <div><strong style="font-size: 0.85rem;">Review Results</strong><br><span style="color: var(--text-muted); font-size: 0.8rem;">Check import log below</span></div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.5rem;">4️⃣</span>
            <div><strong style="font-size: 0.85rem;">View Reports</strong><br><span style="color: var(--text-muted); font-size: 0.8rem;">All data is now refreshed</span></div>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" id="importForm">
        <div class="form-group" style="margin-bottom: 20px;">
            <label for="municipality_id" style="display:block; margin-bottom: 8px; font-weight: 600;">Select Municipality for this import:</label>
            <select name="municipality_id" id="municipality_id" class="form-control" required style="width: 100%; max-width: 400px; padding: 12px; border-radius: 8px; background: rgba(0,0,0,0.2); border: 1px solid var(--panel-border); color: #fff;">
                <option value="">-- Choose Municipality --</option>
                <?php foreach ($municipalities as $mun): ?>
                    <option value="<?= h((string)$mun['id']) ?>"><?= h($mun['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="drag-drop-zone" id="dropZone">
            <input type="file" id="excel_file" name="excel_file" accept=".xlsx" required>
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            <h3 style="margin: 0; color: #fff;" id="fileName">Drag & Drop your Excel file here</h3>
            <p>or click to browse from your computer (.xlsx only)</p>
            <p id="fileInfo" style="font-size: 0.82rem; color: var(--text-muted); margin-top: 4px; display: none;"></p>
        </div>
        <div class="actions" style="justify-content: center; margin-top: 25px;">
            <button class="btn btn-primary" type="submit" id="submitBtn" style="min-width: 200px; padding: 14px; border-radius: 12px; font-size: 1.1rem;">
                <span id="btnText">Begin Import</span>
                <div class="uploading-overlay" id="spinner">
                    <div class="spinner"></div> Importing Data...
                </div>
            </button>
            <a class="btn btn-secondary" href="<?= h(app_url('/reports/index.php')); ?>" id="cancelBtn" style="padding: 14px 20px; border-radius: 12px;">Cancel</a>
        </div>
    </form>
</section>

<?php if ($importOutput !== ''): ?>
    <section class="panel">
        <h2 class="panel-title">Import Sync Logs</h2>
        <div class="log-output-container" style="display: flex; flex-direction: column; gap: 8px;">
            <?php
            $logLines = explode(PHP_EOL, trim($importOutput));
            foreach ($logLines as $line):
                $line = trim($line);
                if ($line === '') continue;
                
                $logBg = 'rgba(255,255,255,0.03)';
                $logBorder = 'rgba(255,255,255,0.08)';
                $logColor = '#fff';
                $logIcon = 'ℹ️';
                
                if (stripos($line, 'imported') !== false || stripos($line, 'success') !== false) {
                    $logBg = 'rgba(16, 185, 129, 0.1)';
                    $logBorder = 'rgba(16, 185, 129, 0.2)';
                    $logColor = '#6ee7b7';
                    $logIcon = '✅';
                } elseif (stripos($line, 'error') !== false || stripos($line, 'failed') !== false || stripos($line, 'not found') !== false) {
                    $logBg = 'rgba(239, 68, 68, 0.1)';
                    $logBorder = 'rgba(239, 68, 68, 0.2)';
                    $logColor = '#fca5a5';
                    $logIcon = '❌';
                } elseif (stripos($line, 'warning') !== false || stripos($line, 'skip') !== false) {
                    $logBg = 'rgba(245, 158, 11, 0.1)';
                    $logBorder = 'rgba(245, 158, 11, 0.2)';
                    $logColor = '#fcd34d';
                    $logIcon = '⚠️';
                }
                ?>
                <div class="log-line" style="background: <?= $logBg ?>; border: 1px solid <?= $logBorder ?>; color: <?= $logColor ?>; padding: 12px 16px; border-radius: 10px; display: flex; align-items: center; gap: 12px; font-family: monospace; font-size: 0.92rem; line-height: 1.4;">
                    <span><?= $logIcon ?></span>
                    <span><?= h($line); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="panel">
    <h2 class="panel-title">Barangay Calculation Preview</h2>
    <div class="table-wrap">
        <table class="table-compact">
            <thead>
                <tr>
                    <th>Barangay</th>
                    <th>Total Area<br><small>(sqm)</small></th>
                    <th>Lots</th>
                    <th>Infra. Deductions<br><small>Alley/Road/Canal</small></th>
                    <th>Community Deductions<br><small>Church/School/Plaza</small></th>
                    <th>After Deductions<br><small>(sqm)</small></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($barangaySummary): ?>
                    <?php foreach ($barangaySummary as $row): 
                        $total_area = (float) $row['total_area_sqm'];
                        $remaining = (float) $row['area_after_deductions'];
                        $utilized = $total_area - $remaining;
                        $percent_utilized = ($total_area > 0) ? ($utilized / $total_area) * 100 : 0;
                        
                        $progress_class = '';
                        if ($percent_utilized > 90) $progress_class = 'danger';
                        elseif ($percent_utilized > 75) $progress_class = 'warning';
                    ?>
                        <tr>
                            <td><strong><?= h($row['name']); ?></strong></td>
                            <td><?= format_number($total_area); ?></td>
                            <td><?= h((string) $row['total_lots']); ?></td>
                            <td><?= format_number((float) $row['infrastructure_area']); ?></td>
                            <td><?= format_number((float) $row['community_area']); ?></td>
                            <td style="min-width: 200px;">
                                <div style="font-weight: 600; color: <?= $remaining < 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                                    <?= format_number($remaining); ?> sqm
                                </div>
                                <div class="progress-container">
                                    <div class="progress-bar <?= $progress_class ?>" style="width: <?= min(100, max(0, $percent_utilized)) ?>%;"></div>
                                </div>
                                <div class="progress-text">
                                    <span>Used: <?= format_percent($percent_utilized) ?></span>
                                    <span>Free: <?= format_percent(100 - $percent_utilized) ?></span>
                                </div>
                            </td>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('excel_file');
    const fileName = document.getElementById('fileName');
    const importForm = document.getElementById('importForm');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const spinner = document.getElementById('spinner');
    const cancelBtn = document.getElementById('cancelBtn');

    // Drag and Drop Effects
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
    });

    dropZone.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length) {
            fileInput.files = files;
            updateFileName();
        }
    }

    fileInput.addEventListener('change', updateFileName);

    function updateFileName() {
        if (fileInput.files.length > 0) {
            const f = fileInput.files[0];
            fileName.textContent = f.name;
            fileName.style.color = 'var(--primary)';
            const sizeKB = (f.size / 1024).toFixed(1);
            const info = document.getElementById('fileInfo');
            if (info) {
                info.textContent = `File size: ${sizeKB} KB`;
                info.style.display = 'block';
            }
        }
    }

    // Submit Animation
    importForm.addEventListener('submit', function() {
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.8';
        submitBtn.style.cursor = 'wait';
        cancelBtn.style.pointerEvents = 'none';
        cancelBtn.style.opacity = '0.5';
        
        btnText.style.display = 'none';
        spinner.style.display = 'flex';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
