<?php
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    jsonResponse(['error' => 'Access denied']);
}

$action = $_GET['action'] ?? null;

if ($action === 'list') {
    $stmt = $pdo->prepare('SELECT id, description, solution, difficulty, category FROM tasks ORDER BY id DESC');
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse($tasks);
}

$postData = json_decode(file_get_contents('php://input'), true);

if ($action === 'add') {
    if (!$postData || !isset($postData['description'], $postData['solution'], $postData['difficulty'])) {
        jsonResponse(['error' => 'Invalid input']);
    }
    $desc = $postData['description'];
    $sol = $postData['solution'];
    $diff = $postData['difficulty'];
    $cat = $postData['category'] ?? null;

    if (!in_array($diff, ['easy','medium','hard'])) jsonResponse(['error' => 'Invalid difficulty']);

    $stmt = $pdo->prepare('INSERT INTO tasks (description, solution, difficulty, category) VALUES (?, ?, ?, ?)');
    $stmt->execute([$desc, $sol, $diff, $cat]);
    jsonResponse(['success' => 'Task added']);
}

if ($action === 'edit') {
    if (!$postData || !isset($postData['id'], $postData['description'], $postData['solution'], $postData['difficulty'])) {
        jsonResponse(['error' => 'Invalid input']);
    }
    $id = (int)$postData['id'];
    $desc = $postData['description'];
    $sol = $postData['solution'];
    $diff = $postData['difficulty'];
    $cat = $postData['category'] ?? null;

    if (!in_array($diff, ['easy','medium','hard'])) jsonResponse(['error' => 'Invalid difficulty']);

    $stmt = $pdo->prepare('UPDATE tasks SET description = ?, solution = ?, difficulty = ?, category = ? WHERE id = ?');
    $stmt->execute([$desc, $sol, $diff, $cat, $id]);
    jsonResponse(['success' => 'Task updated']);
}

if ($action === 'delete' && isset($postData['id'])) {
    $id = (int)$postData['id'];
    $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
    $stmt->execute([$id]);
    jsonResponse(['success' => 'Task deleted']);
}

jsonResponse(['error' => 'Invalid action']);
