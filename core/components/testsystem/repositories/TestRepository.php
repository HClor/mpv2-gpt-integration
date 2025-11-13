<?php
/**
 * Test Repository Class
 *
 * Централизация запросов к базе данных для работы с тестами
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-13
 */

class TestRepository
{
    /**
     * Получение теста по ID
     *
     * @param object $modx MODX объект
     * @param int $testId ID теста
     * @param array $fields Поля для выборки (по умолчанию все основные)
     * @return array|false Данные теста или false
     */
    public static function getTestById($modx, $testId, $fields = [])
    {
        if (empty($fields)) {
            $fields = [
                'id', 'title', 'description', 'created_by', 'publication_status',
                'resource_id', 'mode', 'time_limit', 'pass_score',
                'questions_per_session', 'is_active', 'created_at'
            ];
        }

        $fieldsList = implode(', ', $fields);

        $stmt = $modx->prepare("
            SELECT {$fieldsList}
            FROM modx_test_tests
            WHERE id = ?
        ");
        $stmt->execute([(int)$testId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Получение владельца теста
     *
     * @param object $modx MODX объект
     * @param int $testId ID теста
     * @return int|false ID владельца или false
     */
    public static function getTestOwner($modx, $testId)
    {
        $stmt = $modx->prepare("
            SELECT created_by
            FROM modx_test_tests
            WHERE id = ?
        ");
        $stmt->execute([(int)$testId]);

        $result = $stmt->fetchColumn();
        return $result !== false ? (int)$result : false;
    }

    /**
     * Проверка существования теста
     *
     * @param object $modx MODX объект
     * @param int $testId ID теста
     * @return bool
     */
    public static function testExists($modx, $testId)
    {
        $stmt = $modx->prepare("
            SELECT COUNT(*)
            FROM modx_test_tests
            WHERE id = ?
        ");
        $stmt->execute([(int)$testId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Подсчет количества вопросов в тесте
     *
     * @param object $modx MODX объект
     * @param int $testId ID теста
     * @param bool $publishedOnly Считать только опубликованные вопросы
     * @return int Количество вопросов
     */
    public static function countTestQuestions($modx, $testId, $publishedOnly = true)
    {
        $sql = "SELECT COUNT(*) FROM modx_test_questions WHERE test_id = ?";

        if ($publishedOnly) {
            $sql .= " AND published = 1";
        }

        $stmt = $modx->prepare($sql);
        $stmt->execute([(int)$testId]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Получение вопроса по ID
     *
     * @param object $modx MODX объект
     * @param int $questionId ID вопроса
     * @return array|false Данные вопроса или false
     */
    public static function getQuestionById($modx, $questionId)
    {
        $stmt = $modx->prepare("
            SELECT *
            FROM modx_test_questions
            WHERE id = ?
        ");
        $stmt->execute([(int)$questionId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Получение всех вопросов теста
     *
     * @param object $modx MODX объект
     * @param int $testId ID теста
     * @param bool $publishedOnly Только опубликованные
     * @return array Массив вопросов
     */
    public static function getTestQuestions($modx, $testId, $publishedOnly = true)
    {
        $sql = "
            SELECT *
            FROM modx_test_questions
            WHERE test_id = ?
        ";

        if ($publishedOnly) {
            $sql .= " AND published = 1";
        }

        $sql .= " ORDER BY sort_order ASC";

        $stmt = $modx->prepare($sql);
        $stmt->execute([(int)$testId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получение теста с проверкой прав владельца
     *
     * @param object $modx MODX объект
     * @param int $testId ID теста
     * @param int $userId ID пользователя для проверки
     * @return array Данные теста и информация о правах
     * @throws Exception Если теста не существует или нет прав
     */
    public static function getTestWithOwnerCheck($modx, $testId, $userId)
    {
        $test = self::getTestById($modx, $testId);

        if (!$test) {
            throw new Exception('Test not found');
        }

        $isOwner = ((int)$test['created_by'] === (int)$userId);

        return [
            'test' => $test,
            'isOwner' => $isOwner
        ];
    }

    /**
     * Требует, чтобы пользователь был владельцем теста
     *
     * @param object $modx MODX объект
     * @param int $testId ID теста
     * @param int $userId ID пользователя
     * @param string|null $errorMessage Сообщение об ошибке
     * @return array Данные теста
     * @throws Exception Если не владелец
     */
    public static function requireTestOwner($modx, $testId, $userId, $errorMessage = null)
    {
        $result = self::getTestWithOwnerCheck($modx, $testId, $userId);

        if (!$result['isOwner']) {
            $message = $errorMessage ?? 'Access denied: not test owner';
            throw new Exception($message);
        }

        return $result['test'];
    }

    /**
     * Получение сессии по ID с проверкой владельца
     *
     * @param object $modx MODX объект
     * @param int $sessionId ID сессии
     * @param int $userId ID пользователя для проверки
     * @return array|false Данные сессии или false
     */
    public static function getUserSession($modx, $sessionId, $userId)
    {
        $stmt = $modx->prepare("
            SELECT *
            FROM modx_test_sessions
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([(int)$sessionId, (int)$userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Получение активной сессии пользователя для теста
     *
     * @param object $modx MODX объект
     * @param int $testId ID теста
     * @param int $userId ID пользователя
     * @return array|false Данные сессии или false
     */
    public static function getActiveSession($modx, $testId, $userId)
    {
        $stmt = $modx->prepare("
            SELECT *
            FROM modx_test_sessions
            WHERE test_id = ? AND user_id = ? AND status = 'in_progress'
            ORDER BY started_at DESC
            LIMIT 1
        ");
        $stmt->execute([(int)$testId, (int)$userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Получение публичного теста по slug
     *
     * @param object $modx MODX объект
     * @param string $slug URL slug
     * @return array|false Данные теста или false
     */
    public static function getPublicTestBySlug($modx, $slug)
    {
        $stmt = $modx->prepare("
            SELECT id, title, description, mode, time_limit, pass_score,
                   questions_per_session, publication_status, created_by
            FROM modx_test_tests
            WHERE public_url_slug = ?
            AND publication_status IN ('unlisted', 'public')
            AND is_active = 1
        ");
        $stmt->execute([$slug]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Получение максимального порядка сортировки вопросов в тесте
     *
     * @param object $modx MODX объект
     * @param int $testId ID теста
     * @return int Максимальный sort_order
     */
    public static function getMaxQuestionSortOrder($modx, $testId)
    {
        $stmt = $modx->prepare("
            SELECT MAX(sort_order)
            FROM modx_test_questions
            WHERE test_id = ?
        ");
        $stmt->execute([(int)$testId]);

        $result = $stmt->fetchColumn();
        return $result !== false ? (int)$result : 0;
    }

    /**
     * Удаление теста и всех связанных данных
     *
     * @param object $modx MODX объект
     * @param int $testId ID теста
     * @return bool Успех операции
     */
    public static function deleteTest($modx, $testId)
    {
        $testId = (int)$testId;
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        try {
            // 1. Удаляем ответы на вопросы
            $modx->exec("
                DELETE ua FROM {$prefix}test_user_answers ua
                INNER JOIN {$prefix}test_questions q ON q.id = ua.question_id
                WHERE q.test_id = {$testId}
            ");

            // 2. Удаляем варианты ответов
            $modx->exec("
                DELETE a FROM {$prefix}test_answers a
                INNER JOIN {$prefix}test_questions q ON q.id = a.question_id
                WHERE q.test_id = {$testId}
            ");

            // 3. Удаляем вопросы
            $modx->exec("DELETE FROM {$prefix}test_questions WHERE test_id = {$testId}");

            // 4. Удаляем разрешения
            $modx->exec("DELETE FROM {$prefix}test_permissions WHERE test_id = {$testId}");

            // 5. Удаляем сессии
            $modx->exec("DELETE FROM {$prefix}test_sessions WHERE test_id = {$testId}");

            // 6. Удаляем избранное
            $modx->exec("DELETE FROM {$prefix}test_favorites WHERE test_id = {$testId}");

            // 7. Удаляем сам тест
            $modx->exec("DELETE FROM {$prefix}test_tests WHERE id = {$testId}");

            return true;
        } catch (Exception $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, 'TestRepository::deleteTest error: ' . $e->getMessage());
            return false;
        }
    }
}
