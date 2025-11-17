<?php
/**
 * Analytics Service
 *
 * Сервис для работы с аналитикой и статистикой
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class AnalyticsService
{
    /**
     * Периоды для аналитики
     */
    const PERIOD_ALL_TIME = 'all_time';
    const PERIOD_YEARLY = 'yearly';
    const PERIOD_MONTHLY = 'monthly';
    const PERIOD_WEEKLY = 'weekly';
    const PERIOD_DAILY = 'daily';

    /**
     * Получить статистику пользователя
     *
     * @param modX $modx
     * @param int $userId
     * @param string $period
     * @param bool $useCache
     * @return array|null
     */
    public static function getUserStatistics($modx, $userId, $period = self::PERIOD_ALL_TIME, $useCache = true)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        // Пытаемся получить из кеша
        if ($useCache) {
            $cached = self::getCachedMetrics($modx, 'user_stats', 'user', $userId, $period);
            if ($cached) {
                return $cached;
            }
        }

        // Обновляем кеш через stored procedure
        $stmt = $pdo->prepare("CALL update_user_analytics_cache(?, ?)");
        $stmt->execute([$userId, $period]);

        // Получаем из кеша
        return self::getCachedMetrics($modx, 'user_stats', 'user', $userId, $period);
    }

    /**
     * Получить статистику теста
     *
     * @param modX $modx
     * @param int $testId
     * @param string $period
     * @param bool $useCache
     * @return array|null
     */
    public static function getTestStatistics($modx, $testId, $period = self::PERIOD_ALL_TIME, $useCache = true)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        // Пытаемся получить из кеша
        if ($useCache) {
            $cached = self::getCachedMetrics($modx, 'test_stats', 'test', $testId, $period);
            if ($cached) {
                return $cached;
            }
        }

        // Обновляем кеш через stored procedure
        $stmt = $pdo->prepare("CALL update_test_analytics_cache(?, ?)");
        $stmt->execute([$testId, $period]);

        // Получаем из кеша
        return self::getCachedMetrics($modx, 'test_stats', 'test', $testId, $period);
    }

    /**
     * Получить кешированные метрики
     *
     * @param modX $modx
     * @param string $metricType
     * @param string $entityType
     * @param int $entityId
     * @param string $period
     * @return array|null
     */
    private static function getCachedMetrics($modx, $metricType, $entityType, $entityId, $period)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $periodStart = self::getPeriodStart($period);

        $stmt = $pdo->prepare("
            SELECT metrics, calculated_at
            FROM {$prefix}test_analytics_cache
            WHERE metric_type = ?
            AND entity_type = ?
            AND entity_id = ?
            AND period = ?
            AND (period_start IS NULL OR period_start = ?)
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$metricType, $entityType, $entityId, $period, $periodStart]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $metrics = json_decode($result['metrics'], true);
            $metrics['cached_at'] = $result['calculated_at'];
            return $metrics;
        }

        return null;
    }

    /**
     * Получить начало периода
     *
     * @param string $period
     * @return string|null
     */
    private static function getPeriodStart($period)
    {
        switch ($period) {
            case self::PERIOD_DAILY:
                return date('Y-m-d');
            case self::PERIOD_WEEKLY:
                $weekday = date('N') - 1; // Понедельник = 0
                return date('Y-m-d', strtotime("-$weekday days"));
            case self::PERIOD_MONTHLY:
                return date('Y-m-01');
            case self::PERIOD_YEARLY:
                return date('Y-01-01');
            default:
                return null;
        }
    }

    /**
     * Получить топ пользователей по баллам
     *
     * @param modX $modx
     * @param int $limit
     * @param int $categoryId Фильтр по категории
     * @return array
     */
    public static function getTopUsers($modx, $limit = 10, $categoryId = null)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $categoryCondition = $categoryId ? "AND t.category_id = ?" : "";
        $params = $categoryId ? [$categoryId, $limit] : [$limit];

        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.username,
                COUNT(DISTINCT s.id) as tests_completed,
                AVG(s.score) as avg_score,
                MAX(s.score) as max_score,
                SUM(CASE WHEN s.score = 100 THEN 1 ELSE 0 END) as perfect_scores,
                COALESCE(ue.total_xp, 0) as total_xp,
                COALESCE(ue.current_level, 1) as current_level
            FROM {$prefix}users u
            JOIN {$prefix}test_sessions s ON s.user_id = u.id AND s.status = 'completed'
            JOIN {$prefix}test_tests t ON t.id = s.test_id
            LEFT JOIN {$prefix}test_user_experience ue ON ue.user_id = u.id
            WHERE 1=1 $categoryCondition
            GROUP BY u.id
            ORDER BY avg_score DESC, tests_completed DESC
            LIMIT ?
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получить статистику по вопросу
     *
     * @param modX $modx
     * @param int $questionId
     * @return array|null
     */
    public static function getQuestionStatistics($modx, $questionId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("
            SELECT *
            FROM {$prefix}test_question_statistics
            WHERE question_id = ?
        ");
        $stmt->execute([$questionId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Получить самые сложные вопросы
     *
     * @param modX $modx
     * @param int $limit
     * @param int|null $testId
     * @return array
     */
    public static function getHardestQuestions($modx, $limit = 10, $testId = null)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("CALL get_hardest_questions(?, ?)");
        $stmt->execute([$limit, $testId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получить статистику категории
     *
     * @param modX $modx
     * @param int $categoryId
     * @return array|null
     */
    public static function getCategoryStatistics($modx, $categoryId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("
            SELECT *
            FROM {$prefix}test_category_statistics
            WHERE category_id = ?
        ");
        $stmt->execute([$categoryId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Получить общую статистику всех категорий
     *
     * @param modX $modx
     * @return array
     */
    public static function getAllCategoriesStatistics($modx)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->query("
            SELECT *
            FROM {$prefix}test_category_statistics
            ORDER BY category_name
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получить когортный анализ
     *
     * @param modX $modx
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getCohortAnalysis($modx, $startDate, $endDate)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("CALL get_cohort_analysis(?, ?)");
        $stmt->execute([$startDate, $endDate]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получить сводку активности пользователей за период
     *
     * @param modX $modx
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getUserActivitySummary($modx, $startDate, $endDate)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("CALL get_user_activity_summary(?, ?)");
        $stmt->execute([$startDate, $endDate]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Залогировать активность пользователя
     *
     * @param modX $modx
     * @param int $userId
     * @param string $activityType
     * @param string|null $entityType
     * @param int|null $entityId
     * @param array $metadata
     * @param int|null $duration
     * @return bool
     */
    public static function logActivity($modx, $userId, $activityType, $entityType = null, $entityId = null, $metadata = [], $duration = null)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        try {
            $sessionId = session_id();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $metadataJson = !empty($metadata) ? json_encode($metadata) : null;

            $stmt = $pdo->prepare("
                INSERT INTO {$prefix}test_user_activity_log
                (user_id, activity_type, entity_type, entity_id, session_id, ip_address, user_agent, metadata, duration)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            return $stmt->execute([
                $userId,
                $activityType,
                $entityType,
                $entityId,
                $sessionId,
                $ipAddress,
                $userAgent,
                $metadataJson,
                $duration
            ]);
        } catch (Exception $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, 'Error logging activity: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить распределение баллов для теста
     *
     * @param modX $modx
     * @param int $testId
     * @return array
     */
    public static function getScoreDistribution($modx, $testId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("
            SELECT
                CASE
                    WHEN score >= 90 THEN '90-100'
                    WHEN score >= 80 THEN '80-89'
                    WHEN score >= 70 THEN '70-79'
                    WHEN score >= 60 THEN '60-69'
                    WHEN score >= 50 THEN '50-59'
                    ELSE '0-49'
                END as score_range,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM {$prefix}test_sessions
                                          WHERE test_id = ? AND status = 'completed'), 2) as percentage
            FROM {$prefix}test_sessions
            WHERE test_id = ? AND status = 'completed'
            GROUP BY score_range
            ORDER BY score_range DESC
        ");
        $stmt->execute([$testId, $testId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получить прогресс пользователя по траектории обучения
     *
     * @param modX $modx
     * @param int $userId
     * @param int $pathId
     * @return array|null
     */
    public static function getPathProgress($modx, $userId, $pathId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("
            SELECT
                lp.id as path_id,
                lp.title as path_title,
                lp.total_steps,
                lpp.status as progress_status,
                lpp.current_step,
                lpp.completed_steps,
                lpp.progress_percentage,
                lpp.started_at,
                lpp.completed_at,
                COUNT(DISTINCT lpsc.id) as steps_completed_count,
                AVG(lpsc.score) as avg_score
            FROM {$prefix}test_learning_paths lp
            JOIN {$prefix}test_learning_path_enrollments lpe ON lpe.path_id = lp.id AND lpe.user_id = ?
            JOIN {$prefix}test_learning_path_progress lpp ON lpp.enrollment_id = lpe.id
            LEFT JOIN {$prefix}test_learning_path_step_completion lpsc ON lpsc.progress_id = lpp.id
            WHERE lp.id = ?
            GROUP BY lp.id
        ");
        $stmt->execute([$userId, $pathId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Получить общий дашборд для администратора
     *
     * @param modX $modx
     * @return array
     */
    public static function getAdminDashboard($modx)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $dashboard = [];

        // Общая статистика
        $stmt = $pdo->query("
            SELECT
                (SELECT COUNT(*) FROM {$prefix}users) as total_users,
                (SELECT COUNT(*) FROM {$prefix}test_tests WHERE published = 1) as total_tests,
                (SELECT COUNT(*) FROM {$prefix}test_categories) as total_categories,
                (SELECT COUNT(*) FROM {$prefix}test_questions) as total_questions,
                (SELECT COUNT(*) FROM {$prefix}test_sessions WHERE status = 'completed') as total_sessions,
                (SELECT AVG(score) FROM {$prefix}test_sessions WHERE status = 'completed') as avg_score,
                (SELECT COUNT(DISTINCT user_id) FROM {$prefix}test_sessions
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as active_users_30d,
                (SELECT COUNT(*) FROM {$prefix}test_sessions
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as sessions_7d
        ");
        $dashboard['overview'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Активность за последние 7 дней
        $stmt = $pdo->query("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as sessions_count,
                COUNT(DISTINCT user_id) as unique_users,
                AVG(CASE WHEN status = 'completed' THEN score END) as avg_score
            FROM {$prefix}test_sessions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $dashboard['recent_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Топ 5 тестов по популярности
        $stmt = $pdo->query("
            SELECT
                t.id,
                t.title,
                COUNT(s.id) as attempts,
                AVG(CASE WHEN s.status = 'completed' THEN s.score END) as avg_score
            FROM {$prefix}test_tests t
            LEFT JOIN {$prefix}test_sessions s ON s.test_id = t.id
            WHERE t.published = 1
            GROUP BY t.id
            ORDER BY attempts DESC
            LIMIT 5
        ");
        $dashboard['popular_tests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Топ 5 активных пользователей
        $stmt = $pdo->query("
            SELECT
                u.id,
                u.username,
                COUNT(DISTINCT s.id) as tests_taken,
                AVG(s.score) as avg_score,
                COALESCE(ue.total_xp, 0) as total_xp
            FROM {$prefix}users u
            LEFT JOIN {$prefix}test_sessions s ON s.user_id = u.id AND s.status = 'completed'
            LEFT JOIN {$prefix}test_user_experience ue ON ue.user_id = u.id
            GROUP BY u.id
            HAVING tests_taken > 0
            ORDER BY tests_taken DESC
            LIMIT 5
        ");
        $dashboard['top_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $dashboard;
    }

    /**
     * Получить дашборд для пользователя
     *
     * @param modX $modx
     * @param int $userId
     * @return array
     */
    public static function getUserDashboard($modx, $userId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $dashboard = [];

        // Личная статистика
        $stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT s.id) as total_tests,
                COUNT(DISTINCT CASE WHEN s.status = 'completed' THEN s.id END) as completed_tests,
                AVG(CASE WHEN s.status = 'completed' THEN s.score END) as avg_score,
                MAX(s.score) as best_score,
                SUM(CASE WHEN s.score >= 70 THEN 1 ELSE 0 END) as passed_tests,
                SUM(CASE WHEN s.score = 100 THEN 1 ELSE 0 END) as perfect_scores,
                COALESCE(ue.total_xp, 0) as total_xp,
                COALESCE(ue.current_level, 1) as current_level,
                (SELECT COUNT(*) FROM {$prefix}test_user_achievements WHERE user_id = ?) as achievements_count
            FROM {$prefix}test_sessions s
            LEFT JOIN {$prefix}test_user_experience ue ON ue.user_id = ?
            WHERE s.user_id = ?
        ");
        $stmt->execute([$userId, $userId, $userId]);
        $dashboard['stats'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Последние 5 тестов
        $stmt = $pdo->prepare("
            SELECT
                t.id,
                t.title,
                s.score,
                s.status,
                s.completed_at,
                s.time_spent
            FROM {$prefix}test_sessions s
            JOIN {$prefix}test_tests t ON t.id = s.test_id
            WHERE s.user_id = ?
            ORDER BY s.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$userId]);
        $dashboard['recent_tests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Прогресс по категориям
        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.name,
                COUNT(DISTINCT s.id) as tests_taken,
                AVG(s.score) as avg_score,
                COUNT(DISTINCT t.id) as total_category_tests,
                ROUND(COUNT(DISTINCT s.test_id) * 100.0 / COUNT(DISTINCT t.id), 2) as completion_percentage
            FROM {$prefix}test_categories c
            LEFT JOIN {$prefix}test_tests t ON t.category_id = c.id AND t.published = 1
            LEFT JOIN {$prefix}test_sessions s ON s.test_id = t.id AND s.user_id = ? AND s.status = 'completed'
            GROUP BY c.id
            HAVING tests_taken > 0
            ORDER BY avg_score DESC
        ");
        $stmt->execute([$userId]);
        $dashboard['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Активность за последние 30 дней
        $stmt = $pdo->prepare("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as tests_count
            FROM {$prefix}test_sessions
            WHERE user_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$userId]);
        $dashboard['activity_chart'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $dashboard;
    }

    /**
     * Получить сравнение пользователя с другими
     *
     * @param modX $modx
     * @param int $userId
     * @param int|null $categoryId
     * @return array
     */
    public static function getUserComparison($modx, $userId, $categoryId = null)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $categoryCondition = $categoryId ? "AND t.category_id = ?" : "";
        $params = $categoryId ? [$userId, $categoryId] : [$userId];

        // Получаем статистику пользователя
        $stmt = $pdo->prepare("
            SELECT
                AVG(CASE WHEN s.status = 'completed' THEN s.score END) as user_avg_score,
                COUNT(DISTINCT CASE WHEN s.status = 'completed' THEN s.id END) as user_tests_completed
            FROM {$prefix}test_sessions s
            JOIN {$prefix}test_tests t ON t.id = s.test_id
            WHERE s.user_id = ? $categoryCondition
        ");
        $stmt->execute($params);
        $userStats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Получаем общую статистику
        $stmt = $pdo->prepare("
            SELECT
                AVG(CASE WHEN s.status = 'completed' THEN s.score END) as avg_score,
                STDDEV(s.score) as score_stddev,
                COUNT(DISTINCT s.user_id) as total_users
            FROM {$prefix}test_sessions s
            JOIN {$prefix}test_tests t ON t.id = s.test_id
            WHERE s.status = 'completed' $categoryCondition
        ");
        $stmt->execute($categoryId ? [$categoryId] : []);
        $overallStats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Вычисляем перцентиль
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as users_below
            FROM (
                SELECT user_id, AVG(score) as avg_score
                FROM {$prefix}test_sessions s
                JOIN {$prefix}test_tests t ON t.id = s.test_id
                WHERE s.status = 'completed' $categoryCondition
                GROUP BY user_id
                HAVING avg_score < ?
            ) as subquery
        ");
        $stmt->execute(array_merge($categoryId ? [$categoryId] : [], [$userStats['user_avg_score']]));
        $belowCount = $stmt->fetch(PDO::FETCH_ASSOC)['users_below'];

        $percentile = round(($belowCount / $overallStats['total_users']) * 100, 2);

        return [
            'user' => $userStats,
            'overall' => $overallStats,
            'percentile' => $percentile,
            'comparison' => [
                'better_than_percent' => $percentile,
                'difference_from_average' => round($userStats['user_avg_score'] - $overallStats['avg_score'], 2)
            ]
        ];
    }

    /**
     * Очистить устаревший кеш
     *
     * @param modX $modx
     * @return int
     */
    public static function cleanupCache($modx)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->query("CALL cleanup_analytics_cache()");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['deleted_rows'] : 0;
    }
}
