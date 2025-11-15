<?php
/**
 * Learning Path Service
 *
 * Сервис для управления траекториями обучения
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class LearningPathService
{
    /**
     * Создание траектории обучения
     *
     * @param modX $modx
     * @param array $data
     * @return int ID созданной траектории
     */
    public static function createPath($modx, $data)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "INSERT INTO {$prefix}test_learning_paths
                (name, description, category_id, created_by, status, is_public,
                 difficulty_level, estimated_hours, passing_score)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $modx->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['category_id'] ?? null,
            $data['created_by'],
            $data['status'] ?? 'draft',
            $data['is_public'] ?? 0,
            $data['difficulty_level'] ?? 'beginner',
            $data['estimated_hours'] ?? null,
            $data['passing_score'] ?? 70
        ]);

        return (int)$modx->lastInsertId();
    }

    /**
     * Получение траектории по ID
     *
     * @param modX $modx
     * @param int $pathId
     * @param bool $withSteps Загружать ли шаги
     * @return array|null
     */
    public static function getPath($modx, $pathId, $withSteps = true)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT lp.*, u.username as author_name,
                       c.name as category_name,
                       (SELECT COUNT(*) FROM {$prefix}test_learning_path_enrollments
                        WHERE path_id = lp.id AND is_active = 1) as enrolled_count
                FROM {$prefix}test_learning_paths lp
                LEFT JOIN {$prefix}users u ON u.id = lp.created_by
                LEFT JOIN {$prefix}test_categories c ON c.id = lp.category_id
                WHERE lp.id = ?";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$pathId]);
        $path = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$path) {
            return null;
        }

        if ($withSteps) {
            $path['steps'] = self::getPathSteps($modx, $pathId);
        }

        return $path;
    }

    /**
     * Получение шагов траектории
     *
     * @param modX $modx
     * @param int $pathId
     * @return array
     */
    public static function getPathSteps($modx, $pathId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT lps.*
                FROM {$prefix}test_learning_path_steps lps
                WHERE lps.path_id = ?
                ORDER BY lps.step_number ASC";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$pathId]);
        $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Декодируем JSON условия
        foreach ($steps as &$step) {
            if (!empty($step['unlock_condition'])) {
                $step['unlock_condition'] = json_decode($step['unlock_condition'], true);
            }
        }

        return $steps;
    }

    /**
     * Обновление траектории
     *
     * @param modX $modx
     * @param int $pathId
     * @param array $data
     * @return bool
     */
    public static function updatePath($modx, $pathId, $data)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $fields = [];
        $values = [];

        $allowedFields = ['name', 'description', 'category_id', 'status', 'is_public',
                          'difficulty_level', 'estimated_hours', 'passing_score', 'certificate_template'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $pathId;

        $sql = "UPDATE {$prefix}test_learning_paths
                SET " . implode(', ', $fields) . "
                WHERE id = ?";

        $stmt = $modx->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Удаление траектории
     *
     * @param modX $modx
     * @param int $pathId
     * @return bool
     */
    public static function deletePath($modx, $pathId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "DELETE FROM {$prefix}test_learning_paths WHERE id = ?";

        $stmt = $modx->prepare($sql);
        return $stmt->execute([$pathId]);
    }

    /**
     * Добавление шага в траекторию
     *
     * @param modX $modx
     * @param int $pathId
     * @param array $stepData
     * @return int ID созданного шага
     */
    public static function addStep($modx, $pathId, $stepData)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Получаем следующий номер шага
        $stmt = $modx->prepare("SELECT MAX(step_number) FROM {$prefix}test_learning_path_steps WHERE path_id = ?");
        $stmt->execute([$pathId]);
        $maxStep = (int)$stmt->fetchColumn();
        $stepNumber = $stepData['step_number'] ?? ($maxStep + 1);

        // Кодируем условие разблокировки в JSON
        $unlockCondition = null;
        if (!empty($stepData['unlock_condition'])) {
            $unlockCondition = is_string($stepData['unlock_condition'])
                ? $stepData['unlock_condition']
                : json_encode($stepData['unlock_condition']);
        }

        $sql = "INSERT INTO {$prefix}test_learning_path_steps
                (path_id, step_number, step_type, item_id, name, description,
                 is_required, unlock_condition, min_score, estimated_minutes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $modx->prepare($sql);
        $stmt->execute([
            $pathId,
            $stepNumber,
            $stepData['step_type'],
            $stepData['item_id'],
            $stepData['name'],
            $stepData['description'] ?? null,
            $stepData['is_required'] ?? 1,
            $unlockCondition,
            $stepData['min_score'] ?? null,
            $stepData['estimated_minutes'] ?? null
        ]);

        return (int)$modx->lastInsertId();
    }

    /**
     * Обновление шага
     *
     * @param modX $modx
     * @param int $stepId
     * @param array $data
     * @return bool
     */
    public static function updateStep($modx, $stepId, $data)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $fields = [];
        $values = [];

        $allowedFields = ['step_number', 'step_type', 'item_id', 'name', 'description',
                          'is_required', 'min_score', 'estimated_minutes'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $values[] = $data[$field];
            }
        }

        if (isset($data['unlock_condition'])) {
            $fields[] = "unlock_condition = ?";
            $values[] = is_string($data['unlock_condition'])
                ? $data['unlock_condition']
                : json_encode($data['unlock_condition']);
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $stepId;

        $sql = "UPDATE {$prefix}test_learning_path_steps
                SET " . implode(', ', $fields) . "
                WHERE id = ?";

        $stmt = $modx->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Удаление шага
     *
     * @param modX $modx
     * @param int $stepId
     * @return bool
     */
    public static function deleteStep($modx, $stepId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "DELETE FROM {$prefix}test_learning_path_steps WHERE id = ?";

        $stmt = $modx->prepare($sql);
        return $stmt->execute([$stepId]);
    }

    /**
     * Изменение порядка шагов
     *
     * @param modX $modx
     * @param int $pathId
     * @param array $stepOrder Массив [step_id => step_number]
     * @return bool
     */
    public static function reorderSteps($modx, $pathId, $stepOrder)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        foreach ($stepOrder as $stepId => $stepNumber) {
            $stmt = $modx->prepare("UPDATE {$prefix}test_learning_path_steps
                                   SET step_number = ?
                                   WHERE id = ? AND path_id = ?");
            $stmt->execute([$stepNumber, $stepId, $pathId]);
        }

        return true;
    }

    /**
     * Запись пользователя на траекторию
     *
     * @param modX $modx
     * @param int $pathId
     * @param int $userId
     * @param int|null $enrolledBy ID того, кто записывает (NULL = самозапись)
     * @param DateTime|null $expiresAt Дата истечения доступа
     * @return int|false ID записи или false
     */
    public static function enrollUser($modx, $pathId, $userId, $enrolledBy = null, $expiresAt = null)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $expiresAtStr = $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null;

        $sql = "INSERT INTO {$prefix}test_learning_path_enrollments
                (path_id, user_id, enrolled_by, expires_at)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    is_active = 1,
                    enrolled_at = NOW(),
                    expires_at = ?";

        $stmt = $modx->prepare($sql);

        if ($stmt->execute([$pathId, $userId, $enrolledBy, $expiresAtStr, $expiresAtStr])) {
            return (int)$modx->lastInsertId();
        }

        return false;
    }

    /**
     * Отмена записи пользователя
     *
     * @param modX $modx
     * @param int $pathId
     * @param int $userId
     * @return bool
     */
    public static function unenrollUser($modx, $pathId, $userId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "UPDATE {$prefix}test_learning_path_enrollments
                SET is_active = 0
                WHERE path_id = ? AND user_id = ?";

        $stmt = $modx->prepare($sql);
        return $stmt->execute([$pathId, $userId]);
    }

    /**
     * Получение траекторий, на которые записан пользователь
     *
     * @param modX $modx
     * @param int $userId
     * @param string|null $status Фильтр по статусу
     * @return array
     */
    public static function getEnrolledPaths($modx, $userId, $status = null)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $where = ['e.user_id = ?', 'e.is_active = 1'];
        $params = [$userId];

        if ($status) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }

        $sql = "SELECT lp.*, lpp.status as user_status, lpp.completion_pct,
                       lpp.current_step, lpp.started_at, lpp.completed_at,
                       e.enrolled_at, e.expires_at
                FROM {$prefix}test_learning_path_enrollments e
                JOIN {$prefix}test_learning_paths lp ON lp.id = e.path_id
                LEFT JOIN {$prefix}test_learning_path_progress lpp
                    ON lpp.enrollment_id = e.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY e.enrolled_at DESC";

        $stmt = $modx->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получение прогресса пользователя по траектории
     *
     * @param modX $modx
     * @param int $pathId
     * @param int $userId
     * @return array|null
     */
    public static function getUserProgress($modx, $pathId, $userId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT lpp.*, lp.name as path_name, lp.passing_score
                FROM {$prefix}test_learning_path_progress lpp
                JOIN {$prefix}test_learning_paths lp ON lp.id = lpp.path_id
                WHERE lpp.path_id = ? AND lpp.user_id = ?";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$pathId, $userId]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$progress) {
            return null;
        }

        // Получаем завершение отдельных шагов
        $sql = "SELECT lpsc.*, lps.step_number, lps.name, lps.step_type,
                       lps.is_required, lps.min_score
                FROM {$prefix}test_learning_path_step_completion lpsc
                JOIN {$prefix}test_learning_path_steps lps ON lps.id = lpsc.step_id
                WHERE lpsc.progress_id = ?
                ORDER BY lps.step_number";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$progress['id']]);
        $progress['steps'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $progress;
    }

    /**
     * Завершение шага траектории
     *
     * @param modX $modx
     * @param int $progressId
     * @param int $stepId
     * @param array $completionData score, session_id, material_progress_id
     * @return bool
     */
    public static function completeStep($modx, $progressId, $stepId, $completionData = [])
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "UPDATE {$prefix}test_learning_path_step_completion
                SET status = 'completed',
                    score = ?,
                    completed_at = NOW(),
                    session_id = ?,
                    material_progress_id = ?,
                    attempts = attempts + 1
                WHERE progress_id = ? AND step_id = ?";

        $stmt = $modx->prepare($sql);
        return $stmt->execute([
            $completionData['score'] ?? null,
            $completionData['session_id'] ?? null,
            $completionData['material_progress_id'] ?? null,
            $progressId,
            $stepId
        ]);
    }

    /**
     * Проверка доступа к шагу
     *
     * @param modX $modx
     * @param int $progressId
     * @param int $stepId
     * @return bool
     */
    public static function canAccessStep($modx, $progressId, $stepId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Получаем статус шага и условия разблокировки
        $sql = "SELECT lpsc.status, lps.unlock_condition, lps.step_number
                FROM {$prefix}test_learning_path_step_completion lpsc
                JOIN {$prefix}test_learning_path_steps lps ON lps.id = lpsc.step_id
                WHERE lpsc.progress_id = ? AND lpsc.step_id = ?";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$progressId, $stepId]);
        $step = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$step) {
            return false;
        }

        // Если шаг уже доступен или завершен
        if (in_array($step['status'], ['available', 'in_progress', 'completed'])) {
            return true;
        }

        // Проверяем условия разблокировки
        if (!empty($step['unlock_condition'])) {
            $condition = json_decode($step['unlock_condition'], true);
            return self::checkUnlockCondition($modx, $progressId, $condition);
        }

        // Первый шаг всегда доступен
        if ($step['step_number'] == 1) {
            return true;
        }

        return false;
    }

    /**
     * Проверка условия разблокировки
     *
     * @param modX $modx
     * @param int $progressId
     * @param array $condition
     * @return bool
     */
    private static function checkUnlockCondition($modx, $progressId, $condition)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        switch ($condition['type']) {
            case 'previous_step':
                // Предыдущий шаг завершен
                $sql = "SELECT COUNT(*) FROM {$prefix}test_learning_path_step_completion lpsc
                        JOIN {$prefix}test_learning_path_steps lps ON lps.id = lpsc.step_id
                        WHERE lpsc.progress_id = ?
                        AND lps.step_number = (
                            SELECT lps2.step_number - 1
                            FROM {$prefix}test_learning_path_step_completion lpsc2
                            JOIN {$prefix}test_learning_path_steps lps2 ON lps2.id = lpsc2.step_id
                            WHERE lpsc2.progress_id = ?
                        )
                        AND lpsc.status = 'completed'";
                $stmt = $modx->prepare($sql);
                $stmt->execute([$progressId, $progressId]);
                return (int)$stmt->fetchColumn() > 0;

            case 'previous_step_score':
                // Минимальный балл на предыдущем шаге
                $minScore = $condition['min_score'] ?? 70;
                $sql = "SELECT lpsc.score
                        FROM {$prefix}test_learning_path_step_completion lpsc
                        JOIN {$prefix}test_learning_path_steps lps ON lps.id = lpsc.step_id
                        WHERE lpsc.progress_id = ?
                        AND lps.step_number = (
                            SELECT lps2.step_number - 1
                            FROM {$prefix}test_learning_path_step_completion lpsc2
                            JOIN {$prefix}test_learning_path_steps lps2 ON lps2.id = lpsc2.step_id
                            WHERE lpsc2.progress_id = ?
                        )";
                $stmt = $modx->prepare($sql);
                $stmt->execute([$progressId, $progressId]);
                $score = $stmt->fetchColumn();
                return $score !== false && $score >= $minScore;

            case 'all_previous_steps':
                // Все предыдущие шаги завершены
                return true; // Реализуется автоматически триггерами

            case 'date':
                // Определенная дата
                if (!empty($condition['unlock_date'])) {
                    $unlockDate = new DateTime($condition['unlock_date']);
                    $now = new DateTime();
                    return $now >= $unlockDate;
                }
                return false;

            default:
                return false;
        }
    }

    /**
     * Получение следующего доступного шага
     *
     * @param modX $modx
     * @param int $progressId
     * @return array|null
     */
    public static function getNextStep($modx, $progressId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT lpsc.*, lps.step_number, lps.name, lps.step_type,
                       lps.item_id, lps.description, lps.min_score
                FROM {$prefix}test_learning_path_step_completion lpsc
                JOIN {$prefix}test_learning_path_steps lps ON lps.id = lpsc.step_id
                WHERE lpsc.progress_id = ?
                AND lpsc.status IN ('available', 'in_progress')
                ORDER BY lps.step_number ASC
                LIMIT 1";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$progressId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Выдача сертификата
     *
     * @param modX $modx
     * @param int $progressId
     * @return bool
     */
    public static function issueCertificate($modx, $progressId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "UPDATE {$prefix}test_learning_path_progress
                SET certificate_issued = 1,
                    certificate_issued_at = NOW()
                WHERE id = ?
                AND status = 'completed'
                AND certificate_issued = 0";

        $stmt = $modx->prepare($sql);
        return $stmt->execute([$progressId]);
    }

    /**
     * Получение списка траекторий
     *
     * @param modX $modx
     * @param array $filters
     * @return array
     */
    public static function getPathsList($modx, $filters = [])
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[] = 'lp.category_id = ?';
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'lp.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['created_by'])) {
            $where[] = 'lp.created_by = ?';
            $params[] = $filters['created_by'];
        }

        if (isset($filters['is_public'])) {
            $where[] = 'lp.is_public = ?';
            $params[] = $filters['is_public'];
        }

        if (!empty($filters['difficulty_level'])) {
            $where[] = 'lp.difficulty_level = ?';
            $params[] = $filters['difficulty_level'];
        }

        $sql = "SELECT lp.*, u.username as author_name,
                       c.name as category_name,
                       (SELECT COUNT(*) FROM {$prefix}test_learning_path_steps WHERE path_id = lp.id) as steps_count,
                       (SELECT COUNT(*) FROM {$prefix}test_learning_path_enrollments
                        WHERE path_id = lp.id AND is_active = 1) as enrolled_count
                FROM {$prefix}test_learning_paths lp
                LEFT JOIN {$prefix}users u ON u.id = lp.created_by
                LEFT JOIN {$prefix}test_categories c ON c.id = lp.category_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY lp.created_at DESC";

        $stmt = $modx->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Массовая запись пользователей на траекторию
     *
     * @param modX $modx
     * @param int $pathId
     * @param array $userIds
     * @param int $enrolledBy
     * @return int Количество записанных пользователей
     */
    public static function bulkEnroll($modx, $pathId, $userIds, $enrolledBy)
    {
        $count = 0;

        foreach ($userIds as $userId) {
            if (self::enrollUser($modx, $pathId, $userId, $enrolledBy)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Проверка, записан ли пользователь на траекторию
     *
     * @param modX $modx
     * @param int $pathId
     * @param int $userId
     * @return bool
     */
    public static function isUserEnrolled($modx, $pathId, $userId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT COUNT(*) FROM {$prefix}test_learning_path_enrollments
                WHERE path_id = ? AND user_id = ? AND is_active = 1
                AND (expires_at IS NULL OR expires_at > NOW())";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$pathId, $userId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Получение статистики по траектории
     *
     * @param modX $modx
     * @param int $pathId
     * @return array
     */
    public static function getPathStatistics($modx, $pathId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT
                    COUNT(DISTINCT e.user_id) as total_enrolled,
                    COUNT(DISTINCT CASE WHEN lpp.status = 'completed' THEN e.user_id END) as total_completed,
                    AVG(lpp.completion_pct) as avg_completion_pct,
                    AVG(CASE WHEN lpp.status = 'completed' THEN
                        TIMESTAMPDIFF(DAY, lpp.started_at, lpp.completed_at)
                    END) as avg_completion_days
                FROM {$prefix}test_learning_path_enrollments e
                LEFT JOIN {$prefix}test_learning_path_progress lpp ON lpp.enrollment_id = e.id
                WHERE e.path_id = ? AND e.is_active = 1";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$pathId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
