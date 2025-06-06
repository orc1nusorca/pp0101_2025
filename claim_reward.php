<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not authorized']));
}

$userId = $_SESSION['user_id'];
$today = date('Y-m-d');

try {
    // Создаем таблицу, если ее нет
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_streaks (
            user_id INT PRIMARY KEY,
            streak INT DEFAULT 0,
            last_claim DATE NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    // Проверяем, получал ли пользователь награду сегодня
    $stmt = $pdo->prepare("SELECT streak, last_claim FROM user_streaks WHERE user_id = ?");
    $stmt->execute([$userId]);
    $streakInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($streakInfo && $streakInfo['last_claim'] === $today) {
        die(json_encode(['success' => false, 'message' => 'Already claimed today']));
    }

    // Определяем новую серию
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $newStreak = 1;

    if ($streakInfo && $streakInfo['last_claim'] === $yesterday) {
        $newStreak = $streakInfo['streak'] + 1;
    }

    // Начисляем награду
    $reward = 20; // Базовая награда
    if ($newStreak % 7 == 0) $reward += 50; // Бонус за 7 дней

    $pdo->beginTransaction();
    
    if ($streakInfo) {
        $stmt = $pdo->prepare("
            UPDATE user_streaks 
            SET streak = ?, last_claim = ? 
            WHERE user_id = ?
        ");
        $stmt->execute([$newStreak, $today, $userId]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO user_streaks (user_id, streak, last_claim) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $newStreak, $today]);
    }
    
    // Начисляем монеты
    $stmt = $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
    $stmt->execute([$reward, $userId]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'streak' => $newStreak,
        'reward' => $reward
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'details' => $e->getMessage()
    ]);
}