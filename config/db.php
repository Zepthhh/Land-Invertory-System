<?php
declare(strict_types=1);

if (!defined('MYSQLI_ASSOC')) {
    define('MYSQLI_ASSOC', 1);
}

class SQLiteMySQLiResult
{
    private PDOStatement $stmt;

    public function __construct(PDOStatement $stmt)
    {
        $this->stmt = $stmt;
    }

    public function fetch_assoc(): ?array
    {
        $row = $this->stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function fetch_all(int $mode = MYSQLI_ASSOC): array
    {
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

class SQLiteMySQLiStmt
{
    private PDOStatement $stmt;
    private array $params = [];
    public string $error = '';

    public function __construct(PDOStatement $stmt)
    {
        $this->stmt = $stmt;
    }

    public function bind_param(string $types, mixed &...$vars): bool
    {
        // Store the values by value in params for execution
        $this->params = $vars;
        return true;
    }

    public function execute(): bool
    {
        try {
            $success = $this->stmt->execute($this->params);
            if (!$success) {
                $err = $this->stmt->errorInfo();
                $this->error = $err[2] ?? 'Statement execution failed';
                return false;
            }
            return true;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function get_result()
    {
        return new SQLiteMySQLiResult($this->stmt);
    }

    public function close(): bool
    {
        return true;
    }
}

class SQLiteMySQLiEquivalent
{
    private ?PDO $pdo = null;
    public string $error = '';
    public int $connect_errno = 0;
    public string $connect_error = '';

    public function __construct(string $dbFile)
    {
        try {
            $this->pdo = new PDO('sqlite:' . $dbFile);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->connect_errno = $e->getCode() ?: 1;
            $this->connect_error = $e->getMessage();
        }
    }

    public function query(string $sql)
    {
        try {
            $stmt = $this->pdo->query($sql);
            if ($stmt === false) {
                $err = $this->pdo->errorInfo();
                $this->error = $err[2] ?? 'Query failed';
                return false;
            }
            return new SQLiteMySQLiResult($stmt);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function prepare(string $sql)
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            if ($stmt === false) {
                $err = $this->pdo->errorInfo();
                $this->error = $err[2] ?? 'Prepare failed';
                return false;
            }
            return new SQLiteMySQLiStmt($stmt);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function set_charset(string $charset): bool
    {
        return true;
    }
}

// Config variables for compatibility
$host = '127.0.0.1';
$port = 3306;
$dbname = 'Land Inventory';
$username = 'root';
$password = '';

$dbFile = __DIR__ . '/../data/database.sqlite';
$needsInit = !file_exists($dbFile);

$mysqli = new SQLiteMySQLiEquivalent($dbFile);

if ($needsInit && !$mysqli->connect_errno) {
    $schema = [
        "CREATE TABLE IF NOT EXISTS barangay (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            total_area_sqm REAL NOT NULL DEFAULT 0
        );",
        "CREATE TABLE IF NOT EXISTS lots (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lot_no TEXT NOT NULL,
            survey_no TEXT NULL,
            barangay_id INTEGER NOT NULL,
            area_sqm REAL NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'Unapplied',
            survey_claimant TEXT NULL,
            tax_declarant TEXT NULL,
            current_claimant TEXT NULL,
            claimant_sex TEXT NULL,
            current_address TEXT NULL,
            representative TEXT NULL,
            representative_address TEXT NULL,
            supporting_docs TEXT NULL,
            subdivision TEXT NULL,
            approved_survey_plan TEXT NULL,
            land_case TEXT NULL,
            titling_interest TEXT NULL,
            mode_of_acquisition TEXT NULL,
            dominant_use TEXT NULL,
            remarks TEXT NULL,
            source_sheet TEXT NULL,
            case_reference TEXT NULL,
            sheet_row INTEGER NULL,
            FOREIGN KEY (barangay_id) REFERENCES barangay (id) ON DELETE CASCADE ON UPDATE CASCADE
        );",
        "CREATE TABLE IF NOT EXISTS land_use (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            barangay_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            area_sqm REAL NOT NULL DEFAULT 0,
            FOREIGN KEY (barangay_id) REFERENCES barangay (id) ON DELETE CASCADE ON UPDATE CASCADE
        );"
    ];

    foreach ($schema as $sql) {
        $mysqli->query($sql);
    }
}
