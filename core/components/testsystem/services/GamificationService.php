<?php
/**
 * Gamification Service
 *
 * Сервис для управления системой геймификации: XP, уровни, достижения, рейтинги
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class GamificationService
{
    /**
     * Типы достижений
     */
    const ACHIEVEMENT_TEST_COUNT = 'test_count';
    const ACHIEVEMENT_PERFECT_SCORE = 'perfect_score';
    const ACHIEVEMENT_STREAK = 'streak';
    const ACHIEVEMENT_CATEGORY_MASTER = 'category_master';
    const ACHIEVEMENT_SPEED_DEMON = 'speed_demon';
    const ACHIEVEMENT_FIRST_PLACE = 'first_place';
    const ACHIEVEMENT_CUSTOM = 'custom';

    /**
     * Периоды для рейтингов
     */
    const PERIOD_ALL_TIME = 'all_time';
    const PERIOD_YEARLY = 'yearly';
    const PERIOD_MONTHLY = 'monthly';
    const PERIOD_WEEKLY = 'weekly';

    /**
     * Получить профиль пользователя (XP, уровень, достижения)
     *
     * @param modX $modx
     * @param int $userId
     * @return array|null
     */
    public static function getUserProfile($modx, $userId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        // Получаем XP и уровень
        $stmt = $pdo->prepare("
            SELECT ue.*, lc.title as level_title, lc.xp_required,
                   (SELECT xp_required FROM {$prefix}test_level_config
                    WHERE level = ue.current_level + 1 LIMIT 1) as next_level_xp
            FROM {$prefix}test_user_experience ue
            LEFT JOIN {$prefix}test_level_config lc ON lc.level = ue.current_level
            WHERE ue.user_id = ?
        ");
        $stmt->execute([$userId]);
        $experience = $stmt->fetch(PDO::FETCH_ASSOC);

        // Если записи нет - создаем
        if (!$experience) {
            self::initializeUserExperience($modx, $userId);
            return self::getUserProfile($modx, $userId);
        }

        // Получаем количество достижений
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as earned_count
            FROM {$prefix}test_user_achievements
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $achievementData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Получаем текущую серию
        $stmt = $pdo->prepare("
            SELECT current_streak, longest_streak, last_activity_date
            FROM {$prefix}test_user_streaks
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $streakData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Получаем позицию в рейтинге
        $rank = self::getUserRank($modx, $userId, self::PERIOD_ALL_TIME);

        return [
            'user_id' => $userId,
            'total_xp' => (int)$experience['total_xp'],
            'current_level' => (int)$experience['current_level'],
            'level_title' => $experience['level_title'],
            'xp_for_current_level' => (int)$experience['xp_required'],
            'xp_for_next_level' => $experience['next_level_xp'] ? (int)$experience['next_level_xp'] : null,
            'progress_to_next_level' => self::calculateLevelProgress(
                $experience['total_xp'],
                $experience['xp_required'],
                $experience['next_level_xp']
            ),
            'achievements_earned' => (int)$achievementData['earned_count'],
            'current_streak' => $streakData ? (int)$streakData['current_streak'] : 0,
            'longest_streak' => $streakData ? (int)$streakData['longest_streak'] : 0,
            'last_activity' => $streakData ? $streakData['last_activity_date'] : null,
            'global_rank' => $rank,
            'created_at' => $experience['created_at'],
            'updated_at' => $experience['updated_at']
        ];
    }

    /**
     * Инициализация XP для нового пользователя
     *
     * @param modX $modx
     * @param int $userId
     * @return bool
     */
    private static function initializeUserExperience($modx, $userId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO {$prefix}test_user_experience
            (user_id, total_xp, current_level)
            VALUES (?, 0, 1)
        ");

        return $stmt->execute([$userId]);
    }

    /**
     * Расчет прогресса до следующего уровня
     *
     * @param int $totalXp
     * @param int $currentLevelXp
     * @param int|null $nextLevelXp
     * @return float Процент от 0 до 100
     */
    private static function calculateLevelProgress($totalXp, $currentLevelXp, $nextLevelXp)
    {
        if (!$nextLevelXp) {
            return 100; // Максимальный уровень
        }

        $xpInCurrentLevel = $totalXp - $currentLevelXp;
        $xpNeededForNext = $nextLevelXp - $currentLevelXp;

        if ($xpNeededForNext <= 0) {
            return 100;
        }

        return round(($xpInCurrentLevel / $xpNeededForNext) * 100, 2);
    }

    /**
     * Получить позицию пользователя в рейтинге
     *
     * @param modX $modx
     * @param int $userId
     * @param string $period
     * @param int|null $categoryId
     * @return int|null
     */
    public static function getUserRank($modx, $userId, $period = self::PERIOD_ALL_TIME, $categoryId = null)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $categoryCondition = $categoryId ? "AND category_id = ?" : "AND category_id IS NULL";
        $params = [$period, $userId];
        if ($categoryId) {
            $params[] = $categoryId;
        }

        $stmt = $pdo->prepare("
            SELECT rank_position
            FROM {$prefix}test_leaderboard
            WHERE period = ? AND user_id = ? $categoryCondition
        ");
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['rank_position'] : null;
    }

    /**
     * Получить список достижений пользователя
     *
     * @param modX $modx
     * @param int $userId
     * @param bool $includeNotEarned Включить недостигнутые достижения
     * @return array
     */
    public static function getUserAchievements($modx, $userId, $includeNotEarned = true)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        if ($includeNotEarned) {
            // Все достижения с отметкой earned
            $stmt = $pdo->prepare("
                SELECT a.*,
                       ua.id as user_achievement_id,
                       ua.earned_at,
                       ua.progress,
                       CASE WHEN ua.id IS NOT NULL THEN 1 ELSE 0 END as is_earned,
                       CASE WHEN a.is_secret = 1 AND ua.id IS NULL THEN 1 ELSE 0 END as is_hidden
                FROM {$prefix}test_achievements a
                LEFT JOIN {$prefix}test_user_achievements ua
                    ON ua.achievement_id = a.id AND ua.user_id = ?
                WHERE a.is_active = 1
                ORDER BY
                    CASE WHEN ua.id IS NOT NULL THEN 0 ELSE 1 END,
                    ua.earned_at DESC,
                    a.sort_order ASC,
                    a.name ASC
            ");
            $stmt->execute([$userId]);
        } else {
            // Только заработанные
            $stmt = $pdo->prepare("
                SELECT a.*, ua.earned_at, ua.progress, 1 as is_earned, 0 as is_hidden
                FROM {$prefix}test_user_achievements ua
                JOIN {$prefix}test_achievements a ON a.id = ua.achievement_id
                WHERE ua.user_id = ? AND a.is_active = 1
                ORDER BY ua.earned_at DESC
            ");
            $stmt->execute([$userId]);
        }

        $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Скрываем детали секретных недостигнутых
        foreach ($achievements as &$achievement) {
            if ($achievement['is_hidden']) {
                $achievement['name'] = '???';
                $achievement['description'] = 'Секретное достижение';
                $achievement['icon'] = 'fa-question';
            }

            // Декодируем condition из JSON
            if (!empty($achievement['condition'])) {
                $achievement['condition'] = json_decode($achievement['condition'], true);
            }
        }

        return $achievements;
    }

    /**
     * Начислить XP пользователю вручную
     *
     * @param modX $modx
     * @param int $userId
     * @param int $xpAmount
     * @param string $reason
     * @param int|null $relatedId ID связанной сущности (теста, вопроса и т.д.)
     * @param string|null $relatedType Тип связанной сущности
     * @return bool
     */
    public static function awardXP($modx, $userId, $xpAmount, $reason, $relatedId = null, $relatedType = null)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        try {
            $pdo->beginTransaction();

            // Инициализируем если нужно
            self::initializeUserExperience($modx, $userId);

            // Записываем в историю
            $stmt = $pdo->prepare("
                INSERT INTO {$prefix}test_xp_history
                (user_id, xp_amount, reason, related_id, related_type)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $xpAmount, $reason, $relatedId, $relatedType]);

            // Обновляем общий XP (триггер автоматически обновит уровень)
            $stmt = $pdo->prepare("
                UPDATE {$prefix}test_user_experience
                SET total_xp = total_xp + ?
                WHERE user_id = ?
            ");
            $stmt->execute([$xpAmount, $userId]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $modx->log(modX::LOG_LEVEL_ERROR, 'Error awarding XP: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Обновить серию активности пользователя
     *
     * @param modX $modx
     * @param int $userId
     * @return array Информация о серии
     */
    public static function updateUserStreak($modx, $userId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        // Вызываем stored procedure
        $stmt = $pdo->prepare("CALL update_user_streak(?)");
        $stmt->execute([$userId]);

        // Получаем результат
        $stmt = $pdo->prepare("
            SELECT current_streak, longest_streak, last_activity_date
            FROM {$prefix}test_user_streaks
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return [
                'current_streak' => (int)$result['current_streak'],
                'longest_streak' => (int)$result['longest_streak'],
                'last_activity_date' => $result['last_activity_date']
            ];
        }

        return null;
    }

    /**
     * Проверить и наградить достижениями на основе активности
     *
     * @param modX $modx
     * @param int $userId
     * @param string $activityType Тип активности (test_completed, streak_updated и т.д.)
     * @param array $activityData Данные активности
     * @return array Список новых достижений
     */
    public static function checkAndAwardAchievements($modx, $userId, $activityType, $activityData = [])
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        // Получаем все активные достижения, которые пользователь еще не получил
        $stmt = $pdo->prepare("
            SELECT a.*
            FROM {$prefix}test_achievements a
            WHERE a.is_active = 1
            AND NOT EXISTS (
                SELECT 1 FROM {$prefix}test_user_achievements ua
                WHERE ua.achievement_id = a.id AND ua.user_id = ?
            )
        ");
        $stmt->execute([$userId]);
        $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $newAchievements = [];

        foreach ($achievements as $achievement) {
            $condition = json_decode($achievement['condition'], true);

            if (self::checkAchievementCondition($modx, $userId, $achievement['achievement_type'], $condition, $activityType, $activityData)) {
                // Награждаем достижением
                $stmt = $pdo->prepare("
                    INSERT INTO {$prefix}test_user_achievements
                    (user_id, achievement_id, progress)
                    VALUES (?, ?, 100)
                ");
                $stmt->execute([$userId, $achievement['id']]);

                // Начисляем XP за достижение
                if ($achievement['xp_reward'] > 0) {
                    self::awardXP(
                        $modx,
                        $userId,
                        $achievement['xp_reward'],
                        'Achievement earned: ' . $achievement['name'],
                        $achievement['id'],
                        'achievement'
                    );
                }

                $newAchievements[] = $achievement;
            }
        }

        return $newAchievements;
    }

    /**
     * Проверить условие достижения
     *
     * @param modX $modx
     * @param int $userId
     * @param string $achievementType
     * @param array $condition
     * @param string $activityType
     * @param array $activityData
     * @return bool
     */
    private static function checkAchievementCondition($modx, $userId, $achievementType, $condition, $activityType, $activityData)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        switch ($achievementType) {
            case self::ACHIEVEMENT_TEST_COUNT:
                // Проверяем количество пройденных тестов
                $requiredCount = $condition['count'] ?? 1;
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM {$prefix}test_sessions
                    WHERE user_id = ? AND status = 'completed'
                ");
                $stmt->execute([$userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return (int)$result['count'] >= $requiredCount;

            case self::ACHIEVEMENT_PERFECT_SCORE:
                // Проверяем наличие теста со 100% результатом
                if ($activityType === 'test_completed' && isset($activityData['score']) && $activityData['score'] == 100) {
                    return true;
                }
                // Или проверяем в базе
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM {$prefix}test_sessions
                    WHERE user_id = ? AND status = 'completed' AND score = 100
                ");
                $stmt->execute([$userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return (int)$result['count'] > 0;

            case self::ACHIEVEMENT_STREAK:
                // Проверяем серию
                $requiredStreak = $condition['days'] ?? 1;
                $stmt = $pdo->prepare("
                    SELECT current_streak
                    FROM {$prefix}test_user_streaks
                    WHERE user_id = ?
                ");
                $stmt->execute([$userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result && (int)$result['current_streak'] >= $requiredStreak;

            case self::ACHIEVEMENT_CATEGORY_MASTER:
                // Проверяем мастерство в категории (все тесты категории с высоким баллом)
                $categoryId = $condition['category_id'] ?? null;
                $minScore = $condition['min_score'] ?? 90;

                if (!$categoryId) {
                    return false;
                }

                // Получаем все тесты категории
                $stmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT t.id) as total_tests
                    FROM {$prefix}test_tests t
                    WHERE t.category_id = ? AND t.published = 1
                ");
                $stmt->execute([$categoryId]);
                $totalTests = $stmt->fetch(PDO::FETCH_ASSOC)['total_tests'];

                // Получаем пройденные с высоким баллом
                $stmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT s.test_id) as completed_tests
                    FROM {$prefix}test_sessions s
                    JOIN {$prefix}test_tests t ON t.id = s.test_id
                    WHERE s.user_id = ? AND t.category_id = ?
                    AND s.status = 'completed' AND s.score >= ?
                ");
                $stmt->execute([$userId, $categoryId, $minScore]);
                $completed = $stmt->fetch(PDO::FETCH_ASSOC)['completed_tests'];

                return $totalTests > 0 && $completed >= $totalTests;

            case self::ACHIEVEMENT_SPEED_DEMON:
                // Проверяем быстрое прохождение теста
                if ($activityType === 'test_completed' && isset($activityData['time_spent'])) {
                    $maxTime = $condition['max_time_seconds'] ?? 300; // 5 минут по умолчанию
                    return $activityData['time_spent'] <= $maxTime;
                }
                return false;

            case self::ACHIEVEMENT_FIRST_PLACE:
                // Проверяем первое место в рейтинге
                $period = $condition['period'] ?? self::PERIOD_WEEKLY;
                $rank = self::getUserRank($modx, $userId, $period);
                return $rank === 1;

            case self::ACHIEVEMENT_CUSTOM:
                // Кастомная логика - может быть расширена
                return false;

            default:
                return false;
        }
    }

    /**
     * Получить рейтинг (leaderboard)
     *
     * @param modX $modx
     * @param string $period Период (all_time, yearly, monthly, weekly)
     * @param int|null $categoryId ID категории (null для общего рейтинга)
     * @param int $limit Количество записей
     * @param int $offset Смещение
     * @return array
     */
    public static function getLeaderboard($modx, $period = self::PERIOD_ALL_TIME, $categoryId = null, $limit = 50, $offset = 0)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $categoryCondition = $categoryId ? "AND lb.category_id = ?" : "AND lb.category_id IS NULL";
        $params = [$period];
        if ($categoryId) {
            $params[] = $categoryId;
        }
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $pdo->prepare("
            SELECT lb.*,
                   ue.current_level,
                   lc.title as level_title,
                   u.username,
                   (SELECT COUNT(*) FROM {$prefix}test_user_achievements WHERE user_id = lb.user_id) as achievements_count
            FROM {$prefix}test_leaderboard lb
            JOIN {$prefix}test_user_experience ue ON ue.user_id = lb.user_id
            LEFT JOIN {$prefix}test_level_config lc ON lc.level = ue.current_level
            LEFT JOIN {$prefix}users u ON u.id = lb.user_id
            WHERE lb.period = ? $categoryCondition
            ORDER BY lb.rank_position ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Обновить рейтинг (вызывается периодически через cron)
     *
     * @param modX $modx
     * @param string $period Период для обновления
     * @return bool
     */
    public static function updateLeaderboard($modx, $period = self::PERIOD_ALL_TIME)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        try {
            $pdo->beginTransaction();

            // Определяем временные рамки
            $dateCondition = self::getDateConditionForPeriod($period);

            // Обновляем общий рейтинг (category_id = NULL)
            $stmt = $pdo->prepare("
                INSERT INTO {$prefix}test_leaderboard
                (user_id, period, category_id, total_xp, tests_completed, avg_score, rank_position)
                SELECT
                    ue.user_id,
                    ? as period,
                    NULL as category_id,
                    ue.total_xp,
                    COUNT(DISTINCT s.id) as tests_completed,
                    COALESCE(AVG(s.score), 0) as avg_score,
                    0 as rank_position
                FROM {$prefix}test_user_experience ue
                LEFT JOIN {$prefix}test_sessions s ON s.user_id = ue.user_id
                    AND s.status = 'completed' $dateCondition
                GROUP BY ue.user_id
                ON DUPLICATE KEY UPDATE
                    total_xp = VALUES(total_xp),
                    tests_completed = VALUES(tests_completed),
                    avg_score = VALUES(avg_score),
                    updated_at = NOW()
            ");
            $stmt->execute([$period]);

            // Обновляем позиции в общем рейтинге
            $stmt = $pdo->prepare("
                SET @rank := 0;
                UPDATE {$prefix}test_leaderboard
                SET rank_position = (@rank := @rank + 1)
                WHERE period = ? AND category_id IS NULL
                ORDER BY total_xp DESC, tests_completed DESC, avg_score DESC
            ");
            $stmt->execute([$period]);

            // Обновляем рейтинги по категориям
            $stmt = $pdo->prepare("
                SELECT DISTINCT t.category_id
                FROM {$prefix}test_tests t
                WHERE t.category_id IS NOT NULL
            ");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($categories as $categoryId) {
                // Вставляем/обновляем записи для категории
                $stmt = $pdo->prepare("
                    INSERT INTO {$prefix}test_leaderboard
                    (user_id, period, category_id, total_xp, tests_completed, avg_score, rank_position)
                    SELECT
                        s.user_id,
                        ? as period,
                        ? as category_id,
                        ue.total_xp,
                        COUNT(DISTINCT s.id) as tests_completed,
                        COALESCE(AVG(s.score), 0) as avg_score,
                        0 as rank_position
                    FROM {$prefix}test_sessions s
                    JOIN {$prefix}test_tests t ON t.id = s.test_id
                    JOIN {$prefix}test_user_experience ue ON ue.user_id = s.user_id
                    WHERE t.category_id = ? AND s.status = 'completed' $dateCondition
                    GROUP BY s.user_id
                    HAVING tests_completed > 0
                    ON DUPLICATE KEY UPDATE
                        total_xp = VALUES(total_xp),
                        tests_completed = VALUES(tests_completed),
                        avg_score = VALUES(avg_score),
                        updated_at = NOW()
                ");
                $stmt->execute([$period, $categoryId, $categoryId]);

                // Обновляем позиции в категории
                $stmt = $pdo->prepare("
                    SET @rank := 0;
                    UPDATE {$prefix}test_leaderboard
                    SET rank_position = (@rank := @rank + 1)
                    WHERE period = ? AND category_id = ?
                    ORDER BY tests_completed DESC, avg_score DESC, total_xp DESC
                ");
                $stmt->execute([$period, $categoryId]);
            }

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $modx->log(modX::LOG_LEVEL_ERROR, 'Error updating leaderboard: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить SQL условие для периода
     *
     * @param string $period
     * @return string
     */
    private static function getDateConditionForPeriod($period)
    {
        switch ($period) {
            case self::PERIOD_YEARLY:
                return "AND YEAR(s.completed_at) = YEAR(NOW())";
            case self::PERIOD_MONTHLY:
                return "AND YEAR(s.completed_at) = YEAR(NOW()) AND MONTH(s.completed_at) = MONTH(NOW())";
            case self::PERIOD_WEEKLY:
                return "AND YEARWEEK(s.completed_at, 1) = YEARWEEK(NOW(), 1)";
            case self::PERIOD_ALL_TIME:
            default:
                return "";
        }
    }

    /**
     * Получить историю получения XP
     *
     * @param modX $modx
     * @param int $userId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getXPHistory($modx, $userId, $limit = 50, $offset = 0)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("
            SELECT *
            FROM {$prefix}test_xp_history
            WHERE user_id = ?
            ORDER BY earned_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получить статистику по уровням пользователей
     *
     * @param modX $modx
     * @return array
     */
    public static function getLevelStatistics($modx)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("
            SELECT lc.level, lc.title, lc.xp_required,
                   COUNT(ue.user_id) as user_count
            FROM {$prefix}test_level_config lc
            LEFT JOIN {$prefix}test_user_experience ue ON ue.current_level = lc.level
            GROUP BY lc.level
            ORDER BY lc.level ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получить топ достижений (самые редкие)
     *
     * @param modX $modx
     * @param int $limit
     * @return array
     */
    public static function getRarestAchievements($modx, $limit = 10)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("
            SELECT a.*,
                   COUNT(ua.id) as earned_count,
                   (SELECT COUNT(*) FROM {$prefix}test_user_experience) as total_users,
                   ROUND(COUNT(ua.id) * 100.0 / (SELECT COUNT(*) FROM {$prefix}test_user_experience), 2) as earn_percentage
            FROM {$prefix}test_achievements a
            LEFT JOIN {$prefix}test_user_achievements ua ON ua.achievement_id = a.id
            WHERE a.is_active = 1
            GROUP BY a.id
            ORDER BY earn_percentage ASC, earned_count ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
