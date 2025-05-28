<?php
require 'config.php';

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$difficulty = $_GET['difficulty'] ?? null;

$query = "SELECT t.id, t.description, t.difficulty, t.category 
          FROM tasks t
          LEFT JOIN solved_tasks st ON t.id = st.task_id AND st.user_id = ?
          WHERE st.task_id IS NULL";

$params = [$user_id];

if ($difficulty && in_array($difficulty, ['easy','medium','hard'])) {
    $query .= " AND t.difficulty = ?";
    $params[] = $difficulty;
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse($tasks);
    
} catch (Throwable $e) {
    error_log("Tasks Error: " . $e->getMessage());
    jsonResponse(['error' => 'Failed to fetch tasks'], 500);
}
