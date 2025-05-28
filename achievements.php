<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? 'list';
$response = [];

try {
    switch($action) {
        case 'user':
            $stmt = $pdo->prepare("SELECT a.* FROM user_achievements ua 
                JOIN achievements a ON ua.achievement_id = a.id
                WHERE ua.user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        
        default:
            $stmt = $pdo->query("SELECT * FROM achievements");
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    jsonResponse($response);
    
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error'], 500);
}