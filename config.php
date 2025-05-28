<?php

require_once __DIR__ . '/helpers.php';

$DB_HOST = 'localhost';
$DB_NAME = 'programmer_simulator';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", 
        $DB_USER, 
        $DB_PASS,
        [PDO::ATTR_EMULATE_PREPARES => false]
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $ex) {
    die(json_encode(['error' => 'Database connection failed']));
}

session_start();