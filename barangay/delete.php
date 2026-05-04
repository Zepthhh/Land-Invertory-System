<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(app_url('/barangay/index.php'));
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid barangay selected.');
    redirect(app_url('/barangay/index.php'));
}

$stmt = $mysqli->prepare('DELETE FROM barangay WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

set_flash('success', 'Barangay deleted successfully.');
redirect(app_url('/barangay/index.php'));

