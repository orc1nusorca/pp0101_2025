<?php
include 'config.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['username'], $data['password'])) {
    jsonResponse(['error' => 'Invalid input']);
}

$username = trim($data['username']);
$password = $data['password'];

$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'])) {
    jsonResponse(['error' => 'Invalid username or password']);
}

if ((int)$user['blocked'] === 1) {
    jsonResponse(['error' => 'Your account is blocked. Contact admin.']);
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];

jsonResponse([
    'success' => 'Login successful',
    'user' => [
      'id' => $user['id'],
      'username' => $user['username'],
      'role' => $user['role'],
      'coins' => (int)$user['coins'],
      'level' => (int)$user['level'],
      'xp' => (int)$user['xp']
    ]
]);
