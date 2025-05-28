<?php
require 'config.php';
require_once __DIR__ . '/achievements_checker.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse([]);
}

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Требуется авторизация'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['task_id'], $data['solution'])) {
    jsonResponse(['error' => 'Неверные входные данные'], 400);
}

$user_id = $_SESSION['user_id'];
$task_id = (int)$data['task_id'];
$user_solution = trim(strtolower($data['solution']));

try {
    $pdo->beginTransaction();

    // Проверяем, не решена ли задача уже
    $stmt = $pdo->prepare('SELECT id FROM solved_tasks WHERE user_id = ? AND task_id = ?');
    $stmt->execute([$user_id, $task_id]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Задача уже решена'], 409);
    }

    // Получаем задачу
    $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task || !isset($task['difficulty'])) {
        jsonResponse(['error' => 'Неверная структура задачи'], 500);
    }

    // Проверяем решение
    if (trim(strtolower($user_solution)) !== trim(strtolower($task['solution']))) {
        jsonResponse(['error' => 'Неверное решение'], 400);
    }

    // Получаем активные улучшения пользователя
    $xpMultiplier = 1.0;
    $coinBonus = 0;
    $timeMultiplier = 1.0;
    
    $stmt = $pdo->prepare("
        SELECT u.effect_type, u.effect_value 
        FROM user_upgrades uu
        JOIN upgrades u ON uu.upgrade_id = u.id
        WHERE uu.user_id = ? 
        AND (uu.expires_at IS NULL OR uu.expires_at > NOW())
    ");
    $stmt->execute([$user_id]);
    $upgrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($upgrades as $upgrade) {
        switch ($upgrade['effect_type']) {
            case 'xp_multiplier':
                $xpMultiplier *= (float)$upgrade['effect_value'];
                break;
            case 'coin_bonus':
                $coinBonus += (int)$upgrade['effect_value'];
                break;
            case 'time_reduction':
                $timeMultiplier *= (float)$upgrade['effect_value'];
                break;
        }
    }

    // Награды по сложности
    $baseRewards = [
        'easy' => ['coins' => 10, 'xp' => 5],
        'medium' => ['coins' => 20, 'xp' => 10],
        'hard' => ['coins' => 50, 'xp' => 25]
    ];

    $difficulty = $task['difficulty'] ?? 'easy';
    $reward = $baseRewards[$difficulty] ?? $baseRewards['easy'];

    // Применяем улучшения
    $reward['coins'] = floor($reward['coins'] * $timeMultiplier) + $coinBonus;
    $reward['xp'] = floor($reward['xp'] * $xpMultiplier);

    // Регистрием решение
    $solution_time = $data['solution_time'] ?? 0;
    $attempts = $data['attempts'] ?? 1;
    $category = $task['category'] ?? 'general';

    $stmt = $pdo->prepare('
        INSERT INTO solved_tasks 
        (user_id, task_id, solution_time, attempts, category)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $user_id,
        $task_id,
        $solution_time,
        $attempts,
        $category
    ]);

    // Обновляем данные пользователя
    $stmt = $pdo->prepare('
        UPDATE users 
        SET coins = coins + ?, 
            xp = xp + ?,
            level = IF(xp + ? >= level * 100, level + 1, level)
        WHERE id = ?
    ');
    $stmt->execute([
        $reward['coins'],
        $reward['xp'],
        $reward['xp'],
        $user_id
    ]);

    // Получаем обновленные данные пользователя
    $stmt = $pdo->prepare('
        SELECT coins, xp, level 
        FROM users 
        WHERE id = ?
    ');
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        throw new Exception('Данные пользователя не найдены');
    }

    $coins = $userData['coins'];
    $xp = $userData['xp'];
    $level = $userData['level'];

    // Проверяем достижения
    $solvedTasksStmt = $pdo->prepare('SELECT * FROM solved_tasks WHERE user_id = ?');
    $solvedTasksStmt->execute([$user_id]);
    $solvedTasks = $solvedTasksStmt->fetchAll(PDO::FETCH_ASSOC);

    $achievementChecker = new AchievementChecker($pdo, $user_id, $solvedTasks);
    $unlocked = $achievementChecker->checkForAchievements('task_solved', ['task' => $task]);

    // Коммитим транзакцию
    $pdo->commit();

    // Формируем названия разблокированных достижений
    $achievementNames = [];
    foreach ($unlocked as $ach) {
        $achievementNames[] = $ach['name'];
    }

    jsonResponse([
        'success' => true,
        'coins' => $coins,
        'xp' => $xp,
        'level' => $level,
        'achievements_unlocked' => $achievementNames,
        'reward' => [
            'coins' => $reward['coins'],
            'xp' => $reward['xp']
        ]
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();
    error_log("Critical Error: " . $e->getMessage());
    jsonResponse([
        'error' => 'Ошибка сервера',
        'message' => 'Произошла внутренняя ошибка'
    ], 500);
}