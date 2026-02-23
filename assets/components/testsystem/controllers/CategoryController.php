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
        'createCategory',
        'getCategories',
        'grantCategoryPermission',
        'revokeCategoryPermission',
        'getCategoryUsers',
        'getUserCategories',
        'checkCategoryPermission',
        'getPermissionHistory',
        'bulkGrantPermissions',
        'bulkRevokePermissions',
        'assignCategoryExpert',
        'removeCategoryExpert',
        'getCategoryExperts',
        'getAvailableExperts'
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
                case 'createCategory':
                    return $this->createCategory($data);

                case 'getCategories':
                    return $this->getCategories($data);

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

                case 'assignCategoryExpert':
                    return $this->assignCategoryExpert($data);

                case 'removeCategoryExpert':
                    return $this->removeCategoryExpert($data);

                case 'getCategoryExperts':
                    return $this->getCategoryExperts($data);

                case 'getAvailableExperts':
                    return $this->getAvailableExperts($data);

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

    /**
     * Создание новой категории
     */
    private function createCategory($data)
    {
        $this->requireAuth();

        $currentUserId = $this->getCurrentUserId();

        // Проверка прав - только админы и эксперты могут создавать категории
        $isGlobalAdmin = CategoryPermissionService::isGlobalAdmin($this->modx, $currentUserId);
        $isGlobalExpert = CategoryPermissionService::isGlobalExpert($this->modx, $currentUserId);

        if (!$isGlobalAdmin && !$isGlobalExpert) {
            throw new PermissionException('Только администраторы и эксперты могут создавать категории');
        }

        $name = ValidationHelper::requireString($data, 'name', 'Название категории обязательно');
        $description = $data['description'] ?? '';
        $parentId = isset($data['parent_id']) ? (int)$data['parent_id'] : null;

        // Проверяем уникальность имени
        $stmt = $this->modx->prepare("
            SELECT id FROM {$this->prefix}test_categories WHERE name = ?
        ");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            throw new ValidationException('Категория с таким именем уже существует');
        }

        // Получаем максимальный sort_order
        $stmt = $this->modx->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {$this->prefix}test_categories");
        $stmt->execute();
        $sortOrder = (int)$stmt->fetchColumn();

        // Создаём категорию
        $stmt = $this->modx->prepare("
            INSERT INTO {$this->prefix}test_categories (name, description, parent_id, sort_order, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $result = $stmt->execute([
            $name,
            $description,
            $parentId,
            $sortOrder,
            $currentUserId
        ]);

        if (!$result) {
            throw new Exception('Ошибка при создании категории');
        }

        $categoryId = (int)$this->modx->lastInsertId();

        return $this->success([
            'id' => $categoryId,
            'name' => $name,
            'description' => $description
        ], 'Категория успешно создана');
    }

    /**
     * Получение списка категорий
     */
    private function getCategories($data)
    {
        $stmt = $this->modx->prepare("
            SELECT id, name, description, parent_id, sort_order
            FROM {$this->prefix}test_categories
            ORDER BY sort_order, name
        ");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->success($categories);
    }

    /**
     * Назначить эксперта на категорию
     */
    private function assignCategoryExpert($data)
    {
        $this->requireAuth();

        if (!PermissionHelper::isAdmin($this->modx)) {
            throw new PermissionException('Access denied. Admin only.');
        }

        $userId = $this->getCurrentUserId();

        $categoryId = ValidationHelper::requireInt($data, 'category_id', 'Category ID required');
        $expertUserId = ValidationHelper::requireInt($data, 'expert_user_id', 'Expert user ID required');
        $canManageTests = ValidationHelper::optionalBool($data, 'can_manage_tests', true);
        $canManageQuestions = ValidationHelper::optionalBool($data, 'can_manage_questions', true);
        $canApprove = ValidationHelper::optionalBool($data, 'can_approve', false);

        // Проверяем что категория существует
        $stmt = $this->modx->prepare("SELECT id FROM {$this->prefix}test_categories WHERE id = ?");
        $stmt->execute(array($categoryId));
        if (!$stmt->fetch()) {
            throw new Exception('Category not found');
        }

        // Проверяем что пользователь существует и является экспертом
        $stmt = $this->modx->prepare("
            SELECT u.id, u.username
            FROM {$this->prefix}users u
            JOIN {$this->prefix}member_groups mg ON mg.member = u.id
            JOIN {$this->prefix}membergroup_names mgn ON mgn.id = mg.user_group
            WHERE u.id = ? AND mgn.name = 'LMS Experts'
        ");
        $stmt->execute(array($expertUserId));
        $expert = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$expert) {
            throw new Exception('User not found or not an expert');
        }

        // Назначаем эксперта (INSERT or UPDATE)
        $stmt = $this->modx->prepare("
            INSERT INTO {$this->prefix}test_category_experts
            (category_id, user_id, assigned_by, can_manage_tests, can_manage_questions, can_approve)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                can_manage_tests = VALUES(can_manage_tests),
                can_manage_questions = VALUES(can_manage_questions),
                can_approve = VALUES(can_approve),
                assigned_by = VALUES(assigned_by),
                assigned_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute(array(
            $categoryId,
            $expertUserId,
            $userId,
            $canManageTests ? 1 : 0,
            $canManageQuestions ? 1 : 0,
            $canApprove ? 1 : 0
        ));

        return $this->success(null, 'Expert assigned successfully');
    }

    /**
     * Убрать эксперта из категории
     */
    private function removeCategoryExpert($data)
    {
        $this->requireAuth();

        if (!PermissionHelper::isAdmin($this->modx)) {
            throw new PermissionException('Access denied. Admin only.');
        }

        $categoryId = ValidationHelper::requireInt($data, 'category_id', 'Category ID required');
        $expertUserId = ValidationHelper::requireInt($data, 'expert_user_id', 'Expert user ID required');

        $stmt = $this->modx->prepare("
            DELETE FROM {$this->prefix}test_category_experts
            WHERE category_id = ? AND user_id = ?
        ");
        $stmt->execute(array($categoryId, $expertUserId));

        return $this->success(null, 'Expert removed from category');
    }

    /**
     * Получить список экспертов категории
     */
    private function getCategoryExperts($data)
    {
        $this->requireAuth();

        $categoryId = ValidationHelper::requireInt($data, 'category_id', 'Category ID required');

        $stmt = $this->modx->prepare("
            SELECT
                ce.id,
                ce.user_id,
                u.username,
                up.email,
                ce.can_manage_tests,
                ce.can_manage_questions,
                ce.can_approve,
                ce.assigned_at,
                au.username as assigned_by_username
            FROM {$this->prefix}test_category_experts ce
            JOIN {$this->prefix}users u ON u.id = ce.user_id
            LEFT JOIN {$this->prefix}user_attributes up ON up.internalKey = u.id
            LEFT JOIN {$this->prefix}users au ON au.id = ce.assigned_by
            WHERE ce.category_id = ?
            ORDER BY u.username
        ");
        $stmt->execute(array($categoryId));
        $experts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->success($experts);
    }

    /**
     * Получить список доступных экспертов для назначения
     */
    private function getAvailableExperts($data)
    {
        $this->requireAuth();

        if (!PermissionHelper::isAdmin($this->modx)) {
            throw new PermissionException('Access denied. Admin only.');
        }

        $stmt = $this->modx->prepare("
            SELECT DISTINCT
                u.id,
                u.username,
                up.email,
                up.fullname
            FROM {$this->prefix}users u
            JOIN {$this->prefix}user_attributes up ON up.internalKey = u.id
            JOIN {$this->prefix}member_groups mg ON mg.member = u.id
            JOIN {$this->prefix}membergroup_names mgn ON mgn.id = mg.user_group
            WHERE mgn.name = 'LMS Experts'
            AND up.blocked = 0
            ORDER BY u.username
        ");
        $stmt->execute();
        $experts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->success($experts);
    }
}
