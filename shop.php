<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? null;
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->query("SELECT * FROM upgrades");
            $upgrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse($upgrades);
            break;
            
        case 'purchase':
            $data = json_decode(file_get_contents('php://input'), true);
            $upgrade_id = $data['id'] ?? null;
            
            if (!$upgrade_id) {
                throw new Exception('Invalid upgrade ID');
            }
            
            // Получаем информацию об улучшении
            $stmt = $pdo->prepare("SELECT * FROM upgrades WHERE id = ?");
            $stmt->execute([$upgrade_id]);
            $upgrade = $stmt->fetch();
            
            if (!$upgrade) {
                throw new Exception('Upgrade not found');
            }
            
            // Проверяем баланс пользователя
            $stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user['coins'] < $upgrade['price']) {
                throw new Exception('Not enough coins');
            }
            
            // Начинаем транзакцию
            $pdo->beginTransaction();
            
            try {
                // Вычитаем монеты
                $stmt = $pdo->prepare("UPDATE users SET coins = coins - ? WHERE id = ?");
                $stmt->execute([$upgrade['price'], $user_id]);
                
                // Сохраняем время покупки (UNIX timestamp)
                $purchased_at = time();
                
                $stmt = $pdo->prepare("
                    INSERT INTO user_upgrades 
                    (user_id, upgrade_id, duration, purchased_at) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, 
                    $upgrade_id,
                    $upgrade['duration'],
                    $purchased_at
                ]);
                
                // Получаем ID новой записи
                $userUpgradeId = $pdo->lastInsertId();
                
                $pdo->commit();
                
                // Получаем обновленный баланс
                $stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $new_balance = $stmt->fetchColumn();
                
                // Получаем полную информацию о купленном улучшении
                $stmt = $pdo->prepare("
                    SELECT u.*, uu.purchased_at, uu.duration 
                    FROM user_upgrades uu
                    JOIN upgrades u ON uu.upgrade_id = u.id
                    WHERE uu.id = ?
                ");
                $stmt->execute([$userUpgradeId]);
                $newUpgrade = $stmt->fetch(PDO::FETCH_ASSOC);
                
                jsonResponse([
                    'success' => 'Upgrade purchased',
                    'new_balance' => $new_balance,
                    'upgrade' => $newUpgrade
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        case 'active':
            $stmt = $pdo->prepare("
                SELECT 
                    u.*, 
                    uu.id as user_upgrade_id,
                    uu.purchased_at,
                    uu.duration
                FROM user_upgrades uu
                JOIN upgrades u ON uu.upgrade_id = u.id
                WHERE uu.user_id = ?
            ");
            $stmt->execute([$user_id]);
            $userUpgrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            jsonResponse($userUpgrades);
            break;
            
        case 'remove':
            $data = json_decode(file_get_contents('php://input'), true);
            $userUpgradeId = $data['id'] ?? null;
            
            if (!$userUpgradeId) {
                throw new Exception('Invalid upgrade ID');
            }
            
            // Проверяем принадлежность улучшения пользователю
            $stmt = $pdo->prepare("
                SELECT user_id 
                FROM user_upgrades 
                WHERE id = ?
            ");
            $stmt->execute([$userUpgradeId]);
            $upgrade = $stmt->fetch();
            
            if (!$upgrade || $upgrade['user_id'] != $user_id) {
                throw new Exception('Upgrade not found or access denied');
            }
            
            // Удаляем улучшение
            $stmt = $pdo->prepare("DELETE FROM user_upgrades WHERE id = ?");
            $stmt->execute([$userUpgradeId]);
            
            jsonResponse(['success' => 'Upgrade removed']);
            break;
            
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 400);
}