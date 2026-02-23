<?php
/**
 * Admin Controller
 *
 * Контроллер для административных операций
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class AdminController extends BaseController
{
    /**
     * Список доступных действий
     */
    private $actions = [
        'checkIntegrity',
        'cleanOrphanedData',
        'cleanOrphanedTests',
        'cleanOrphanedQuestions',
        'cleanOrphanedAnswers',
        'cleanOrphanedSessions',
        'cleanOldSessions',
        'getSystemStats',
        'checkSiteSettings',
        'cleanupResourceFiles',
        'diagnoseMaterialsAuth'
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
                case 'checkIntegrity':
                    return $this->checkIntegrity($data);

                case 'cleanOrphanedData':
                    return $this->cleanOrphanedData($data);

                case 'cleanOrphanedTests':
                    return $this->cleanOrphanedTests($data);

                case 'cleanOrphanedQuestions':
                    return $this->cleanOrphanedQuestions($data);

                case 'cleanOrphanedAnswers':
                    return $this->cleanOrphanedAnswers($data);

                case 'cleanOrphanedSessions':
                    return $this->cleanOrphanedSessions($data);

                case 'cleanOldSessions':
                    return $this->cleanOldSessions($data);

                case 'getSystemStats':
                    return $this->getSystemStats($data);

                case 'checkSiteSettings':
                    return $this->checkSiteSettings($data);

                case 'cleanupResourceFiles':
                    return $this->cleanupResourceFiles($data);

                case 'diagnoseMaterialsAuth':
                    return $this->diagnoseMaterialsAuth($data);

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
     * Проверка прав администратора
     *
     * @throws PermissionException
     */
    private function requireAdminRights()
    {
        $this->requireAuth();

        $userId = $this->getCurrentUserId();
        $userGroups = $this->modx->user->getUserGroups();

        $adminGroup = Config::getGroup('admins');
        $isAdmin = in_array($adminGroup, $userGroups, true) || $userId === 1;

        if (!$isAdmin) {
            throw new PermissionException('Admin rights required');
        }
    }

    /**
     * Проверка целостности данных
     */
    private function checkIntegrity($data)
    {
        $this->requireAdminRights();

        $report = DataIntegrityService::checkIntegrity($this->modx);

        return $this->success($report);
    }

    /**
     * Полная очистка всех осиротевших данных
     */
    private function cleanOrphanedData($data)
    {
        $this->requireAdminRights();

        // Подтверждение требуется для массового удаления
        $confirmed = ValidationHelper::optionalInt($data, 'confirmed', 0);

        if (!$confirmed) {
            // Возвращаем preview того, что будет удалено
            $report = DataIntegrityService::checkIntegrity($this->modx);

            return $this->success([
                'preview' => true,
                'report' => $report,
                'message' => 'Please confirm deletion by setting confirmed=1'
            ]);
        }

        $result = DataIntegrityService::cleanAll($this->modx);

        return $this->success($result, 'Orphaned data cleaned successfully');
    }

    /**
     * Очистка осиротевших тестов
     */
    private function cleanOrphanedTests($data)
    {
        $this->requireAdminRights();

        $testIds = $data['test_ids'] ?? [];

        $result = DataIntegrityService::cleanOrphanedTests($this->modx, $testIds);

        return $this->success($result);
    }

    /**
     * Очистка осиротевших вопросов
     */
    private function cleanOrphanedQuestions($data)
    {
        $this->requireAdminRights();

        $questionIds = $data['question_ids'] ?? [];

        $result = DataIntegrityService::cleanOrphanedQuestions($this->modx, $questionIds);

        return $this->success($result);
    }

    /**
     * Очистка осиротевших ответов
     */
    private function cleanOrphanedAnswers($data)
    {
        $this->requireAdminRights();

        $answerIds = $data['answer_ids'] ?? [];

        $result = DataIntegrityService::cleanOrphanedAnswers($this->modx, $answerIds);

        return $this->success($result);
    }

    /**
     * Очистка осиротевших сессий
     */
    private function cleanOrphanedSessions($data)
    {
        $this->requireAdminRights();

        $sessionIds = $data['session_ids'] ?? [];

        $result = DataIntegrityService::cleanOrphanedSessions($this->modx, $sessionIds);

        return $this->success($result);
    }

    /**
     * Очистка старых завершенных сессий
     */
    private function cleanOldSessions($data)
    {
        $this->requireAdminRights();

        $daysOld = ValidationHelper::optionalInt($data, 'days_old', 90);

        // Минимум 30 дней для безопасности
        if ($daysOld < 30) {
            $daysOld = 30;
        }

        $result = DataIntegrityService::cleanOldSessions($this->modx, $daysOld);

        return $this->success($result);
    }

    /**
     * Получение статистики системы
     */
    private function getSystemStats($data)
    {
        $this->requireAdminRights();

        $prefix = $this->prefix;

        $stats = [];

        // Количество тестов
        $stmt = $this->modx->query("SELECT COUNT(*) FROM {$prefix}test_tests");
        $stats['total_tests'] = (int)$stmt->fetchColumn();

        $stmt = $this->modx->query("SELECT COUNT(*) FROM {$prefix}test_tests WHERE is_active = 1");
        $stats['active_tests'] = (int)$stmt->fetchColumn();

        // Количество вопросов
        $stmt = $this->modx->query("SELECT COUNT(*) FROM {$prefix}test_questions");
        $stats['total_questions'] = (int)$stmt->fetchColumn();

        $stmt = $this->modx->query("SELECT COUNT(*) FROM {$prefix}test_questions WHERE published = 1");
        $stats['published_questions'] = (int)$stmt->fetchColumn();

        // Количество ответов
        $stmt = $this->modx->query("SELECT COUNT(*) FROM {$prefix}test_answers");
        $stats['total_answers'] = (int)$stmt->fetchColumn();

        // Количество категорий
        $stmt = $this->modx->query("SELECT COUNT(*) FROM {$prefix}test_categories");
        $stats['total_categories'] = (int)$stmt->fetchColumn();

        // Количество сессий
        $stmt = $this->modx->query("SELECT COUNT(*) FROM {$prefix}test_sessions");
        $stats['total_sessions'] = (int)$stmt->fetchColumn();

        $stmt = $this->modx->query("SELECT COUNT(*) FROM {$prefix}test_sessions WHERE status = 'completed'");
        $stats['completed_sessions'] = (int)$stmt->fetchColumn();

        $stmt = $this->modx->query("SELECT COUNT(*) FROM {$prefix}test_sessions WHERE status = 'in_progress'");
        $stats['active_sessions'] = (int)$stmt->fetchColumn();

        // Количество пользователей с активностью
        $stmt = $this->modx->query("SELECT COUNT(DISTINCT user_id) FROM {$prefix}test_sessions");
        $stats['active_users'] = (int)$stmt->fetchColumn();

        // Количество пользовательских ответов
        $stmt = $this->modx->query("SELECT COUNT(*) FROM {$prefix}test_user_answers");
        $stats['total_user_answers'] = (int)$stmt->fetchColumn();

        // Средний балл по всем сессиям
        $stmt = $this->modx->query("
            SELECT AVG(score_pct) FROM {$prefix}test_sessions
            WHERE status = 'completed' AND score_pct IS NOT NULL
        ");
        $stats['avg_score'] = round((float)$stmt->fetchColumn(), 2);

        // Топ-5 популярных тестов (по количеству сессий)
        $stmt = $this->modx->query("
            SELECT t.id, t.title, COUNT(s.id) as session_count
            FROM {$prefix}test_tests t
            LEFT JOIN {$prefix}test_sessions s ON s.test_id = t.id
            WHERE t.is_active = 1
            GROUP BY t.id, t.title
            ORDER BY session_count DESC
            LIMIT 5
        ");
        $stats['popular_tests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Активность за последние 30 дней
        $stmt = $this->modx->query("
            SELECT COUNT(*) FROM {$prefix}test_sessions
            WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stats['sessions_last_30_days'] = (int)$stmt->fetchColumn();

        // Размер базы данных (приблизительно)
        $stmt = $this->modx->query("
            SELECT
                SUM(data_length + index_length) as size_bytes
            FROM information_schema.TABLES
            WHERE table_schema = DATABASE()
              AND table_name LIKE '{$prefix}test_%'
        ");
        $sizeBytes = (int)$stmt->fetchColumn();
        $stats['database_size_mb'] = round($sizeBytes / 1024 / 1024, 2);

        return $this->success($stats);
    }

    /**
     * Проверка настроек сайта MODX
     */
    private function checkSiteSettings($data)
    {
        $this->requireAdminRights();

        return $this->success(array(
            'site_url' => $this->modx->getOption('site_url'),
            'base_url' => $this->modx->getOption('base_url'),
            'friendly_urls' => $this->modx->getOption('friendly_urls'),
            'use_alias_path' => $this->modx->getOption('use_alias_path'),
            'site_start' => $this->modx->getOption('site_start')
        ));
    }

    /**
     * Очистка файлов для конкретного ресурса
     */
    private function cleanupResourceFiles($data)
    {
        $this->requireAdminRights();

        $resourceId = ValidationHelper::requireInt($data, 'resource_id', 'Не указан ID ресурса');

        $deletedFiles = 0;
        $imagePath = MODX_BASE_PATH . 'assets/uploads/images/' . intval($resourceId) . '/';
        $documentPath = MODX_BASE_PATH . 'assets/uploads/documents/' . intval($resourceId) . '/';

        $deleteDirectory = function($dir) use (&$deleteDirectory, &$deletedFiles) {
            if (!is_dir($dir)) return false;
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    $deleteDirectory($path);
                } else {
                    unlink($path);
                    $deletedFiles++;
                }
            }
            return rmdir($dir);
        };

        $imageDeleted = $deleteDirectory($imagePath);
        $documentDeleted = $deleteDirectory($documentPath);

        return $this->success(array(
            'deleted_files' => $deletedFiles,
            'images_folder_deleted' => $imageDeleted,
            'documents_folder_deleted' => $documentDeleted
        ), 'Файлы очищены');
    }

    /**
     * Диагностика аутентификации для учебных материалов
     */
    private function diagnoseMaterialsAuth($data)
    {
        $this->requireAdminRights();

        $diagnosis = array(
            'user' => array(
                'id' => $this->modx->user->get('id'),
                'username' => $this->modx->user->get('username'),
            ),
            'authentication' => array(
                'web' => $this->modx->user->isAuthenticated('web'),
                'mgr' => $this->modx->user->isAuthenticated('mgr'),
                'helper_isAuthenticated' => PermissionHelper::isAuthenticated($this->modx),
                'helper_requireAuthentication_would_pass' => false,
            ),
            'permissions' => array(
                'isAdmin' => PermissionHelper::isAdmin($this->modx),
                'isExpert' => method_exists('PermissionHelper', 'isExpert') ? PermissionHelper::isExpert($this->modx) : 'method not exists',
            ),
            'methods_exist' => array(
                'getCurrentUserIdWithMgr' => method_exists('PermissionHelper', 'getCurrentUserIdWithMgr'),
            ),
        );

        try {
            PermissionHelper::requireAuthentication($this->modx, 'Test');
            $diagnosis['authentication']['helper_requireAuthentication_would_pass'] = true;
        } catch (Exception $e) {
            $diagnosis['authentication']['helper_requireAuthentication_error'] = $e->getMessage();
        }

        if (method_exists('PermissionHelper', 'getCurrentUserIdWithMgr')) {
            $diagnosis['user']['id_with_mgr'] = PermissionHelper::getCurrentUserIdWithMgr($this->modx);
        }

        return $this->success($diagnosis, 'Диагностика завершена');
    }
}
