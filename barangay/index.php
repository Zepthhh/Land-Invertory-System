<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['Admin', 'Editor']);
    $name = trim($_POST['name'] ?? '');
    $totalArea = (float) ($_POST['total_area_sqm'] ?? 0);

    if ($name === '' || $totalArea <= 0) {
        set_flash('error', 'Please provide a barangay name and a valid total area.');
        redirect(app_url('/barangay/index.php'));
    }

    $stmt = $mysqli->prepare('INSERT INTO barangay (name, total_area_sqm) VALUES (?, ?)');
    $stmt->bind_param('sd', $name, $totalArea);
    $stmt->execute();
    $stmt->close();

    log_action($mysqli, 'Create Barangay', "Created Barangay: $name, Area: $totalArea sqm.");
    set_flash('success', 'Barangay added successfully.');
    redirect(app_url('/barangay/index.php'));
}

$pageTitle = 'Barangay Management';
$pageDescription = 'Create barangays and maintain the base total area used in calculations.';

$barangays = fetch_barangays($mysqli);
$barangayFormAction = app_url('/barangay/index.php');
$barangayFormValues = [
    'id' => '',
    'name' => '',
    'total_area_sqm' => '',
];
$barangaySubmitLabel = 'Save Barangay';
$barangayShowBack = false;

$currentUserRole = get_current_user_role();

require_once __DIR__ . '/../includes/header.php';
?>
<section class="grid-form-table">
    <div class="panel">
        <h2 class="panel-title">Add Barangay</h2>
        <?php if ($currentUserRole === 'Viewer'): ?>
            <div class="empty-state-fancy" style="padding: 20px 10px;">
                <p style="color: var(--text-muted);">You are logged in as a <strong>Viewer</strong>. You do not have permission to add or modify barangay records.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px;">
                <p class="section-text" style="margin:0;">Use the quick form below or open the dedicated create page if you want a focused entry screen.</p>
                <a class="btn btn-secondary" style="justify-content: center;" href="<?= h(app_url('/barangay/create.php')); ?>">Open Create Page</a>
            </div>
            <?php require __DIR__ . '/form.php'; ?>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="actions-spread" style="margin-bottom: 15px;">
            <h2 class="panel-title" style="margin:0;">Barangay List</h2>
            <span class="table-counter" id="barangayCounter"><?= count($barangays); ?> barangays</span>
        </div>

        <!-- Live Filter Bar -->
        <div class="filter-toolbar">
            <input type="text" id="barangayFilterText" placeholder="🔍  Search barangay name..." oninput="filterBarangays()">
        </div>

        <div class="table-wrap">
            <table class="table-compact" id="barangayTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Total Area (sqm)</th>
                        <?php if ($currentUserRole !== 'Viewer'): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="barangayBody">
                    <?php if ($barangays): ?>
                        <?php foreach ($barangays as $idx => $barangay): ?>
                            <tr class="barangay-row" data-name="<?= h(strtolower($barangay['name'])); ?>">
                                <td style="color: var(--text-muted); font-size: 0.8rem;"><?= $idx + 1; ?></td>
                                <td><strong><?= h($barangay['name']); ?></strong></td>
                                <td><?= format_number((float) $barangay['total_area_sqm']); ?></td>
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
                                    <td colspan="4">
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
                            <td colspan="4">
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
                        <td colspan="4">
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
function filterBarangays() {
    const text = document.getElementById('barangayFilterText').value.toLowerCase();
    const rows = document.querySelectorAll('#barangayBody .barangay-row');
    const noResults = document.getElementById('brgyNoResultsRow');
    let visible = 0;

    rows.forEach(row => {
        const match = row.dataset.name.includes(text);
        row.style.display = match ? '' : 'none';
        // Hide confirm row too
        const nextRow = row.nextElementSibling;
        if (nextRow && nextRow.classList.contains('confirm-row')) {
            nextRow.style.display = 'none';
            nextRow.classList.remove('active');
        }
        if (match) visible++;
    });

    noResults.style.display = (visible === 0 && rows.length > 0) ? '' : 'none';
    document.getElementById('barangayCounter').textContent = visible + ' of <?= count($barangays); ?> barangays';
}

function showBrgyConfirm(id, name) {
    document.querySelectorAll('.confirm-row').forEach(r => r.classList.remove('active'));
    document.getElementById('brgy-confirm-label-' + id).textContent = '"' + name + '"';
    document.getElementById('brgy-confirm-' + id).classList.add('active');
}

function hideBrgyConfirm(id) {
    document.getElementById('brgy-confirm-' + id).classList.remove('active');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
