<?php
/**
 * Test Permission Helper
 *
 * Управление правами доступа к тестам
 * Реализует модель прав как в Moodle/Canvas
 *
 * @package TestSystem
 * @version 1.0
 */

class TestPermissionHelper
{
    /**
     * Роли пользователей
     */
    const ROLE_AUTHOR = 'author';
    const ROLE_EDITOR = 'editor';
    const ROLE_VIEWER = 'viewer';

    /**
     * Группы пользователей в MODX
     */
    const GROUP_ADMINISTRATOR = 'LMS Admins';
    const GROUP_EXPERT = 'LMS Experts';

    /**
     * Проверяет, является ли пользователь администратором
     *
     * @param object $modx
     * @param int $userId
     * @return bool
     */
    public static function isAdministrator($modx, $userId = null)
    {
        if ($userId === null) {
            $userId = $modx->user->id;
        }

        if (!$userId) {
            return false;
        }

        $user = $modx->getObject('modUser', $userId);
        if (!$user) {
            return false;
        }

        return $user->isMember(self::GROUP_ADMINISTRATOR);
    }

    /**
     * Проверяет, является ли пользователь экспертом
     *
     * @param object $modx
     * @param int $userId
     * @return bool
     */
    public static function isExpert($modx, $userId = null)
    {
        if ($userId === null) {
            $userId = $modx->user->id;
        }

        if (!$userId) {
            return false;
        }

        $user = $modx->getObject('modUser', $userId);
        if (!$user) {
            return false;
        }

        return $user->isMember(self::GROUP_EXPERT);
    }

    /**
     * Проверяет, является ли пользователь админом или экспертом
     *
     * @param object $modx
     * @param int $userId
     * @return bool
     */
    public static function isAdminOrExpert($modx, $userId = null)
    {
        return self::isAdministrator($modx, $userId) || self::isExpert($modx, $userId);
    }

    /**
     * Проверяет, является ли пользователь создателем теста
     *
     * @param object $modx
     * @param int $testId
     * @param int $userId
     * @return bool
     */
    public static function isTestCreator($modx, $testId, $userId = null)
    {
        if ($userId === null) {
            $userId = $modx->user->id;
        }

        if (!$userId || !$testId) {
            return false;
        }

        $prefix = $modx->getOption('table_prefix');
        $stmt = $modx->prepare("
            SELECT created_by
            FROM {$prefix}test_tests
            WHERE id = ?
        ");
        $stmt->execute([$testId]);
        $createdBy = $stmt->fetchColumn();

        return $createdBy && (int)$createdBy === (int)$userId;
    }

    /**
     * Получает роль пользователя для теста
     *
     * @param object $modx
     * @param int $testId
     * @param int $userId
     * @return string|null author, editor, viewer или null
     */
    public static function getUserRole($modx, $testId, $userId = null)
    {
        if ($userId === null) {
            $userId = $modx->user->id;
        }

        if (!$userId || !$testId) {
            return null;
        }

        // Создатель теста имеет роль author
        if (self::isTestCreator($modx, $testId, $userId)) {
            return self::ROLE_AUTHOR;
        }

        // Проверяем явные права из таблицы permissions
        $prefix = $modx->getOption('table_prefix');
        $stmt = $modx->prepare("
            SELECT role
            FROM {$prefix}test_permissions
            WHERE test_id = ? AND user_id = ?
        ");
        $stmt->execute([$testId, $userId]);
        $role = $stmt->fetchColumn();

        return $role ?: null;
    }

    /**
     * Может ли пользователь видеть тест
     *
     * @param object $modx
     * @param int $testId
     * @param string $publicationStatus
     * @param int $userId
     * @return bool
     */
    public static function canView($modx, $testId, $publicationStatus = 'public', $userId = null)
    {
        if ($userId === null) {
            $userId = $modx->user->id;
        }

        // Админы видят все
        if (self::isAdministrator($modx, $userId)) {
            return true;
        }

        // Public тесты видят все
        if ($publicationStatus === 'public') {
            return true;
        }

        // Draft тесты видят только админы, эксперты и те, у кого есть права
        if ($publicationStatus === 'draft') {
            if (self::isAdminOrExpert($modx, $userId)) {
                return true;
            }
            return self::getUserRole($modx, $testId, $userId) !== null;
        }

        // Private тесты видят админы и те, у кого есть права
        if ($publicationStatus === 'private') {
            if (self::isAdministrator($modx, $userId)) {
                return true;
            }
            return self::getUserRole($modx, $testId, $userId) !== null;
        }

        return false;
    }

    /**
     * Может ли пользователь редактировать тест
     *
     * @param object $modx
     * @param int $testId
     * @param string $publicationStatus
     * @param int $userId
     * @return bool
     */
    public static function canEdit($modx, $testId, $publicationStatus = 'public', $userId = null)
    {
        if ($userId === null) {
            $userId = $modx->user->id;
        }

        // Админы редактируют все
        if (self::isAdministrator($modx, $userId)) {
            return true;
        }

        // Эксперты редактируют public и draft (но не private чужие)
        if (self::isExpert($modx, $userId)) {
            if ($publicationStatus !== 'private') {
                return true;
            }
            // Private только если есть права
            $role = self::getUserRole($modx, $testId, $userId);
            return in_array($role, [self::ROLE_AUTHOR, self::ROLE_EDITOR]);
        }

        // Обычные пользователи - только если есть права author или editor
        $role = self::getUserRole($modx, $testId, $userId);
        return in_array($role, [self::ROLE_AUTHOR, self::ROLE_EDITOR]);
    }

    /**
     * Может ли пользователь управлять доступом к тесту
     *
     * @param object $modx
     * @param int $testId
     * @param int $userId
     * @return bool
     */
    public static function canManageAccess($modx, $testId, $userId = null)
    {
        if ($userId === null) {
            $userId = $modx->user->id;
        }

        // Админы управляют доступом к любым тестам
        if (self::isAdministrator($modx, $userId)) {
            return true;
        }

        // Только создатели (author) могут управлять доступом
        $role = self::getUserRole($modx, $testId, $userId);
        return $role === self::ROLE_AUTHOR;
    }

    /**
     * Может ли пользователь изменять статус публикации
     *
     * @param object $modx
     * @param int $testId
     * @param int $userId
     * @return bool
     */
    public static function canChangeStatus($modx, $testId, $userId = null)
    {
        // Только админы и создатели могут менять статус
        return self::isAdministrator($modx, $userId) ||
               self::getUserRole($modx, $testId, $userId) === self::ROLE_AUTHOR;
    }

    /**
     * Добавляет право доступа пользователю
     *
     * @param object $modx
     * @param int $testId
     * @param int $targetUserId Кому дать доступ
     * @param string $role Роль (author, editor, viewer)
     * @param int $grantedBy Кто выдает доступ
     * @return bool
     */
    public static function grantAccess($modx, $testId, $targetUserId, $role, $grantedBy)
    {
        $modx->log(modX::LOG_LEVEL_ERROR, '[TestPermissionHelper::grantAccess] Start - testId: ' . $testId . ', targetUserId: ' . $targetUserId . ', role: ' . $role);

        if (!in_array($role, [self::ROLE_AUTHOR, self::ROLE_EDITOR, self::ROLE_VIEWER])) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[TestPermissionHelper::grantAccess] Invalid role: ' . $role);
            return false;
        }

        $prefix = $modx->getOption('table_prefix');
        $modx->log(modX::LOG_LEVEL_ERROR, '[TestPermissionHelper::grantAccess] Table prefix: ' . $prefix);

        // Проверяем, есть ли уже доступ
        $sql = "SELECT id FROM {$prefix}test_permissions WHERE test_id = ? AND user_id = ?";
        $modx->log(modX::LOG_LEVEL_ERROR, '[TestPermissionHelper::grantAccess] Check SQL: ' . $sql);

        $stmt = $modx->prepare($sql);
        if (!$stmt) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[TestPermissionHelper::grantAccess] Failed to prepare SELECT statement');
            return false;
        }

        $result = $stmt->execute([$testId, $targetUserId]);
        if (!$result) {
            $error = $stmt->errorInfo();
            $modx->log(modX::LOG_LEVEL_ERROR, '[TestPermissionHelper::grantAccess] SELECT execute failed: ' . print_r($error, true));
            return false;
        }

        $exists = $stmt->fetchColumn();
        $modx->log(modX::LOG_LEVEL_ERROR, '[TestPermissionHelper::grantAccess] Existing record ID: ' . ($exists ? $exists : 'none'));

        if ($exists) {
            // Обновляем роль
            $sql = "UPDATE {$prefix}test_permissions SET role = ?, granted_by = ?, granted_at = NOW() WHERE test_id = ? AND user_id = ?";
            $modx->log(modX::LOG_LEVEL_ERROR, '[TestPermissionHelper::grantAccess] UPDATE SQL: ' . $sql);

            $stmt = $modx->prepare($sql);
            if (!$stmt) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[TestPermissionHelper::grantAccess] Failed to prepare UPDATE statement');
                return false;
            }

            $result = $stmt->execute([$role, $grantedBy, $testId, $targetUserId]);
            if (!$result) {
                $error = $stmt->errorInfo();
                $modx->log(modX::LOG_LEVEL_ERROR, '[TestPermissionHelper::grantAccess] UPDATE execute failed: ' . print_r($error, true));
            } else {
                $modx->log(modX::LOG_LEVEL_ERROR, '[TestPermissionHelper::grantAccess] UPDATE success');
            }
            return $result;
        } else {
            // Создаем новую запись
            $sql = "INSERT INTO {$prefix}test_permissions (test_id, user_id, role, granted_by, granted_at) VALUES (?, ?, ?, ?, NOW())";
            $modx->log(modX::LOG_LEVEL_ERROR, '[TestPermissionHelper::grantAccess] INSERT SQL: ' . $sql);

            $stmt = $modx->prepare($sql);
            if (!$stmt) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[TestPermissionHelper::grantAccess] Failed to prepare INSERT statement');
                return false;
            }

            $result = $stmt->execute([$testId, $targetUserId, $role, $grantedBy]);
            if (!$result) {
                $error = $stmt->errorInfo();
                $modx->log(modX::LOG_LEVEL_ERROR, '[TestPermissionHelper::grantAccess] INSERT execute failed: ' . print_r($error, true));
            } else {
                $modx->log(modX::LOG_LEVEL_ERROR, '[TestPermissionHelper::grantAccess] INSERT success');
            }
            return $result;
        }
    }

    /**
     * Удаляет право доступа
     *
     * @param object $modx
     * @param int $testId
     * @param int $targetUserId
     * @return bool
     */
    public static function revokeAccess($modx, $testId, $targetUserId)
    {
        $prefix = $modx->getOption('table_prefix');
        $stmt = $modx->prepare("
            DELETE FROM {$prefix}test_permissions
            WHERE test_id = ? AND user_id = ?
        ");
        return $stmt->execute([$testId, $targetUserId]);
    }

    /**
     * Получает список пользователей с доступом к тесту
     *
     * @param object $modx
     * @param int $testId
     * @return array
     */
    public static function getTestUsers($modx, $testId)
    {
        $prefix = $modx->getOption('table_prefix');
        $stmt = $modx->prepare("
            SELECT
                p.user_id,
                p.role,
                p.granted_by,
                p.granted_at,
                u.username,
                up.fullname,
                up.email
            FROM {$prefix}test_permissions p
            INNER JOIN {$prefix}users u ON u.id = p.user_id
            LEFT JOIN {$prefix}user_attributes up ON up.internalKey = p.user_id
            WHERE p.test_id = ?
            ORDER BY p.granted_at DESC
        ");
        $stmt->execute([$testId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
