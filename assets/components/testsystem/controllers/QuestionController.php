<?php
/**
 * Question Controller
 *
 * Контроллер для управления вопросами
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class QuestionController extends BaseController
{
    /**
     * Список доступных действий
     */
    private $actions = [
        'createQuestion',
        'getQuestion',
        'updateQuestion',
        'deleteQuestion',
        'getAllQuestions',
        'getQuestionAnswers',
        'togglePublished',
        'toggleLearning'
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
                case 'createQuestion':
                    return $this->createQuestion($data);

                case 'getQuestion':
                    return $this->getQuestion($data);

                case 'updateQuestion':
                    return $this->updateQuestion($data);

                case 'deleteQuestion':
                    return $this->deleteQuestion($data);

                case 'getAllQuestions':
                    return $this->getAllQuestions($data);

                case 'getQuestionAnswers':
                    return $this->getQuestionAnswers($data);

                case 'togglePublished':
                    return $this->togglePublished($data);

                case 'toggleLearning':
                    return $this->toggleLearning($data);

                default:
                    return $this->error('Action not implemented', 501);
            }
        } catch (AuthenticationException $e) {
            return $this->error($e->getMessage(), 401);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (PermissionException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Создание нового вопроса
     */
    private function createQuestion($data)
    {
        $this->requireEditRights('No permission to create questions');

        $testId = ValidationHelper::requireInt($data, 'test_id', 'Test ID is required');
        $questionText = ValidationHelper::requireString($data, 'question_text', 'Question text is required');
        $questionType = ValidationHelper::optionalString($data, 'question_type', 'single');
        $explanation = ValidationHelper::optionalString($data, 'explanation');
        $questionImage = ValidationHelper::optionalString($data, 'question_image');
        $explanationImage = ValidationHelper::optionalString($data, 'explanation_image');
        $published = ValidationHelper::optionalInt($data, 'published', 1);
        $isLearning = ValidationHelper::optionalInt($data, 'is_learning', 0);
        $answers = ValidationHelper::requireArray($data, 'answers', 2, 'At least 2 answers required');

        $questionType = ValidationHelper::validateQuestionType($questionType);
        ValidationHelper::requireCorrectAnswer($answers);

        try {
            // Получаем максимальный sort_order
            $stmt = $this->modx->prepare("SELECT MAX(sort_order) FROM {$this->prefix}test_questions WHERE test_id = ?");
            $stmt->execute([$testId]);
            $maxSort = (int)$stmt->fetchColumn();
            $newSort = $maxSort + 1;

            // Создаем вопрос
            $sql = "INSERT INTO {$this->prefix}test_questions
                    (test_id, question_text, question_type, explanation, question_image, explanation_image, published, is_learning, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->modx->prepare($sql);

            if (!$stmt) {
                throw new Exception('Database error: failed to prepare statement');
            }

            $executeResult = $stmt->execute([
                $testId,
                $questionText,
                $questionType,
                $explanation,
                $questionImage,
                $explanationImage,
                $published,
                $isLearning,
                $newSort
            ]);

            if (!$executeResult) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception('Database error: ' . $errorInfo[2]);
            }

            $questionId = (int)$this->modx->lastInsertId();

            if ($questionId <= 0) {
                throw new Exception('Failed to get new question ID');
            }

            // Добавляем ответы
            $sortOrder = 1;
            $addedAnswers = 0;

            foreach ($answers as $answer) {
                $answerText = trim($answer['text'] ?? '');
                if (empty($answerText)) {
                    continue;
                }

                $isCorrect = (int)($answer['is_correct'] ?? 0);

                $stmt = $this->modx->prepare("
                    INSERT INTO {$this->prefix}test_answers
                    (question_id, answer_text, is_correct, sort_order)
                    VALUES (?, ?, ?, ?)
                ");

                if (!$stmt) {
                    throw new Exception('Database error: failed to prepare answer insert');
                }

                $answerResult = $stmt->execute([
                    $questionId,
                    $answerText,
                    $isCorrect,
                    $sortOrder
                ]);

                if (!$answerResult) {
                    $errorInfo = $stmt->errorInfo();
                    throw new Exception('Failed to insert answer: ' . $errorInfo[2]);
                }

                $addedAnswers++;
                $sortOrder++;
            }

            if ($addedAnswers < 2) {
                // Откатываем создание вопроса
                $this->modx->prepare("DELETE FROM {$this->prefix}test_questions WHERE id = ?")->execute([$questionId]);
                throw new Exception('Failed to add minimum 2 answers');
            }

            return $this->success(
                ['question_id' => $questionId],
                'Question created successfully'
            );

        } catch (PDOException $e) {
            throw new Exception('Database error: ' . $e->getMessage());
        }
    }

    /**
     * Получение вопроса
     */
    private function getQuestion($data)
    {
        $this->requireEditRights('No permission to edit questions');

        $questionId = ValidationHelper::requireQuestionId($data['question_id'] ?? 0, 'Question ID required');

        $stmt = $this->modx->prepare("
            SELECT id, question_text, question_type, explanation, test_id,
                    question_image, explanation_image, published, is_learning
            FROM {$this->prefix}test_questions
            WHERE id = ?
        ");

        if (!$stmt) {
            throw new Exception('Database error');
        }

        $stmt->execute([$questionId]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$question) {
            throw new Exception('Question not found');
        }

        // IDOR Protection: проверяем право редактировать тест, которому принадлежит вопрос
        if (!PermissionHelper::canEditTest($this->modx, $question['test_id'])) {
            throw new PermissionException('You do not have permission to edit this test');
        }

        $stmt = $this->modx->prepare("
            SELECT id, answer_text, is_correct, sort_order
            FROM {$this->prefix}test_answers
            WHERE question_id = ?
            ORDER BY sort_order
        ");
        $stmt->execute([$questionId]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $question['answers'] = $answers;

        return $this->success($question);
    }

    /**
     * Обновление вопроса
     */
    private function updateQuestion($data)
    {
        $this->requireEditRights('No permission to edit questions');

        $questionId = ValidationHelper::requireQuestionId($data['question_id'] ?? 0, 'Question ID required');
        $questionText = ValidationHelper::requireString($data, 'question_text', 'Question text is required');
        $questionType = ValidationHelper::optionalString($data, 'question_type', 'single');
        $explanation = ValidationHelper::optionalString($data, 'explanation');
        $questionImage = ValidationHelper::optionalString($data, 'question_image');
        $explanationImage = ValidationHelper::optionalString($data, 'explanation_image');
        $published = ValidationHelper::optionalInt($data, 'published', 1);
        $isLearning = ValidationHelper::optionalInt($data, 'is_learning', 0);
        $answers = $data['answers'] ?? [];

        $questionType = ValidationHelper::validateQuestionType($questionType);

        $stmt = $this->modx->prepare("
            UPDATE {$this->prefix}test_questions
            SET question_text = ?, question_type = ?, explanation = ?,
                question_image = ?, explanation_image = ?, published = ?, is_learning = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $questionText,
            $questionType,
            $explanation,
            $questionImage,
            $explanationImage,
            $published,
            $isLearning,
            $questionId
        ]);

        foreach ($answers as $answer) {
            if (empty($answer['id'])) continue;

            $stmt = $this->modx->prepare("
                UPDATE {$this->prefix}test_answers
                SET answer_text = ?, is_correct = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $answer['text'],
                $answer['is_correct'],
                $answer['id']
            ]);
        }

        return $this->success(null, 'Question updated');
    }

    /**
     * Удаление вопроса
     */
    private function deleteQuestion($data)
    {
        $this->requireEditRights('No permission to delete questions');

        $questionId = ValidationHelper::requireQuestionId($data['question_id'] ?? 0, 'Question ID required');
        $sessionId = ValidationHelper::optionalInt($data, 'session_id', 0);

        // IDOR Protection: получаем test_id вопроса и проверяем право на редактирование
        $stmt = $this->modx->prepare("SELECT test_id FROM {$this->prefix}test_questions WHERE id = ?");
        if (!$stmt || !$stmt->execute([$questionId])) {
            throw new Exception('Database error');
        }

        $question = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$question) {
            throw new Exception('Question not found');
        }

        // Проверяем право редактировать тест, которому принадлежит вопрос
        if (!PermissionHelper::canEditTest($this->modx, $question['test_id'])) {
            throw new PermissionException('You do not have permission to delete questions from this test');
        }

        if ($sessionId > 0) {
            $stmt = $this->modx->prepare("SELECT question_order FROM {$this->prefix}test_sessions WHERE id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session) {
                $questionOrder = json_decode($session['question_order'], true);
                $questionOrder = array_values(array_filter($questionOrder, function($qid) use ($questionId) {
                    return $qid != $questionId;
                }));

                $stmt = $this->modx->prepare("UPDATE {$this->prefix}test_sessions SET question_order = ? WHERE id = ?");
                $stmt->execute([json_encode($questionOrder), $sessionId]);
            }
        }

        $stmt = $this->modx->prepare("DELETE FROM {$this->prefix}test_user_answers WHERE question_id = ?");
        $stmt->execute([$questionId]);

        $stmt = $this->modx->prepare("DELETE FROM {$this->prefix}test_answers WHERE question_id = ?");
        $stmt->execute([$questionId]);

        $stmt = $this->modx->prepare("DELETE FROM {$this->prefix}test_questions WHERE id = ?");
        $stmt->execute([$questionId]);

        return $this->success(null, 'Question deleted');
    }

    /**
     * Получение всех вопросов теста
     */
    private function getAllQuestions($data)
    {
        $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');

        // НЕ фильтруем по is_learning здесь, показываем ВСЕ вопросы
        $stmt = $this->modx->prepare("
            SELECT id, question_text, explanation, question_type, published, is_learning
            FROM {$this->prefix}test_questions
            WHERE test_id = ?
            ORDER BY sort_order
        ");
        $stmt->execute([$testId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->success($questions);
    }

    /**
     * Получение ответов на вопрос
     */
    private function getQuestionAnswers($data)
    {
        $questionId = ValidationHelper::requireQuestionId($data['question_id'] ?? 0, 'Question ID required');
        $sessionId = ValidationHelper::requireInt($data, 'session_id', 'Session ID required');

        // Получаем сессию для проверки режима
        $stmt = $this->modx->prepare("
            SELECT s.mode, t.randomize_answers
            FROM {$this->prefix}test_sessions s
            JOIN {$this->prefix}test_tests t ON t.id = s.test_id
            WHERE s.id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            throw new Exception('Session not found');
        }

        // Загружаем ответы
        $sql = "
            SELECT id, answer_text
            FROM {$this->prefix}test_answers
            WHERE question_id = ?
            ORDER BY sort_order
        ";

        $stmt = $this->modx->prepare($sql);
        $stmt->execute([$questionId]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Проверяем, был ли уже дан ответ на этот вопрос
        $stmt = $this->modx->prepare("
            SELECT DISTINCT answer_id, is_correct
            FROM {$this->prefix}test_user_answers
            WHERE session_id = ? AND question_id = ?
        ");
        $stmt->execute([$sessionId, $questionId]);
        $userAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $responseData = [
            'answers' => $answers,
            'user_answered' => count($userAnswers) > 0
        ];

        // Если пользователь уже ответил и режим тренинга - возвращаем feedback
        if (count($userAnswers) > 0 && $session['mode'] === 'training') {
            $userAnswerIds = array_column($userAnswers, 'answer_id');

            // Получаем правильные ответы
            $stmt = $this->modx->prepare("
                SELECT id
                FROM {$this->prefix}test_answers
                WHERE question_id = ? AND is_correct = 1
            ");
            $stmt->execute([$questionId]);
            $correctIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Получаем объяснение
            $stmt = $this->modx->prepare("
                SELECT explanation, explanation_image
                FROM {$this->prefix}test_questions
                WHERE id = ?
            ");
            $stmt->execute([$questionId]);
            $qData = $stmt->fetch(PDO::FETCH_ASSOC);

            $responseData['feedback'] = [
                'user_answer_ids' => array_values(array_unique(array_map('intval', $userAnswerIds))),
                'correct_answer_ids' => array_values(array_map('intval', $correctIds)),
                'explanation' => $qData['explanation'] ?? '',
                'explanation_image' => $qData['explanation_image'] ?? '',
                'is_correct' => (count(array_diff($correctIds, $userAnswerIds)) === 0 && count(array_diff($userAnswerIds, $correctIds)) === 0) ? 1 : 0
            ];
        }

        return $this->success($responseData);
    }

    /**
     * Переключение статуса публикации
     */
    private function togglePublished($data)
    {
        $this->requireEditRights('No permission');

        $questionId = ValidationHelper::requireQuestionId($data['question_id'] ?? 0, 'Question ID required');

        // Получаем текущий статус
        $stmt = $this->modx->prepare("SELECT published FROM {$this->prefix}test_questions WHERE id = ?");
        $stmt->execute([$questionId]);
        $current = (int)$stmt->fetchColumn();

        // Переключаем
        $newStatus = $current ? 0 : 1;
        $stmt = $this->modx->prepare("UPDATE {$this->prefix}test_questions SET published = ? WHERE id = ?");
        $stmt->execute([$newStatus, $questionId]);

        return $this->success(['published' => $newStatus]);
    }

    /**
     * Переключение статуса обучающего материала
     */
    private function toggleLearning($data)
    {
        $this->requireEditRights('No permission');

        $questionId = ValidationHelper::requireQuestionId($data['question_id'] ?? 0, 'Question ID required');

        // Получаем текущий статус
        $stmt = $this->modx->prepare("SELECT is_learning FROM {$this->prefix}test_questions WHERE id = ?");
        $stmt->execute([$questionId]);
        $current = (int)$stmt->fetchColumn();

        // Переключаем
        $newStatus = $current ? 0 : 1;
        $stmt = $this->modx->prepare("UPDATE {$this->prefix}test_questions SET is_learning = ? WHERE id = ?");
        $stmt->execute([$newStatus, $questionId]);

        return $this->success(['is_learning' => $newStatus]);
    }
}
