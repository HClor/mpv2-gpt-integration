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

require_once MODX_CORE_PATH . 'components/testsystem/services/TestService.php';
require_once MODX_CORE_PATH . 'components/testsystem/helpers/UrlHelper.php';

class TestController extends BaseController
{
    /**
     * Список доступных действий
     */
    private $actions = array(
        'getTestInfo',
        'getTestSettings',
        'updateTestSettings',
        'updateTest',
        'deleteTest',
        'publishTest',
        'getPublicTestBySlug',
        'checkResourcePermissions',
        'createTestWithPage',
        'createTest',
        'createTestPage',
        'getMyTests',
        'getSharedWithMe',
        'getPublicTests',
        'grantTestAccess',
        'revokeTestAccess',
        'getTestPermissions',
        'getAvailableUsersForTest',
        'checkTestAccess',
        'searchUsers',
        'grantAccess',
        'revokeAccess',
        'checkEditRights'
    );

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

                case 'publishTest':
                    return $this->publishTest($data);

                case 'getPublicTestBySlug':
                    return $this->getPublicTestBySlug($data);

                case 'checkResourcePermissions':
                    return $this->checkResourcePermissions($data);

                case 'createTestWithPage':
                    return $this->createTestWithPage($data);

                case 'createTest':
                    return $this->createTest($data);

                case 'createTestPage':
                    return $this->createTestPage($data);

                case 'getMyTests':
                    return $this->getMyTests($data);

                case 'getSharedWithMe':
                    return $this->getSharedWithMe($data);

                case 'getPublicTests':
                    return $this->getPublicTests($data);

                case 'grantTestAccess':
                    return $this->grantTestAccess($data);

                case 'revokeTestAccess':
                    return $this->revokeTestAccess($data);

                case 'getTestPermissions':
                    return $this->getTestPermissions($data);

                case 'getAvailableUsersForTest':
                    return $this->getAvailableUsersForTest($data);

                case 'checkTestAccess':
                    return $this->checkTestAccess($data);

                case 'searchUsers':
                    return $this->searchUsers($data);

                case 'grantAccess':
                    return $this->grantAccess($data);

                case 'revokeAccess':
                    return $this->revokeAccess($data);

                case 'checkEditRights':
                    return $this->checkEditRights($data);

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
        $mode = ValidationHelper::optionalString($data, 'mode', 'training');
        $passScore = ValidationHelper::optionalInt($data, 'pass_score', 70);
        $timeLimit = ValidationHelper::optionalInt($data, 'time_limit', 0);
        $questionsPerSession = ValidationHelper::optionalInt($data, 'questions_per_session', 0);
        $randomizeQuestions = ValidationHelper::optionalInt($data, 'randomize_questions', 1);
        $randomizeAnswers = ValidationHelper::optionalInt($data, 'randomize_answers', 1);

        $stmt = $this->modx->prepare("
            UPDATE {$this->prefix}test_tests
            SET title = ?,
                description = ?,
                is_active = ?,
                is_learning_material = ?,
                mode = ?,
                pass_score = ?,
                time_limit = ?,
                questions_per_session = ?,
                randomize_questions = ?,
                randomize_answers = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $title, $description, $isActive, $isLearningMaterial,
            $mode, $passScore, $timeLimit, $questionsPerSession,
            $randomizeQuestions, $randomizeAnswers, $testId
        ]);

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

        return $this->success(null, 'Тест успешно удален');
    }

    /**
     * Публикация теста (изменение статуса публикации)
     */
    private function publishTest($data)
    {
        $this->requireAuth();
        $currentUserId = $this->getCurrentUserId();
        $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');
        $publicationStatus = ValidationHelper::optionalString($data, 'status', 'private');
        $result = TestService::publishTest($this->modx, $testId, $publicationStatus, $currentUserId);
        return $this->success($result, 'Publication status updated');
    }

    /**
     * Получение публичного теста по slug
     */
    private function getPublicTestBySlug($data)
    {
        $slug = trim($data['slug'] ?? '');
        if (empty($slug)) {
            throw new ValidationException('Slug is required');
        }

        // Получаем тест по slug
        $stmt = $this->modx->prepare("
            SELECT id, title, description, mode, time_limit, pass_score,
                   questions_per_session, created_by, publication_status, public_url_slug
            FROM modx_test_tests
            WHERE public_url_slug = ?
              AND publication_status IN ('unlisted', 'public')
              AND is_active = 1
        ");
        $stmt->execute(array($slug));
        $test = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$test) {
            throw new Exception('Test not found');
        }

        // Подсчет опубликованных вопросов
        $stmt = $this->modx->prepare("
            SELECT COUNT(*) FROM modx_test_questions
            WHERE test_id = ? AND published = 1
        ");
        $stmt->execute(array($test['id']));
        $test['questions_count'] = (int)$stmt->fetchColumn();

        // Статистика прохождений
        $stmt = $this->modx->prepare("
            SELECT COUNT(*) as total_sessions,
                   AVG(score) as avg_score,
                   MAX(score) as max_score
            FROM modx_test_sessions
            WHERE test_id = ? AND status = 'completed'
        ");
        $stmt->execute(array($test['id']));
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $test['total_sessions'] = (int)($stats['total_sessions'] ?? 0);
        $test['avg_score'] = round((float)($stats['avg_score'] ?? 0), 1);
        $test['max_score'] = (float)($stats['max_score'] ?? 0);

        return $this->success($test);
    }

    /**
     * Проверка прав на создание ресурсов
     */
    private function checkResourcePermissions($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $user = $this->modx->getObject('modUser', $userId);

        if (!$user) {
            throw new Exception('User not found');
        }

        $canCreate = $user->hasPermission('new_document');
        $canSave = $user->hasPermission('save_document');

        $groups = $this->modx->user->getUserGroupNames();

        return $this->success(array(
            'user_id' => $userId,
            'can_create' => $canCreate,
            'can_save' => $canSave,
            'groups' => $groups
        ));
    }

    /**
     * Создание теста вместе со страницей
     */
    private function createTestWithPage($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();
        $title = ValidationHelper::requireString($data, 'title', 'Title is required');
        $description = ValidationHelper::optionalString($data, 'description');
        $publicationStatus = ValidationHelper::optionalString($data, 'publication_status', 'draft');
        $result = TestService::createTestWithPage($this->modx, $title, $description, $publicationStatus, $userId);
        return $this->success($result, 'Test and page created successfully');
    }

    /**
     * Создание теста
     */
    private function createTest($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $title = ValidationHelper::requireString($data, 'title', 'Title is required');
        $description = ValidationHelper::optionalString($data, 'description');
        $publicationStatus = ValidationHelper::optionalString($data, 'publication_status', 'draft');

        // Валидация статуса
        $allowedStatuses = array('draft', 'private', 'unlisted', 'public');
        if (!in_array($publicationStatus, $allowedStatuses, true)) {
            $publicationStatus = 'draft';
        }

        // Проверяем права пользователя
        $rights = PermissionHelper::getUserRights($this->modx);

        if (!$rights['canEdit']) {
            // Обычные пользователи могут создавать только private/draft тесты
            if (!in_array($publicationStatus, array('private', 'draft'), true)) {
                $publicationStatus = 'private';
            }

            // Лимит на количество тестов для обычных пользователей
            $stmt = $this->modx->prepare("
                SELECT COUNT(*) FROM modx_test_tests
                WHERE created_by = ? AND is_active = 1
            ");
            $stmt->execute(array($userId));
            $testCount = (int)$stmt->fetchColumn();

            if ($testCount >= 10) {
                throw new PermissionException('You have reached the maximum number of tests (10)');
            }
        }

        // Создаём тест
        $stmt = $this->modx->prepare("
            INSERT INTO modx_test_tests
            (title, description, created_by, created_at, publication_status, is_active,
             mode, time_limit, pass_score, questions_per_session)
            VALUES (?, ?, ?, NOW(), ?, 1, 'training', 0, 70, 20)
        ");

        if (!$stmt || !$stmt->execute(array($title, $description, $userId, $publicationStatus))) {
            throw new Exception('Failed to create test');
        }

        $testId = (int)$this->modx->lastInsertId();

        return $this->success(array('test_id' => $testId), 'Test created successfully');
    }

    /**
     * Создание страницы для теста
     */
    private function createTestPage($data)
    {
        throw new ValidationException('createTestPage устарел: тесты больше не привязываются к ресурсам MODX.');
    }

    /**
     * Получение списка тестов текущего пользователя
     */
    private function getMyTests($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $stmt = $this->modx->prepare("
            SELECT t.id, t.title, t.description, t.publication_status,
                   t.created_at, t.mode, t.time_limit, t.pass_score,
                   (SELECT COUNT(*) FROM modx_test_questions q WHERE q.test_id = t.id AND q.published = 1) as questions_count,
                   (SELECT COUNT(*) FROM modx_test_permissions p WHERE p.test_id = t.id) as shared_with_count
            FROM modx_test_tests t
            WHERE t.created_by = ? AND t.is_active = 1
            ORDER BY t.created_at DESC
        ");
        $stmt->execute(array($userId));
        $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        UrlHelper::addUrlsToTests($this->modx, $tests);

        return $this->success($tests);
    }

    /**
     * Получение тестов, к которым предоставлен доступ
     */
    private function getSharedWithMe($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $stmt = $this->modx->prepare("
            SELECT t.id, t.title, t.description, t.publication_status,
                   t.created_at, t.mode, t.created_by,
                   p.can_edit, p.granted_at
            FROM modx_test_tests t
            INNER JOIN modx_test_permissions p ON p.test_id = t.id
            WHERE p.user_id = ? AND t.is_active = 1
            ORDER BY p.granted_at DESC
        ");
        $stmt->execute(array($userId));
        $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        UrlHelper::addUrlsToTests($this->modx, $tests);

        return $this->success($tests);
    }

    /**
     * Получение публичных тестов
     */
    private function getPublicTests($data)
    {
        $stmt = $this->modx->prepare("
            SELECT t.id, t.title, t.description, t.publication_status,
                   t.created_at, t.mode, t.created_by, t.public_url_slug,
                   (SELECT COUNT(*)
                      FROM {$this->prefix}test_questions q
                     WHERE q.test_id = t.id AND q.published = 1) as questions_count,
                   COALESCE(NULLIF(ua.fullname, ''), u.username) as creator_name
            FROM {$this->prefix}test_tests t
            LEFT JOIN {$this->prefix}users u ON u.id = t.created_by
            LEFT JOIN {$this->prefix}user_attributes ua ON ua.internalKey = u.id
            WHERE t.publication_status = 'public' AND t.is_active = 1
            ORDER BY t.created_at DESC
            LIMIT 100
        ");
        $stmt->execute();
        $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        UrlHelper::addUrlsToTests($this->modx, $tests);

        return $this->success($tests);
    }

    /**
     * Предоставить доступ к приватному тесту
     */
    private function grantTestAccess($data)
    {
        $this->requireAuth();
        $currentUserId = $this->getCurrentUserId();

        $testId = ValidationHelper::requireInt($data, 'test_id', 'Test ID required');
        $targetUserId = ValidationHelper::requireInt($data, 'user_id', 'User ID required');
        $canView = ValidationHelper::optionalBool($data, 'can_view', true);
        $canEdit = ValidationHelper::optionalBool($data, 'can_edit', false);
        $expiresAt = $data['expires_at'] ?? null;

        $stmt = $this->modx->prepare("SELECT created_by, publication_status FROM {$this->prefix}test_tests WHERE id = ?");
        $stmt->execute(array($testId));
        $test = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$test) {
            throw new Exception('Test not found');
        }

        $isOwner = ((int)$test['created_by'] === $currentUserId);
        $isAdmin = PermissionHelper::isAdmin($this->modx);

        if (!$isOwner && !$isAdmin) {
            throw new PermissionException('Only test owner or admin can grant access');
        }

        $stmt = $this->modx->prepare("SELECT id FROM {$this->prefix}users WHERE id = ?");
        $stmt->execute(array($targetUserId));
        if (!$stmt->fetch()) {
            throw new Exception('User not found');
        }

        $stmt = $this->modx->prepare("
            INSERT INTO {$this->prefix}test_permissions
            (test_id, user_id, granted_by, can_view, can_edit, expires_at, granted_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                can_view = VALUES(can_view),
                can_edit = VALUES(can_edit),
                granted_by = VALUES(granted_by),
                expires_at = VALUES(expires_at),
                granted_at = NOW()
        ");

        $stmt->execute(array(
            $testId,
            $targetUserId,
            $currentUserId,
            $canView ? 1 : 0,
            $canEdit ? 1 : 0,
            $expiresAt
        ));

        return $this->success(array(
            'test_id' => $testId,
            'user_id' => $targetUserId,
            'can_view' => $canView,
            'can_edit' => $canEdit
        ), 'Access granted successfully');
    }

    /**
     * Отозвать доступ к приватному тесту
     */
    private function revokeTestAccess($data)
    {
        $this->requireAuth();
        $currentUserId = $this->getCurrentUserId();

        $testId = ValidationHelper::requireInt($data, 'test_id', 'Test ID required');
        $targetUserId = ValidationHelper::requireInt($data, 'user_id', 'User ID required');

        $stmt = $this->modx->prepare("SELECT created_by FROM {$this->prefix}test_tests WHERE id = ?");
        $stmt->execute(array($testId));
        $test = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$test) {
            throw new Exception('Test not found');
        }

        $isOwner = ((int)$test['created_by'] === $currentUserId);
        $isAdmin = PermissionHelper::isAdmin($this->modx);

        if (!$isOwner && !$isAdmin) {
            throw new PermissionException('Only test owner or admin can revoke access');
        }

        $stmt = $this->modx->prepare("
            DELETE FROM {$this->prefix}test_permissions
            WHERE test_id = ? AND user_id = ?
        ");
        $stmt->execute(array($testId, $targetUserId));

        return $this->success(null, 'Access revoked successfully');
    }

    /**
     * Получить список пользователей с доступом к тесту
     */
    private function getTestPermissions($data)
    {
        $this->requireAuth();
        $currentUserId = $this->getCurrentUserId();

        $testId = ValidationHelper::requireInt($data, 'test_id', 'Test ID required');

        $stmt = $this->modx->prepare("SELECT created_by, title, publication_status FROM {$this->prefix}test_tests WHERE id = ?");
        $stmt->execute(array($testId));
        $test = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$test) {
            throw new Exception('Test not found');
        }

        $isOwner = ((int)$test['created_by'] === $currentUserId);
        $isAdmin = PermissionHelper::isAdmin($this->modx);

        if (!$isOwner && !$isAdmin) {
            throw new PermissionException('Only test owner or admin can view permissions');
        }

        $stmt = $this->modx->prepare("
            SELECT
                tp.id,
                tp.user_id,
                u.username,
                ua.fullname,
                ua.email,
                tp.can_view,
                tp.can_edit,
                tp.granted_at,
                tp.expires_at,
                granted_by_user.username as granted_by_username
            FROM {$this->prefix}test_permissions tp
            JOIN {$this->prefix}users u ON u.id = tp.user_id
            LEFT JOIN {$this->prefix}user_attributes ua ON ua.internalKey = u.id
            LEFT JOIN {$this->prefix}users granted_by_user ON granted_by_user.id = tp.granted_by
            WHERE tp.test_id = ?
            ORDER BY tp.granted_at DESC
        ");
        $stmt->execute(array($testId));
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->success(array(
            'test' => $test,
            'permissions' => $permissions
        ));
    }

    /**
     * Получить список пользователей для предоставления доступа
     */
    private function getAvailableUsersForTest($data)
    {
        $this->requireAuth();
        $currentUserId = $this->getCurrentUserId();

        $testId = ValidationHelper::requireInt($data, 'test_id', 'Test ID required');

        $stmt = $this->modx->prepare("SELECT created_by FROM {$this->prefix}test_tests WHERE id = ?");
        $stmt->execute(array($testId));
        $test = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$test) {
            throw new Exception('Test not found');
        }

        $isOwner = ((int)$test['created_by'] === $currentUserId);
        $isAdmin = PermissionHelper::isAdmin($this->modx);

        if (!$isOwner && !$isAdmin) {
            throw new PermissionException('Only test owner or admin can manage permissions');
        }

        $stmt = $this->modx->prepare("
            SELECT DISTINCT
                u.id,
                u.username,
                ua.fullname,
                ua.email
            FROM {$this->prefix}users u
            LEFT JOIN {$this->prefix}user_attributes ua ON ua.internalKey = u.id
            WHERE u.id NOT IN (
                SELECT user_id FROM {$this->prefix}test_permissions WHERE test_id = ?
            )
            AND u.id != ?
            AND u.id != ?
            ORDER BY u.username
            LIMIT 100
        ");
        $stmt->execute(array($testId, $currentUserId, (int)$test['created_by']));
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->success($users);
    }

    /**
     * Проверить права доступа к тесту для текущего пользователя
     */
    private function checkTestAccess($data)
    {
        $this->requireAuth();
        $currentUserId = $this->getCurrentUserId();

        $testId = ValidationHelper::requireInt($data, 'test_id', 'Test ID required');

        $stmt = $this->modx->prepare("
            SELECT created_by, publication_status, is_active
            FROM {$this->prefix}test_tests
            WHERE id = ?
        ");
        $stmt->execute(array($testId));
        $test = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$test) {
            throw new Exception('Test not found');
        }

        if (PermissionHelper::isAdmin($this->modx)) {
            return $this->success(array(
                'has_access' => true,
                'can_view' => true,
                'can_edit' => true,
                'is_owner' => ((int)$test['created_by'] === $currentUserId),
                'access_type' => 'admin'
            ));
        }

        if ((int)$test['created_by'] === $currentUserId) {
            return $this->success(array(
                'has_access' => true,
                'can_view' => true,
                'can_edit' => true,
                'is_owner' => true,
                'access_type' => 'owner'
            ));
        }

        if (in_array($test['publication_status'], array('public', 'unlisted'))) {
            return $this->success(array(
                'has_access' => true,
                'can_view' => true,
                'can_edit' => false,
                'is_owner' => false,
                'access_type' => 'public'
            ));
        }

        $stmt = $this->modx->prepare("
            SELECT can_view, can_edit, expires_at
            FROM {$this->prefix}test_permissions
            WHERE test_id = ? AND user_id = ?
        ");
        $stmt->execute(array($testId, $currentUserId));
        $permission = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($permission) {
            $isExpired = false;
            if ($permission['expires_at'] !== null) {
                $expiresAt = strtotime($permission['expires_at']);
                $isExpired = ($expiresAt < time());
            }

            if (!$isExpired) {
                return $this->success(array(
                    'has_access' => true,
                    'can_view' => (bool)$permission['can_view'],
                    'can_edit' => (bool)$permission['can_edit'],
                    'is_owner' => false,
                    'access_type' => 'shared',
                    'expires_at' => $permission['expires_at']
                ));
            }
        }

        return $this->success(array(
            'has_access' => false,
            'can_view' => false,
            'can_edit' => false,
            'is_owner' => false,
            'access_type' => 'none'
        ));
    }

    /**
     * Поиск пользователей (для предоставления доступа к тесту)
     */
    private function searchUsers($data)
    {
        $this->requireAuth();
        $currentUserId = $this->getCurrentUserId();
        $rights = PermissionHelper::getUserRights($this->modx);

        $query = ValidationHelper::requireString($data, 'query', 'Search query required');
        $testId = ValidationHelper::optionalInt($data, 'test_id', 0);

        if (strlen($query) < 2) {
            throw new ValidationException('Search query too short (min 2 chars)');
        }

        if ($testId > 0) {
            $testOwnerId = TestRepository::getTestOwner($this->modx, $testId);

            if ($testOwnerId === false) {
                throw new Exception('Test not found');
            }

            $canSearch = $rights['canEdit'] || ($testOwnerId === $currentUserId);

            if (!$canSearch) {
                throw new PermissionException('Permission denied');
            }
        } elseif (!$rights['canEdit']) {
            throw new PermissionException('Permission denied');
        }

        $searchPattern = '%' . $query . '%';

        $stmt = $this->modx->prepare("
            SELECT u.id, u.username, up.email, up.fullname
            FROM {$this->prefix}users u
            LEFT JOIN {$this->prefix}user_attributes up ON up.internalKey = u.id
            WHERE (u.username LIKE ? OR up.email LIKE ? OR up.fullname LIKE ?)
            AND u.id != ?
            ORDER BY u.username
            LIMIT 20
        ");

        $stmt->execute(array($searchPattern, $searchPattern, $searchPattern, $currentUserId));
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($testId > 0 && !empty($users)) {
            $userIds = array_column($users, 'id');
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $stmt = $this->modx->prepare("
                SELECT user_id, can_edit
                FROM {$this->prefix}test_permissions
                WHERE test_id = ? AND user_id IN ($placeholders)
            ");
            $params = array_merge(array($testId), $userIds);
            $stmt->execute($params);
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $permMap = array();
            foreach ($permissions as $perm) {
                $permMap[(int)$perm['user_id']] = (bool)$perm['can_edit'];
            }

            foreach ($users as &$user) {
                $user['has_access'] = isset($permMap[(int)$user['id']]);
                $user['can_edit'] = isset($permMap[(int)$user['id']]) ? $permMap[(int)$user['id']] : false;
            }
        }

        return $this->success($users);
    }

    /**
     * Предоставить доступ (legacy alias для grantTestAccess с уведомлениями)
     */
    private function grantAccess($data)
    {
        $this->requireAuth();
        $currentUserId = $this->getCurrentUserId();
        $rights = PermissionHelper::getUserRights($this->modx);

        $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');
        $targetUserId = ValidationHelper::requireInt($data, 'user_id', 'User ID required');
        $canEdit = ValidationHelper::optionalInt($data, 'can_edit', 0);

        $stmt = $this->modx->prepare("SELECT created_by, title FROM {$this->prefix}test_tests WHERE id = ?");
        $stmt->execute(array($testId));
        $test = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$test) {
            throw new Exception('Test not found');
        }

        $canGrant = $rights['canEdit'] || ((int)$test['created_by'] === $currentUserId);

        if (!$canGrant) {
            throw new PermissionException('Permission denied');
        }

        $stmt = $this->modx->prepare("
            INSERT INTO {$this->prefix}test_permissions (test_id, user_id, granted_by, can_edit)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE can_edit = VALUES(can_edit), granted_by = VALUES(granted_by), granted_at = NOW()
        ");

        $stmt->execute(array($testId, $targetUserId, $currentUserId, $canEdit));

        // Отправляем уведомление
        $stmt = $this->modx->prepare("SELECT username FROM {$this->prefix}users WHERE id = ?");
        $stmt->execute(array($currentUserId));
        $initiatorName = $stmt->fetchColumn();

        $editText = $canEdit ? ' с правами редактирования' : '';
        $message = "Пользователь {$initiatorName} предоставил вам доступ{$editText} к тесту \"{$test['title']}\"";

        $stmt = $this->modx->prepare("
            INSERT INTO {$this->prefix}test_notifications (user_id, type, test_id, initiator_id, message)
            VALUES (?, 'access_granted', ?, ?, ?)
        ");
        $stmt->execute(array($targetUserId, $testId, $currentUserId, $message));

        return $this->success(null, 'Access granted');
    }

    /**
     * Отозвать доступ (legacy alias для revokeTestAccess)
     */
    private function revokeAccess($data)
    {
        $this->requireAuth();
        $currentUserId = $this->getCurrentUserId();
        $rights = PermissionHelper::getUserRights($this->modx);

        $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');
        $targetUserId = ValidationHelper::requireInt($data, 'user_id', 'User ID required');

        $testOwnerId = TestRepository::getTestOwner($this->modx, $testId);

        if ($testOwnerId === false) {
            throw new Exception('Test not found');
        }

        $canRevoke = $rights['canEdit'] || ($testOwnerId === $currentUserId);

        if (!$canRevoke) {
            throw new PermissionException('Permission denied');
        }

        $stmt = $this->modx->prepare("DELETE FROM {$this->prefix}test_permissions WHERE test_id = ? AND user_id = ?");
        $stmt->execute(array($testId, $targetUserId));

        return $this->success(null, 'Access revoked');
    }

    /**
     * Проверка прав редактирования текущего пользователя
     */
    private function checkEditRights($data)
    {
        $rights = PermissionHelper::getUserRights($this->modx);

        return $this->success($rights);
    }
}
