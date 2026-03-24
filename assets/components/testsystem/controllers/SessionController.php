<?php
/**
 * Session Controller
 *
 * Контроллер для управления сессиями тестирования
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class SessionController extends BaseController
{
    /**
     * Список доступных действий
     */
    private $actions = [
        'startSession',
        'cleanupOldSessions',
        'getNextQuestion',
        'submitAnswer',
        'finishTest',
        'getSessionInfo'
    ];

    /**
     * Обработка действия
     *
     * @param string $action Название действия
     * @param array $data Данные запроса
     * @return array
     */
    public function handle($action, $data)
    {
        if (!in_array($action, $this->actions)) {
            return $this->error('Unknown action: ' . $action, 404);
        }

        try {
            switch ($action) {
                case 'startSession':
                    return $this->startSession($data);

                case 'cleanupOldSessions':
                    return $this->cleanupOldSessions();

                case 'getNextQuestion':
                    return $this->getNextQuestion($data);

                case 'submitAnswer':
                    return $this->submitAnswer($data);

                case 'finishTest':
                    return $this->finishTest($data);

                case 'getSessionInfo':
                    return $this->getSessionInfo($data);

                default:
                    return $this->error('Action not implemented', 501);
            }
        } catch (AuthenticationException $e) {
            return $this->error($e->getMessage(), 401);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Запуск новой сессии тестирования
     */
    private function startSession($data)
    {
        $testId = ValidationHelper::requireInt($data, 'test_id', 'Test ID required');
        $mode = ValidationHelper::optionalString($data, 'mode', 'training');
        $requestedCount = isset($data['questions_count'])
            ? ValidationHelper::requireInt($data, 'questions_count', null, false, 1)
            : null;

        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        // Используем SessionService
        $sessionData = SessionService::startSession($this->modx, $testId, $userId, $mode, $requestedCount);

        return $this->success($sessionData);
    }

    /**
     * Очистка старых сессий
     */
    private function cleanupOldSessions()
    {
        $this->modx->exec("
            UPDATE {$this->prefix}test_sessions
            SET status = 'expired'
            WHERE status = 'active'
            AND started_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");

        return $this->success();
    }

    /**
     * Получение следующего вопроса
     */
    private function getNextQuestion($data)
    {
        $sessionId = ValidationHelper::requireInt($data, 'session_id', 'Session ID required');

        // Получаем сессию
        $stmt = $this->modx->prepare("
            SELECT s.test_id, s.mode, s.status, s.question_order,
                   COALESCE(t.randomize_answers, 1) as randomize_answers
            FROM {$this->prefix}test_sessions s
            LEFT JOIN {$this->prefix}test_tests t ON t.id = s.test_id
            WHERE s.id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session || $session['status'] !== 'active') {
            throw new Exception('Invalid or completed session');
        }

        $questionOrder = json_decode($session['question_order'], true);

        // Получаем уже отвеченные вопросы
        $stmt = $this->modx->prepare("
            SELECT question_id
            FROM {$this->prefix}test_user_answers
            WHERE session_id = ?
        ");
        $stmt->execute([$sessionId]);
        $answeredIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Находим следующий неотвеченный И опубликованный вопрос
        $answeredMap = array_flip(array_map('intval', $answeredIds));
        $unansweredIds = [];
        foreach ($questionOrder as $qid) {
            $qid = (int)$qid;
            if ($qid > 0 && !isset($answeredMap[$qid])) {
                $unansweredIds[] = $qid;
            }
        }

        $nextQuestionId = null;
        if (!empty($unansweredIds)) {
            $placeholders = implode(',', array_fill(0, count($unansweredIds), '?'));
            $stmt = $this->modx->prepare("\n                SELECT id\n                FROM {$this->prefix}test_questions\n                WHERE id IN ({$placeholders}) AND published = 1\n            ");
            $stmt->execute($unansweredIds);
            $publishedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $publishedMap = array_flip(array_map('intval', $publishedIds));

            foreach ($unansweredIds as $qid) {
                if (isset($publishedMap[$qid])) {
                    $nextQuestionId = $qid;
                    break;
                }
            }
        }

        if (!$nextQuestionId) {
            return $this->success(['finished' => true]);
        }

        // Получаем вопрос с ответами
        $isKnowledgeArea = ((int)$session['test_id'] === -1);
        $questionData = $this->getQuestionData($nextQuestionId, $isKnowledgeArea, (bool)$session['randomize_answers']);

        // ИСПРАВЛЕНО: Разделяем вопрос и ответы в правильную структуру
        $answers = $questionData['answers'];
        unset($questionData['answers']);

        return $this->success([
            'question' => $questionData,
            'answers' => $answers
        ]);
    }

    /**
     * Получение данных вопроса
     */
    private function getQuestionData($questionId, $isKnowledgeArea, $randomizeAnswers)
    {
        if ($isKnowledgeArea) {
            $stmt = $this->modx->prepare("
                SELECT q.id, q.question_text, q.question_type, q.question_image, q.test_id,
                       t.title as test_title
                FROM {$this->prefix}test_questions q
                LEFT JOIN {$this->prefix}test_tests t ON t.id = q.test_id
                WHERE q.id = ?
            ");
        } else {
            $stmt = $this->modx->prepare("
                SELECT id, question_text, question_type, question_image
                FROM {$this->prefix}test_questions
                WHERE id = ?
            ");
        }

        $stmt->execute([$questionId]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$question) {
            throw new Exception('Question not found');
        }

        // Получаем ответы
        $sql = "SELECT id, answer_text
                FROM {$this->prefix}test_answers
                WHERE question_id = ?";

        if ($randomizeAnswers) {
            $sql .= " ORDER BY RAND()";
        } else {
            $sql .= " ORDER BY id";
        }

        $stmt = $this->modx->prepare($sql);
        $stmt->execute([$questionId]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $question['answers'] = $answers;

        return $question;
    }

    /**
     * Отправка ответа
     */
    private function submitAnswer($data)
    {
        $sessionId = ValidationHelper::requireInt($data, 'session_id', 'Session ID required');
        $questionId = ValidationHelper::requireInt($data, 'question_id', 'Question ID required');
        $answerIds = $data['answer_ids'] ?? [];

        // Используем SessionService
        $responseData = SessionService::submitAnswer($this->modx, $sessionId, $questionId, $answerIds);

        return $this->success($responseData);
    }

    /**
     * Завершение теста
     */
    private function finishTest($data)
    {
        $sessionId = ValidationHelper::requireInt($data, 'session_id', 'Session ID required');
        error_log("=== finishTest called for session $sessionId ===");

        // Получаем сессию
        $stmt = $this->modx->prepare("
            SELECT s.test_id, s.mode, s.status, s.user_id, t.pass_score
            FROM {$this->prefix}test_sessions s
            JOIN {$this->prefix}test_tests t ON t.id = s.test_id
            WHERE s.id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Session found: " . ($session ? "YES" : "NO"));

        if (!$session) {
            throw new Exception('Session not found');
        }

        // Подсчитываем статистику
        $stmt = $this->modx->prepare("
            SELECT COUNT(*) as total,
                   SUM(is_correct) as correct
            FROM {$this->prefix}test_user_answers
            WHERE session_id = ?
        ");
        $stmt->execute([$sessionId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = (int)$stats['total'];
        $correct = (int)$stats['correct'];
        $score = $total > 0 ? round(($correct / $total) * 100) : 0;
        $passed = $score >= (int)$session['pass_score'];
        error_log("Stats calculated: total=$total, correct=$correct, score=$score, passed=" . ($passed ? 1 : 0));

        // Обновляем сессию
        $tableName = $this->prefix . 'test_sessions';
        error_log("Updating table: $tableName, session_id: $sessionId");
        $stmt = $this->modx->prepare("
            UPDATE {$this->prefix}test_sessions
            SET status = 'completed',
                score = ?,
                passed = ?,
                finished_at = NOW()
            WHERE id = ?
        ");
        $result = $stmt->execute([$score, $passed ? 1 : 0, $sessionId]);
        $rowCount = $stmt->rowCount();
        error_log("UPDATE executed: " . ($result ? "SUCCESS" : "FAILED") . ", rows affected: $rowCount");

        // Обновляем статистику по категориям
        $this->updateCategoryStats($session['test_id'], $session['user_id'], $score, $passed);

        // Начисляем XP (раньше это делал триггер)
        $this->awardXpForCompletion($session['user_id'], $sessionId, $score);

        // Выдаём сертификат если тест пройден
        if ($passed) {
            $this->issueCertificateForTest($session['user_id'], $session['test_id'], $sessionId, $score);
        }

        return $this->success([
            'score' => $score,
            'passed' => $passed,
            'correct_count' => $correct,
            'incorrect_count' => $total - $correct,
            'total_count' => $total,
            'pass_score' => (int)$session['pass_score']
        ]);
    }

    /**
     * Обновление статистики по категориям
     */
    private function updateCategoryStats($testId, $userId, $score, $passed)
    {
        $stmt = $this->modx->prepare("SELECT category_id FROM {$this->prefix}test_tests WHERE id = ?");
        $stmt->execute([$testId]);
        $testData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$testData || empty($testData['category_id'])) {
            return;
        }
        $categoryId = (int)$testData['category_id'];

        // Обновляем статистику
        $stmt = $this->modx->prepare("
            INSERT INTO {$this->prefix}test_category_stats
            (user_id, category_id, tests_completed, tests_passed, avg_score_pct)
            VALUES (?, ?, 1, ?, ?)
            ON DUPLICATE KEY UPDATE
                tests_completed = tests_completed + 1,
                tests_passed = tests_passed + ?,
                avg_score_pct = (avg_score_pct * tests_completed + ?) / (tests_completed + 1)
        ");
        $stmt->execute([
            $userId,
            $categoryId,
            $passed ? 1 : 0,
            $score,
            $passed ? 1 : 0,
            $score
        ]);
    }

    /**
     * Начисление XP за завершение теста
     * (Заменяет функционал триггера trg_session_complete_award_xp)
     */
    private function awardXpForCompletion($userId, $sessionId, $score)
    {
        // Рассчитываем XP по той же логике, что была в триггере
        $baseXp = 10;
        if ($score >= 90) {
            $baseXp = 50;
        } elseif ($score >= 70) {
            $baseXp = 30;
        } elseif ($score >= 50) {
            $baseXp = 20;
        }

        $bonusXp = ($score == 100) ? 25 : 0;
        $totalXp = $baseXp + $bonusXp;

        // Записываем в историю XP
        $stmt = $this->modx->prepare("
            INSERT INTO {$this->prefix}test_xp_history
            (user_id, xp_amount, reason, reference_type, reference_id)
            VALUES (?, ?, ?, 'test', ?)
        ");
        $stmt->execute([
            $userId,
            $totalXp,
            "Test completed: {$score}%",
            $sessionId
        ]);

        // Обновляем общий XP пользователя
        $stmt = $this->modx->prepare("
            INSERT INTO {$this->prefix}test_user_experience (user_id, total_xp)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE total_xp = total_xp + ?
        ");
        $stmt->execute([$userId, $totalXp, $totalXp]);

        // Обновляем streak
        $this->updateUserStreak($userId);
        DatabaseLogicService::syncUserLevel($this->modx, $userId);
    }

    /**
     * Обновление streak пользователя
     * (Заменяет функционал процедуры update_user_streak)
     */
    private function updateUserStreak($userId)
    {
        DatabaseLogicService::updateUserStreak($this->modx, $userId);
    }

    /**
     * Выдача сертификата за прохождение теста
     */
    private function issueCertificateForTest($userId, $testId, $sessionId, $score)
    {
        // Загружаем CertificateService
        $servicePath = MODX_CORE_PATH . 'components/testsystem/services/CertificateService.php';
        if (file_exists($servicePath)) {
            require_once $servicePath;
        } else {
            error_log("CertificateService not found at: $servicePath");
            return false;
        }

        // Получаем название теста
        $stmt = $this->modx->prepare("
            SELECT title FROM {$this->prefix}test_tests WHERE id = ?
        ");
        $stmt->execute([$testId]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$test) {
            error_log("Test not found: $testId");
            return false;
        }

        // Данные для сертификата
        $certificateData = [
            'test_name' => $test['title'],
            'score' => $score,
            'grade' => $this->getGradeByScore($score)
        ];

        // Выдаём сертификат (templateId=1 для тестов)
        $certificateId = CertificateService::issueCertificate(
            $this->modx,
            1, // Template ID для тестов
            $userId,
            'test',
            $testId,
            $certificateData,
            null // issued_by (система)
        );

        if ($certificateId) {
            error_log("Certificate issued: ID=$certificateId for user=$userId, test=$testId, score=$score");
        } else {
            error_log("Failed to issue certificate for user=$userId, test=$testId");
        }

        return $certificateId;
    }

    /**
     * Определение оценки по баллу
     */
    private function getGradeByScore($score)
    {
        if ($score >= 90) return 'Отлично';
        if ($score >= 80) return 'Хорошо';
        if ($score >= 70) return 'Удовлетворительно';
        return 'Зачтено';
    }

    /**
     * Получение информации о существующей сессии
     * Используется для продолжения теста по прямой ссылке с sessionId
     */
    private function getSessionInfo($data)
    {
        $sessionId = ValidationHelper::requireInt($data, 'session_id', 'Session ID required');

        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        // Получаем данные сессии через raw SQL (xPDO-модель TestSession не зарегистрирована)
        $stmt = $this->modx->prepare("
            SELECT s.id, s.user_id, s.test_id, s.mode, s.status, s.question_order,
                   t.title as test_title
            FROM {$this->prefix}test_sessions s
            LEFT JOIN {$this->prefix}test_tests t ON t.id = s.test_id
            WHERE s.id = ?
        ");

        if (!$stmt || !$stmt->execute(array($sessionId))) {
            return $this->error('Session not found', 404);
        }

        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return $this->error('Session not found', 404);
        }

        // Проверяем права доступа
        if ((int)$session['user_id'] !== $userId) {
            return $this->error('Access denied to this session', 403);
        }

        if (!$session['test_title']) {
            return $this->error('Test not found', 404);
        }

        // Подсчитываем общее количество вопросов в сессии
        $questionOrder = json_decode($session['question_order'], true);
        $totalQuestions = is_array($questionOrder) ? count($questionOrder) : 0;

        // Подсчитываем текущий номер вопроса (сколько уже отвечено)
        $stmt = $this->modx->prepare("
            SELECT COUNT(*) as answered_count
            FROM {$this->prefix}test_user_answers
            WHERE session_id = ?
        ");
        $stmt->execute(array($sessionId));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentQuestionNumber = (int)$result['answered_count'];

        return $this->success(array(
            'session_id' => $sessionId,
            'test_id' => (int)$session['test_id'],
            'mode' => $session['mode'],
            'status' => $session['status'],
            'total_questions' => $totalQuestions,
            'current_question_number' => $currentQuestionNumber,
            'test_title' => $session['test_title']
        ));
    }
}
