<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['Admin', 'Editor']);
    $municipalityId = (int)($_POST['municipality_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $totalArea = (float) ($_POST['total_area_sqm'] ?? 0);

    if ($municipalityId <= 0 || $name === '' || $totalArea <= 0) {
        set_flash('error', 'Please provide a municipality, barangay name, and a valid total area.');
        redirect(app_url('/barangay/index.php'));
    }

    $stmt = $mysqli->prepare('INSERT INTO barangay (municipality_id, name, total_area_sqm) VALUES (?, ?, ?)');
    $stmt->bind_param('isd', $municipalityId, $name, $totalArea);
    $stmt->execute();
    $stmt->close();

    log_action($mysqli, 'Create Barangay', "Created Barangay: $name, Area: $totalArea sqm.");
    set_flash('success', 'Barangay added successfully.');
    redirect(app_url('/barangay/index.php'));
}

$pageTitle = 'Barangay Management';
$pageDescription = 'Create barangays and maintain the base total area used in calculations.';

$pageDescription = 'Manage barangays and monitor their overall land utilization and remaining balance.';

// Fetch detailed barangay stats
$summaryResult = $mysqli->query("
    SELECT
        b.id,
        b.name,
        b.total_area_sqm,
        COALESCE(l.total_lots, 0) AS total_lots,
        COALESCE(l.total_lot_area, 0) AS total_lot_area,
        COALESCE(u.total_land_use, 0) AS total_land_use,
        ((b.total_area_sqm - COALESCE(u.total_land_use, 0)) - COALESCE(l.total_lot_area, 0)) AS remaining_balance,
        m.id AS municipality_id,
        m.name AS municipality_name
    FROM barangay b
    LEFT JOIN municipality m ON b.municipality_id = m.id
    LEFT JOIN (
        SELECT barangay_id, COUNT(*) AS total_lots, SUM(area_sqm) AS total_lot_area
        FROM lots
        GROUP BY barangay_id
    ) l ON l.barangay_id = b.id
    LEFT JOIN (
        SELECT barangay_id, SUM(area_sqm) AS total_land_use
        FROM land_use
        GROUP BY barangay_id
    ) u ON u.barangay_id = b.id
    ORDER BY m.name ASC, b.name ASC
");
$barangays = $summaryResult ? $summaryResult->fetch_all(MYSQLI_ASSOC) : [];

// Fetch municipalities for filter
$munResult = $mysqli->query("SELECT id, name FROM municipality ORDER BY name ASC");
$municipalities = $munResult ? $munResult->fetch_all(MYSQLI_ASSOC) : [];

$barangayFormAction = app_url('/barangay/index.php');
$barangayFormValues = [
    'id' => '',
    'municipality_id' => '',
    'name' => '',
    'total_area_sqm' => '',
];
$barangaySubmitLabel = 'Save Barangay';
$barangayShowBack = false;

$currentUserRole = get_current_user_role();
$initialSearch = $_GET['q'] ?? '';
$initialMunId = $_GET['mun_id'] ?? '';

require_once __DIR__ . '/../includes/header.php';
?>
<!-- Header Actions -->
<div style="margin-bottom: 20px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
    <?php if ($currentUserRole !== 'Viewer'): ?>
        <button type="button" id="toggleFormBtn" class="btn btn-primary" onclick="toggleAddForm()" style="padding: 9px 18px; font-size: 0.9rem; min-height: 38px; gap: 6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add Barangay
        </button>
        <a class="btn btn-secondary" href="<?= h(app_url('/barangay/create.php')); ?>" style="padding: 9px 18px; font-size: 0.9rem; min-height: 38px;">
            📋 Full Create Page
        </a>
    <?php endif; ?>
</div>

<!-- Collapsible Add Form -->
<div id="addBarangayFormPanel" style="display:none; margin-bottom: 20px;">
    <div class="panel" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h2 class="panel-title" style="margin:0;">Add Barangay</h2>
            <button type="button" class="btn btn-secondary" onclick="toggleAddForm()" style="padding: 6px 14px; font-size: 0.82rem; min-height: 32px;">✕ Close</button>
        </div>
        <?php require __DIR__ . '/form.php'; ?>
    </div>
</div>

<section style="width:100%;">
    <div class="panel">
        <div class="actions-spread" style="margin-bottom: 15px;">
            <h2 class="panel-title" style="margin:0;">Barangay Registry</h2>
            <span class="table-counter" id="barangayCounter"><?= count($barangays); ?> barangays</span>
        </div>

        <!-- Live Filter Bar -->
        <div class="table-filter-bar" style="display:flex; gap:10px; margin-bottom: 15px;">
            <input type="text" id="barangayFilterText" placeholder="🔍  Search barangay name..." oninput="filterBarangays()" value="<?= h($initialSearch) ?>" style="flex:1;">
            <select id="municipalityFilter" onchange="filterBarangays()">
                <option value="">All Municipalities</option>
                <?php foreach ($municipalities as $m): ?>
                    <option value="<?= h((string)$m['id']); ?>" <?= $initialMunId == $m['id'] ? 'selected' : '' ?>><?= h($m['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="table-wrap">
            <table class="table-compact" id="barangayTable">
                <thead>
                    <tr>
                        <th>Municipality</th>
                        <th>Barangay Name</th>
                        <th>Total Lots</th>
                        <th>Total Area (sqm)</th>
                        <?php if ($currentUserRole !== 'Viewer'): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="barangayBody">
                    <?php if ($barangays): ?>
                        <?php foreach ($barangays as $idx => $barangay): 
                            $total_area = (float) $barangay['total_area_sqm'];
                            $remaining = (float) $barangay['remaining_balance'];
                            $utilized = $total_area - $remaining;
                            $percent_utilized = ($total_area > 0) ? ($utilized / $total_area) * 100 : 0;
                            
                            $progress_class = '';
                            if ($percent_utilized > 90) $progress_class = 'danger';
                            elseif ($percent_utilized > 75) $progress_class = 'warning';
                        ?>
                            <tr class="barangay-row clickable-row" onclick="if(event.target.closest('button, a')) return; openSummaryModal('barangay', <?= (int)$barangay['id'] ?>)" data-name="<?= h(strtolower((string)($barangay['name'] ?? ''))); ?> <?= h(strtolower((string)($barangay['municipality_name'] ?? ''))); ?>" data-mun-id="<?= h((string)$barangay['municipality_id']) ?>">
                                <td><?= h($barangay['municipality_name']); ?></td>
                                <td><strong><?= h($barangay['name']); ?></strong></td>
                                <td><span class="badge badge-info"><?= h((string)$barangay['total_lots']); ?> lots</span></td>
                                <td><?= format_number($total_area); ?></td>
                                <?php if ($currentUserRole !== 'Viewer'): ?>
                                    <td>
                                        <div class="inline-actions">
                                            <a class="btn btn-secondary" href="<?= h(app_url('/barangay/edit.php?id=' . $barangay['id'])); ?>">Edit</a>
                                            <button class="btn btn-danger" type="button" onclick="showBrgyConfirm(<?= (int)$barangay['id']; ?>, '<?= h(addslashes($barangay['name'])); ?>')">Delete</button>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php if ($currentUserRole !== 'Viewer'): ?>
                                <!-- Inline Confirm Row -->
                                <tr class="confirm-row" id="brgy-confirm-<?= (int)$barangay['id']; ?>">
                                    <td colspan="5">
                                        <div class="confirm-message">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                            Delete barangay <strong id="brgy-confirm-label-<?= (int)$barangay['id']; ?>"></strong>? All related lots and land use entries will also be removed.
                                            <form method="post" action="<?= h(app_url('/barangay/delete.php')); ?>" style="display:inline; margin:0;">
                                                <input type="hidden" name="id" value="<?= h((string) $barangay['id']); ?>">
                                                <button class="btn btn-danger" type="submit" style="padding: 5px 14px; font-size: 0.82rem;">Yes, Delete</button>
                                            </form>
                                            <button class="btn btn-secondary" type="button" onclick="hideBrgyConfirm(<?= (int)$barangay['id']; ?>)" style="padding: 5px 14px; font-size: 0.82rem;">Cancel</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state-fancy">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                    <h3>No Barangays Yet</h3>
                                    <p>Add your first barangay using the form on the left.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <!-- No filter results row -->
                    <tr id="brgyNoResultsRow" style="display:none;">
                        <td colspan="5">
                            <div class="empty-state-fancy" style="padding: 30px;">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                <h3>No Matching Barangay</h3>
                                <p>Try a different search term.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
function toggleAddForm() {
    const panel = document.getElementById('addBarangayFormPanel');
    const btn = document.getElementById('toggleFormBtn');
    const isOpen = panel.style.display !== 'none';
    if (isOpen) {
        panel.style.display = 'none';
        if (btn) btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Add Barangay';
    } else {
        panel.style.display = 'block';
        if (btn) btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line></svg> Hide Form';
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function filterBarangays() {
    const text = document.getElementById('barangayFilterText').value.toLowerCase();
    const munId = document.getElementById('municipalityFilter').value;
    const rows = document.querySelectorAll('#barangayBody .barangay-row');
    const noResults = document.getElementById('brgyNoResultsRow');
    let visible = 0;

    rows.forEach(row => {
        const textMatch = row.dataset.name.includes(text);
        const munMatch = !munId || row.dataset.munId === munId;
        const show = textMatch && munMatch;
        row.style.display = show ? '' : 'none';
        
        // Hide confirm row too
        const nextRow = row.nextElementSibling;
        if (nextRow && nextRow.classList.contains('confirm-row')) {
            nextRow.style.display = 'none';
            nextRow.classList.remove('active');
        }
        if (show) visible++;
    });

    noResults.style.display = (visible === 0 && rows.length > 0) ? '' : 'none';
    document.getElementById('barangayCounter').textContent = visible + ' of <?= count($barangays); ?> barangays';
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('barangayFilterText').value || document.getElementById('municipalityFilter').value) {
        filterBarangays();
    }
});

function showBrgyConfirm(id, name) {
    document.querySelectorAll('.confirm-row').forEach(r => r.classList.remove('active'));
    document.getElementById('brgy-confirm-label-' + id).textContent = '"' + name + '"';
    document.getElementById('brgy-confirm-' + id).classList.add('active');
}

function hideBrgyConfirm(id) {
    document.getElementById('brgy-confirm-' + id).classList.remove('active');
}
</script>

<?php require_once __DIR__ . '/../includes/modal_summary.php'; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
