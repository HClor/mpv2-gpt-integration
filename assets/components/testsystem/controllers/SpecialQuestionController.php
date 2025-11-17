<?php
/**
 * Special Question Controller
 *
 * Контроллер для работы с расширенными типами вопросов
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class SpecialQuestionController extends BaseController
{
    /**
     * Список доступных действий
     */
    private $actions = [
        'getQuestionTypeData',
        'reviewEssay',
        'getEssaysForReview',
        'getMyEssays'
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
                case 'getQuestionTypeData':
                    return $this->getQuestionTypeData($data);

                case 'reviewEssay':
                    return $this->reviewEssay($data);

                case 'getEssaysForReview':
                    return $this->getEssaysForReview($data);

                case 'getMyEssays':
                    return $this->getMyEssays($data);

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
     * Получение данных вопроса в зависимости от типа
     */
    private function getQuestionTypeData($data)
    {
        $questionId = ValidationHelper::requireInt($data, 'question_id', 'Question ID required');

        // Получаем тип вопроса
        $prefix = $this->modx->getOption('table_prefix', null, 'modx_');
        $stmt = $this->modx->prepare("SELECT question_type FROM {$prefix}test_questions WHERE id = ?");
        $stmt->execute([$questionId]);
        $questionType = $stmt->fetchColumn();

        if (!$questionType) {
            throw new Exception('Question not found');
        }

        // Для специальных типов возвращаем дополнительные данные
        $typeData = QuestionTypeService::getQuestionTypeData($this->modx, $questionId, $questionType);

        return $this->success([
            'question_type' => $questionType,
            'type_data' => $typeData
        ]);
    }

    /**
     * Ручная проверка эссе (только для экспертов и админов)
     */
    private function reviewEssay($data)
    {
        $this->requireAuth();
        $this->requireEditRights('Only experts can review essays');

        $currentUserId = $this->getCurrentUserId();
        $reviewId = ValidationHelper::requireInt($data, 'review_id', 'Review ID required');
        $score = ValidationHelper::requireInt($data, 'score', 'Score required', true, 0);
        $comment = ValidationHelper::optionalString($data, 'comment');

        // Получаем review для проверки прав
        $prefix = $this->modx->getOption('table_prefix', null, 'modx_');
        $stmt = $this->modx->prepare("SELECT er.*, q.test_id
                                      FROM {$prefix}test_essay_reviews er
                                      JOIN {$prefix}test_questions q ON q.id = er.question_id
                                      WHERE er.id = ?");
        $stmt->execute([$reviewId]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$review) {
            throw new Exception('Essay review not found');
        }

        // Проверяем права на тест
        $testId = $review['test_id'];
        $canEdit = PermissionHelper::canEditTest($this->modx, $testId);

        if (!$canEdit) {
            throw new PermissionException('No permission to review this essay');
        }

        $success = QuestionTypeService::reviewEssay($this->modx, $reviewId, $currentUserId, $score, $comment);

        if ($success) {
            return $this->success(null, 'Essay reviewed successfully');
        } else {
            throw new Exception('Failed to review essay');
        }
    }

    /**
     * Получение списка эссе для проверки (для экспертов и админов)
     */
    private function getEssaysForReview($data)
    {
        $this->requireAuth();
        $this->requireEditRights('Only experts can review essays');

        $filters = [];

        if (isset($data['status'])) {
            $filters['status'] = $data['status'];
        }

        if (isset($data['test_id'])) {
            $filters['test_id'] = ValidationHelper::requireInt($data, 'test_id');
        }

        // Если не админ, показываем только эссе по тестам, которые может редактировать
        $currentUserId = $this->getCurrentUserId();
        $isAdmin = CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId);

        $essays = QuestionTypeService::getEssaysForReview($this->modx, $filters);

        // Фильтруем по правам, если не админ
        if (!$isAdmin) {
            $essays = array_filter($essays, function($essay) {
                return PermissionHelper::canEditTest($this->modx, $essay['test_id']);
            });
        }

        return $this->success($essays);
    }

    /**
     * Получение моих эссе (для студентов)
     */
    private function getMyEssays($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $prefix = $this->modx->getOption('table_prefix', null, 'modx_');

        $where = ['er.user_id = ?'];
        $params = [$currentUserId];

        if (isset($data['status'])) {
            $where[] = 'er.status = ?';
            $params[] = $data['status'];
        }

        $sql = "SELECT er.*, q.question_text, t.title as test_title,
                       qe.max_score, u.username as reviewer_name
                FROM {$prefix}test_essay_reviews er
                JOIN {$prefix}test_questions q ON q.id = er.question_id
                JOIN {$prefix}test_sessions ts ON ts.id = er.session_id
                JOIN {$prefix}test_tests t ON t.id = ts.test_id
                LEFT JOIN {$prefix}test_question_essays qe ON qe.question_id = er.question_id
                LEFT JOIN {$prefix}users u ON u.id = er.reviewer_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY er.submitted_at DESC";

        $stmt = $this->modx->prepare($sql);
        $stmt->execute($params);
        $essays = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->success($essays);
    }
}
