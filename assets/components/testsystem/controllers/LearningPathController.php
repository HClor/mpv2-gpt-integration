<?php
/**
 * Learning Path Controller
 *
 * Контроллер для управления траекториями обучения
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class LearningPathController extends BaseController
{
    /**
     * Список доступных действий
     */
    private $actions = [
        'createPath',
        'getPath',
        'updatePath',
        'deletePath',
        'addStep',
        'updateStep',
        'deleteStep',
        'reorderSteps',
        'enrollOnPath',
        'unenrollFromPath',
        'getMyPaths',
        'getPathProgress',
        'completePathStep',
        'getNextPathStep',
        'getPathsList',
        'bulkEnrollOnPath',
        'getPathStatistics'
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
                case 'createPath':
                    return $this->createPath($data);

                case 'getPath':
                    return $this->getPath($data);

                case 'updatePath':
                    return $this->updatePath($data);

                case 'deletePath':
                    return $this->deletePath($data);

                case 'addStep':
                    return $this->addStep($data);

                case 'updateStep':
                    return $this->updateStep($data);

                case 'deleteStep':
                    return $this->deleteStep($data);

                case 'reorderSteps':
                    return $this->reorderSteps($data);

                case 'enrollOnPath':
                    return $this->enrollOnPath($data);

                case 'unenrollFromPath':
                    return $this->unenrollFromPath($data);

                case 'getMyPaths':
                    return $this->getMyPaths($data);

                case 'getPathProgress':
                    return $this->getPathProgress($data);

                case 'completePathStep':
                    return $this->completePathStep($data);

                case 'getNextPathStep':
                    return $this->getNextPathStep($data);

                case 'getPathsList':
                    return $this->getPathsList($data);

                case 'bulkEnrollOnPath':
                    return $this->bulkEnrollOnPath($data);

                case 'getPathStatistics':
                    return $this->getPathStatistics($data);

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
     * Создание траектории обучения
     */
    private function createPath($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();

        // Проверка прав: создавать траектории могут только эксперты и админы
        if (!CategoryPermissionService::isGlobalExpert($this->modx, $currentUserId) &&
            !CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId)) {
            throw new PermissionException('Only experts and admins can create learning paths');
        }

        // Валидация
        $name = ValidationHelper::requireString($data, 'name', 'Path name required');
        $description = ValidationHelper::optionalString($data, 'description');
        $categoryId = ValidationHelper::optionalInt($data, 'category_id');
        $status = ValidationHelper::optionalString($data, 'status', 'draft');
        $isPublic = ValidationHelper::optionalInt($data, 'is_public', 0);
        $difficultyLevel = ValidationHelper::optionalString($data, 'difficulty_level', 'beginner');
        $estimatedHours = ValidationHelper::optionalInt($data, 'estimated_hours');
        $passingScore = ValidationHelper::optionalInt($data, 'passing_score', 70);

        // Валидация статуса
        if (!in_array($status, ['draft', 'published', 'archived'])) {
            throw new ValidationException('Invalid status');
        }

        // Валидация уровня сложности
        if (!in_array($difficultyLevel, ['beginner', 'intermediate', 'advanced', 'expert'])) {
            throw new ValidationException('Invalid difficulty level');
        }

        // Если указана категория, проверяем права
        if ($categoryId) {
            $canCreate = CategoryPermissionService::canCreateContent($this->modx, $categoryId, $currentUserId);
            if (!$canCreate) {
                throw new PermissionException('No permission to create paths in this category');
            }
        }

        $pathId = LearningPathService::createPath($this->modx, [
            'name' => $name,
            'description' => $description,
            'category_id' => $categoryId,
            'created_by' => $currentUserId,
            'status' => $status,
            'is_public' => $isPublic,
            'difficulty_level' => $difficultyLevel,
            'estimated_hours' => $estimatedHours,
            'passing_score' => $passingScore
        ]);

        return $this->success(['path_id' => $pathId], 'Learning path created successfully');
    }

    /**
     * Получение траектории
     */
    private function getPath($data)
    {
        $pathId = ValidationHelper::requireInt($data, 'path_id', 'Path ID required');
        $withSteps = ValidationHelper::optionalInt($data, 'with_steps', 1);

        $path = LearningPathService::getPath($this->modx, $pathId, (bool)$withSteps);

        if (!$path) {
            throw new Exception('Learning path not found');
        }

        // Проверка доступа
        if ($path['status'] !== 'published' && !$path['is_public']) {
            $this->requireAuth();
            $currentUserId = $this->getCurrentUserId();

            $isAuthor = (int)$path['created_by'] === $currentUserId;
            $isAdmin = CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId);

            $canView = $isAuthor || $isAdmin;

            // Проверяем права по категории
            if (!$canView && $path['category_id']) {
                $canView = CategoryPermissionService::canViewStats(
                    $this->modx,
                    $path['category_id'],
                    $currentUserId
                );
            }

            if (!$canView) {
                throw new PermissionException('No permission to view this learning path');
            }
        }

        return $this->success($path);
    }

    /**
     * Обновление траектории
     */
    private function updatePath($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $pathId = ValidationHelper::requireInt($data, 'path_id', 'Path ID required');

        // Проверяем права
        $path = LearningPathService::getPath($this->modx, $pathId, false);

        if (!$path) {
            throw new Exception('Learning path not found');
        }

        $isAuthor = (int)$path['created_by'] === $currentUserId;
        $isAdmin = CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId);

        $canEdit = $isAuthor || $isAdmin;

        if (!$canEdit && $path['category_id']) {
            $canEdit = CategoryPermissionService::canCreateContent(
                $this->modx,
                $path['category_id'],
                $currentUserId
            );
        }

        if (!$canEdit) {
            throw new PermissionException('No permission to edit this learning path');
        }

        // Валидация статуса
        if (isset($data['status']) && !in_array($data['status'], ['draft', 'published', 'archived'])) {
            throw new ValidationException('Invalid status');
        }

        // Валидация уровня сложности
        if (isset($data['difficulty_level']) &&
            !in_array($data['difficulty_level'], ['beginner', 'intermediate', 'advanced', 'expert'])) {
            throw new ValidationException('Invalid difficulty level');
        }

        $success = LearningPathService::updatePath($this->modx, $pathId, $data);

        if ($success) {
            return $this->success(null, 'Learning path updated successfully');
        } else {
            throw new Exception('Failed to update learning path');
        }
    }

    /**
     * Удаление траектории
     */
    private function deletePath($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $pathId = ValidationHelper::requireInt($data, 'path_id', 'Path ID required');

        // Проверяем права
        $path = LearningPathService::getPath($this->modx, $pathId, false);

        if (!$path) {
            throw new Exception('Learning path not found');
        }

        $isAuthor = (int)$path['created_by'] === $currentUserId;
        $isAdmin = CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId);

        if (!$isAuthor && !$isAdmin) {
            throw new PermissionException('Only path author or admin can delete learning paths');
        }

        $success = LearningPathService::deletePath($this->modx, $pathId);

        if ($success) {
            return $this->success(null, 'Learning path deleted successfully');
        } else {
            throw new Exception('Failed to delete learning path');
        }
    }

    /**
     * Добавление шага в траекторию
     */
    private function addStep($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $pathId = ValidationHelper::requireInt($data, 'path_id', 'Path ID required');

        // Проверяем права на редактирование траектории
        $path = LearningPathService::getPath($this->modx, $pathId, false);

        if (!$path) {
            throw new Exception('Learning path not found');
        }

        $isAuthor = (int)$path['created_by'] === $currentUserId;
        $isAdmin = CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId);

        if (!$isAuthor && !$isAdmin) {
            throw new PermissionException('No permission to edit this learning path');
        }

        // Валидация
        $stepType = ValidationHelper::requireString($data, 'step_type', 'Step type required');
        $itemId = ValidationHelper::requireInt($data, 'item_id', 'Item ID required');
        $name = ValidationHelper::requireString($data, 'name', 'Step name required');

        if (!in_array($stepType, ['material', 'test', 'quiz', 'assignment'])) {
            throw new ValidationException('Invalid step type');
        }

        $stepData = [
            'step_type' => $stepType,
            'item_id' => $itemId,
            'name' => $name,
            'description' => ValidationHelper::optionalString($data, 'description'),
            'is_required' => ValidationHelper::optionalInt($data, 'is_required', 1),
            'unlock_condition' => $data['unlock_condition'] ?? null,
            'min_score' => ValidationHelper::optionalInt($data, 'min_score'),
            'estimated_minutes' => ValidationHelper::optionalInt($data, 'estimated_minutes')
        ];

        $stepId = LearningPathService::addStep($this->modx, $pathId, $stepData);

        return $this->success(['step_id' => $stepId], 'Step added successfully');
    }

    /**
     * Обновление шага
     */
    private function updateStep($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $stepId = ValidationHelper::requireInt($data, 'step_id', 'Step ID required');

        // Получаем шаг и проверяем права через траекторию
        $prefix = $this->modx->getOption('table_prefix', null, 'modx_');
        $stmt = $this->modx->prepare("SELECT path_id FROM {$prefix}test_learning_path_steps WHERE id = ?");
        $stmt->execute([$stepId]);
        $pathId = $stmt->fetchColumn();

        if (!$pathId) {
            throw new Exception('Step not found');
        }

        $path = LearningPathService::getPath($this->modx, $pathId, false);
        $isAuthor = (int)$path['created_by'] === $currentUserId;
        $isAdmin = CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId);

        if (!$isAuthor && !$isAdmin) {
            throw new PermissionException('No permission to edit this learning path');
        }

        // Валидация step_type
        if (isset($data['step_type']) && !in_array($data['step_type'], ['material', 'test', 'quiz', 'assignment'])) {
            throw new ValidationException('Invalid step type');
        }

        $success = LearningPathService::updateStep($this->modx, $stepId, $data);

        if ($success) {
            return $this->success(null, 'Step updated successfully');
        } else {
            throw new Exception('Failed to update step');
        }
    }

    /**
     * Удаление шага
     */
    private function deleteStep($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $stepId = ValidationHelper::requireInt($data, 'step_id', 'Step ID required');

        // Получаем шаг и проверяем права
        $prefix = $this->modx->getOption('table_prefix', null, 'modx_');
        $stmt = $this->modx->prepare("SELECT path_id FROM {$prefix}test_learning_path_steps WHERE id = ?");
        $stmt->execute([$stepId]);
        $pathId = $stmt->fetchColumn();

        if (!$pathId) {
            throw new Exception('Step not found');
        }

        $path = LearningPathService::getPath($this->modx, $pathId, false);
        $isAuthor = (int)$path['created_by'] === $currentUserId;
        $isAdmin = CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId);

        if (!$isAuthor && !$isAdmin) {
            throw new PermissionException('No permission to edit this learning path');
        }

        $success = LearningPathService::deleteStep($this->modx, $stepId);

        if ($success) {
            return $this->success(null, 'Step deleted successfully');
        } else {
            throw new Exception('Failed to delete step');
        }
    }

    /**
     * Изменение порядка шагов
     */
    private function reorderSteps($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $pathId = ValidationHelper::requireInt($data, 'path_id', 'Path ID required');
        $stepOrder = ValidationHelper::requireArray($data, 'step_order', 1, 'Step order required');

        // Проверяем права
        $path = LearningPathService::getPath($this->modx, $pathId, false);
        $isAuthor = (int)$path['created_by'] === $currentUserId;
        $isAdmin = CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId);

        if (!$isAuthor && !$isAdmin) {
            throw new PermissionException('No permission to edit this learning path');
        }

        LearningPathService::reorderSteps($this->modx, $pathId, $stepOrder);

        return $this->success(null, 'Steps reordered successfully');
    }

    /**
     * Запись пользователя на траекторию
     */
    private function enrollOnPath($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $pathId = ValidationHelper::requireInt($data, 'path_id', 'Path ID required');

        // Проверяем доступность траектории
        $path = LearningPathService::getPath($this->modx, $pathId, false);

        if (!$path) {
            throw new Exception('Learning path not found');
        }

        // Публичные траектории доступны всем
        if ($path['status'] !== 'published' && !$path['is_public']) {
            throw new PermissionException('Learning path is not available for enrollment');
        }

        // Проверяем, не записан ли уже
        if (LearningPathService::isUserEnrolled($this->modx, $pathId, $currentUserId)) {
            throw new Exception('Already enrolled on this path');
        }

        $enrollmentId = LearningPathService::enrollUser($this->modx, $pathId, $currentUserId);

        if ($enrollmentId) {
            return $this->success(['enrollment_id' => $enrollmentId], 'Enrolled successfully');
        } else {
            throw new Exception('Failed to enroll');
        }
    }

    /**
     * Отмена записи с траектории
     */
    private function unenrollFromPath($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $pathId = ValidationHelper::requireInt($data, 'path_id', 'Path ID required');

        $success = LearningPathService::unenrollUser($this->modx, $pathId, $currentUserId);

        if ($success) {
            return $this->success(null, 'Unenrolled successfully');
        } else {
            throw new Exception('Failed to unenroll');
        }
    }

    /**
     * Получение моих траекторий (на которые я записан)
     */
    private function getMyPaths($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $status = ValidationHelper::optionalString($data, 'status');

        $paths = LearningPathService::getEnrolledPaths($this->modx, $currentUserId, $status);

        return $this->success($paths);
    }

    /**
     * Получение прогресса по траектории
     */
    private function getPathProgress($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $pathId = ValidationHelper::requireInt($data, 'path_id', 'Path ID required');

        // Проверяем, записан ли пользователь
        if (!LearningPathService::isUserEnrolled($this->modx, $pathId, $currentUserId)) {
            throw new PermissionException('Not enrolled on this path');
        }

        $progress = LearningPathService::getUserProgress($this->modx, $pathId, $currentUserId);

        if (!$progress) {
            throw new Exception('Progress not found');
        }

        return $this->success($progress);
    }

    /**
     * Завершение шага траектории
     */
    private function completePathStep($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $pathId = ValidationHelper::requireInt($data, 'path_id', 'Path ID required');
        $stepId = ValidationHelper::requireInt($data, 'step_id', 'Step ID required');

        // Получаем прогресс
        $progress = LearningPathService::getUserProgress($this->modx, $pathId, $currentUserId);

        if (!$progress) {
            throw new Exception('Not enrolled on this path');
        }

        // Проверяем доступ к шагу
        if (!LearningPathService::canAccessStep($this->modx, $progress['id'], $stepId)) {
            throw new PermissionException('Step is not available yet');
        }

        $completionData = [
            'score' => ValidationHelper::optionalInt($data, 'score'),
            'session_id' => ValidationHelper::optionalInt($data, 'session_id'),
            'material_progress_id' => ValidationHelper::optionalInt($data, 'material_progress_id')
        ];

        $success = LearningPathService::completeStep($this->modx, $progress['id'], $stepId, $completionData);

        if ($success) {
            return $this->success(null, 'Step completed successfully');
        } else {
            throw new Exception('Failed to complete step');
        }
    }

    /**
     * Получение следующего доступного шага
     */
    private function getNextPathStep($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $pathId = ValidationHelper::requireInt($data, 'path_id', 'Path ID required');

        // Получаем прогресс
        $progress = LearningPathService::getUserProgress($this->modx, $pathId, $currentUserId);

        if (!$progress) {
            throw new Exception('Not enrolled on this path');
        }

        $nextStep = LearningPathService::getNextStep($this->modx, $progress['id']);

        if (!$nextStep) {
            return $this->success(['finished' => true], 'All steps completed');
        }

        return $this->success($nextStep);
    }

    /**
     * Получение списка траекторий
     */
    private function getPathsList($data)
    {
        $filters = [];

        if (isset($data['category_id'])) {
            $filters['category_id'] = ValidationHelper::requireInt($data, 'category_id');
        }

        if (isset($data['status'])) {
            $filters['status'] = $data['status'];
        }

        if (isset($data['difficulty_level'])) {
            $filters['difficulty_level'] = $data['difficulty_level'];
        }

        if (isset($data['is_public'])) {
            $filters['is_public'] = (int)$data['is_public'];
        }

        // Если не указан статус, показываем только published для не-админов
        if (!isset($filters['status'])) {
            if ($this->isAuthenticated()) {
                $currentUserId = $this->getCurrentUserId();
                $isAdmin = CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId);

                if (!$isAdmin) {
                    $filters['status'] = 'published';
                }
            } else {
                $filters['status'] = 'published';
            }
        }

        $paths = LearningPathService::getPathsList($this->modx, $filters);

        return $this->success($paths);
    }

    /**
     * Массовая запись пользователей на траекторию
     */
    private function bulkEnrollOnPath($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $pathId = ValidationHelper::requireInt($data, 'path_id', 'Path ID required');
        $userIds = ValidationHelper::requireArray($data, 'user_ids', 1, 'User IDs required');

        // Проверяем права на траекторию
        $path = LearningPathService::getPath($this->modx, $pathId, false);

        if (!$path) {
            throw new Exception('Learning path not found');
        }

        $isAuthor = (int)$path['created_by'] === $currentUserId;
        $isAdmin = CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId);

        $canEnrollOthers = $isAuthor || $isAdmin;

        if (!$canEnrollOthers && $path['category_id']) {
            $canEnrollOthers = CategoryPermissionService::canManageCategory(
                $this->modx,
                $path['category_id'],
                $currentUserId
            );
        }

        if (!$canEnrollOthers) {
            throw new PermissionException('No permission to enroll users on this path');
        }

        $count = LearningPathService::bulkEnroll($this->modx, $pathId, $userIds, $currentUserId);

        return $this->success(
            ['enrolled_count' => $count, 'total_users' => count($userIds)],
            "{$count} users enrolled successfully"
        );
    }

    /**
     * Получение статистики по траектории
     */
    private function getPathStatistics($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $pathId = ValidationHelper::requireInt($data, 'path_id', 'Path ID required');

        // Проверяем права на просмотр статистики
        $path = LearningPathService::getPath($this->modx, $pathId, false);

        if (!$path) {
            throw new Exception('Learning path not found');
        }

        $isAuthor = (int)$path['created_by'] === $currentUserId;
        $isAdmin = CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId);

        $canViewStats = $isAuthor || $isAdmin;

        if (!$canViewStats && $path['category_id']) {
            $canViewStats = CategoryPermissionService::canViewStats(
                $this->modx,
                $path['category_id'],
                $currentUserId
            );
        }

        if (!$canViewStats) {
            throw new PermissionException('No permission to view statistics');
        }

        $stats = LearningPathService::getPathStatistics($this->modx, $pathId);

        return $this->success($stats);
    }
}
