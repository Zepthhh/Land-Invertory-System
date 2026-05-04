<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_number(float $value): string
{
    return number_format($value, 2);
}

function format_percent(float $value): string
{
    return number_format($value, 2) . '%';
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function fetch_barangays(mysqli $mysqli): array
{
    $sql = 'SELECT id, name, total_area_sqm FROM barangay ORDER BY name ASC';
    $result = $mysqli->query($sql);

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function lot_statuses(): array
{
    return ['Unapplied', 'Applied', 'Titled', 'Conflict'];
}

function get_status_label(string $status): string
{
    return match ($status) {
        'Conflict' => 'With land claims and conflicts',
        default => $status,
    };
}

function land_use_types(): array
{
    return ['Road', 'Alley', 'Irrigation', 'Canal', 'Church', 'School Site', 'Plaza'];
}

function land_use_infra_types(): array
{
    return ['Alley', 'Road', 'Irrigation', 'Canal'];
}

function land_use_community_types(): array
{
    return ['Church', 'School', 'School Site', 'Plaza'];
}

function app_url(string $path = ''): string
{
    $base = '/Land%20Inventory%20System';
    return $base . $path;
}

function count_table_rows(mysqli $mysqli, string $table): int
{
    $allowed = ['barangay', 'lots', 'land_use'];
    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    $result = $mysqli->query("SELECT COUNT(*) AS total FROM {$table}");
    $row = $result ? $result->fetch_assoc() : ['total' => 0];

    return (int) ($row['total'] ?? 0);
}

function sum_table_area(mysqli $mysqli, string $table, string $column = 'area_sqm'): float
{
    $allowed = ['barangay', 'lots', 'land_use'];
    if (!in_array($table, $allowed, true)) {
        return 0.0;
    }

    $result = $mysqli->query("SELECT COALESCE(SUM({$column}), 0) AS total FROM {$table}");
    $row = $result ? $result->fetch_assoc() : ['total' => 0];

    return (float) ($row['total'] ?? 0);
}

function get_status_badge_class(string $status): string
{
    return match ($status) {
        'Applied' => 'badge blue',
        'Titled' => 'badge green',
        'Conflict' => 'badge red',
        default => 'badge amber',
    };
}
