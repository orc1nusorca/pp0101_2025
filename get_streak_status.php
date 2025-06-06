<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Not authorized']));
}

$userId = $_SESSION['user_id'];
$today = date('Y-m-d');

try {
    // Проверяем существование таблицы
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_streaks (
            user_id INT PRIMARY KEY,
            streak INT DEFAULT 0,
            last_claim DATE NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    
    // Получаем данные о серии
    $stmt = $pdo->prepare("SELECT streak, last_claim FROM user_streaks WHERE user_id = ?");
    $stmt->execute([$userId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $claimedToday = false;
    if ($data && $data['last_claim'] === $today) {
        $claimedToday = true;
    }
    
    echo json_encode([
        'streak' => $data['streak'] ?? 0,
        'lastClaim' => $data['last_claim'] ?? null,
        'claimedToday' => $claimedToday
    ]);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
}