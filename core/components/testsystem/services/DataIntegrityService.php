<?php
/**
 * Data Integrity Service
 *
 * Сервис для проверки целостности данных и очистки "осиротевших" записей
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class DataIntegrityService
{
    /**
     * Проверка целостности всей системы
     *
     * @param modX $modx
     * @return array Отчет о проблемах
     */
    public static function checkIntegrity($modx)
    {
        $report = [
            'orphaned_tests' => self::findOrphanedTests($modx),
            'orphaned_questions' => self::findOrphanedQuestions($modx),
            'orphaned_answers' => self::findOrphanedAnswers($modx),
            'orphaned_sessions' => self::findOrphanedSessions($modx),
            'orphaned_user_answers' => self::findOrphanedUserAnswers($modx),
            'orphaned_favorites' => self::findOrphanedFavorites($modx),
            'invalid_category_refs' => self::findInvalidCategoryReferences($modx),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Подсчет общего количества проблем
        $report['total_issues'] =
            count($report['orphaned_tests']) +
            count($report['orphaned_questions']) +
            count($report['orphaned_answers']) +
            count($report['orphaned_sessions']) +
            count($report['orphaned_user_answers']) +
            count($report['orphaned_favorites']) +
            count($report['invalid_category_refs']);

        return $report;
    }

    /**
     * Поиск тестов с несуществующими resource_id
     *
     * @param modX $modx
     * @return array
     */
    public static function findOrphanedTests($modx)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "
            SELECT t.id, t.title, t.resource_id, t.created_at
            FROM {$prefix}test_tests t
            LEFT JOIN {$prefix}site_content sc ON sc.id = t.resource_id
            WHERE t.resource_id IS NOT NULL
              AND sc.id IS NULL
        ";

        $stmt = $modx->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Поиск вопросов с несуществующими test_id
     *
     * @param modX $modx
     * @return array
     */
    public static function findOrphanedQuestions($modx)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "
            SELECT q.id, q.question_text, q.test_id, q.created_at
            FROM {$prefix}test_questions q
            LEFT JOIN {$prefix}test_tests t ON t.id = q.test_id
            WHERE t.id IS NULL
        ";

        $stmt = $modx->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Поиск ответов с несуществующими question_id
     *
     * @param modX $modx
     * @return array
     */
    public static function findOrphanedAnswers($modx)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "
            SELECT a.id, a.answer_text, a.question_id
            FROM {$prefix}test_answers a
            LEFT JOIN {$prefix}test_questions q ON q.id = a.question_id
            WHERE q.id IS NULL
        ";

        $stmt = $modx->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Поиск сессий с несуществующими test_id или user_id
     *
     * @param modX $modx
     * @return array
     */
    public static function findOrphanedSessions($modx)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "
            SELECT s.id, s.test_id, s.user_id, s.started_at,
                   CASE
                       WHEN t.id IS NULL THEN 'invalid_test'
                       WHEN u.id IS NULL THEN 'invalid_user'
                       ELSE 'unknown'
                   END as issue_type
            FROM {$prefix}test_sessions s
            LEFT JOIN {$prefix}test_tests t ON t.id = s.test_id
            LEFT JOIN {$prefix}users u ON u.id = s.user_id
            WHERE t.id IS NULL OR u.id IS NULL
        ";

        $stmt = $modx->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Поиск пользовательских ответов с несуществующими session_id или question_id
     *
     * @param modX $modx
     * @return array
     */
    public static function findOrphanedUserAnswers($modx)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "
            SELECT ua.id, ua.session_id, ua.question_id, ua.answer_id,
                   CASE
                       WHEN s.id IS NULL THEN 'invalid_session'
                       WHEN q.id IS NULL THEN 'invalid_question'
                       WHEN a.id IS NULL THEN 'invalid_answer'
                       ELSE 'unknown'
                   END as issue_type
            FROM {$prefix}test_user_answers ua
            LEFT JOIN {$prefix}test_sessions s ON s.id = ua.session_id
            LEFT JOIN {$prefix}test_questions q ON q.id = ua.question_id
            LEFT JOIN {$prefix}test_answers a ON a.id = ua.answer_id
            WHERE s.id IS NULL OR q.id IS NULL OR a.id IS NULL
        ";

        $stmt = $modx->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Поиск избранных вопросов с несуществующими user_id или question_id
     *
     * @param modX $modx
     * @return array
     */
    public static function findOrphanedFavorites($modx)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "
            SELECT f.id, f.user_id, f.question_id, f.added_at,
                   CASE
                       WHEN u.id IS NULL THEN 'invalid_user'
                       WHEN q.id IS NULL THEN 'invalid_question'
                       ELSE 'unknown'
                   END as issue_type
            FROM {$prefix}test_favorite_questions f
            LEFT JOIN {$prefix}users u ON u.id = f.user_id
            LEFT JOIN {$prefix}test_questions q ON q.id = f.question_id
            WHERE u.id IS NULL OR q.id IS NULL
        ";

        $stmt = $modx->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Поиск тестов с несуществующими category_id
     *
     * @param modX $modx
     * @return array
     */
    public static function findInvalidCategoryReferences($modx)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "
            SELECT t.id, t.title, t.category_id
            FROM {$prefix}test_tests t
            LEFT JOIN {$prefix}test_categories c ON c.id = t.category_id
            WHERE t.category_id IS NOT NULL AND c.id IS NULL
        ";

        $stmt = $modx->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Очистка осиротевших тестов
     *
     * @param modX $modx
     * @param array $testIds Массив ID тестов для удаления (если пустой - удаляются все найденные)
     * @return array Результат операции
     */
    public static function cleanOrphanedTests($modx, $testIds = [])
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        if (empty($testIds)) {
            $orphaned = self::findOrphanedTests($modx);
            $testIds = array_column($orphaned, 'id');
        }

        if (empty($testIds)) {
            return ['success' => true, 'deleted' => 0, 'message' => 'No orphaned tests found'];
        }

        $deleted = 0;
        foreach ($testIds as $testId) {
            // Используем существующий метод из TestRepository
            if (TestRepository::deleteTest($modx, $testId)) {
                $deleted++;
            }
        }

        return [
            'success' => true,
            'deleted' => $deleted,
            'total' => count($testIds),
            'message' => "Deleted {$deleted} orphaned tests"
        ];
    }

    /**
     * Очистка осиротевших вопросов
     *
     * @param modX $modx
     * @param array $questionIds
     * @return array
     */
    public static function cleanOrphanedQuestions($modx, $questionIds = [])
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        if (empty($questionIds)) {
            $orphaned = self::findOrphanedQuestions($modx);
            $questionIds = array_column($orphaned, 'id');
        }

        if (empty($questionIds)) {
            return ['success' => true, 'deleted' => 0, 'message' => 'No orphaned questions found'];
        }

        $placeholders = implode(',', array_fill(0, count($questionIds), '?'));

        // Удаляем связанные данные
        $stmt = $modx->prepare("DELETE FROM {$prefix}test_user_answers WHERE question_id IN ({$placeholders})");
        $stmt->execute($questionIds);

        $stmt = $modx->prepare("DELETE FROM {$prefix}test_answers WHERE question_id IN ({$placeholders})");
        $stmt->execute($questionIds);

        $stmt = $modx->prepare("DELETE FROM {$prefix}test_favorite_questions WHERE question_id IN ({$placeholders})");
        $stmt->execute($questionIds);

        // Удаляем сами вопросы
        $stmt = $modx->prepare("DELETE FROM {$prefix}test_questions WHERE id IN ({$placeholders})");
        $stmt->execute($questionIds);

        return [
            'success' => true,
            'deleted' => count($questionIds),
            'message' => "Deleted " . count($questionIds) . " orphaned questions"
        ];
    }

    /**
     * Очистка осиротевших ответов
     *
     * @param modX $modx
     * @param array $answerIds
     * @return array
     */
    public static function cleanOrphanedAnswers($modx, $answerIds = [])
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        if (empty($answerIds)) {
            $orphaned = self::findOrphanedAnswers($modx);
            $answerIds = array_column($orphaned, 'id');
        }

        if (empty($answerIds)) {
            return ['success' => true, 'deleted' => 0, 'message' => 'No orphaned answers found'];
        }

        $placeholders = implode(',', array_fill(0, count($answerIds), '?'));

        // Удаляем связанные пользовательские ответы
        $stmt = $modx->prepare("DELETE FROM {$prefix}test_user_answers WHERE answer_id IN ({$placeholders})");
        $stmt->execute($answerIds);

        // Удаляем сами ответы
        $stmt = $modx->prepare("DELETE FROM {$prefix}test_answers WHERE id IN ({$placeholders})");
        $stmt->execute($answerIds);

        return [
            'success' => true,
            'deleted' => count($answerIds),
            'message' => "Deleted " . count($answerIds) . " orphaned answers"
        ];
    }

    /**
     * Очистка осиротевших сессий
     *
     * @param modX $modx
     * @param array $sessionIds
     * @return array
     */
    public static function cleanOrphanedSessions($modx, $sessionIds = [])
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        if (empty($sessionIds)) {
            $orphaned = self::findOrphanedSessions($modx);
            $sessionIds = array_column($orphaned, 'id');
        }

        if (empty($sessionIds)) {
            return ['success' => true, 'deleted' => 0, 'message' => 'No orphaned sessions found'];
        }

        $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));

        // Удаляем связанные пользовательские ответы
        $stmt = $modx->prepare("DELETE FROM {$prefix}test_user_answers WHERE session_id IN ({$placeholders})");
        $stmt->execute($sessionIds);

        // Удаляем сами сессии
        $stmt = $modx->prepare("DELETE FROM {$prefix}test_sessions WHERE id IN ({$placeholders})");
        $stmt->execute($sessionIds);

        return [
            'success' => true,
            'deleted' => count($sessionIds),
            'message' => "Deleted " . count($sessionIds) . " orphaned sessions"
        ];
    }

    /**
     * Очистка осиротевших пользовательских ответов
     *
     * @param modX $modx
     * @param array $userAnswerIds
     * @return array
     */
    public static function cleanOrphanedUserAnswers($modx, $userAnswerIds = [])
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        if (empty($userAnswerIds)) {
            $orphaned = self::findOrphanedUserAnswers($modx);
            $userAnswerIds = array_column($orphaned, 'id');
        }

        if (empty($userAnswerIds)) {
            return ['success' => true, 'deleted' => 0, 'message' => 'No orphaned user answers found'];
        }

        $placeholders = implode(',', array_fill(0, count($userAnswerIds), '?'));

        $stmt = $modx->prepare("DELETE FROM {$prefix}test_user_answers WHERE id IN ({$placeholders})");
        $stmt->execute($userAnswerIds);

        return [
            'success' => true,
            'deleted' => count($userAnswerIds),
            'message' => "Deleted " . count($userAnswerIds) . " orphaned user answers"
        ];
    }

    /**
     * Очистка осиротевших избранных вопросов
     *
     * @param modX $modx
     * @param array $favoriteIds
     * @return array
     */
    public static function cleanOrphanedFavorites($modx, $favoriteIds = [])
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        if (empty($favoriteIds)) {
            $orphaned = self::findOrphanedFavorites($modx);
            $favoriteIds = array_column($orphaned, 'id');
        }

        if (empty($favoriteIds)) {
            return ['success' => true, 'deleted' => 0, 'message' => 'No orphaned favorites found'];
        }

        $placeholders = implode(',', array_fill(0, count($favoriteIds), '?'));

        $stmt = $modx->prepare("DELETE FROM {$prefix}test_favorite_questions WHERE id IN ({$placeholders})");
        $stmt->execute($favoriteIds);

        return [
            'success' => true,
            'deleted' => count($favoriteIds),
            'message' => "Deleted " . count($favoriteIds) . " orphaned favorites"
        ];
    }

    /**
     * Полная очистка всех найденных проблем
     *
     * @param modX $modx
     * @return array Детальный отчет об очистке
     */
    public static function cleanAll($modx)
    {
        $results = [];

        // Очищаем в правильном порядке (от зависимых к независимым)
        $results['user_answers'] = self::cleanOrphanedUserAnswers($modx);
        $results['favorites'] = self::cleanOrphanedFavorites($modx);
        $results['sessions'] = self::cleanOrphanedSessions($modx);
        $results['answers'] = self::cleanOrphanedAnswers($modx);
        $results['questions'] = self::cleanOrphanedQuestions($modx);
        $results['tests'] = self::cleanOrphanedTests($modx);

        // Подсчет общего количества удаленных записей
        $totalDeleted =
            $results['user_answers']['deleted'] +
            $results['favorites']['deleted'] +
            $results['sessions']['deleted'] +
            $results['answers']['deleted'] +
            $results['questions']['deleted'] +
            $results['tests']['deleted'];

        return [
            'success' => true,
            'total_deleted' => $totalDeleted,
            'details' => $results,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Очистка старых завершенных сессий (старше N дней)
     *
     * @param modX $modx
     * @param int $daysOld Количество дней
     * @return array
     */
    public static function cleanOldSessions($modx, $daysOld = 90)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        // Получаем ID старых завершенных сессий
        $stmt = $modx->prepare("
            SELECT id FROM {$prefix}test_sessions
            WHERE status = 'completed'
              AND finished_at < ?
        ");
        $stmt->execute([$cutoffDate]);
        $sessionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($sessionIds)) {
            return [
                'success' => true,
                'deleted' => 0,
                'message' => "No old sessions found (older than {$daysOld} days)"
            ];
        }

        return self::cleanOrphanedSessions($modx, $sessionIds);
    }
}
