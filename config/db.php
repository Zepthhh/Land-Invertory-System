<?php
declare(strict_types=1);

$host = '127.0.0.1';
$port = 3306;
$dbname = 'Land Inventory';
$username = 'root';
$password = '';

$mysqli = new mysqli($host, $username, $password, $dbname, $port);

if ($mysqli->connect_errno) {
    die('Database connection failed: ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');
