<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Editor']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $municipalityId = (int)($_POST['municipality_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $totalArea = (float) ($_POST['total_area_sqm'] ?? 0);

    if ($municipalityId <= 0 || $name === '' || $totalArea <= 0) {
        set_flash('error', 'Please provide a municipality, barangay name, and a valid total area.');
        redirect(app_url('/barangay/create.php'));
    }

    $stmt = $mysqli->prepare('INSERT INTO barangay (municipality_id, name, total_area_sqm) VALUES (?, ?, ?)');
    $stmt->bind_param('isd', $municipalityId, $name, $totalArea);
    $stmt->execute();
    $stmt->close();

    log_action($mysqli, 'Create Barangay', "Created Barangay: $name, Area: $totalArea sqm.");
    set_flash('success', 'Barangay added successfully.');
    redirect(app_url('/barangay/index.php'));
}

$pageTitle = 'Add Barangay';
$pageDescription = 'Create a barangay record using the database structure already provided.';
$barangayFormAction = app_url('/barangay/create.php');
$barangayFormValues = [
    'id' => '',
    'municipality_id' => '',
    'name' => '',
    'total_area_sqm' => '',
];
$barangaySubmitLabel = 'Save Barangay';
$barangayShowBack = true;

require_once __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <h2 class="panel-title">New Barangay</h2>
    <?php require __DIR__ . '/form.php'; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
