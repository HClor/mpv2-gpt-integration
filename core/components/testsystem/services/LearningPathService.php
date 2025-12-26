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
                 is_template, parent_id, difficulty_level, estimated_hours, passing_score, certificate_template)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        // Нормализация: 0 и пустые строки -> NULL для nullable FK полей
        $categoryId = !empty($data['category_id']) ? (int)$data['category_id'] : null;
        $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        $estimatedHours = !empty($data['estimated_hours']) ? (int)$data['estimated_hours'] : null;
        $certificateTemplate = !empty($data['certificate_template']) ? $data['certificate_template'] : null;
        $description = !empty($data['description']) ? $data['description'] : null;

        $params = [
            $data['name'],
            $description,
            $categoryId,
            $data['created_by'],
            $data['status'] ?? 'draft',
            $data['is_public'] ?? 0,
            $data['is_template'] ?? 0,
            $parentId,
            $data['difficulty_level'] ?? 'beginner',
            $estimatedHours,
            $data['passing_score'] ?? 70,
            $certificateTemplate
        ];

        $stmt = $modx->prepare($sql);
        $result = $stmt->execute($params);

        if (!$result) {
            return 0;
        }

        // Пробуем $modx->lastInsertId() как в других сервисах
        $lastId = (int)$modx->lastInsertId();

        if ($lastId > 0) {
            return $lastId;
        }

        // Fallback: LAST_INSERT_ID()
        $result = $modx->query("SELECT LAST_INSERT_ID() as id");
        if ($result) {
            $row = $result->fetch(PDO::FETCH_ASSOC);
            $lastId = (int)($row['id'] ?? 0);
            return $lastId;
        }

        return 0;
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
                          'is_template', 'difficulty_level', 'estimated_hours', 'passing_score', 'certificate_template'];

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
     * @param DateTime|string|null $expiresAt Дата истечения доступа
     * @param DateTime|string|null $deadline Срок выполнения траектории
     * @return int|false ID записи или false
     */
    public static function enrollUser($modx, $pathId, $userId, $enrolledBy = null, $expiresAt = null, $deadline = null)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $expiresAtStr = null;
        if ($expiresAt) {
            $expiresAtStr = $expiresAt instanceof DateTime ? $expiresAt->format('Y-m-d H:i:s') : $expiresAt;
        }

        $deadlineStr = null;
        if ($deadline) {
            $deadlineStr = $deadline instanceof DateTime ? $deadline->format('Y-m-d H:i:s') : $deadline;
        }

        $sql = "INSERT INTO {$prefix}test_learning_path_enrollments
                (path_id, user_id, enrolled_by, expires_at, deadline)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    is_active = 1,
                    enrolled_at = NOW(),
                    expires_at = ?,
                    deadline = ?";

        $stmt = $modx->prepare($sql);

        if ($stmt->execute([$pathId, $userId, $enrolledBy, $expiresAtStr, $deadlineStr, $expiresAtStr, $deadlineStr])) {
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
                       e.enrolled_at, e.expires_at, e.deadline, e.enrolled_by,
                       enroller.username as enrolled_by_name
                FROM {$prefix}test_learning_path_enrollments e
                JOIN {$prefix}test_learning_paths lp ON lp.id = e.path_id
                LEFT JOIN {$prefix}test_learning_path_progress lpp
                    ON lpp.enrollment_id = e.id
                LEFT JOIN {$prefix}users enroller ON enroller.id = e.enrolled_by
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

        // Сначала проверяем, существует ли запись
        $checkSql = "SELECT id FROM {$prefix}test_learning_path_step_completion
                     WHERE progress_id = ? AND step_id = ?";
        $checkStmt = $modx->prepare($checkSql);
        $checkStmt->execute([$progressId, $stepId]);

        if (!$checkStmt->fetch()) {
            // Записи нет - создаём её
            $insertSql = "INSERT INTO {$prefix}test_learning_path_step_completion
                          (progress_id, step_id, status, score, completed_at, session_id, material_progress_id, attempts)
                          VALUES (?, ?, 'completed', ?, NOW(), ?, ?, 1)";
            $insertStmt = $modx->prepare($insertSql);
            return $insertStmt->execute([
                $progressId,
                $stepId,
                $completionData['score'] ?? null,
                $completionData['session_id'] ?? null,
                $completionData['material_progress_id'] ?? null
            ]);
        }

        // Запись есть - обновляем
        $sql = "UPDATE {$prefix}test_learning_path_step_completion
                SET status = 'completed',
                    score = ?,
                    completed_at = NOW(),
                    session_id = ?,
                    material_progress_id = ?,
                    attempts = attempts + 1
                WHERE progress_id = ? AND step_id = ?";

        $stmt = $modx->prepare($sql);
        $result = $stmt->execute([
            $completionData['score'] ?? null,
            $completionData['session_id'] ?? null,
            $completionData['material_progress_id'] ?? null,
            $progressId,
            $stepId
        ]);

        return $result && $stmt->rowCount() > 0;
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

        // Если записи нет в step_completion, проверяем существование шага
        if (!$step) {
            // Проверяем, существует ли сам шаг в траектории
            $sql = "SELECT lps.step_number, lps.unlock_condition
                    FROM {$prefix}test_learning_path_steps lps
                    JOIN {$prefix}test_learning_path_progress lpp ON lpp.path_id = lps.path_id
                    WHERE lpp.id = ? AND lps.id = ?";
            $stmt = $modx->prepare($sql);
            $stmt->execute([$progressId, $stepId]);
            $stepExists = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$stepExists) {
                return false;
            }

            // Первый шаг или шаг без условий - разрешаем
            if ($stepExists['step_number'] == 1 || empty($stepExists['unlock_condition'])) {
                return true;
            }

            // Проверяем условия разблокировки
            $condition = json_decode($stepExists['unlock_condition'], true);
            if ($condition) {
                return self::checkUnlockCondition($modx, $progressId, $condition);
            }

            return true;
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

        if (isset($filters['is_template'])) {
            $where[] = 'lp.is_template = ?';
            $params[] = (int)$filters['is_template'];
        }

        if (!empty($filters['parent_id'])) {
            $where[] = 'lp.parent_id = ?';
            $params[] = $filters['parent_id'];
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
     * @param string|null $deadline
     * @return int Количество записанных пользователей
     */
    public static function bulkEnroll($modx, $pathId, $userIds, $enrolledBy, $deadline = null)
    {
        $count = 0;

        foreach ($userIds as $userId) {
            if (self::enrollUser($modx, $pathId, $userId, $enrolledBy, null, $deadline)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Клонирование траектории
     *
     * Создаёт копию траектории со всеми шагами.
     * Используется для создания индивидуальных траекторий из шаблона.
     *
     * @param modX $modx
     * @param int $sourcePathId ID исходной траектории
     * @param int $createdBy ID пользователя, создающего клон
     * @param array $overrides Переопределения полей (name, description, etc.)
     * @return int ID созданной траектории
     */
    public static function clonePath($modx, $sourcePathId, $createdBy, $overrides = [])
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Получаем исходную траекторию
        $sourcePath = self::getPath($modx, $sourcePathId, true);

        if (!$sourcePath) {
            throw new Exception('Source path not found');
        }

        // Формируем данные для новой траектории
        $newPathData = [
            'name' => $overrides['name'] ?? $sourcePath['name'] . ' (копия)',
            'description' => $overrides['description'] ?? $sourcePath['description'],
            'category_id' => $overrides['category_id'] ?? $sourcePath['category_id'],
            'created_by' => $createdBy,
            'status' => $overrides['status'] ?? 'draft',  // Клон всегда создаётся как черновик
            'is_public' => $overrides['is_public'] ?? 0,  // Клон приватный по умолчанию
            'is_template' => 0,  // Клон не является шаблоном
            'parent_id' => $sourcePathId,  // Ссылка на родительскую траекторию
            'difficulty_level' => $overrides['difficulty_level'] ?? $sourcePath['difficulty_level'],
            'estimated_hours' => $overrides['estimated_hours'] ?? $sourcePath['estimated_hours'],
            'passing_score' => $overrides['passing_score'] ?? $sourcePath['passing_score']
        ];

        // Создаём новую траекторию
        $newPathId = self::createPath($modx, $newPathData);

        // Клонируем шаги
        if (!empty($sourcePath['steps'])) {
            foreach ($sourcePath['steps'] as $step) {
                $stepData = [
                    'step_number' => $step['step_number'],
                    'step_type' => $step['step_type'],
                    'item_id' => $step['item_id'],
                    'name' => $step['name'],
                    'description' => $step['description'],
                    'is_required' => $step['is_required'],
                    'unlock_condition' => $step['unlock_condition'],
                    'min_score' => $step['min_score'],
                    'estimated_minutes' => $step['estimated_minutes']
                ];

                self::addStep($modx, $newPathId, $stepData);
            }
        }

        // Клонируем достижения (опционально)
        if (!empty($overrides['clone_achievements']) && $overrides['clone_achievements']) {
            $achievements = self::getPathAchievements($modx, $sourcePathId);

            foreach ($achievements as $achievement) {
                self::createAchievement($modx, $newPathId, [
                    'name' => $achievement['name'],
                    'description' => $achievement['description'],
                    'badge_icon' => $achievement['badge_icon'],
                    'condition_type' => $achievement['condition_type'],
                    'condition_value' => $achievement['condition_value']
                ]);
            }
        }

        return $newPathId;
    }

    /**
     * Получение списка шаблонов траекторий
     *
     * @param modX $modx
     * @param array $filters Дополнительные фильтры
     * @return array
     */
    public static function getTemplates($modx, $filters = [])
    {
        $filters['is_template'] = 1;
        $filters['status'] = 'published';

        return self::getPathsList($modx, $filters);
    }

    /**
     * Получение клонов траектории
     *
     * @param modX $modx
     * @param int $parentId ID родительской траектории
     * @return array
     */
    public static function getClones($modx, $parentId)
    {
        return self::getPathsList($modx, ['parent_id' => $parentId]);
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

    /**
     * Получение шага по ID
     *
     * @param modX $modx
     * @param int $stepId
     * @return array|null
     */
    public static function getStepById($modx, $stepId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT lps.*, lp.name as path_name
                FROM {$prefix}test_learning_path_steps lps
                JOIN {$prefix}test_learning_paths lp ON lp.id = lps.path_id
                WHERE lps.id = ?";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$stepId]);
        $step = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($step && !empty($step['unlock_condition'])) {
            $step['unlock_condition'] = json_decode($step['unlock_condition'], true);
        }

        return $step ?: null;
    }

    /**
     * Обновление статуса шага
     *
     * @param modX $modx
     * @param int $progressId
     * @param int $stepId
     * @param string $status
     * @return bool
     */
    public static function updateStepStatus($modx, $progressId, $stepId, $status)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $validStatuses = ['locked', 'available', 'in_progress', 'completed', 'skipped'];
        if (!in_array($status, $validStatuses)) {
            return false;
        }

        $sql = "UPDATE {$prefix}test_learning_path_step_completion
                SET status = ?
                WHERE progress_id = ? AND step_id = ?";

        $stmt = $modx->prepare($sql);
        return $stmt->execute([$status, $progressId, $stepId]);
    }

    /**
     * Начало прохождения шага
     *
     * @param modX $modx
     * @param int $progressId
     * @param int $stepId
     * @return bool
     */
    public static function startStep($modx, $progressId, $stepId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "UPDATE {$prefix}test_learning_path_step_completion
                SET status = 'in_progress',
                    started_at = COALESCE(started_at, NOW()),
                    attempts = attempts + 1
                WHERE progress_id = ? AND step_id = ?
                AND status IN ('available', 'in_progress')";

        $stmt = $modx->prepare($sql);
        $result = $stmt->execute([$progressId, $stepId]);

        // Обновляем общий прогресс
        if ($result) {
            $sql = "UPDATE {$prefix}test_learning_path_progress
                    SET status = 'in_progress',
                        started_at = COALESCE(started_at, NOW()),
                        last_activity_at = NOW()
                    WHERE id = ?";
            $stmt = $modx->prepare($sql);
            $stmt->execute([$progressId]);
        }

        return $result;
    }

    /**
     * Получение достижений траектории
     *
     * @param modX $modx
     * @param int $pathId
     * @return array
     */
    public static function getPathAchievements($modx, $pathId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "SELECT *
                FROM {$prefix}test_learning_path_achievements
                WHERE path_id = ?
                ORDER BY id";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$pathId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получение достижений пользователя
     *
     * @param modX $modx
     * @param int $userId
     * @param int|null $pathId Опционально фильтр по траектории
     * @return array
     */
    public static function getUserAchievements($modx, $userId, $pathId = null)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $where = ['lpua.user_id = ?'];
        $params = [$userId];

        if ($pathId) {
            $where[] = 'lpa.path_id = ?';
            $params[] = $pathId;
        }

        $sql = "SELECT lpua.*, lpa.name, lpa.description, lpa.badge_icon,
                       lpa.condition_type, lpa.condition_value,
                       lp.name as path_name
                FROM {$prefix}test_learning_path_user_achievements lpua
                JOIN {$prefix}test_learning_path_achievements lpa ON lpa.id = lpua.achievement_id
                JOIN {$prefix}test_learning_paths lp ON lp.id = lpa.path_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY lpua.earned_at DESC";

        $stmt = $modx->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Создание достижения для траектории
     *
     * @param modX $modx
     * @param int $pathId
     * @param array $data
     * @return int ID созданного достижения
     */
    public static function createAchievement($modx, $pathId, $data)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sql = "INSERT INTO {$prefix}test_learning_path_achievements
                (path_id, name, description, badge_icon, condition_type, condition_value)
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $modx->prepare($sql);
        $stmt->execute([
            $pathId,
            $data['name'],
            $data['description'] ?? null,
            $data['badge_icon'] ?? null,
            $data['condition_type'] ?? 'complete_path',
            $data['condition_value'] ?? null
        ]);

        return (int)$modx->lastInsertId();
    }

    /**
     * Выдача достижения пользователю
     *
     * @param modX $modx
     * @param int $achievementId
     * @param int $userId
     * @param int $progressId
     * @return bool
     */
    public static function awardAchievement($modx, $achievementId, $userId, $progressId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Проверяем, не выдано ли уже
        $sql = "SELECT id FROM {$prefix}test_learning_path_user_achievements
                WHERE achievement_id = ? AND user_id = ?";
        $stmt = $modx->prepare($sql);
        $stmt->execute([$achievementId, $userId]);

        if ($stmt->fetch()) {
            return false; // Уже выдано
        }

        $sql = "INSERT INTO {$prefix}test_learning_path_user_achievements
                (achievement_id, user_id, progress_id)
                VALUES (?, ?, ?)";

        $stmt = $modx->prepare($sql);
        return $stmt->execute([$achievementId, $userId, $progressId]);
    }

    /**
     * Проверка и выдача достижений при завершении траектории
     *
     * @param modX $modx
     * @param int $progressId
     * @return array Список выданных достижений
     */
    public static function checkAndAwardAchievements($modx, $progressId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Получаем прогресс
        $sql = "SELECT lpp.*, lp.passing_score
                FROM {$prefix}test_learning_path_progress lpp
                JOIN {$prefix}test_learning_paths lp ON lp.id = lpp.path_id
                WHERE lpp.id = ?";
        $stmt = $modx->prepare($sql);
        $stmt->execute([$progressId]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$progress) {
            return [];
        }

        // Получаем достижения траектории
        $achievements = self::getPathAchievements($modx, $progress['path_id']);
        $awarded = [];

        foreach ($achievements as $achievement) {
            $shouldAward = false;

            switch ($achievement['condition_type']) {
                case 'complete_path':
                    $shouldAward = $progress['status'] === 'completed';
                    break;

                case 'score_threshold':
                    $shouldAward = $progress['status'] === 'completed'
                        && $progress['total_score'] >= $achievement['condition_value'];
                    break;

                case 'perfect_score':
                    $shouldAward = $progress['status'] === 'completed'
                        && $progress['total_score'] >= 100;
                    break;

                case 'time_limit':
                    if ($progress['status'] === 'completed' && $progress['started_at'] && $progress['completed_at']) {
                        $started = new DateTime($progress['started_at']);
                        $completed = new DateTime($progress['completed_at']);
                        $diffDays = $started->diff($completed)->days;
                        $shouldAward = $diffDays <= $achievement['condition_value'];
                    }
                    break;
            }

            if ($shouldAward) {
                if (self::awardAchievement($modx, $achievement['id'], $progress['user_id'], $progressId)) {
                    $awarded[] = $achievement;
                }
            }
        }

        return $awarded;
    }

    /**
     * Получение детального прогресса со всеми шагами
     *
     * @param modX $modx
     * @param int $pathId
     * @param int $userId
     * @return array|null
     */
    public static function getDetailedProgress($modx, $pathId, $userId)
    {
        $progress = self::getUserProgress($modx, $pathId, $userId);

        if (!$progress) {
            return null;
        }

        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Подсчитываем статистику
        $sql = "SELECT
                    COUNT(*) as total_steps,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_steps,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_steps,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_steps,
                    SUM(CASE WHEN status = 'locked' THEN 1 ELSE 0 END) as locked_steps,
                    SUM(time_spent_minutes) as total_time_spent,
                    AVG(CASE WHEN status = 'completed' AND score IS NOT NULL THEN score END) as avg_score
                FROM {$prefix}test_learning_path_step_completion
                WHERE progress_id = ?";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$progress['id']]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $progress['stats'] = $stats;
        $progress['completed_step_ids'] = [];

        // Собираем ID завершённых шагов
        foreach ($progress['steps'] as $step) {
            if ($step['status'] === 'completed') {
                $progress['completed_step_ids'][] = (int)$step['step_id'];
            }
        }

        return $progress;
    }
}
