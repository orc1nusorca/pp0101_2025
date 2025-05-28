<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Not logged in']);
}

$stmt = $pdo->prepare('SELECT id, username, role, coins, level, xp, blocked FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    jsonResponse(['error' => 'User not found']);
}

if ((int)$user['blocked'] === 1) {
    session_destroy();
    jsonResponse(['error' => 'Your account is blocked.']);
}

jsonResponse($user);
