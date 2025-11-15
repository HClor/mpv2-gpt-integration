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
        'finishTest'
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

        // Находим следующий неотвеченный вопрос
        $nextQuestionId = null;
        foreach ($questionOrder as $qid) {
            if (!in_array($qid, $answeredIds)) {
                $nextQuestionId = $qid;
                break;
            }
        }

        if (!$nextQuestionId) {
            return $this->success(['finished' => true]);
        }

        // Получаем вопрос с ответами
        $isKnowledgeArea = ((int)$session['test_id'] === -1);
        $questionData = $this->getQuestionData($nextQuestionId, $isKnowledgeArea, (bool)$session['randomize_answers']);

        return $this->success($questionData);
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

        // Получаем сессию
        $stmt = $this->modx->prepare("
            SELECT s.test_id, s.mode, s.status, s.user_id, t.pass_score
            FROM {$this->prefix}test_sessions s
            JOIN {$this->prefix}test_tests t ON t.id = s.test_id
            WHERE s.id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

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

        // Обновляем сессию
        $stmt = $this->modx->prepare("
            UPDATE {$this->prefix}test_sessions
            SET status = 'completed',
                score = ?,
                passed = ?,
                completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$score, $passed ? 1 : 0, $sessionId]);

        // Обновляем статистику по категориям
        $this->updateCategoryStats($session['test_id'], $session['user_id'], $score, $passed);

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
        $stmt = $this->modx->prepare("SELECT resource_id FROM {$this->prefix}test_tests WHERE id = ?");
        $stmt->execute([$testId]);
        $testData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$testData || !$testData['resource_id']) {
            return;
        }

        // Получаем категорию
        $stmt = $this->modx->prepare("SELECT parent FROM {$this->prefix}site_content WHERE id = ?");
        $stmt->execute([$testData['resource_id']]);
        $resData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resData || !$resData['parent']) {
            return;
        }

        $categoryId = (int)$resData['parent'];

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
}
