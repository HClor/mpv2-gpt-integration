<?php
/**
 * Category Permission Service
 *
 * Сервис для управления правами доступа к категориям
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class CategoryPermissionService
{
    /**
     * Роли пользователей в категориях
     */
    const ROLE_ADMIN = 'admin';
    const ROLE_EXPERT = 'expert';
    const ROLE_VIEWER = 'viewer';

    /**
     * Назначение прав пользователю на категорию
     *
     * @param modX $modx
     * @param int $categoryId
     * @param int $userId
     * @param string $role
     * @param int $grantedBy
     * @param DateTime|null $expiresAt
     * @return bool
     */
    public static function grantPermission($modx, $categoryId, $userId, $role, $grantedBy, $expiresAt = null)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Валидация роли
        if (!in_array($role, [self::ROLE_ADMIN, self::ROLE_EXPERT, self::ROLE_VIEWER])) {
            throw new Exception('Invalid role');
        }

        $expiresAtStr = $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null;

        $sql = "INSERT INTO {$prefix}test_category_permissions
                (category_id, user_id, role, granted_by, expires_at)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    role = ?,
                    granted_by = ?,
                    granted_at = NOW(),
                    expires_at = ?";

        $stmt = $modx->prepare($sql);
        return $stmt->execute([
            $categoryId, $userId, $role, $grantedBy, $expiresAtStr,
            $role, $grantedBy, $expiresAtStr
        ]);
    }

    /**
     * Отзыв прав пользователя на категорию
     *
     * @param modX $modx
     * @param int $categoryId
     * @param int $userId
     * @param int $performedBy ID пользователя, выполняющего операцию
     * @return bool
     */
    public static function revokePermission($modx, $categoryId, $userId, $performedBy)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Устанавливаем переменную для триггера
        $modx->exec("SET @performed_by_user_id = {$performedBy}");

        $sql = "DELETE FROM {$prefix}test_category_permissions
                WHERE category_id = ? AND user_id = ?";

        $stmt = $modx->prepare($sql);
        return $stmt->execute([$categoryId, $userId]);
    }

    /**
     * Получение роли пользователя в категории
     *
     * @param modX $modx
     * @param int $categoryId
     * @param int $userId
     * @return string|null Роль или null если прав нет
     */
    public static function getUserRole($modx, $categoryId, $userId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT role FROM {$prefix}test_category_permissions
                WHERE category_id = ?
                  AND user_id = ?
                  AND (expires_at IS NULL OR expires_at > NOW())";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$categoryId, $userId]);

        return $stmt->fetchColumn();
    }

    /**
     * Проверка прав пользователя на категорию
     *
     * @param modX $modx
     * @param int $categoryId
     * @param int $userId
     * @param string|array $requiredRole Требуемая роль или массив ролей
     * @return bool
     */
    public static function hasPermission($modx, $categoryId, $userId, $requiredRole)
    {
        // Глобальные админы имеют полный доступ
        if (self::isGlobalAdmin($modx, $userId)) {
            return true;
        }

        // Глобальные эксперты имеют права expert во всех категориях
        if (self::isGlobalExpert($modx, $userId)) {
            $requiredRoles = is_array($requiredRole) ? $requiredRole : [$requiredRole];
            if (in_array(self::ROLE_EXPERT, $requiredRoles) || in_array(self::ROLE_VIEWER, $requiredRoles)) {
                return true;
            }
        }

        $userRole = self::getUserRole($modx, $categoryId, $userId);

        if (!$userRole) {
            return false;
        }

        // Проверка роли
        if (is_array($requiredRole)) {
            return in_array($userRole, $requiredRole);
        }

        // admin имеет все права
        if ($userRole === self::ROLE_ADMIN) {
            return true;
        }

        // expert имеет права expert и viewer
        if ($userRole === self::ROLE_EXPERT && in_array($requiredRole, [self::ROLE_EXPERT, self::ROLE_VIEWER])) {
            return true;
        }

        // viewer имеет только права viewer
        if ($userRole === self::ROLE_VIEWER && $requiredRole === self::ROLE_VIEWER) {
            return true;
        }

        return false;
    }

    /**
     * Проверка, является ли пользователь глобальным администратором
     *
     * @param modX $modx
     * @param int $userId
     * @return bool
     */
    public static function isGlobalAdmin($modx, $userId)
    {
        if ($userId === 1) {
            return true;
        }

        $user = $modx->getObject('modUser', $userId);
        if (!$user) {
            return false;
        }

        $userGroups = array_keys($user->getUserGroups());
        $adminGroup = Config::getGroup('admins');

        return in_array($adminGroup, $userGroups, true);
    }

    /**
     * Проверка, является ли пользователь глобальным экспертом
     *
     * @param modX $modx
     * @param int $userId
     * @return bool
     */
    public static function isGlobalExpert($modx, $userId)
    {
        $user = $modx->getObject('modUser', $userId);
        if (!$user) {
            return false;
        }

        $userGroups = array_keys($user->getUserGroups());
        $expertGroup = Config::getGroup('experts');

        return in_array($expertGroup, $userGroups, true);
    }

    /**
     * Получение всех пользователей категории
     *
     * @param modX $modx
     * @param int $categoryId
     * @param string|null $role Фильтр по роли
     * @return array
     */
    public static function getCategoryUsers($modx, $categoryId, $role = null)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $where = ['cp.category_id = ?'];
        $params = [$categoryId];

        if ($role) {
            $where[] = 'cp.role = ?';
            $params[] = $role;
        }

        $sql = "SELECT cp.*, u.username, u.email,
                       gb.username as granted_by_username
                FROM {$prefix}test_category_permissions cp
                JOIN {$prefix}users u ON u.id = cp.user_id
                LEFT JOIN {$prefix}users gb ON gb.id = cp.granted_by
                WHERE " . implode(' AND ', $where) . "
                  AND (cp.expires_at IS NULL OR cp.expires_at > NOW())
                ORDER BY cp.role DESC, u.username ASC";

        $stmt = $modx->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получение всех категорий пользователя
     *
     * @param modX $modx
     * @param int $userId
     * @param string|null $role Фильтр по роли
     * @return array
     */
    public static function getUserCategories($modx, $userId, $role = null)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $where = ['cp.user_id = ?'];
        $params = [$userId];

        if ($role) {
            $where[] = 'cp.role = ?';
            $params[] = $role;
        }

        $sql = "SELECT cp.*, c.name as category_name, c.description
                FROM {$prefix}test_category_permissions cp
                JOIN {$prefix}test_categories c ON c.id = cp.category_id
                WHERE " . implode(' AND ', $where) . "
                  AND (cp.expires_at IS NULL OR cp.expires_at > NOW())
                ORDER BY c.name ASC";

        $stmt = $modx->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Проверка, может ли пользователь управлять категорией
     *
     * @param modX $modx
     * @param int $categoryId
     * @param int $userId
     * @return bool
     */
    public static function canManageCategory($modx, $categoryId, $userId)
    {
        return self::hasPermission($modx, $categoryId, $userId, self::ROLE_ADMIN);
    }

    /**
     * Проверка, может ли пользователь создавать контент в категории
     *
     * @param modX $modx
     * @param int $categoryId
     * @param int $userId
     * @return bool
     */
    public static function canCreateContent($modx, $categoryId, $userId)
    {
        return self::hasPermission($modx, $categoryId, $userId, [self::ROLE_ADMIN, self::ROLE_EXPERT]);
    }

    /**
     * Проверка, может ли пользователь просматривать статистику категории
     *
     * @param modX $modx
     * @param int $categoryId
     * @param int $userId
     * @return bool
     */
    public static function canViewStats($modx, $categoryId, $userId)
    {
        return self::hasPermission($modx, $categoryId, $userId, [self::ROLE_ADMIN, self::ROLE_EXPERT, self::ROLE_VIEWER]);
    }

    /**
     * Получение истории изменений прав для категории
     *
     * @param modX $modx
     * @param int $categoryId
     * @param int $limit
     * @return array
     */
    public static function getPermissionHistory($modx, $categoryId, $limit = 50)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT ph.*,
                       u.username,
                       pb.username as performed_by_username
                FROM {$prefix}test_permission_history ph
                JOIN {$prefix}users u ON u.id = ph.user_id
                LEFT JOIN {$prefix}users pb ON pb.id = ph.performed_by
                WHERE ph.category_id = ?
                ORDER BY ph.performed_at DESC
                LIMIT ?";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$categoryId, (int)$limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получение категории теста
     *
     * @param modX $modx
     * @param int $testId
     * @return int|null
     */
    public static function getTestCategory($modx, $testId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT category_id FROM {$prefix}test_tests WHERE id = ?";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$testId]);

        return $stmt->fetchColumn();
    }

    /**
     * Получение категории материала
     *
     * @param modX $modx
     * @param int $materialId
     * @return int|null
     */
    public static function getMaterialCategory($modx, $materialId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT category_id FROM {$prefix}test_learning_materials WHERE id = ?";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$materialId]);

        return $stmt->fetchColumn();
    }

    /**
     * Проверка прав на тест с учетом категории
     *
     * @param modX $modx
     * @param int $testId
     * @param int $userId
     * @param string|array $requiredRole
     * @return bool
     */
    public static function canAccessTest($modx, $testId, $userId, $requiredRole = self::ROLE_VIEWER)
    {
        $categoryId = self::getTestCategory($modx, $testId);

        if (!$categoryId) {
            // Если нет категории, проверяем глобальные права
            return self::isGlobalAdmin($modx, $userId) || self::isGlobalExpert($modx, $userId);
        }

        return self::hasPermission($modx, $categoryId, $userId, $requiredRole);
    }

    /**
     * Проверка прав на материал с учетом категории
     *
     * @param modX $modx
     * @param int $materialId
     * @param int $userId
     * @param string|array $requiredRole
     * @return bool
     */
    public static function canAccessMaterial($modx, $materialId, $userId, $requiredRole = self::ROLE_VIEWER)
    {
        $categoryId = self::getMaterialCategory($modx, $materialId);

        if (!$categoryId) {
            // Если нет категории, проверяем глобальные права
            return self::isGlobalAdmin($modx, $userId) || self::isGlobalExpert($modx, $userId);
        }

        return self::hasPermission($modx, $categoryId, $userId, $requiredRole);
    }

    /**
     * Массовое назначение прав
     *
     * @param modX $modx
     * @param int $categoryId
     * @param array $userIds
     * @param string $role
     * @param int $grantedBy
     * @return int Количество назначенных прав
     */
    public static function bulkGrantPermissions($modx, $categoryId, $userIds, $role, $grantedBy)
    {
        $count = 0;

        foreach ($userIds as $userId) {
            if (self::grantPermission($modx, $categoryId, $userId, $role, $grantedBy)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Массовый отзыв прав
     *
     * @param modX $modx
     * @param int $categoryId
     * @param array $userIds
     * @param int $performedBy
     * @return int Количество отозванных прав
     */
    public static function bulkRevokePermissions($modx, $categoryId, $userIds, $performedBy)
    {
        $count = 0;

        foreach ($userIds as $userId) {
            if (self::revokePermission($modx, $categoryId, $userId, $performedBy)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Очистка истекших прав
     *
     * @param modX $modx
     * @return int Количество удаленных прав
     */
    public static function cleanupExpiredPermissions($modx)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "DELETE FROM {$prefix}test_category_permissions
                WHERE expires_at IS NOT NULL AND expires_at < NOW()";

        $stmt = $modx->prepare($sql);
        $stmt->execute();

        return $stmt->rowCount();
    }
}
