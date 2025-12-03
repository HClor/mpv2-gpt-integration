<?php
/**
 * Learning Material Service
 *
 * Сервис для работы с учебными материалами
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class LearningMaterialService
{
    /**
     * Создание нового материала
     *
     * @param modX $modx
     * @param array $data Данные материала
     * @return array
     */
    public static function createMaterial($modx, $data)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $title = $data['title'] ?? '';
        $description = $data['description'] ?? '';
        $categoryId = $data['category_id'] ?? null;
        $contentType = $data['content_type'] ?? 'text';
        $createdBy = $data['created_by'];
        $durationMinutes = $data['duration_minutes'] ?? null;

        $sql = "INSERT INTO {$prefix}test_learning_materials
                (title, description, category_id, content_type, created_by, duration_minutes, status)
                VALUES (?, ?, ?, ?, ?, ?, 'draft')";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$title, $description, $categoryId, $contentType, $createdBy, $durationMinutes]);

        $materialId = (int)$modx->lastInsertId();

        return [
            'id' => $materialId,
            'title' => $title,
            'status' => 'draft'
        ];
    }

    /**
     * Получение материала по ID
     *
     * @param modX $modx
     * @param int $materialId
     * @param bool $includeContent Включить контент и вложения
     * @return array|null
     */
    public static function getMaterialById($modx, $materialId, $includeContent = true)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT m.*, u.username as author_name
                FROM {$prefix}test_learning_materials m
                LEFT JOIN {$prefix}users u ON u.id = m.created_by
                WHERE m.id = ?";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$materialId]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$material) {
            return null;
        }

        if ($includeContent) {
            // Получаем блоки контента
            $material['content_blocks'] = self::getContentBlocks($modx, $materialId);

            // Получаем вложения
            $material['attachments'] = self::getAttachments($modx, $materialId);

            // Получаем теги
            $material['tags'] = self::getTags($modx, $materialId);

            // Получаем связанные тесты
            $material['linked_tests'] = self::getLinkedTests($modx, $materialId);
        }

        return $material;
    }

    /**
     * Обновление материала
     *
     * @param modX $modx
     * @param int $materialId
     * @param array $data
     * @return bool
     */
    public static function updateMaterial($modx, $materialId, $data)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $fields = [];
        $values = [];

        if (isset($data['title'])) {
            $fields[] = 'title = ?';
            $values[] = $data['title'];
        }

        if (isset($data['description'])) {
            $fields[] = 'description = ?';
            $values[] = $data['description'];
        }

        if (isset($data['category_id'])) {
            $fields[] = 'category_id = ?';
            $values[] = $data['category_id'];
        }

        if (isset($data['content_type'])) {
            $fields[] = 'content_type = ?';
            $values[] = $data['content_type'];
        }

        if (isset($data['status'])) {
            $fields[] = 'status = ?';
            $values[] = $data['status'];

            // При публикации устанавливаем published_at
            if ($data['status'] === 'published') {
                $fields[] = 'published_at = NOW()';
            }
        }

        if (isset($data['duration_minutes'])) {
            $fields[] = 'duration_minutes = ?';
            $values[] = $data['duration_minutes'];
        }

        if (empty($fields)) {
            return true;
        }

        $values[] = $materialId;

        $sql = "UPDATE {$prefix}test_learning_materials
                SET " . implode(', ', $fields) . "
                WHERE id = ?";

        $stmt = $modx->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Удаление материала
     *
     * @param modX $modx
     * @param int $materialId
     * @return bool
     */
    public static function deleteMaterial($modx, $materialId)
    {
        // ИСПРАВЛЕНО: Soft delete из site_content (учебные материалы теперь MODX ресурсы)
        // Используем прямой SQL т.к. MODX ORM блокирует изменение поля deleted из web контекста

        require_once MODX_CORE_PATH . 'components/testsystem/helpers/PermissionHelper.php';

        $prefix = $modx->getOption('table_prefix', null, 'modx_');
        $userId = PermissionHelper::getCurrentUserIdWithMgr($modx);
        $now = time();

        $stmt = $modx->prepare("
            UPDATE {$prefix}site_content
            SET deleted = 1,
                deletedon = :deletedon,
                deletedby = :deletedby
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $materialId,
            ':deletedon' => $now,
            ':deletedby' => $userId
        ]);
    }

    /**
     * Получение списка материалов (MODX ресурсы с template=6)
     *
     * @param modX $modx
     * @param array $filters
     * @return array
     */
    public static function getMaterialsList($modx, $filters = [])
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $templateId = 6; // Template "LMS Bootstrap 5 - учебные материалы"
        $parentId = $filters['parent_id'] ?? 0;

        $sql = "
            SELECT
                id, pagetitle, longtitle, description, introtext,
                parent, createdby, createdon, publishedon, published, alias
            FROM {$prefix}site_content
            WHERE template = ?
                AND deleted = 0
        ";

        $params = [$templateId];

        if ($parentId > 0) {
            $sql .= " AND parent = ?";
            $params[] = $parentId;
        }

        $sql .= " ORDER BY menuindex ASC, createdon DESC";

        $stmt = $modx->prepare($sql);
        $stmt->execute($params);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Проверяем права редактирования (только для авторизованных)
        require_once MODX_CORE_PATH . 'components/testsystem/helpers/PermissionHelper.php';

        $isAuthenticated = PermissionHelper::isAuthenticated($modx);
        $userId = $isAuthenticated ? PermissionHelper::getCurrentUserId($modx) : 0;
        $isAdmin = $isAuthenticated ? PermissionHelper::isAdmin($modx) : false;

        foreach ($materials as &$material) {
            $material['can_edit'] = $isAuthenticated &&
                ((int)$material['createdby'] === $userId || $isAdmin);

            // Генерируем URL с полным путем
            $url = $modx->makeUrl($material['id'], '', '', 'full');
            if (empty($url) || strpos($url, 'http') === false) {
                // Резервный вариант: используем alias с site_url
                $siteUrl = rtrim($modx->getOption('site_url'), '/');
                $alias = ltrim($material['alias'], '/');
                $material['url'] = $siteUrl . '/' . $alias;
            } else {
                $material['url'] = $url;
            }
        }

        return $materials;
    }

    /**
     * Добавление блока контента
     *
     * @param modX $modx
     * @param int $materialId
     * @param array $blockData
     * @return int Block ID
     */
    public static function addContentBlock($modx, $materialId, $blockData)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $blockType = $blockData['block_type'] ?? 'text';
        $contentHtml = $blockData['content_html'] ?? '';
        $contentData = $blockData['content_data'] ?? null;
        $sortOrder = $blockData['sort_order'] ?? 0;

        $sql = "INSERT INTO {$prefix}test_learning_content
                (material_id, block_type, content_html, content_data, sort_order)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$materialId, $blockType, $contentHtml, $contentData, $sortOrder]);

        return (int)$modx->lastInsertId();
    }

    /**
     * Получение блоков контента материала
     *
     * @param modX $modx
     * @param int $materialId
     * @return array
     */
    public static function getContentBlocks($modx, $materialId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT * FROM {$prefix}test_learning_content
                WHERE material_id = ?
                ORDER BY sort_order ASC";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$materialId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Обновление блока контента
     *
     * @param modX $modx
     * @param int $blockId
     * @param array $data
     * @return bool
     */
    public static function updateContentBlock($modx, $blockId, $data)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $fields = [];
        $values = [];

        if (isset($data['block_type'])) {
            $fields[] = 'block_type = ?';
            $values[] = $data['block_type'];
        }

        if (isset($data['content_html'])) {
            $fields[] = 'content_html = ?';
            $values[] = $data['content_html'];
        }

        if (isset($data['content_data'])) {
            $fields[] = 'content_data = ?';
            $values[] = $data['content_data'];
        }

        if (isset($data['sort_order'])) {
            $fields[] = 'sort_order = ?';
            $values[] = $data['sort_order'];
        }

        if (empty($fields)) {
            return true;
        }

        $values[] = $blockId;

        $sql = "UPDATE {$prefix}test_learning_content
                SET " . implode(', ', $fields) . "
                WHERE id = ?";

        $stmt = $modx->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Удаление блока контента
     *
     * @param modX $modx
     * @param int $blockId
     * @return bool
     */
    public static function deleteContentBlock($modx, $blockId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $stmt = $modx->prepare("DELETE FROM {$prefix}test_learning_content WHERE id = ?");
        return $stmt->execute([$blockId]);
    }

    /**
     * Добавление вложения
     *
     * @param modX $modx
     * @param int $materialId
     * @param array $attachmentData
     * @return int Attachment ID
     */
    public static function addAttachment($modx, $materialId, $attachmentData)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "INSERT INTO {$prefix}test_learning_attachments
                (material_id, title, file_path, file_type, file_size, attachment_type, is_primary)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $modx->prepare($sql);
        $stmt->execute([
            $materialId,
            $attachmentData['title'],
            $attachmentData['file_path'],
            $attachmentData['file_type'] ?? null,
            $attachmentData['file_size'] ?? null,
            $attachmentData['attachment_type'] ?? 'document',
            $attachmentData['is_primary'] ?? 0
        ]);

        return (int)$modx->lastInsertId();
    }

    /**
     * Получение вложений материала
     *
     * @param modX $modx
     * @param int $materialId
     * @return array
     */
    public static function getAttachments($modx, $materialId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT * FROM {$prefix}test_learning_attachments
                WHERE material_id = ?
                ORDER BY is_primary DESC, id ASC";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$materialId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Удаление вложения
     *
     * @param modX $modx
     * @param int $attachmentId
     * @return bool
     */
    public static function deleteAttachment($modx, $attachmentId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $stmt = $modx->prepare("DELETE FROM {$prefix}test_learning_attachments WHERE id = ?");
        return $stmt->execute([$attachmentId]);
    }

    /**
     * Отслеживание прогресса пользователя
     *
     * @param modX $modx
     * @param int $userId
     * @param int $materialId
     * @param array $progressData
     * @return bool
     */
    public static function updateProgress($modx, $userId, $materialId, $progressData)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $status = $progressData['status'] ?? 'in_progress';
        $progressPct = $progressData['progress_pct'] ?? 0;
        $timeSpent = $progressData['time_spent_minutes'] ?? 0;

        // Проверяем, существует ли запись
        $stmt = $modx->prepare("SELECT id, status FROM {$prefix}test_material_progress
                                WHERE user_id = ? AND material_id = ?");
        $stmt->execute([$userId, $materialId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Обновление
            $fields = ['status = ?', 'progress_pct = ?', 'time_spent_minutes = time_spent_minutes + ?'];
            $values = [$status, $progressPct, $timeSpent];

            if ($status === 'completed' && $existing['status'] !== 'completed') {
                $fields[] = 'completed_at = NOW()';
            }

            if ($status === 'in_progress' && $existing['status'] === 'not_started') {
                $fields[] = 'started_at = NOW()';
            }

            $values[] = $userId;
            $values[] = $materialId;

            $sql = "UPDATE {$prefix}test_material_progress
                    SET " . implode(', ', $fields) . "
                    WHERE user_id = ? AND material_id = ?";

            $stmt = $modx->prepare($sql);
            return $stmt->execute($values);
        } else {
            // Создание новой записи
            $sql = "INSERT INTO {$prefix}test_material_progress
                    (user_id, material_id, status, progress_pct, time_spent_minutes, started_at)
                    VALUES (?, ?, ?, ?, ?, NOW())";

            $stmt = $modx->prepare($sql);
            return $stmt->execute([$userId, $materialId, $status, $progressPct, $timeSpent]);
        }
    }

    /**
     * Получение прогресса пользователя по материалу
     *
     * @param modX $modx
     * @param int $userId
     * @param int $materialId
     * @return array|null
     */
    public static function getUserProgress($modx, $userId, $materialId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT * FROM {$prefix}test_material_progress
                WHERE user_id = ? AND material_id = ?";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$userId, $materialId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Получение тегов материала
     *
     * @param modX $modx
     * @param int $materialId
     * @return array
     */
    public static function getTags($modx, $materialId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT tag FROM {$prefix}test_material_tags WHERE material_id = ?";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$materialId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Установка тегов материала
     *
     * @param modX $modx
     * @param int $materialId
     * @param array $tags
     * @return bool
     */
    public static function setTags($modx, $materialId, $tags)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Удаляем старые теги
        $stmt = $modx->prepare("DELETE FROM {$prefix}test_material_tags WHERE material_id = ?");
        $stmt->execute([$materialId]);

        // Добавляем новые
        if (empty($tags)) {
            return true;
        }

        $sql = "INSERT INTO {$prefix}test_material_tags (material_id, tag) VALUES (?, ?)";
        $stmt = $modx->prepare($sql);

        foreach ($tags as $tag) {
            $tag = trim($tag);
            if (!empty($tag)) {
                $stmt->execute([$materialId, $tag]);
            }
        }

        return true;
    }

    /**
     * Получение связанных тестов
     *
     * @param modX $modx
     * @param int $materialId
     * @return array
     */
    public static function getLinkedTests($modx, $materialId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT l.*, t.title as test_title
                FROM {$prefix}test_material_test_links l
                JOIN {$prefix}test_tests t ON t.id = l.test_id
                WHERE l.material_id = ?
                ORDER BY l.sort_order ASC";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$materialId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Связывание материала с тестом
     *
     * @param modX $modx
     * @param int $materialId
     * @param int $testId
     * @param string $linkType
     * @return bool
     */
    public static function linkTest($modx, $materialId, $testId, $linkType = 'related')
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "INSERT INTO {$prefix}test_material_test_links
                (material_id, test_id, link_type)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE link_type = ?";

        $stmt = $modx->prepare($sql);
        return $stmt->execute([$materialId, $testId, $linkType, $linkType]);
    }

    /**
     * Удаление связи с тестом
     *
     * @param modX $modx
     * @param int $materialId
     * @param int $testId
     * @return bool
     */
    public static function unlinkTest($modx, $materialId, $testId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $stmt = $modx->prepare("DELETE FROM {$prefix}test_material_test_links
                                WHERE material_id = ? AND test_id = ?");
        return $stmt->execute([$materialId, $testId]);
    }
}
