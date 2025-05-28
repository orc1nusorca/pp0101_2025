<?php
include '../config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$method = $_SERVER['REQUEST_METHOD'];

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    jsonResponse(['error' => 'Access denied'], 403);
}

$action = $_GET['action'] ?? $input['action'] ?? null;
$id = $input['id'] ?? null;

try {
    switch ($action) {
        case 'list':
            if ($method !== 'GET') throw new Exception('Invalid method');
            $stmt = $pdo->query('SELECT id, username, role, blocked, coins, level, xp FROM users ORDER BY id DESC');
            jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'block':
            if ($method !== 'POST') throw new Exception('Invalid method');
            validateUserAction($id);
            updateBlockStatus(1, $id);
            jsonResponse(['success' => 'User blocked']);
            break;

        case 'unblock':
            if ($method !== 'POST') throw new Exception('Invalid method');
            validateUserAction($id);
            updateBlockStatus(0, $id);
            jsonResponse(['success' => 'User unblocked']);
            break;

        case 'delete':
            if ($method !== 'DELETE') throw new Exception('Invalid method');
            validateUserAction($id);
            deleteUser($id);
            jsonResponse(['success' => 'User deleted']);
            break;

        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 400);
}

function validateUserAction($id) {
    if (!$id || !is_numeric($id)) {
        throw new Exception('Invalid user ID');
    }
    if ($id == $_SESSION['user_id']) {
        throw new Exception('Cannot perform action on yourself');
    }
}

function updateBlockStatus($status, $id) {
    global $pdo;
    $stmt = $pdo->prepare('UPDATE users SET blocked = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
    if ($stmt->rowCount() === 0) {
        throw new Exception('User not found');
    }
}

function deleteUser($id) {
    global $pdo;
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        throw new Exception('User not found');
    }
}
