<?php
require __DIR__ . '/config.php';

try {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    session_start();
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['username'], $data['password'], $data['role'])) {
        jsonResponse(['error' => 'Неверные входные данные'], 400);
    }

    $username = trim($data['username']);
    $password = $data['password'];
    $role = $data['role'];

    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        jsonResponse(['error' => 'Имя может содержать только буквы, цифры и подчеркивания (3-20 символов)'], 400);
    }

    if (!in_array($role, ['player', 'admin'])) {
        jsonResponse(['error' => 'Недопустимая роль'], 400);
    }

    if (strlen($password) < 8) {
        jsonResponse(['error' => 'Пароль должен быть минимум 8 символов'], 400);
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Имя пользователя уже занято'], 409);
    }

    $passHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
    $stmt->execute([$username, $passHash, $role]);
    
    jsonResponse(['success' => 'Пользователь успешно зарегистрирован']);

} catch (Throwable $e) {
    error_log("Registration Error: " . $e->getMessage());
    jsonResponse(['error' => 'Внутренняя ошибка сервера'], 500);
}