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
                <p class="section-text" style="margin:0; line-height: 1.5; color: var(--text-muted);">Use the quick form below or open the dedicated create page if you want a focused entry screen.</p>
                <a class="btn btn-secondary" style="justify-content: center;" href="<?= h(app_url('/barangay/create.php')); ?>">Open Create Page</a>
            </div>
            <?php require __DIR__ . '/form.php'; ?>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2 class="panel-title">Barangay List</h2>
        <div class="table-wrap">
            <table class="table-compact">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Total Area (sqm)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($barangays): ?>
                        <?php foreach ($barangays as $barangay): ?>
                            <tr>
                                <td><?= h((string) $barangay['id']); ?></td>
                                <td><?= h($barangay['name']); ?></td>
                                <td><?= format_number((float) $barangay['total_area_sqm']); ?></td>
                                <td>
                                    <div class="inline-actions">
                                        <?php if ($currentUserRole !== 'Viewer'): ?>
                                            <a class="btn btn-secondary" href="<?= h(app_url('/barangay/edit.php?id=' . $barangay['id'])); ?>">Edit</a>
                                            <form method="post" action="<?= h(app_url('/barangay/delete.php')); ?>" onsubmit="return confirm('Delete this barangay? Related lots and land use entries will also be removed.');">
                                                <input type="hidden" name="id" value="<?= h((string) $barangay['id']); ?>">
                                                <button class="btn btn-danger" type="submit">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.85rem;">None</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">No barangays available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
