<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(app_url('/lots/index.php'));
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid lot selected.');
    redirect(app_url('/lots/index.php'));
}

$stmt = $mysqli->prepare('DELETE FROM lots WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

set_flash('success', 'Lot deleted successfully.');
redirect(app_url('/lots/index.php'));

