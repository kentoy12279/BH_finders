<?php
// db.php
session_start();
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = ''; // change if you set a password
$DB_NAME = 'bh_system';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    die('DB connection error: ' . $mysqli->connect_error);
}
function esc($s){ return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }