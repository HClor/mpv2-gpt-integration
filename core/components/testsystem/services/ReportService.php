<?php
/**
 * Report Service
 *
 * Сервис для генерации и экспорта отчетов
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class ReportService
{
    /**
     * Форматы отчетов
     */
    const FORMAT_CSV = 'csv';
    const FORMAT_JSON = 'json';
    const FORMAT_HTML = 'html';
    const FORMAT_PDF = 'pdf';

    /**
     * Типы отчетов
     */
    const TYPE_USER_PROGRESS = 'user_progress';
    const TYPE_TEST_PERFORMANCE = 'test_performance';
    const TYPE_QUESTION_DIFFICULTY = 'question_difficulty';
    const TYPE_CATEGORY_OVERVIEW = 'category_overview';
    const TYPE_USER_ACTIVITY = 'user_activity';
    const TYPE_CUSTOM = 'custom';

    /**
     * Сгенерировать отчет
     *
     * @param modX $modx
     * @param string $reportType
     * @param string $format
     * @param array $filters
     * @param int $userId
     * @return array|false
     */
    public static function generateReport($modx, $reportType, $format, $filters = [], $userId = null)
    {
        $startTime = microtime(true);

        // Получаем данные для отчета
        $data = self::getReportData($modx, $reportType, $filters);

        if (!$data) {
            return false;
        }

        // Генерируем отчет в нужном формате
        $result = self::formatReport($modx, $data, $format, $reportType);

        if (!$result) {
            return false;
        }

        $generationTime = round(microtime(true) - $startTime, 3);

        // Логируем генерацию
        self::logReportGeneration($modx, null, $reportType, $userId, $result['file_path'] ?? null,
            $result['file_size'] ?? null, $format, $filters, $result['rows_count'] ?? null, $generationTime);

        return $result;
    }

    /**
     * Получить данные для отчета
     *
     * @param modX $modx
     * @param string $reportType
     * @param array $filters
     * @return array|null
     */
    private static function getReportData($modx, $reportType, $filters)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        switch ($reportType) {
            case self::TYPE_USER_PROGRESS:
                return self::getUserProgressData($modx, $filters);

            case self::TYPE_TEST_PERFORMANCE:
                return self::getTestPerformanceData($modx, $filters);

            case self::TYPE_QUESTION_DIFFICULTY:
                return self::getQuestionDifficultyData($modx, $filters);

            case self::TYPE_CATEGORY_OVERVIEW:
                return self::getCategoryOverviewData($modx, $filters);

            case self::TYPE_USER_ACTIVITY:
                return self::getUserActivityData($modx, $filters);

            default:
                return null;
        }
    }

    /**
     * Получить данные отчета по прогрессу пользователей
     *
     * @param modX $modx
     * @param array $filters
     * @return array
     */
    private static function getUserProgressData($modx, $filters)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $conditions = ["1=1"];
        $params = [];

        if (!empty($filters['user_id'])) {
            $conditions[] = "u.id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['min_tests'])) {
            $conditions[] = "tests_completed >= ?";
            $params[] = $filters['min_tests'];
        }

        $whereClause = implode(' AND ', $conditions);

        $stmt = $pdo->prepare("
            SELECT
                u.id as user_id,
                u.username,
                COUNT(DISTINCT s.id) as total_tests_taken,
                COUNT(DISTINCT CASE WHEN s.status = 'completed' THEN s.id END) as tests_completed,
                AVG(CASE WHEN s.status = 'completed' THEN s.score END) as avg_score,
                MAX(s.score) as max_score,
                MIN(s.score) as min_score,
                SUM(CASE WHEN s.score >= 70 THEN 1 ELSE 0 END) as tests_passed,
                SUM(CASE WHEN s.score < 70 THEN 1 ELSE 0 END) as tests_failed,
                COUNT(CASE WHEN s.score = 100 THEN 1 END) as perfect_scores,
                COALESCE(ue.total_xp, 0) as total_xp,
                COALESCE(ue.current_level, 1) as current_level,
                COUNT(DISTINCT ua.id) as achievements_count,
                MAX(s.completed_at) as last_test_date
            FROM {$prefix}users u
            LEFT JOIN {$prefix}test_sessions s ON s.user_id = u.id
            LEFT JOIN {$prefix}test_user_experience ue ON ue.user_id = u.id
            LEFT JOIN {$prefix}test_user_achievements ua ON ua.user_id = u.id
            WHERE $whereClause
            GROUP BY u.id
            HAVING tests_completed > 0
            ORDER BY avg_score DESC, tests_completed DESC
        ");
        $stmt->execute($params);

        return [
            'title' => 'Прогресс пользователей',
            'headers' => [
                'user_id' => 'ID пользователя',
                'username' => 'Имя пользователя',
                'tests_completed' => 'Тестов завершено',
                'avg_score' => 'Средний балл',
                'max_score' => 'Макс. балл',
                'tests_passed' => 'Сдано',
                'perfect_scores' => 'Идеальные результаты',
                'current_level' => 'Уровень',
                'total_xp' => 'Всего XP',
                'achievements_count' => 'Достижений',
                'last_test_date' => 'Последний тест'
            ],
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * Получить данные отчета по эффективности тестов
     *
     * @param modX $modx
     * @param array $filters
     * @return array
     */
    private static function getTestPerformanceData($modx, $filters)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $conditions = ["1=1"];
        $params = [];

        if (!empty($filters['category_id'])) {
            $conditions[] = "t.category_id = ?";
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['test_id'])) {
            $conditions[] = "t.id = ?";
            $params[] = $filters['test_id'];
        }

        $whereClause = implode(' AND ', $conditions);

        $stmt = $pdo->prepare("
            SELECT *
            FROM {$prefix}test_test_statistics
            WHERE $whereClause
            ORDER BY avg_score DESC
        ");
        $stmt->execute($params);

        return [
            'title' => 'Эффективность тестов',
            'headers' => [
                'test_id' => 'ID теста',
                'title' => 'Название',
                'category_name' => 'Категория',
                'unique_users' => 'Уник. пользователей',
                'total_attempts' => 'Всего попыток',
                'completed_attempts' => 'Завершено',
                'avg_score' => 'Средний балл',
                'pass_rate' => 'Процент прохождения',
                'avg_time_spent' => 'Среднее время (сек)',
                'perfect_scores_count' => 'Идеальных результатов',
                'questions_count' => 'Вопросов'
            ],
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * Получить данные отчета по сложности вопросов
     *
     * @param modX $modx
     * @param array $filters
     * @return array
     */
    private static function getQuestionDifficultyData($modx, $filters)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $conditions = ["1=1"];
        $params = [];

        if (!empty($filters['test_id'])) {
            $conditions[] = "test_id = ?";
            $params[] = $filters['test_id'];
        }

        if (!empty($filters['difficulty'])) {
            $conditions[] = "difficulty_level = ?";
            $params[] = $filters['difficulty'];
        }

        $whereClause = implode(' AND ', $conditions);

        $stmt = $pdo->prepare("
            SELECT
                question_id,
                test_id,
                LEFT(question_text, 100) as question_preview,
                question_type,
                total_answers,
                correct_answers,
                incorrect_answers,
                correct_rate,
                difficulty_level
            FROM {$prefix}test_question_statistics
            WHERE $whereClause AND total_answers > 0
            ORDER BY correct_rate ASC, total_answers DESC
        ");
        $stmt->execute($params);

        return [
            'title' => 'Сложность вопросов',
            'headers' => [
                'question_id' => 'ID вопроса',
                'test_id' => 'ID теста',
                'question_preview' => 'Вопрос (превью)',
                'question_type' => 'Тип',
                'total_answers' => 'Всего ответов',
                'correct_answers' => 'Правильных',
                'incorrect_answers' => 'Неправильных',
                'correct_rate' => '% правильных',
                'difficulty_level' => 'Уровень сложности'
            ],
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * Получить данные отчета по категориям
     *
     * @param modX $modx
     * @param array $filters
     * @return array
     */
    private static function getCategoryOverviewData($modx, $filters)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->query("
            SELECT *
            FROM {$prefix}test_category_statistics
            ORDER BY category_name
        ");

        return [
            'title' => 'Обзор по категориям',
            'headers' => [
                'category_id' => 'ID категории',
                'category_name' => 'Название',
                'tests_count' => 'Тестов',
                'total_questions' => 'Вопросов',
                'unique_users' => 'Уник. пользователей',
                'total_attempts' => 'Всего попыток',
                'avg_score' => 'Средний балл',
                'pass_rate' => '% прохождения'
            ],
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * Получить данные отчета по активности пользователей
     *
     * @param modX $modx
     * @param array $filters
     * @return array
     */
    private static function getUserActivityData($modx, $filters)
    {
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');

        $data = AnalyticsService::getUserActivitySummary($modx, $startDate, $endDate);

        return [
            'title' => 'Активность пользователей',
            'headers' => [
                'activity_date' => 'Дата',
                'active_users' => 'Активных пользователей',
                'users_started_tests' => 'Начали тесты',
                'users_completed_tests' => 'Завершили тесты',
                'tests_started' => 'Тестов начато',
                'tests_completed' => 'Тестов завершено',
                'avg_session_duration' => 'Среднее время сессии'
            ],
            'rows' => $data
        ];
    }

    /**
     * Форматировать отчет
     *
     * @param modX $modx
     * @param array $data
     * @param string $format
     * @param string $reportType
     * @return array|false
     */
    private static function formatReport($modx, $data, $format, $reportType)
    {
        switch ($format) {
            case self::FORMAT_CSV:
                return self::generateCSV($modx, $data, $reportType);
            case self::FORMAT_JSON:
                return self::generateJSON($data);
            case self::FORMAT_HTML:
                return self::generateHTML($data, $reportType);
            case self::FORMAT_PDF:
                return self::generatePDF($data, $reportType);
            default:
                return false;
        }
    }

    /**
     * Генерация CSV отчета
     *
     * @param modX $modx
     * @param array $data
     * @param string $reportType
     * @return array
     */
    private static function generateCSV($modx, $data, $reportType)
    {
        $fileName = $reportType . '_' . date('Y-m-d_H-i-s') . '.csv';
        $filePath = MODX_ASSETS_PATH . 'components/testsystem/reports/' . $fileName;

        // Создаем директорию если не существует
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($filePath, 'w');

        // BOM для корректного отображения UTF-8 в Excel
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

        // Заголовки
        fputcsv($fp, array_values($data['headers']), ';');

        // Данные
        foreach ($data['rows'] as $row) {
            $rowData = [];
            foreach (array_keys($data['headers']) as $key) {
                $rowData[] = $row[$key] ?? '';
            }
            fputcsv($fp, $rowData, ';');
        }

        fclose($fp);

        $fileSize = filesize($filePath);

        return [
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'rows_count' => count($data['rows']),
            'download_url' => MODX_ASSETS_URL . 'components/testsystem/reports/' . $fileName
        ];
    }

    /**
     * Генерация JSON отчета
     *
     * @param array $data
     * @return array
     */
    private static function generateJSON($data)
    {
        $json = json_encode([
            'title' => $data['title'],
            'generated_at' => date('Y-m-d H:i:s'),
            'rows_count' => count($data['rows']),
            'data' => $data['rows']
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return [
            'content' => $json,
            'rows_count' => count($data['rows'])
        ];
    }

    /**
     * Генерация HTML отчета
     *
     * @param array $data
     * @param string $reportType
     * @return array
     */
    private static function generateHTML($data, $reportType)
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($data['title']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        tr:hover { background-color: #ddd; }
        .meta { color: #666; font-size: 14px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <h1>' . htmlspecialchars($data['title']) . '</h1>
    <div class="meta">
        Дата создания: ' . date('Y-m-d H:i:s') . '<br>
        Всего записей: ' . count($data['rows']) . '
    </div>
    <table>
        <thead>
            <tr>';

        foreach ($data['headers'] as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }

        $html .= '</tr>
        </thead>
        <tbody>';

        foreach ($data['rows'] as $row) {
            $html .= '<tr>';
            foreach (array_keys($data['headers']) as $key) {
                $value = $row[$key] ?? '';
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody>
    </table>
</body>
</html>';

        return [
            'content' => $html,
            'rows_count' => count($data['rows'])
        ];
    }

    /**
     * Генерация PDF отчета (заглушка)
     *
     * @param array $data
     * @param string $reportType
     * @return array|false
     */
    private static function generatePDF($data, $reportType)
    {
        // TODO: Реализовать генерацию PDF с использованием библиотеки (TCPDF, Dompdf и т.д.)
        // Пока возвращаем false
        return false;
    }

    /**
     * Залогировать генерацию отчета
     *
     * @param modX $modx
     * @param int|null $reportId
     * @param string $reportType
     * @param int $generatedBy
     * @param string|null $filePath
     * @param int|null $fileSize
     * @param string $format
     * @param array $filters
     * @param int|null $rowsCount
     * @param float $generationTime
     * @return bool
     */
    private static function logReportGeneration($modx, $reportId, $reportType, $generatedBy, $filePath, $fileSize,
                                                $format, $filters, $rowsCount, $generationTime)
    {
        if (!$generatedBy) {
            return false;
        }

        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO {$prefix}test_report_history
                (report_id, report_type, generated_by, file_path, file_size, format, filters_used,
                 rows_count, generation_time, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
            ");

            return $stmt->execute([
                $reportId,
                $reportType,
                $generatedBy,
                $filePath,
                $fileSize,
                $format,
                json_encode($filters),
                $rowsCount,
                $generationTime
            ]);
        } catch (Exception $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, 'Error logging report generation: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить историю генерации отчетов
     *
     * @param modX $modx
     * @param int|null $userId
     * @param int $limit
     * @return array
     */
    public static function getReportHistory($modx, $userId = null, $limit = 50)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $conditions = ["1=1"];
        $params = [];

        if ($userId) {
            $conditions[] = "generated_by = ?";
            $params[] = $userId;
        }

        $whereClause = implode(' AND ', $conditions);
        $params[] = $limit;

        $stmt = $pdo->prepare("
            SELECT
                rh.*,
                u.username as generated_by_username
            FROM {$prefix}test_report_history rh
            LEFT JOIN {$prefix}users u ON u.id = rh.generated_by
            WHERE $whereClause
            ORDER BY rh.generated_at DESC
            LIMIT ?
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Удалить старые файлы отчетов
     *
     * @param modX $modx
     * @return int Количество удаленных файлов
     */
    public static function cleanupOldReports($modx)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        // Получаем список истекших отчетов с файлами
        $stmt = $pdo->query("
            SELECT id, file_path
            FROM {$prefix}test_report_history
            WHERE expires_at IS NOT NULL
            AND expires_at < NOW()
            AND file_path IS NOT NULL
        ");
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $deletedCount = 0;

        foreach ($reports as $report) {
            if (file_exists($report['file_path'])) {
                if (unlink($report['file_path'])) {
                    $deletedCount++;
                }
            }

            // Обновляем запись - убираем путь к файлу
            $updateStmt = $pdo->prepare("
                UPDATE {$prefix}test_report_history
                SET file_path = NULL
                WHERE id = ?
            ");
            $updateStmt->execute([$report['id']]);
        }

        return $deletedCount;
    }
}
