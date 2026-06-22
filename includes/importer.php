<?php
declare(strict_types=1);

function run_excel_import(string $uploadedPath, string $sqliteDbPath, int $municipalityId): array
{
    $python = 'python';
    $scriptPath = __DIR__ . '/../scripts/import_rlta_from_excel.py';

    if (!is_file($scriptPath)) {
        throw new RuntimeException('Import script not found.');
    }

    $command = sprintf(
        '%s %s %s --sqlite-db %s --municipality-id %d 2>&1',
        escapeshellcmd($python),
        escapeshellarg($scriptPath),
        escapeshellarg($uploadedPath),
        escapeshellarg($sqliteDbPath),
        $municipalityId
    );

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    return [
        'exit_code' => $exitCode,
        'output' => implode(PHP_EOL, $output),
    ];
}
