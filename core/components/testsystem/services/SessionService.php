<?php
/**
 * Session Service Class
 *
 * Сервис для работы с сессиями тестирования
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-13
 */

class SessionService
{
    /**
     * Запуск новой сессии тестирования
     *
     * @param object $modx MODX объект
     * @param int $testId ID теста
     * @param int $userId ID пользователя
     * @param string $mode Режим (training/exam)
     * @param int|null $requestedCount Запрошенное количество вопросов (null = по умолчанию)
     * @return array ['session_id' => int, 'mode' => string, 'total_questions' => int]
     * @throws Exception При ошибках создания сессии
     */
    public static function startSession($modx, $testId, $userId, $mode = 'training', $requestedCount = null)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Очистка старых сессий
        self::cleanupOldSessions($modx, $prefix);

        // Закрываем старые активные сессии пользователя для этого теста
        self::closeUserActiveSessions($modx, $prefix, $testId, $userId);

        // Получаем настройки теста
        $testSettings = self::getTestSettings($modx, $prefix, $testId);

        // Определяем количество вопросов
        $questionsLimit = $requestedCount !== null
            ? $requestedCount
            : (int)($testSettings['questions_per_session'] ?? 20);

        $randomize = (int)($testSettings['randomize_questions'] ?? 1);

        // Выбираем вопросы
        $questionIds = self::selectQuestions($modx, $prefix, $testId, $questionsLimit, $randomize);

        // Создаём сессию
        $sessionId = self::createSession($modx, $prefix, $testId, $userId, $mode, $questionIds);

        return [
            'session_id' => $sessionId,
            'mode' => $mode,
            'total_questions' => count($questionIds)
        ];
    }

    /**
     * Очистка старых истекших сессий (всех пользователей)
     */
    private static function cleanupOldSessions($modx, $prefix)
    {
        $modx->exec("
            UPDATE {$prefix}test_sessions
            SET status = 'expired'
            WHERE status = 'active'
            AND started_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
    }

    /**
     * Закрытие старых активных сессий пользователя для конкретного теста
     */
    private static function closeUserActiveSessions($modx, $prefix, $testId, $userId)
    {
        $stmt = $modx->prepare("
            UPDATE {$prefix}test_sessions
            SET status = 'abandoned'
            WHERE test_id = ? AND user_id = ? AND status = 'active'
        ");
        $stmt->execute([$testId, $userId]);
    }

    /**
     * Получение настроек теста
     */
    private static function getTestSettings($modx, $prefix, $testId)
    {
        $stmt = $modx->prepare("
            SELECT questions_per_session, randomize_questions
            FROM {$prefix}test_tests
            WHERE id = ?
        ");
        $stmt->execute([$testId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Выбор вопросов для сессии
     */
    private static function selectQuestions($modx, $prefix, $testId, $questionsLimit, $randomize)
    {
        $sql = "SELECT id FROM {$prefix}test_questions WHERE test_id = ? AND published = 1";

        if ($randomize) {
            // Используем RAND() с random seed для лучшей случайности
            $sql .= " ORDER BY RAND(" . mt_rand() . ")";
        } else {
            $sql .= " ORDER BY sort_order";
        }

        $sql .= " LIMIT " . $questionsLimit;

        $stmt = $modx->prepare($sql);
        $stmt->execute([$testId]);
        $questionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($questionIds)) {
            throw new Exception('No questions found');
        }

        return $questionIds;
    }

    /**
     * Создание записи сессии в БД
     */
    private static function createSession($modx, $prefix, $testId, $userId, $mode, $questionIds)
    {
        $stmt = $modx->prepare("
            INSERT INTO {$prefix}test_sessions
            (test_id, user_id, mode, question_order, status, started_at)
            VALUES (?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$testId, $userId, $mode, json_encode($questionIds)]);

        return (int)$modx->lastInsertId();
    }

    /**
     * Отправка ответа на вопрос
     *
     * @param object $modx MODX объект
     * @param int $sessionId ID сессии
     * @param int $questionId ID вопроса
     * @param array $answerIds Массив ID выбранных ответов
     * @return array Результат проверки ответа
     * @throws Exception При ошибках
     */
    public static function submitAnswer($modx, $sessionId, $questionId, $answerIds)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Валидация сессии
        $session = self::validateSession($modx, $prefix, $sessionId);

        // Проверка, не был ли уже дан ответ
        self::checkDuplicateAnswer($modx, $prefix, $sessionId, $questionId);

        // Приводим к массиву если нужно
        if (!is_array($answerIds)) {
            $answerIds = [$answerIds];
        }

        // Приводим все ID к int
        $answerIds = array_map('intval', $answerIds);

        // Получаем правильные ответы
        $correctIds = self::getCorrectAnswerIds($modx, $prefix, $questionId);

        // Проверяем правильность
        $isCorrect = self::checkAnswerCorrectness($answerIds, $correctIds);

        // Сохраняем ответы
        self::saveUserAnswers($modx, $prefix, $sessionId, $questionId, $answerIds, $correctIds);

        // Формируем ответ
        $responseData = [
            'is_correct' => $isCorrect,
            'user_answer_ids' => $answerIds
        ];

        // Для режима training добавляем объяснение
        if ($session['mode'] === 'training') {
            $explanation = self::getQuestionExplanation($modx, $prefix, $questionId);
            $responseData['correct_answer_ids'] = $correctIds;

            if (!empty($explanation['explanation'])) {
                $responseData['explanation'] = $explanation['explanation'];
            }
            if (!empty($explanation['explanation_image'])) {
                $responseData['explanation_image'] = $explanation['explanation_image'];
            }
        }

        return $responseData;
    }

    /**
     * Валидация сессии
     */
    private static function validateSession($modx, $prefix, $sessionId)
    {
        $stmt = $modx->prepare("
            SELECT s.mode, s.status
            FROM {$prefix}test_sessions s
            WHERE s.id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session || $session['status'] !== 'active') {
            throw new Exception('Invalid or completed session');
        }

        return $session;
    }

    /**
     * Проверка на дубликат ответа
     */
    private static function checkDuplicateAnswer($modx, $prefix, $sessionId, $questionId)
    {
        $stmt = $modx->prepare("
            SELECT COUNT(*)
            FROM {$prefix}test_user_answers
            WHERE session_id = ? AND question_id = ?
        ");
        $stmt->execute([$sessionId, $questionId]);
        $alreadyAnswered = (int)$stmt->fetchColumn();

        if ($alreadyAnswered > 0) {
            throw new Exception('Question already answered');
        }
    }

    /**
     * Получение правильных ID ответов
     */
    private static function getCorrectAnswerIds($modx, $prefix, $questionId)
    {
        $stmt = $modx->prepare("
            SELECT id, is_correct
            FROM {$prefix}test_answers
            WHERE question_id = ?
        ");
        $stmt->execute([$questionId]);
        $allAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $correctIds = [];
        foreach ($allAnswers as $ans) {
            if ($ans['is_correct']) {
                $correctIds[] = (int)$ans['id'];
            }
        }

        return $correctIds;
    }

    /**
     * Проверка правильности ответа
     */
    private static function checkAnswerCorrectness($answerIds, $correctIds)
    {
        sort($answerIds);
        sort($correctIds);

        return ($answerIds === $correctIds) ? 1 : 0;
    }

    /**
     * Сохранение ответов пользователя в БД
     */
    private static function saveUserAnswers($modx, $prefix, $sessionId, $questionId, $answerIds, $correctIds)
    {
        $stmt = $modx->prepare("
            INSERT INTO {$prefix}test_user_answers
            (session_id, question_id, answer_id, is_correct, answered_at)
            VALUES (?, ?, ?, ?, NOW())
        ");

        if (!$stmt) {
            throw new Exception('Database error: failed to prepare');
        }

        // Вставляем каждый выбранный ответ отдельной строкой
        foreach ($answerIds as $answerId) {
            // Проверяем, правильный ли этот ответ
            $isThisAnswerCorrect = in_array($answerId, $correctIds) ? 1 : 0;

            $insertResult = $stmt->execute([
                $sessionId,
                $questionId,
                $answerId,
                $isThisAnswerCorrect
            ]);

            if (!$insertResult) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception('Failed to save answer: ' . $errorInfo[2]);
            }
        }
    }

    /**
     * Получение объяснения к вопросу
     */
    private static function getQuestionExplanation($modx, $prefix, $questionId)
    {
        $stmt = $modx->prepare("
            SELECT explanation, explanation_image
            FROM {$prefix}test_questions
            WHERE id = ?
        ");
        $stmt->execute([$questionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
