<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = getenv('CATTRACKER_DB_HOST') ?: 'localhost';
$dbUser = getenv('CATTRACKER_DB_USER') ?: 'root';
$dbPass = getenv('CATTRACKER_DB_PASS') ?: '';
$dbName = getenv('CATTRACKER_DB_NAME') ?: 'cattracker_local';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
$conn->set_charset('utf8mb4');
