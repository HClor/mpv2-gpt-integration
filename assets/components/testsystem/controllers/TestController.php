<?php
/**
 * Test Controller
 *
 * Контроллер для управления тестами
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class TestController extends BaseController
{
    /**
     * Список доступных действий
     */
    private $actions = [
        'getTestInfo',
        'getTestSettings',
        'updateTestSettings',
        'updateTest',
        'deleteTest'
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
                case 'getTestInfo':
                    return $this->getTestInfo($data);

                case 'getTestSettings':
                    return $this->getTestSettings($data);

                case 'updateTestSettings':
                    return $this->updateTestSettings($data);

                case 'updateTest':
                    return $this->updateTest($data);

                case 'deleteTest':
                    return $this->deleteTest($data);

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
     * Получение информации о тесте
     */
    private function getTestInfo($data)
    {
        $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');

        $this->requireAuth();

        $userId = $this->getCurrentUserId();

        // Загружаем тест
        $stmt = $this->modx->prepare("
            SELECT id, title, description, mode, time_limit, pass_score,
                   questions_per_session, created_by, publication_status
            FROM {$this->prefix}test_tests
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$testId]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$test) {
            throw new Exception('Test not found');
        }

        // Проверка доступа с использованием PermissionHelper
        $access = PermissionHelper::requireTestAccess($this->modx, $test, 'Access denied');
        $canEdit = $access['canEdit'];

        // Подсчет вопросов
        $stmt = $this->modx->prepare("SELECT COUNT(*) FROM {$this->prefix}test_questions WHERE test_id = ? AND published = 1");
        $stmt->execute([$testId]);
        $test['total_questions'] = (int)$stmt->fetchColumn();
        $test['can_edit'] = $canEdit;

        return $this->success($test);
    }

    /**
     * Получение настроек теста
     */
    private function getTestSettings($data)
    {
        $this->requireEditRights('No permission to edit test settings');

        $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');

        $stmt = $this->modx->prepare("
            SELECT id, title, description, is_active, is_learning_material,
                   mode, time_limit, pass_score, questions_per_session,
                   randomize_questions, randomize_answers
            FROM {$this->prefix}test_tests
            WHERE id = ?
        ");
        $stmt->execute([$testId]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$test) {
            throw new Exception('Test not found');
        }

        return $this->success($test);
    }

    /**
     * Обновление настроек теста
     */
    private function updateTestSettings($data)
    {
        $this->requireEditRights('No permission to edit test settings');

        $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');
        $title = ValidationHelper::requireString($data, 'title', 'Title is required');
        $description = ValidationHelper::optionalString($data, 'description');
        $isActive = ValidationHelper::optionalInt($data, 'is_active', 1);
        $isLearningMaterial = ValidationHelper::optionalInt($data, 'is_learning_material', 0);

        $stmt = $this->modx->prepare("
            UPDATE {$this->prefix}test_tests
            SET title = ?,
                description = ?,
                is_active = ?,
                is_learning_material = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $isActive, $isLearningMaterial, $testId]);

        return $this->success(null, 'Test settings updated');
    }

    /**
     * Обновление теста
     */
    private function updateTest($data)
    {
        $this->requireAuth();

        $userId = $this->getCurrentUserId();

        $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');
        $title = ValidationHelper::requireString($data, 'title', 'Title required');
        $description = ValidationHelper::optionalString($data, 'description');
        $publicationStatus = ValidationHelper::optionalString($data, 'publication_status', 'private');

        // Валидация статуса
        $allowedStatuses = ['draft', 'private', 'unlisted', 'public'];
        if (!in_array($publicationStatus, $allowedStatuses, true)) {
            $publicationStatus = 'private';
        }

        // Проверяем права владельца
        $test = TestRepository::requireTestOwner($this->modx, $testId, $userId, 'Access denied: not test owner');

        // Обновляем тест
        $stmt = $this->modx->prepare("
            UPDATE {$this->prefix}test_tests
            SET title = ?, description = ?, publication_status = ?
            WHERE id = ?
        ");

        if (!$stmt || !$stmt->execute([$title, $description, $publicationStatus, $testId])) {
            throw new Exception('Failed to update test');
        }

        // Обновляем pagetitle страницы если она есть
        if (!empty($test['resource_id'])) {
            $resourceId = (int)$test['resource_id'];
            $stmt = $this->modx->prepare("UPDATE {$this->prefix}site_content SET pagetitle = ? WHERE id = ?");
            $stmt->execute([$title, $resourceId]);
        }

        return $this->success(null, 'Test updated successfully');
    }

    /**
     * Удаление теста
     */
    private function deleteTest($data)
    {
        $this->requireAuth();

        $userId = $this->getCurrentUserId();

        $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Не указан ID теста');

        // Проверяем права владельца и получаем данные теста
        $test = TestRepository::requireTestOwner($this->modx, $testId, $userId, 'У вас нет прав на удаление этого теста');

        // Удаляем тест и все связанные данные
        $success = TestRepository::deleteTest($this->modx, $testId);

        if (!$success) {
            throw new Exception('Произошла ошибка при удалении теста');
        }

        // Удаляем страницу MODX если она существует
        if (!empty($test['resource_id'])) {
            $resourceId = (int)$test['resource_id'];
            $this->modx->exec("DELETE FROM {$this->prefix}site_content WHERE id = {$resourceId}");
        }

        return $this->success(null, 'Тест успешно удален');
    }
}
