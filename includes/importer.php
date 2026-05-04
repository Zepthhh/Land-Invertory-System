<?php
declare(strict_types=1);

function run_excel_import(string $uploadedPath, array $dbConfig): array
{
    $python = 'python';
    $scriptPath = __DIR__ . '/../scripts/import_rlta_from_excel.py';

    if (!is_file($scriptPath)) {
        throw new RuntimeException('Import script not found.');
    }

    $command = sprintf(
        '%s %s %s --mysql-exe %s --host %s --port %d --user %s --password %s --database %s 2>&1',
        escapeshellcmd($python),
        escapeshellarg($scriptPath),
        escapeshellarg($uploadedPath),
        escapeshellarg($dbConfig['mysql_exe']),
        escapeshellarg($dbConfig['host']),
        (int) $dbConfig['port'],
        escapeshellarg($dbConfig['username']),
        escapeshellarg($dbConfig['password']),
        escapeshellarg($dbConfig['database'])
    );

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    return [
        'exit_code' => $exitCode,
        'output' => implode(PHP_EOL, $output),
    ];
}
