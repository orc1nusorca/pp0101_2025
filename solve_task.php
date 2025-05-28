<?php
ob_start();

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

    $stmt = $pdo->prepare('SELECT id FROM solved_tasks WHERE user_id = ? AND task_id = ?');
    $stmt->execute([$user_id, $task_id]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Задача уже решена'], 409);
    }

    $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task || !isset($task['difficulty'])) {
        jsonResponse(['error' => 'Неверная структура задачи'], 500);
    }

    if (trim(strtolower($user_solution)) !== trim(strtolower($task['solution']))) {
        jsonResponse(['error' => 'Неверное решение'], 400);
    }

    $stmt = $pdo->prepare('
        INSERT INTO solved_tasks 
        (user_id, task_id, solution_time, attempts, category)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $user_id,
        $task_id,
        $data['solution_time'] ?? 0,
        $data['attempts'] ?? 1,
        $task['category'] ?? 'general'
    ]);

    $rewards = [
        'easy' => ['coins' => 10, 'xp' => 5],
        'medium' => ['coins' => 20, 'xp' => 10],
        'hard' => ['coins' => 50, 'xp' => 25]
    ];

    $difficulty = $task['difficulty'] ?? 'easy';

    if (!array_key_exists($difficulty, $rewards)) {
        $difficulty = 'easy';
    }

    $reward = $rewards[$difficulty];

    $updateStmt = $pdo->prepare('
        UPDATE users 
        SET coins = coins + ?, 
            xp = xp + ?,
            level = IF(xp + ? >= level * 100, level + 1, level)
        WHERE id = ?
    ');
    $updateStmt->execute([
        $reward['coins'],
        $reward['xp'],
        $reward['xp'],
        $user_id
    ]);

    $userStmt = $pdo->prepare('
        SELECT coins, xp, level 
        FROM users 
        WHERE id = ?
    ');
    $userStmt->execute([$user_id]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        throw new Exception('Данные пользователя не найдены');
    }

    $coins = $userData['coins'];
    $xp = $userData['xp'];
    $level = $userData['level'];

    $solvedTasksStmt = $pdo->prepare('SELECT * FROM solved_tasks WHERE user_id = ?');
    $solvedTasksStmt->execute([$user_id]);
    $solvedTasks = $solvedTasksStmt->fetchAll(PDO::FETCH_ASSOC);

    $achievementChecker = new AchievementChecker($pdo, $user_id, $solvedTasks);
    $unlocked = $achievementChecker->checkForAchievements('task_solved', ['task' => $task]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'coins' => $coins,
        'xp' => $xp,
        'level' => $level,
        'achievements_unlocked' => array_column($unlocked, 'name')
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();
    error_log("Critical Error: " . $e->getMessage());
    jsonResponse([
        'error' => 'Ошибка сервера',
        'message' => 'Произошла внутренняя ошибка'
    ], 500);
}

ob_end_flush();