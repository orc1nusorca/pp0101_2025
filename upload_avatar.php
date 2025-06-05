<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Требуется авторизация'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Invalid method'], 405);
}

if (!isset($_FILES['avatar'])) {
    jsonResponse(['error' => 'No file uploaded'], 400);
}

$user_id = $_SESSION['user_id'];
$file = $_FILES['avatar'];

// Проверка типа файла
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($file['type'], $allowed_types)) {
    jsonResponse(['error' => 'Invalid file type'], 400);
}

if ($file['size'] > 2097152) {
    jsonResponse(['error' => 'File too large (max 2MB)'], 400);
}

// Создаем папку uploads, если ее нет
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Генерация уникального имени
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
$upload_path = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
    jsonResponse(['error' => 'Failed to save file'], 500);
}

// Сохраняем путь в БД
try {
    $stmt = $pdo->prepare('
        UPDATE user_settings 
        SET avatar = ?
        WHERE user_id = ?
    ');
    $stmt->execute([$filename, $user_id]);
    
    // Возвращаем полный URL для доступа к файлу
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $avatarUrl = $baseUrl . '/uploads/' . $filename;
    
    jsonResponse([
        'success' => true,
        'avatar_url' => $avatarUrl
    ]);
    
} catch (PDOException $e) {
    unlink($upload_path);
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}