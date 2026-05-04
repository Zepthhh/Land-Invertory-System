<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(app_url('/land_use/index.php'));
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid land use entry selected.');
    redirect(app_url('/land_use/index.php'));
}

$stmt = $mysqli->prepare('DELETE FROM land_use WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

set_flash('success', 'Land use entry deleted successfully.');
redirect(app_url('/land_use/index.php'));

