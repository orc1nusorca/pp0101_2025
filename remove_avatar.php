<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Требуется авторизация'], 401);
}

$user_id = $_SESSION['user_id'];

try {
    // Получаем текущий аватар
    $stmt = $pdo->prepare('SELECT avatar FROM user_settings WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $avatar = $stmt->fetchColumn();
    
    // Удаляем файл, если он существует
    if ($avatar && file_exists(__DIR__ . '/' . $avatar)) {
        unlink(__DIR__ . '/' . $avatar);
    }
    
    // Обновляем запись в БД
    $updateStmt = $pdo->prepare('
        UPDATE user_settings 
        SET avatar = NULL 
        WHERE user_id = ?
    ');
    $updateStmt->execute([$user_id]);
    
    jsonResponse(['success' => true]);
    
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}