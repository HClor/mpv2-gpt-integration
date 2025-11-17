<?php
/**
 * Material Controller
 *
 * Контроллер для управления учебными материалами
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class MaterialController extends BaseController
{
    /**
     * Список доступных действий
     */
    private $actions = [
        'createMaterial',
        'getMaterial',
        'updateMaterial',
        'deleteMaterial',
        'getMaterialsList',
        'addContentBlock',
        'updateContentBlock',
        'deleteContentBlock',
        'addAttachment',
        'deleteAttachment',
        'updateProgress',
        'getUserProgress',
        'setTags',
        'linkTest',
        'unlinkTest'
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
                case 'createMaterial':
                    return $this->createMaterial($data);

                case 'getMaterial':
                    return $this->getMaterial($data);

                case 'updateMaterial':
                    return $this->updateMaterial($data);

                case 'deleteMaterial':
                    return $this->deleteMaterial($data);

                case 'getMaterialsList':
                    return $this->getMaterialsList($data);

                case 'addContentBlock':
                    return $this->addContentBlock($data);

                case 'updateContentBlock':
                    return $this->updateContentBlock($data);

                case 'deleteContentBlock':
                    return $this->deleteContentBlock($data);

                case 'addAttachment':
                    return $this->addAttachment($data);

                case 'deleteAttachment':
                    return $this->deleteAttachment($data);

                case 'updateProgress':
                    return $this->updateProgress($data);

                case 'getUserProgress':
                    return $this->getUserProgress($data);

                case 'setTags':
                    return $this->setTags($data);

                case 'linkTest':
                    return $this->linkTest($data);

                case 'unlinkTest':
                    return $this->unlinkTest($data);

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
     * Создание материала
     */
    private function createMaterial($data)
    {
        $this->requireEditRights('No permission to create materials');

        $title = ValidationHelper::requireString($data, 'title', 'Title is required');
        $description = ValidationHelper::optionalString($data, 'description');
        $categoryId = ValidationHelper::optionalInt($data, 'category_id');
        $contentType = ValidationHelper::optionalString($data, 'content_type', 'text');
        $durationMinutes = ValidationHelper::optionalInt($data, 'duration_minutes');

        $userId = $this->getCurrentUserId();

        $material = LearningMaterialService::createMaterial($this->modx, [
            'title' => $title,
            'description' => $description,
            'category_id' => $categoryId,
            'content_type' => $contentType,
            'created_by' => $userId,
            'duration_minutes' => $durationMinutes
        ]);

        return $this->success($material, 'Material created successfully');
    }

    /**
     * Получение материала
     */
    private function getMaterial($data)
    {
        $materialId = ValidationHelper::requireInt($data, 'material_id', 'Material ID required');
        $includeContent = ValidationHelper::optionalInt($data, 'include_content', 1);

        $material = LearningMaterialService::getMaterialById($this->modx, $materialId, (bool)$includeContent);

        if (!$material) {
            throw new Exception('Material not found');
        }

        // Проверка доступа
        if ($material['status'] !== 'published') {
            // Только автор, админы и эксперты могут видеть неопубликованные материалы
            $this->requireAuth();
            $userId = $this->getCurrentUserId();

            $isAuthor = ($userId == $material['created_by']);
            $hasEditRights = PermissionHelper::getUserRights($this->modx)['canEdit'];

            if (!$isAuthor && !$hasEditRights) {
                throw new PermissionException('Access denied');
            }
        }

        return $this->success($material);
    }

    /**
     * Обновление материала
     */
    private function updateMaterial($data)
    {
        $this->requireEditRights('No permission to update materials');

        $materialId = ValidationHelper::requireInt($data, 'material_id', 'Material ID required');

        // Проверяем существование материала
        $material = LearningMaterialService::getMaterialById($this->modx, $materialId, false);
        if (!$material) {
            throw new Exception('Material not found');
        }

        $updateData = [];

        if (isset($data['title'])) {
            $updateData['title'] = ValidationHelper::requireString($data, 'title', 'Title cannot be empty');
        }

        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }

        if (isset($data['category_id'])) {
            $updateData['category_id'] = $data['category_id'];
        }

        if (isset($data['content_type'])) {
            $updateData['content_type'] = $data['content_type'];
        }

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        if (isset($data['duration_minutes'])) {
            $updateData['duration_minutes'] = $data['duration_minutes'];
        }

        $success = LearningMaterialService::updateMaterial($this->modx, $materialId, $updateData);

        if ($success) {
            return $this->success(null, 'Material updated successfully');
        } else {
            throw new Exception('Failed to update material');
        }
    }

    /**
     * Удаление материала
     */
    private function deleteMaterial($data)
    {
        $this->requireEditRights('No permission to delete materials');

        $materialId = ValidationHelper::requireInt($data, 'material_id', 'Material ID required');

        $success = LearningMaterialService::deleteMaterial($this->modx, $materialId);

        if ($success) {
            return $this->success(null, 'Material deleted successfully');
        } else {
            throw new Exception('Failed to delete material');
        }
    }

    /**
     * Получение списка материалов
     */
    private function getMaterialsList($data)
    {
        $filters = [];

        if (isset($data['category_id'])) {
            $filters['category_id'] = ValidationHelper::requireInt($data, 'category_id', null, false);
        }

        if (isset($data['status'])) {
            $filters['status'] = $data['status'];
        } else {
            // По умолчанию показываем только опубликованные материалы обычным пользователям
            $filters['status'] = 'published';
        }

        if (isset($data['content_type'])) {
            $filters['content_type'] = $data['content_type'];
        }

        if (isset($data['search'])) {
            $filters['search'] = $data['search'];
        }

        if (isset($data['limit'])) {
            $filters['limit'] = ValidationHelper::requireInt($data, 'limit', null, false, 1, 100);
        }

        if (isset($data['offset'])) {
            $filters['offset'] = ValidationHelper::requireInt($data, 'offset', null, false, 0);
        }

        $materials = LearningMaterialService::getMaterialsList($this->modx, $filters);

        return $this->success($materials);
    }

    /**
     * Добавление блока контента
     */
    private function addContentBlock($data)
    {
        $this->requireEditRights('No permission to add content blocks');

        $materialId = ValidationHelper::requireInt($data, 'material_id', 'Material ID required');
        $blockType = ValidationHelper::optionalString($data, 'block_type', 'text');
        $contentHtml = ValidationHelper::optionalString($data, 'content_html', '');
        $contentData = $data['content_data'] ?? null;
        $sortOrder = ValidationHelper::optionalInt($data, 'sort_order', 0);

        $blockId = LearningMaterialService::addContentBlock($this->modx, $materialId, [
            'block_type' => $blockType,
            'content_html' => $contentHtml,
            'content_data' => $contentData ? json_encode($contentData) : null,
            'sort_order' => $sortOrder
        ]);

        return $this->success(['block_id' => $blockId], 'Content block added');
    }

    /**
     * Обновление блока контента
     */
    private function updateContentBlock($data)
    {
        $this->requireEditRights('No permission to update content blocks');

        $blockId = ValidationHelper::requireInt($data, 'block_id', 'Block ID required');

        $updateData = [];

        if (isset($data['block_type'])) {
            $updateData['block_type'] = $data['block_type'];
        }

        if (isset($data['content_html'])) {
            $updateData['content_html'] = $data['content_html'];
        }

        if (isset($data['content_data'])) {
            $updateData['content_data'] = json_encode($data['content_data']);
        }

        if (isset($data['sort_order'])) {
            $updateData['sort_order'] = $data['sort_order'];
        }

        $success = LearningMaterialService::updateContentBlock($this->modx, $blockId, $updateData);

        if ($success) {
            return $this->success(null, 'Content block updated');
        } else {
            throw new Exception('Failed to update content block');
        }
    }

    /**
     * Удаление блока контента
     */
    private function deleteContentBlock($data)
    {
        $this->requireEditRights('No permission to delete content blocks');

        $blockId = ValidationHelper::requireInt($data, 'block_id', 'Block ID required');

        $success = LearningMaterialService::deleteContentBlock($this->modx, $blockId);

        if ($success) {
            return $this->success(null, 'Content block deleted');
        } else {
            throw new Exception('Failed to delete content block');
        }
    }

    /**
     * Добавление вложения
     */
    private function addAttachment($data)
    {
        $this->requireEditRights('No permission to add attachments');

        $materialId = ValidationHelper::requireInt($data, 'material_id', 'Material ID required');
        $title = ValidationHelper::requireString($data, 'title', 'Title required');
        $filePath = ValidationHelper::requireString($data, 'file_path', 'File path required');
        $fileType = $data['file_type'] ?? null;
        $fileSize = $data['file_size'] ?? null;
        $attachmentType = $data['attachment_type'] ?? 'document';
        $isPrimary = ValidationHelper::optionalInt($data, 'is_primary', 0);

        $attachmentId = LearningMaterialService::addAttachment($this->modx, $materialId, [
            'title' => $title,
            'file_path' => $filePath,
            'file_type' => $fileType,
            'file_size' => $fileSize,
            'attachment_type' => $attachmentType,
            'is_primary' => $isPrimary
        ]);

        return $this->success(['attachment_id' => $attachmentId], 'Attachment added');
    }

    /**
     * Удаление вложения
     */
    private function deleteAttachment($data)
    {
        $this->requireEditRights('No permission to delete attachments');

        $attachmentId = ValidationHelper::requireInt($data, 'attachment_id', 'Attachment ID required');

        $success = LearningMaterialService::deleteAttachment($this->modx, $attachmentId);

        if ($success) {
            return $this->success(null, 'Attachment deleted');
        } else {
            throw new Exception('Failed to delete attachment');
        }
    }

    /**
     * Обновление прогресса пользователя
     */
    private function updateProgress($data)
    {
        $this->requireAuth();

        $userId = $this->getCurrentUserId();
        $materialId = ValidationHelper::requireInt($data, 'material_id', 'Material ID required');
        $status = ValidationHelper::optionalString($data, 'status', 'in_progress');
        $progressPct = ValidationHelper::optionalInt($data, 'progress_pct', 0);
        $timeSpent = ValidationHelper::optionalInt($data, 'time_spent_minutes', 0);

        $success = LearningMaterialService::updateProgress($this->modx, $userId, $materialId, [
            'status' => $status,
            'progress_pct' => $progressPct,
            'time_spent_minutes' => $timeSpent
        ]);

        if ($success) {
            return $this->success(null, 'Progress updated');
        } else {
            throw new Exception('Failed to update progress');
        }
    }

    /**
     * Получение прогресса пользователя
     */
    private function getUserProgress($data)
    {
        $this->requireAuth();

        $userId = $this->getCurrentUserId();
        $materialId = ValidationHelper::requireInt($data, 'material_id', 'Material ID required');

        $progress = LearningMaterialService::getUserProgress($this->modx, $userId, $materialId);

        if (!$progress) {
            // Если нет записи о прогрессе, возвращаем начальное состояние
            $progress = [
                'status' => 'not_started',
                'progress_pct' => 0,
                'time_spent_minutes' => 0
            ];
        }

        return $this->success($progress);
    }

    /**
     * Установка тегов
     */
    private function setTags($data)
    {
        $this->requireEditRights('No permission to set tags');

        $materialId = ValidationHelper::requireInt($data, 'material_id', 'Material ID required');
        $tags = $data['tags'] ?? [];

        if (!is_array($tags)) {
            throw new ValidationException('Tags must be an array');
        }

        $success = LearningMaterialService::setTags($this->modx, $materialId, $tags);

        if ($success) {
            return $this->success(null, 'Tags updated');
        } else {
            throw new Exception('Failed to update tags');
        }
    }

    /**
     * Связывание с тестом
     */
    private function linkTest($data)
    {
        $this->requireEditRights('No permission to link tests');

        $materialId = ValidationHelper::requireInt($data, 'material_id', 'Material ID required');
        $testId = ValidationHelper::requireInt($data, 'test_id', 'Test ID required');
        $linkType = ValidationHelper::optionalString($data, 'link_type', 'related');

        $success = LearningMaterialService::linkTest($this->modx, $materialId, $testId, $linkType);

        if ($success) {
            return $this->success(null, 'Test linked');
        } else {
            throw new Exception('Failed to link test');
        }
    }

    /**
     * Удаление связи с тестом
     */
    private function unlinkTest($data)
    {
        $this->requireEditRights('No permission to unlink tests');

        $materialId = ValidationHelper::requireInt($data, 'material_id', 'Material ID required');
        $testId = ValidationHelper::requireInt($data, 'test_id', 'Test ID required');

        $success = LearningMaterialService::unlinkTest($this->modx, $materialId, $testId);

        if ($success) {
            return $this->success(null, 'Test unlinked');
        } else {
            throw new Exception('Failed to unlink test');
        }
    }
}
