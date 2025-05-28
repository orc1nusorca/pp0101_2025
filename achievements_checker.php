<?php
class AchievementChecker {
    private $pdo;
    private $userId;
    private $solvedTasks;

    public function __construct(PDO $pdo, int $userId, array $solvedTasks = []) {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->solvedTasks = $solvedTasks;
    }

    public function checkForAchievements(string $triggerType, array $context = []): array {
        $unlocked = [];

        switch ($triggerType) {
            case 'task_solved':
                $unlocked = $this->checkTaskAchievements($context['task']);
                break;
            
            case 'daily_login':
                $unlocked = $this->checkDailyLoginAchievements();
                break;

            case 'level_up':
                $unlocked = $this->checkLevelAchievements($context['new_level']);
                break;
        }

        $this->saveAchievements($unlocked);
        return $unlocked;
    }

    private function checkTaskAchievements(array $task): array {
        $unlocked = [];
        $totalSolved = count($this->solvedTasks);

        if ($totalSolved === 1) {
            $achievement = $this->getAchievementByName('Первый шаг');
            if ($achievement) $unlocked[] = $achievement;
        }

        if ($task['difficulty'] === 'hard') {
            $hardSolved = $this->getSolvedTasksCount('hard');
            $achievement = $this->getAchievementByName('Мастер сложных задач');
            if ($hardSolved >= 10 && $achievement) $unlocked[] = $achievement;
        }

        foreach ($this->solvedTasks as $solved) {
            if ($solved['task_id'] == $task['id'] && $solved['solution_time'] < 300) {
                $achievement = $this->getAchievementByName('Скорострел');
                if ($achievement) {
                    $unlocked[] = $achievement;
                    break;
                }
            }
        }

        return $unlocked;
    }

    private function checkDailyLoginAchievements(): array {
        $stmt = $this->pdo->prepare("
            SELECT DATEDIFF(CURDATE(), last_daily_reward) AS streak 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$this->userId]);
        $streak = $stmt->fetchColumn();

        $achievements = [];
        if ($streak >= 7) $achievements[] = $this->getAchievementByName('Ежедневный герой');
        if ($streak >= 30) $achievements[] = $this->getAchievementByName('Марафонец');

        return $achievements;
    }

    private function checkLevelAchievements(int $newLevel): array {
        $achievements = [];
        if ($newLevel >= 5) $achievements[] = $this->getAchievementByName('Ученик');
        if ($newLevel >= 20) $achievements[] = $this->getAchievementByName('Мастер');
        if ($newLevel >= 50) $achievements[] = $this->getAchievementByName('Ветеран');
        return $achievements;
    }

    private function saveAchievements(array $achievements): void {
        foreach ($achievements as $achievement) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT IGNORE INTO user_achievements 
                    (user_id, achievement_id) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$this->userId, $achievement['id']]);
            } catch (PDOException $e) {
                error_log("Error saving achievement: " . $e->getMessage());
            }
        }
    }

    private function getAchievementByName(string $name): ?array {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM achievements WHERE name = ?");
            $stmt->execute([$name]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Achievement fetch error: " . $e->getMessage());
            return null;
        }
    }

    private function getSolvedTasksCount(?string $difficulty = null): int {
        $count = 0;
        foreach ($this->solvedTasks as $task) {
            if (!$difficulty || $task['difficulty'] === $difficulty) {
                $count++;
            }
        }
        return $count;
    }
}