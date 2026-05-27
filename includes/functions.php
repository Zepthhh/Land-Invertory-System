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

function fetch_barangays(object $mysqli): array
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
    // Auto-detect base: if running via PHP built-in server (php -S),
    // DOCUMENT_ROOT points directly to our folder, so base is ''.
    // Under XAMPP/Apache it lives at /Land-Invertory-System/.
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (str_starts_with($scriptName, '/Land-Invertory-System')) {
        $base = '/Land-Invertory-System';
    } else {
        $base = '';
    }
    return $base . $path;
}

function count_table_rows(object $mysqli, string $table): int
{
    $allowed = ['barangay', 'lots', 'land_use'];
    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    $result = $mysqli->query("SELECT COUNT(*) AS total FROM {$table}");
    $row = $result ? $result->fetch_assoc() : ['total' => 0];

    return (int) ($row['total'] ?? 0);
}

function sum_table_area(object $mysqli, string $table, string $column = 'area_sqm'): float
{
    $allowed = ['barangay', 'lots', 'land_use'];
    if (!in_array($table, $allowed, true)) {
        return 0.0;
    }

    $result = $mysqli->query("SELECT COALESCE(SUM({$column}), 0) AS total FROM {$table}");
    $row = $result ? $result->fetch_assoc() : ['total' => 0];

    return (float) ($row['total'] ?? 0);
}

function get_status_icon(string $status): string
{
    return match ($status) {
        'Conflict' => '⚠️',
        'Titled' => '✅',
        'Applied' => '📄',
        default => '🔍',
    };
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

function log_action(object $mysqli, string $action, ?string $details = null): void
{
    // Logging deactivated for local mode
}

function is_logged_in(): bool
{
    return true;
}

function require_login(): void
{
    // Bypass for local-only access
}

function get_current_user_role(): ?string
{
    return 'Admin'; // Always Admin for local-only access
}

function get_current_username(): ?string
{
    return 'Local Admin';
}

function require_role(array|string $roles): void
{
    // Bypass for local-only access
}

