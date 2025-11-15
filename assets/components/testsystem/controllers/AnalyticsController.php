<?php
/**
 * Analytics Controller
 *
 * Контроллер для работы с аналитикой и отчетами
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

require_once __DIR__ . '/BaseController.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/AnalyticsService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/ReportService.php';

class AnalyticsController extends BaseController
{
    /**
     * Обработка действий
     *
     * @param string $action Название действия
     * @param array $data Данные запроса
     * @return array Ответ
     */
    public function handle($action, $data)
    {
        try {
            switch ($action) {
                case 'getMyStatistics':
                    return $this->getMyStatistics($data);

                case 'getUserStatistics':
                    return $this->getUserStatistics($data);

                case 'getTestStatistics':
                    return $this->getTestStatistics($data);

                case 'getCategoryStatistics':
                    return $this->getCategoryStatistics($data);

                case 'getQuestionStatistics':
                    return $this->getQuestionStatistics($data);

                case 'getTopUsers':
                    return $this->getTopUsers($data);

                case 'getHardestQuestions':
                    return $this->getHardestQuestions($data);

                case 'getScoreDistribution':
                    return $this->getScoreDistribution($data);

                case 'getCohortAnalysis':
                    return $this->getCohortAnalysis($data);

                case 'getUserActivitySummary':
                    return $this->getUserActivitySummary($data);

                case 'getAdminDashboard':
                    return $this->getAdminDashboard($data);

                case 'getMyDashboard':
                    return $this->getMyDashboard($data);

                case 'getUserComparison':
                    return $this->getUserComparison($data);

                case 'generateReport':
                    return $this->generateReport($data);

                case 'getReportHistory':
                    return $this->getReportHistory($data);

                case 'cleanupCache':
                    return $this->cleanupCache($data);

                default:
                    return ResponseHelper::error('Unknown action: ' . $action, 404);
            }
        } catch (AuthenticationException $e) {
            return ResponseHelper::error($e->getMessage(), 401);
        } catch (PermissionException $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        } catch (ValidationException $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'AnalyticsController error: ' . $e->getMessage());
            return ResponseHelper::error('An error occurred while processing your request', 500);
        }
    }

    /**
     * Получить статистику текущего пользователя
     *
     * @param array $data
     * @return array
     */
    private function getMyStatistics($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $period = ValidationHelper::optionalString($data, 'period', AnalyticsService::PERIOD_ALL_TIME);
        $useCache = ValidationHelper::optionalBool($data, 'use_cache', true);

        $stats = AnalyticsService::getUserStatistics($this->modx, $userId, $period, $useCache);

        if (!$stats) {
            return ResponseHelper::error('Failed to load statistics', 500);
        }

        return ResponseHelper::success('Statistics loaded successfully', $stats);
    }

    /**
     * Получить статистику пользователя (для админов/экспертов)
     *
     * @param array $data
     * @return array
     */
    private function getUserStatistics($data)
    {
        $this->requireAuth();
        $this->requireViewRights('Only admins and experts can view user statistics');

        $userId = ValidationHelper::requireInt($data, 'user_id', 'User ID is required');
        $period = ValidationHelper::optionalString($data, 'period', AnalyticsService::PERIOD_ALL_TIME);
        $useCache = ValidationHelper::optionalBool($data, 'use_cache', true);

        $stats = AnalyticsService::getUserStatistics($this->modx, $userId, $period, $useCache);

        if (!$stats) {
            return ResponseHelper::error('Failed to load statistics', 500);
        }

        return ResponseHelper::success('Statistics loaded successfully', $stats);
    }

    /**
     * Получить статистику теста
     *
     * @param array $data
     * @return array
     */
    private function getTestStatistics($data)
    {
        $this->requireAuth();

        $testId = ValidationHelper::requireInt($data, 'test_id', 'Test ID is required');
        $period = ValidationHelper::optionalString($data, 'period', AnalyticsService::PERIOD_ALL_TIME);
        $useCache = ValidationHelper::optionalBool($data, 'use_cache', true);

        $stats = AnalyticsService::getTestStatistics($this->modx, $testId, $period, $useCache);

        if (!$stats) {
            return ResponseHelper::error('Failed to load statistics', 500);
        }

        return ResponseHelper::success('Statistics loaded successfully', $stats);
    }

    /**
     * Получить статистику категории
     *
     * @param array $data
     * @return array
     */
    private function getCategoryStatistics($data)
    {
        $this->requireAuth();

        $categoryId = ValidationHelper::optionalInt($data, 'category_id', null);

        if ($categoryId) {
            $stats = AnalyticsService::getCategoryStatistics($this->modx, $categoryId);
        } else {
            $stats = AnalyticsService::getAllCategoriesStatistics($this->modx);
        }

        return ResponseHelper::success('Statistics loaded successfully', [
            'statistics' => $stats
        ]);
    }

    /**
     * Получить статистику вопроса
     *
     * @param array $data
     * @return array
     */
    private function getQuestionStatistics($data)
    {
        $this->requireAuth();
        $this->requireViewRights('Only admins and experts can view question statistics');

        $questionId = ValidationHelper::requireInt($data, 'question_id', 'Question ID is required');

        $stats = AnalyticsService::getQuestionStatistics($this->modx, $questionId);

        if (!$stats) {
            return ResponseHelper::error('Question not found or no statistics available', 404);
        }

        return ResponseHelper::success('Statistics loaded successfully', $stats);
    }

    /**
     * Получить топ пользователей
     *
     * @param array $data
     * @return array
     */
    private function getTopUsers($data)
    {
        $this->requireAuth();

        $limit = ValidationHelper::optionalInt($data, 'limit', 10);
        $categoryId = ValidationHelper::optionalInt($data, 'category_id', null);

        if ($limit > 100) {
            $limit = 100;
        }

        $users = AnalyticsService::getTopUsers($this->modx, $limit, $categoryId);

        return ResponseHelper::success('Top users loaded successfully', [
            'users' => $users,
            'count' => count($users)
        ]);
    }

    /**
     * Получить самые сложные вопросы
     *
     * @param array $data
     * @return array
     */
    private function getHardestQuestions($data)
    {
        $this->requireAuth();
        $this->requireViewRights('Only admins and experts can view question difficulty');

        $limit = ValidationHelper::optionalInt($data, 'limit', 10);
        $testId = ValidationHelper::optionalInt($data, 'test_id', null);

        if ($limit > 50) {
            $limit = 50;
        }

        $questions = AnalyticsService::getHardestQuestions($this->modx, $limit, $testId);

        return ResponseHelper::success('Hardest questions loaded successfully', [
            'questions' => $questions,
            'count' => count($questions)
        ]);
    }

    /**
     * Получить распределение баллов для теста
     *
     * @param array $data
     * @return array
     */
    private function getScoreDistribution($data)
    {
        $this->requireAuth();

        $testId = ValidationHelper::requireInt($data, 'test_id', 'Test ID is required');

        $distribution = AnalyticsService::getScoreDistribution($this->modx, $testId);

        return ResponseHelper::success('Score distribution loaded successfully', [
            'distribution' => $distribution
        ]);
    }

    /**
     * Получить когортный анализ
     *
     * @param array $data
     * @return array
     */
    private function getCohortAnalysis($data)
    {
        $this->requireAuth();
        $this->requireAdmin('Only admins can view cohort analysis');

        $startDate = ValidationHelper::optionalString($data, 'start_date', date('Y-m-d', strtotime('-6 months')));
        $endDate = ValidationHelper::optionalString($data, 'end_date', date('Y-m-d'));

        // Валидация дат
        if (!strtotime($startDate) || !strtotime($endDate)) {
            throw new ValidationException('Invalid date format');
        }

        $cohorts = AnalyticsService::getCohortAnalysis($this->modx, $startDate, $endDate);

        return ResponseHelper::success('Cohort analysis loaded successfully', [
            'cohorts' => $cohorts,
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
    }

    /**
     * Получить сводку активности пользователей
     *
     * @param array $data
     * @return array
     */
    private function getUserActivitySummary($data)
    {
        $this->requireAuth();
        $this->requireViewRights('Only admins and experts can view activity summary');

        $startDate = ValidationHelper::optionalString($data, 'start_date', date('Y-m-d', strtotime('-30 days')));
        $endDate = ValidationHelper::optionalString($data, 'end_date', date('Y-m-d'));

        // Валидация дат
        if (!strtotime($startDate) || !strtotime($endDate)) {
            throw new ValidationException('Invalid date format');
        }

        $activity = AnalyticsService::getUserActivitySummary($this->modx, $startDate, $endDate);

        return ResponseHelper::success('Activity summary loaded successfully', [
            'activity' => $activity,
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
    }

    /**
     * Получить дашборд администратора
     *
     * @param array $data
     * @return array
     */
    private function getAdminDashboard($data)
    {
        $this->requireAuth();
        $this->requireViewRights('Only admins and experts can view admin dashboard');

        $dashboard = AnalyticsService::getAdminDashboard($this->modx);

        return ResponseHelper::success('Dashboard loaded successfully', $dashboard);
    }

    /**
     * Получить дашборд пользователя
     *
     * @param array $data
     * @return array
     */
    private function getMyDashboard($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $dashboard = AnalyticsService::getUserDashboard($this->modx, $userId);

        return ResponseHelper::success('Dashboard loaded successfully', $dashboard);
    }

    /**
     * Получить сравнение пользователя с другими
     *
     * @param array $data
     * @return array
     */
    private function getUserComparison($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $categoryId = ValidationHelper::optionalInt($data, 'category_id', null);

        $comparison = AnalyticsService::getUserComparison($this->modx, $userId, $categoryId);

        return ResponseHelper::success('Comparison loaded successfully', $comparison);
    }

    /**
     * Сгенерировать отчет
     *
     * @param array $data
     * @return array
     */
    private function generateReport($data)
    {
        $this->requireAuth();
        $this->requireViewRights('Only admins and experts can generate reports');

        $reportType = ValidationHelper::requireString($data, 'report_type', 'Report type is required');
        $format = ValidationHelper::optionalString($data, 'format', ReportService::FORMAT_JSON);
        $filters = $data['filters'] ?? [];

        // Валидация формата
        $allowedFormats = [
            ReportService::FORMAT_CSV,
            ReportService::FORMAT_JSON,
            ReportService::FORMAT_HTML
        ];

        if (!in_array($format, $allowedFormats, true)) {
            throw new ValidationException('Invalid format. Allowed: ' . implode(', ', $allowedFormats));
        }

        $userId = $this->getCurrentUserId();
        $result = ReportService::generateReport($this->modx, $reportType, $format, $filters, $userId);

        if (!$result) {
            return ResponseHelper::error('Failed to generate report', 500);
        }

        return ResponseHelper::success('Report generated successfully', $result);
    }

    /**
     * Получить историю генерации отчетов
     *
     * @param array $data
     * @return array
     */
    private function getReportHistory($data)
    {
        $this->requireAuth();

        $limit = ValidationHelper::optionalInt($data, 'limit', 50);
        $isAdmin = $this->isAdmin() || $this->isExpert();

        // Админы/эксперты видят все отчеты, пользователи - только свои
        $userId = $isAdmin ? null : $this->getCurrentUserId();

        $history = ReportService::getReportHistory($this->modx, $userId, $limit);

        return ResponseHelper::success('Report history loaded successfully', [
            'history' => $history,
            'count' => count($history)
        ]);
    }

    /**
     * Очистить кеш аналитики (для админов)
     *
     * @param array $data
     * @return array
     */
    private function cleanupCache($data)
    {
        $this->requireAuth();
        $this->requireAdmin('Only admins can cleanup analytics cache');

        $deletedRows = AnalyticsService::cleanupCache($this->modx);
        $deletedFiles = ReportService::cleanupOldReports($this->modx);

        return ResponseHelper::success('Cache cleaned up successfully', [
            'deleted_cache_rows' => $deletedRows,
            'deleted_report_files' => $deletedFiles
        ]);
    }
}
