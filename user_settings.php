<?php
require 'config.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse([]);
}

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Требуется авторизация'], 401);
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Получение настроек
    try {
        $stmt = $pdo->prepare('SELECT * FROM user_settings WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings) {
            // Создаем настройки по умолчанию
            $insertStmt = $pdo->prepare('
                INSERT INTO user_settings (user_id)
                VALUES (?)
            ');
            $insertStmt->execute([$user_id]);
            
            $stmt->execute([$user_id]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        jsonResponse($settings);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Сохранение настроек
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        jsonResponse(['error' => 'Invalid input'], 400);
    }
    
try {
    $stmt = $pdo->prepare('
        INSERT INTO user_settings 
        (user_id, theme, avatar, show_level_progress, show_achievements_badge)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            theme = VALUES(theme),
            avatar = VALUES(avatar),
            show_level_progress = VALUES(show_level_progress),
            show_achievements_badge = VALUES(show_achievements_badge)
    ');
    
    $stmt->execute([
        $user_id,
        $data['theme'] ?? 'default',
        $data['avatar'] ?? null, // Сохраняем только имя файла
        $data['show_level_progress'] ?? 1,
        $data['show_achievements_badge'] ?? 1
    ]);
    
    jsonResponse(['success' => true]);
    
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}
}