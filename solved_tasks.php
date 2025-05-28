<?php
require __DIR__ . '/config.php';

try {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare('
        SELECT t.id, t.description, st.solved_at 
        FROM solved_tasks st
        JOIN tasks t ON t.id = st.task_id
        WHERE st.user_id = ?
    ');
    $stmt->execute([$user_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(['solved_tasks' => $tasks]);
    
} catch (Throwable $e) {
    jsonResponse(['error' => 'Ошибка получения данных'], 500);
}