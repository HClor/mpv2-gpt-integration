<?php
/**
 * Category Controller
 *
 * Контроллер для управления категориями и правами доступа
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class CategoryController extends BaseController
{
    /**
     * Список доступных действий
     */
    private $actions = [
        'grantCategoryPermission',
        'revokeCategoryPermission',
        'getCategoryUsers',
        'getUserCategories',
        'checkCategoryPermission',
        'getPermissionHistory',
        'bulkGrantPermissions',
        'bulkRevokePermissions'
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
                case 'grantCategoryPermission':
                    return $this->grantCategoryPermission($data);

                case 'revokeCategoryPermission':
                    return $this->revokeCategoryPermission($data);

                case 'getCategoryUsers':
                    return $this->getCategoryUsers($data);

                case 'getUserCategories':
                    return $this->getUserCategories($data);

                case 'checkCategoryPermission':
                    return $this->checkCategoryPermission($data);

                case 'getPermissionHistory':
                    return $this->getPermissionHistory($data);

                case 'bulkGrantPermissions':
                    return $this->bulkGrantPermissions($data);

                case 'bulkRevokePermissions':
                    return $this->bulkRevokePermissions($data);

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
     * Назначение прав на категорию
     */
    private function grantCategoryPermission($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $categoryId = ValidationHelper::requireInt($data, 'category_id', 'Category ID required');
        $userId = ValidationHelper::requireInt($data, 'user_id', 'User ID required');
        $role = ValidationHelper::requireString($data, 'role', 'Role required');

        // Валидация роли
        $validRoles = [
            CategoryPermissionService::ROLE_ADMIN,
            CategoryPermissionService::ROLE_EXPERT,
            CategoryPermissionService::ROLE_VIEWER
        ];

        if (!in_array($role, $validRoles)) {
            throw new ValidationException('Invalid role. Allowed: admin, expert, viewer');
        }

        // Проверка прав текущего пользователя
        // Только глобальные админы или админы категории могут назначать права
        $isGlobalAdmin = CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId);
        $isCategoryAdmin = CategoryPermissionService::canManageCategory($this->modx, $categoryId, $currentUserId);

        if (!$isGlobalAdmin && !$isCategoryAdmin) {
            throw new PermissionException('Only category admins can grant permissions');
        }

        // Админов категории могут назначать только глобальные админы
        if ($role === CategoryPermissionService::ROLE_ADMIN && !$isGlobalAdmin) {
            throw new PermissionException('Only global admins can grant admin role');
        }

        // Парсим дату истечения, если указана
        $expiresAt = null;
        if (isset($data['expires_at']) && !empty($data['expires_at'])) {
            try {
                $expiresAt = new DateTime($data['expires_at']);
            } catch (Exception $e) {
                throw new ValidationException('Invalid expires_at date format');
            }
        }

        $success = CategoryPermissionService::grantPermission(
            $this->modx,
            $categoryId,
            $userId,
            $role,
            $currentUserId,
            $expiresAt
        );

        if ($success) {
            return $this->success(null, 'Permission granted successfully');
        } else {
            throw new Exception('Failed to grant permission');
        }
    }

    /**
     * Отзыв прав на категорию
     */
    private function revokeCategoryPermission($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $categoryId = ValidationHelper::requireInt($data, 'category_id', 'Category ID required');
        $userId = ValidationHelper::requireInt($data, 'user_id', 'User ID required');

        // Проверка прав текущего пользователя
        $isGlobalAdmin = CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId);
        $isCategoryAdmin = CategoryPermissionService::canManageCategory($this->modx, $categoryId, $currentUserId);

        if (!$isGlobalAdmin && !$isCategoryAdmin) {
            throw new PermissionException('Only category admins can revoke permissions');
        }

        $success = CategoryPermissionService::revokePermission(
            $this->modx,
            $categoryId,
            $userId,
            $currentUserId
        );

        if ($success) {
            return $this->success(null, 'Permission revoked successfully');
        } else {
            throw new Exception('Failed to revoke permission');
        }
    }

    /**
     * Получение списка пользователей категории
     */
    private function getCategoryUsers($data)
    {
        $this->requireAuth();

        $categoryId = ValidationHelper::requireInt($data, 'category_id', 'Category ID required');
        $role = $data['role'] ?? null;

        $currentUserId = $this->getCurrentUserId();

        // Проверка прав: просмотреть список могут админы категории и просто эксперты
        $canView = CategoryPermissionService::canViewStats($this->modx, $categoryId, $currentUserId);

        if (!$canView) {
            throw new PermissionException('No permission to view category users');
        }

        $users = CategoryPermissionService::getCategoryUsers($this->modx, $categoryId, $role);

        return $this->success($users);
    }

    /**
     * Получение списка категорий пользователя
     */
    private function getUserCategories($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();

        // Можно запросить категории другого пользователя только глобальным админам
        $userId = $data['user_id'] ?? $currentUserId;

        if ($userId != $currentUserId && !CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId)) {
            throw new PermissionException('Only admins can view other users categories');
        }

        $role = $data['role'] ?? null;

        $categories = CategoryPermissionService::getUserCategories($this->modx, $userId, $role);

        return $this->success($categories);
    }

    /**
     * Проверка прав на категорию
     */
    private function checkCategoryPermission($data)
    {
        $this->requireAuth();

        $categoryId = ValidationHelper::requireInt($data, 'category_id', 'Category ID required');
        $requiredRole = $data['required_role'] ?? CategoryPermissionService::ROLE_VIEWER;

        $currentUserId = $this->getCurrentUserId();

        $hasPermission = CategoryPermissionService::hasPermission(
            $this->modx,
            $categoryId,
            $currentUserId,
            $requiredRole
        );

        $userRole = CategoryPermissionService::getUserRole($this->modx, $categoryId, $currentUserId);

        return $this->success([
            'has_permission' => $hasPermission,
            'user_role' => $userRole,
            'is_global_admin' => CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId),
            'is_global_expert' => CategoryPermissionService::isGlobalExpert($this->modx, $currentUserId)
        ]);
    }

    /**
     * Получение истории изменений прав
     */
    private function getPermissionHistory($data)
    {
        $this->requireAuth();

        $categoryId = ValidationHelper::requireInt($data, 'category_id', 'Category ID required');
        $limit = ValidationHelper::optionalInt($data, 'limit', 50);

        $currentUserId = $this->getCurrentUserId();

        // История доступна только админам категории
        if (!CategoryPermissionService::canManageCategory($this->modx, $categoryId, $currentUserId)) {
            throw new PermissionException('Only category admins can view permission history');
        }

        $history = CategoryPermissionService::getPermissionHistory($this->modx, $categoryId, $limit);

        return $this->success($history);
    }

    /**
     * Массовое назначение прав
     */
    private function bulkGrantPermissions($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $categoryId = ValidationHelper::requireInt($data, 'category_id', 'Category ID required');
        $userIds = ValidationHelper::requireArray($data, 'user_ids', 1, 'User IDs required');
        $role = ValidationHelper::requireString($data, 'role', 'Role required');

        // Валидация роли
        $validRoles = [
            CategoryPermissionService::ROLE_ADMIN,
            CategoryPermissionService::ROLE_EXPERT,
            CategoryPermissionService::ROLE_VIEWER
        ];

        if (!in_array($role, $validRoles)) {
            throw new ValidationException('Invalid role');
        }

        // Проверка прав
        $isGlobalAdmin = CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId);
        $isCategoryAdmin = CategoryPermissionService::canManageCategory($this->modx, $categoryId, $currentUserId);

        if (!$isGlobalAdmin && !$isCategoryAdmin) {
            throw new PermissionException('Only category admins can grant permissions');
        }

        if ($role === CategoryPermissionService::ROLE_ADMIN && !$isGlobalAdmin) {
            throw new PermissionException('Only global admins can grant admin role');
        }

        $count = CategoryPermissionService::bulkGrantPermissions(
            $this->modx,
            $categoryId,
            $userIds,
            $role,
            $currentUserId
        );

        return $this->success([
            'granted_count' => $count,
            'total_users' => count($userIds)
        ], "{$count} permissions granted");
    }

    /**
     * Массовый отзыв прав
     */
    private function bulkRevokePermissions($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();
        $categoryId = ValidationHelper::requireInt($data, 'category_id', 'Category ID required');
        $userIds = ValidationHelper::requireArray($data, 'user_ids', 1, 'User IDs required');

        // Проверка прав
        $isGlobalAdmin = CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId);
        $isCategoryAdmin = CategoryPermissionService::canManageCategory($this->modx, $categoryId, $currentUserId);

        if (!$isGlobalAdmin && !$isCategoryAdmin) {
            throw new PermissionException('Only category admins can revoke permissions');
        }

        $count = CategoryPermissionService::bulkRevokePermissions(
            $this->modx,
            $categoryId,
            $userIds,
            $currentUserId
        );

        return $this->success([
            'revoked_count' => $count,
            'total_users' => count($userIds)
        ], "{$count} permissions revoked");
    }
}
