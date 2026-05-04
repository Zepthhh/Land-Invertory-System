<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barangayId = (int) ($_POST['barangay_id'] ?? 0);
    $type = $_POST['type'] ?? '';
    $area = (float) ($_POST['area_sqm'] ?? 0);
    $allowedTypes = land_use_types();

    if ($barangayId <= 0 || $area <= 0 || !in_array($type, $allowedTypes, true)) {
        set_flash('error', 'Please complete all land use fields with valid values.');
        redirect(app_url('/land_use/index.php'));
    }

    $stmt = $mysqli->prepare('INSERT INTO land_use (barangay_id, type, area_sqm) VALUES (?, ?, ?)');
    $stmt->bind_param('isd', $barangayId, $type, $area);
    $stmt->execute();
    $stmt->close();

    set_flash('success', 'Land use entry added successfully.');
    redirect(app_url('/land_use/index.php'));
}

$pageTitle = 'Land Use Management';
$pageDescription = 'Record area deductions such as roads, church, school site, plaza, and similar uses.';

$barangays = fetch_barangays($mysqli);
$landUseTypes = land_use_types();
$landUseFormAction = app_url('/land_use/index.php');
$landUseFormValues = [
    'id' => '',
    'barangay_id' => '',
    'type' => 'Road',
    'area_sqm' => '',
];
$landUseSubmitLabel = 'Save Land Use';
$landUseShowBack = false;
$landUseResult = $mysqli->query("
    SELECT u.id, b.name AS barangay_name, u.type, u.area_sqm
    FROM land_use u
    INNER JOIN barangay b ON b.id = u.barangay_id
    ORDER BY u.id DESC
");
$landUses = $landUseResult ? $landUseResult->fetch_all(MYSQLI_ASSOC) : [];

require_once __DIR__ . '/../includes/header.php';
?>
<section class="grid-2">
    <div class="panel">
        <h2 class="panel-title">Add Land Use</h2>
        <?php if (!$barangays): ?>
            <div class="empty-state">Add a barangay first before recording land use deductions.</div>
        <?php else: ?>
            <div class="actions actions-spread">
                <p class="section-text">Quick entry is still available here, and the dedicated create page is ready if you prefer a separate form.</p>
                <a class="btn btn-secondary" href="<?= h(app_url('/land_use/create.php')); ?>">Open Create Page</a>
            </div>
            <?php require __DIR__ . '/form.php'; ?>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2 class="panel-title">Land Use List</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Barangay</th>
                        <th>Type</th>
                        <th>Area (sqm)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($landUses): ?>
                        <?php foreach ($landUses as $entry): ?>
                            <tr>
                                <td><?= h((string) $entry['id']); ?></td>
                                <td><?= h($entry['barangay_name']); ?></td>
                                <td><?= h($entry['type']); ?></td>
                                <td><?= format_number((float) $entry['area_sqm']); ?></td>
                                <td>
                                    <div class="inline-actions">
                                        <a class="btn btn-secondary" href="<?= h(app_url('/land_use/edit.php?id=' . $entry['id'])); ?>">Edit</a>
                                        <form method="post" action="<?= h(app_url('/land_use/delete.php')); ?>" onsubmit="return confirm('Delete this land use entry?');">
                                            <input type="hidden" name="id" value="<?= h((string) $entry['id']); ?>">
                                            <button class="btn btn-danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No land use entries found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
