<?php
// db.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// If a single DATABASE_URL is provided (e.g. mysql://user:pass@host:3306/dbname), parse it for compatibility with Railway
if (getenv('DATABASE_URL')) {
    $dbUrl = getenv('DATABASE_URL');
    $parts = parse_url($dbUrl);
    if ($parts && isset($parts['scheme']) && $parts['scheme'] === 'mysql') {
        putenv('DB_HOST='.$parts['host']);
        putenv('DB_USER='.$parts['user']);
        putenv('DB_PASS='.(isset($parts['pass']) ? $parts['pass'] : ''));
        putenv('DB_NAME='.ltrim($parts['path'],'/'));
    }
}

$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'bh_system';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    die('DB connection error: ' . $mysqli->connect_error);
}

// CORS headers for API calls
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

function esc($s){ return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
