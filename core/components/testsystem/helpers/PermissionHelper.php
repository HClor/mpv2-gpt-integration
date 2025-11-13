<?php
/**
 * Permission Helper Class
 *
 * Централизация проверок прав доступа
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-13
 */

class PermissionHelper
{
    /**
     * Получение прав пользователя (замена checkUserRights)
     *
     * @param object $modx MODX объект
     * @return array ['isAdmin' => bool, 'isExpert' => bool, 'canEdit' => bool, 'userId' => int]
     */
    public static function getUserRights($modx)
    {
        // Проверка на авторизацию в web-контексте
        if (!$modx->user->hasSessionContext('web')) {
            return [
                'isAdmin' => false,
                'isExpert' => false,
                'canEdit' => false,
                'userId' => 0
            ];
        }

        $userId = (int)$modx->user->get('id');
        $userGroups = $modx->user->getUserGroupNames();

        // Проверка на эксперта
        $isExpert = in_array('LMS Experts', $userGroups, true);

        // Проверка на админа (superadmin или LMS Admins group)
        $isAdmin = in_array('LMS Admins', $userGroups, true) || $userId === 1;

        return [
            'isAdmin' => $isAdmin,
            'isExpert' => $isExpert,
            'canEdit' => $isAdmin || $isExpert,
            'userId' => $userId
        ];
    }

    /**
     * Проверка прав на редактирование теста
     *
     * @param object $modx MODX объект
     * @param int $testId ID теста
     * @param int|null $userId ID пользователя (если null, берется текущий)
     * @return bool True если пользователь может редактировать тест
     */
    public static function canEditTest($modx, $testId, $userId = null)
    {
        if ($userId === null) {
            $userId = (int)$modx->user->get('id');
        } else {
            $userId = (int)$userId;
        }

        // Superadmin всегда может
        if ($userId === 1) {
            return true;
        }

        // Админы и эксперты могут
        $userGroups = $modx->user->getUserGroupNames();
        if (in_array('LMS Admins', $userGroups, true) || in_array('LMS Experts', $userGroups, true)) {
            return true;
        }

        // Получаем информацию о тесте
        $stmt = $modx->prepare("
            SELECT created_by, publication_status
            FROM modx_test_tests
            WHERE id = ?
        ");
        $stmt->execute([(int)$testId]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$test) {
            return false;
        }

        // Владелец теста может редактировать
        if ((int)$test['created_by'] === $userId) {
            return true;
        }

        // Для private тестов проверяем permissions
        if ($test['publication_status'] === 'private') {
            $stmt = $modx->prepare("
                SELECT can_edit
                FROM modx_test_permissions
                WHERE test_id = ? AND user_id = ?
            ");
            $stmt->execute([(int)$testId, $userId]);
            $perm = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($perm && (int)$perm['can_edit'] === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверка прав на просмотр теста
     *
     * @param object $modx MODX объект
     * @param array $test Данные теста (должны содержать created_by и publication_status)
     * @param int|null $userId ID пользователя (если null, берется текущий)
     * @return array ['hasAccess' => bool, 'canEdit' => bool]
     */
    public static function checkTestAccess($modx, $test, $userId = null)
    {
        if ($userId === null) {
            $userId = (int)$modx->user->get('id');
        } else {
            $userId = (int)$userId;
        }

        $hasAccess = false;
        $canEdit = false;

        $rights = self::getUserRights($modx);

        // Админы и эксперты видят все
        if ($rights['isAdmin'] || $rights['isExpert']) {
            $hasAccess = true;
            $canEdit = true;
        }
        // Владелец теста
        elseif ((int)$test['created_by'] === $userId) {
            $hasAccess = true;
            $canEdit = true;
        }
        // Public тесты
        elseif ($test['publication_status'] === 'public') {
            $hasAccess = true;
        }
        // Unlisted тесты (по прямой ссылке)
        elseif ($test['publication_status'] === 'unlisted') {
            $hasAccess = true;
        }
        // Проверяем permissions для private
        elseif ($test['publication_status'] === 'private') {
            $stmt = $modx->prepare("
                SELECT can_edit
                FROM modx_test_permissions
                WHERE test_id = ? AND user_id = ?
            ");
            $stmt->execute([(int)$test['id'], $userId]);
            $perm = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($perm) {
                $hasAccess = true;
                $canEdit = (bool)$perm['can_edit'];
            }
        }

        return [
            'hasAccess' => $hasAccess,
            'canEdit' => $canEdit
        ];
    }

    /**
     * Требует права на редактирование
     *
     * @param object $modx MODX объект
     * @param string|null $errorMessage Сообщение об ошибке
     * @return void
     * @throws Exception Если нет прав
     */
    public static function requireEditRights($modx, $errorMessage = null)
    {
        $rights = self::getUserRights($modx);

        if (!$rights['canEdit']) {
            $message = $errorMessage ?? 'No permission to perform this action';
            throw new Exception($message);
        }
    }

    /**
     * Требует права на редактирование конкретного теста
     *
     * @param object $modx MODX объект
     * @param int $testId ID теста
     * @param string|null $errorMessage Сообщение об ошибке
     * @return void
     * @throws Exception Если нет прав
     */
    public static function requireTestEditRights($modx, $testId, $errorMessage = null)
    {
        if (!self::canEditTest($modx, $testId)) {
            $message = $errorMessage ?? 'No permission to edit this test';
            throw new Exception($message);
        }
    }

    /**
     * Требует права на доступ к тесту
     *
     * @param object $modx MODX объект
     * @param array $test Данные теста
     * @param string|null $errorMessage Сообщение об ошибке
     * @return array Результат проверки ['hasAccess' => bool, 'canEdit' => bool]
     * @throws Exception Если нет доступа
     */
    public static function requireTestAccess($modx, $test, $errorMessage = null)
    {
        $access = self::checkTestAccess($modx, $test);

        if (!$access['hasAccess']) {
            $message = $errorMessage ?? 'Access denied to this test';
            throw new Exception($message);
        }

        return $access;
    }

    /**
     * Проверка, является ли пользователь администратором
     *
     * @param object $modx MODX объект
     * @return bool
     */
    public static function isAdmin($modx)
    {
        $userId = (int)$modx->user->get('id');
        $userGroups = $modx->user->getUserGroupNames();
        return ($userId === 1) || in_array('LMS Admins', $userGroups, true);
    }

    /**
     * Проверка, является ли пользователь экспертом
     *
     * @param object $modx MODX объект
     * @return bool
     */
    public static function isExpert($modx)
    {
        $userGroups = $modx->user->getUserGroupNames();
        return in_array('LMS Experts', $userGroups, true);
    }

    /**
     * Получение ID текущего пользователя
     *
     * @param object $modx MODX объект
     * @return int
     */
    public static function getCurrentUserId($modx)
    {
        return (int)$modx->user->get('id');
    }

    /**
     * Проверка, авторизован ли пользователь
     *
     * @param object $modx MODX объект
     * @return bool
     */
    public static function isAuthenticated($modx)
    {
        return $modx->user->isAuthenticated('web');
    }

    /**
     * Требует авторизации
     *
     * @param object $modx MODX объект
     * @param string|null $errorMessage Сообщение об ошибке
     * @return void
     * @throws Exception Если не авторизован
     */
    public static function requireAuthentication($modx, $errorMessage = null)
    {
        if (!self::isAuthenticated($modx)) {
            $message = $errorMessage ?? 'Authentication required';
            throw new Exception($message);
        }
    }
}
