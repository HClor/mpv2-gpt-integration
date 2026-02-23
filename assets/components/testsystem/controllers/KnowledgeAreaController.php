<?php
/**
 * Knowledge Area Controller
 *
 * Контроллер для управления областями знаний
 *
 * @package TestSystem
 * @version 1.0
 * @created 2026-02-22
 */

require_once __DIR__ . '/BaseController.php';

class KnowledgeAreaController extends BaseController
{
    /**
     * Обработка действия
     *
     * @param string $action Название действия
     * @param array $data Данные запроса
     * @return array
     */
    public function handle($action, $data)
    {
        try {
            switch ($action) {
                case 'getKnowledgeAreas':
                    return $this->getKnowledgeAreas($data);

                case 'createKnowledgeArea':
                    return $this->createKnowledgeArea($data);

                case 'updateKnowledgeArea':
                    return $this->updateKnowledgeArea($data);

                case 'deleteKnowledgeArea':
                    return $this->deleteKnowledgeArea($data);

                case 'getKnowledgeAreaDetails':
                    return $this->getKnowledgeAreaDetails($data);

                case 'getAvailableTestsTree':
                    return $this->getAvailableTestsTree($data);

                case 'startKnowledgeAreaSession':
                    return $this->startKnowledgeAreaSession($data);

                default:
                    return $this->error('Unknown action: ' . $action, 404);
            }
        } catch (AuthenticationException $e) {
            return $this->error($e->getMessage(), 401);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (PermissionException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'KnowledgeAreaController error: ' . $e->getMessage());
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Получить список областей знаний текущего пользователя
     */
    private function getKnowledgeAreas($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $stmt = $this->modx->prepare("
            SELECT ka.id, ka.name, ka.description, ka.test_ids,
                   ka.questions_per_session, ka.created_at, ka.updated_at
            FROM {$this->prefix}test_knowledge_areas ka
            WHERE ka.user_id = ? AND ka.is_active = 1
            ORDER BY ka.updated_at DESC
        ");
        $stmt->execute(array($userId));
        $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($areas as &$area) {
            $testIds = json_decode($area['test_ids'], true);
            if (!is_array($testIds) || empty($testIds)) {
                $area['tests_count'] = 0;
                $area['questions_count'] = 0;
                continue;
            }

            $area['tests_count'] = count($testIds);

            $placeholders = implode(',', array_fill(0, count($testIds), '?'));
            $stmt = $this->modx->prepare("
                SELECT COUNT(*)
                FROM {$this->prefix}test_questions
                WHERE test_id IN ($placeholders) AND published = 1
            ");
            $stmt->execute($testIds);
            $area['questions_count'] = (int)$stmt->fetchColumn();
        }

        return $this->success($areas);
    }

    /**
     * Создать область знаний
     */
    private function createKnowledgeArea($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $name = ValidationHelper::requireString($data, 'name', 'Name is required');
        $description = ValidationHelper::optionalString($data, 'description');
        $testIds = ValidationHelper::requireArray($data, 'test_ids', 1, 'At least one test must be selected');
        $questionsPerSession = ValidationHelper::optionalInt($data, 'questions_per_session', 20);
        $distributionMode = ValidationHelper::optionalString($data, 'question_distribution_mode', 'proportional');
        $minQuestionsPerTest = ValidationHelper::optionalInt($data, 'min_questions_per_test', 3);

        if (!in_array($distributionMode, array('proportional', 'equal'), true)) {
            $distributionMode = 'proportional';
        }

        // Валидация: все тесты должны существовать и быть активными
        $placeholders = implode(',', array_fill(0, count($testIds), '?'));
        $stmt = $this->modx->prepare("
            SELECT COUNT(*)
            FROM {$this->prefix}test_tests
            WHERE id IN ($placeholders) AND is_active = 1
        ");
        $stmt->execute($testIds);
        $validCount = (int)$stmt->fetchColumn();

        if ($validCount !== count($testIds)) {
            throw new Exception('Some tests are invalid or inactive');
        }

        // Ограничение: максимум 20 областей на пользователя
        $stmt = $this->modx->prepare("
            SELECT COUNT(*)
            FROM {$this->prefix}test_knowledge_areas
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt->execute(array($userId));
        $areasCount = (int)$stmt->fetchColumn();

        if ($areasCount >= 20) {
            throw new Exception('Maximum 20 knowledge areas per user');
        }

        $stmt = $this->modx->prepare("
            INSERT INTO {$this->prefix}test_knowledge_areas
            (user_id, name, description, test_ids, questions_per_session, question_distribution_mode, min_questions_per_test)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(array(
            $userId,
            $name,
            $description,
            json_encode(array_map('intval', $testIds)),
            $questionsPerSession,
            $distributionMode,
            $minQuestionsPerTest
        ));

        $areaId = $this->modx->lastInsertId();

        return $this->success(
            array('area_id' => $areaId),
            'Knowledge area created'
        );
    }

    /**
     * Обновить область знаний
     */
    private function updateKnowledgeArea($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $areaId = ValidationHelper::requireInt($data, 'area_id', 'Area ID required');
        $name = ValidationHelper::requireString($data, 'name', 'Name is required');
        $description = ValidationHelper::optionalString($data, 'description');
        $testIds = ValidationHelper::requireArray($data, 'test_ids', 1, 'At least one test must be selected');
        $questionsPerSession = ValidationHelper::optionalInt($data, 'questions_per_session', 20);
        $distributionMode = ValidationHelper::optionalString($data, 'question_distribution_mode', 'proportional');
        $minQuestionsPerTest = ValidationHelper::optionalInt($data, 'min_questions_per_test', 3);

        if (!in_array($distributionMode, array('proportional', 'equal'), true)) {
            $distributionMode = 'proportional';
        }

        // Проверяем что область принадлежит текущему пользователю
        $stmt = $this->modx->prepare("
            SELECT user_id
            FROM {$this->prefix}test_knowledge_areas
            WHERE id = ?
        ");
        $stmt->execute(array($areaId));
        $ownerId = (int)$stmt->fetchColumn();

        if ($ownerId !== $userId) {
            throw new Exception('Access denied');
        }

        // Валидация тестов
        $placeholders = implode(',', array_fill(0, count($testIds), '?'));
        $stmt = $this->modx->prepare("
            SELECT COUNT(*)
            FROM {$this->prefix}test_tests
            WHERE id IN ($placeholders) AND is_active = 1
        ");
        $stmt->execute($testIds);
        $validCount = (int)$stmt->fetchColumn();

        if ($validCount !== count($testIds)) {
            throw new Exception('Some tests are invalid or inactive');
        }

        $stmt = $this->modx->prepare("
            UPDATE {$this->prefix}test_knowledge_areas
            SET name = ?, description = ?, test_ids = ?, questions_per_session = ?,
                question_distribution_mode = ?, min_questions_per_test = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute(array(
            $name,
            $description,
            json_encode(array_map('intval', $testIds)),
            $questionsPerSession,
            $distributionMode,
            $minQuestionsPerTest,
            $areaId,
            $userId
        ));

        return $this->success(null, 'Knowledge area updated');
    }

    /**
     * Удалить область знаний (мягкое удаление)
     */
    private function deleteKnowledgeArea($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $areaId = ValidationHelper::requireInt($data, 'area_id', 'Area ID required');

        // Проверяем владельца
        $stmt = $this->modx->prepare("
            SELECT user_id
            FROM {$this->prefix}test_knowledge_areas
            WHERE id = ?
        ");
        $stmt->execute(array($areaId));
        $ownerId = (int)$stmt->fetchColumn();

        if ($ownerId !== $userId) {
            throw new Exception('Access denied');
        }

        $stmt = $this->modx->prepare("
            UPDATE {$this->prefix}test_knowledge_areas
            SET is_active = 0
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute(array($areaId, $userId));

        return $this->success(null, 'Knowledge area deleted');
    }

    /**
     * Получить детали области знаний
     */
    private function getKnowledgeAreaDetails($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $areaId = ValidationHelper::requireInt($data, 'area_id', 'Area ID required');

        $stmt = $this->modx->prepare("
            SELECT ka.*,
                   (SELECT COUNT(*) FROM {$this->prefix}test_questions q
                    WHERE FIND_IN_SET(q.test_id, REPLACE(REPLACE(ka.test_ids, '[', ''), ']', ''))
                    AND q.published = 1) as total_questions
            FROM {$this->prefix}test_knowledge_areas ka
            WHERE ka.id = ? AND ka.user_id = ? AND ka.is_active = 1
        ");

        $stmt->execute(array($areaId, $userId));
        $area = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$area) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "[KA] Area not found for areaId={$areaId}, userId={$userId}");
            throw new Exception('Knowledge area not found or access denied');
        }

        // Получаем информацию о тестах
        $testIds = json_decode($area['test_ids'], true);
        if (is_array($testIds) && !empty($testIds)) {
            $placeholders = implode(',', array_fill(0, count($testIds), '?'));
            $stmt = $this->modx->prepare("
                SELECT t.id, t.title, t.resource_id,
                       (SELECT COUNT(*) FROM {$this->prefix}test_questions WHERE test_id = t.id AND published = 1) as questions_count
                FROM {$this->prefix}test_tests t
                WHERE t.id IN ($placeholders) AND t.is_active = 1
                ORDER BY t.title
            ");
            $stmt->execute($testIds);
            $area['tests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $area['tests'] = array();
        }

        return $this->success($area);
    }

    /**
     * Получить дерево доступных тестов для добавления в область знаний
     */
    private function getAvailableTestsTree($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $stmt = $this->modx->prepare("
            SELECT DISTINCT
                t.id as test_id,
                t.title as test_title,
                t.resource_id,
                t.publication_status,
                t.created_by,
                t.category_id,
                c.name as category_title,
                (SELECT COUNT(*) FROM {$this->prefix}test_questions WHERE test_id = t.id AND published = 1) as questions_count,
                p.can_view,
                p.can_edit,
                p.expires_at,
                CASE
                    WHEN t.created_by = ? THEN 'owner'
                    WHEN t.publication_status IN ('public', 'unlisted') THEN 'public'
                    WHEN p.user_id IS NOT NULL AND p.can_view = 1
                         AND (p.expires_at IS NULL OR p.expires_at > NOW()) THEN 'shared'
                    ELSE NULL
                END as access_type
            FROM {$this->prefix}test_tests t
            LEFT JOIN {$this->prefix}test_categories c ON c.id = t.category_id
            LEFT JOIN {$this->prefix}test_permissions p ON p.test_id = t.id AND p.user_id = ?
            WHERE t.is_active = 1
            AND (
                t.publication_status IN ('public', 'unlisted')
                OR t.created_by = ?
                OR (
                    p.user_id = ?
                    AND p.can_view = 1
                    AND (p.expires_at IS NULL OR p.expires_at > NOW())
                )
            )
            HAVING access_type IS NOT NULL
            ORDER BY c.name, t.title
        ");

        $stmt->execute(array($userId, $userId, $userId, $userId));
        $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Группируем по категориям
        $tree = array();

        foreach ($tests as $test) {
            $categoryId = (int)($test['category_id'] ?? 0);

            if ($test['access_type'] === 'owner' && !in_array($test['publication_status'], array('public', 'unlisted'))) {
                $categoryId = -1;
                $categoryTitle = "\xF0\x9F\x93\x81 \xD0\x9C\xD0\xBE\xD0\xB8 \xD0\xBF\xD1\x80\xD0\xB8\xD0\xB2\xD0\xB0\xD1\x82\xD0\xBD\xD1\x8B\xD0\xB5 \xD1\x82\xD0\xB5\xD1\x81\xD1\x82\xD1\x8B"; // Мои приватные тесты
            } elseif ($test['access_type'] === 'shared') {
                $categoryId = -2;
                $categoryTitle = "\xF0\x9F\x94\x97 \xD0\x94\xD0\xBE\xD1\x81\xD1\x82\xD1\x83\xD0\xBF\xD0\xBD\xD1\x8B\xD0\xB5 \xD0\xBC\xD0\xBD\xD0\xB5"; // Доступные мне
            } else {
                $categoryTitle = $test['category_title'] ?? "\xD0\x91\xD0\xB5\xD0\xB7 \xD0\xBA\xD0\xB0\xD1\x82\xD0\xB5\xD0\xB3\xD0\xBE\xD1\x80\xD0\xB8\xD0\xB8"; // Без категории
            }

            if (!isset($tree[$categoryId])) {
                $tree[$categoryId] = array(
                    'category_id' => $categoryId,
                    'category_title' => $categoryTitle,
                    'tests' => array()
                );
            }

            $testTitle = $test['test_title'];

            if ($test['publication_status'] === 'draft') {
                $testTitle = "\xF0\x9F\x93\x9D " . $testTitle;
            } elseif ($test['publication_status'] === 'private') {
                $testTitle = "\xF0\x9F\x94\x92 " . $testTitle;
            } elseif ($test['publication_status'] === 'unlisted') {
                $testTitle = "\xF0\x9F\x94\x97 " . $testTitle;
            } elseif ($test['access_type'] === 'shared') {
                $testTitle = "\xF0\x9F\xA4\x9D " . $testTitle;
            }

            $tree[$categoryId]['tests'][] = array(
                'test_id' => (int)$test['test_id'],
                'test_title' => $testTitle,
                'questions_count' => (int)$test['questions_count'],
                'access_type' => $test['access_type'],
                'publication_status' => $test['publication_status']
            );
        }

        uksort($tree, function($a, $b) {
            if ($a < 0 && $b >= 0) return -1;
            if ($a >= 0 && $b < 0) return 1;
            if ($a === -1) return -1;
            if ($b === -1) return 1;
            return 0;
        });

        return $this->success(array_values($tree));
    }

    /**
     * Запустить сессию тестирования по области знаний
     */
    private function startKnowledgeAreaSession($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();
        $areaId = ValidationHelper::requireInt($data, 'area_id', 'Area ID required');

        // Загружаем область знаний
        $stmt = $this->modx->prepare("
            SELECT test_ids, questions_per_session, name, question_distribution_mode,
                   min_questions_per_test, user_id, is_active
            FROM {$this->prefix}test_knowledge_areas
            WHERE id = ?
        ");
        $stmt->execute(array($areaId));
        $area = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$area) {
            throw new Exception('Knowledge area not found');
        }

        if ((int)$area['is_active'] !== 1) {
            throw new Exception('Knowledge area is inactive');
        }

        if ((int)$area['user_id'] !== $userId) {
            $this->modx->log(modX::LOG_LEVEL_WARN, "[KA] Access denied: area {$areaId} belongs to user {$area['user_id']}, not {$userId}");
            throw new Exception('Knowledge area not found or access denied');
        }

        $testIds = json_decode($area['test_ids'], true);

        if (!is_array($testIds) || empty($testIds)) {
            throw new Exception('No tests in knowledge area');
        }

        $questionsLimit = (int)$area['questions_per_session'];
        $distributionMode = $area['question_distribution_mode'] ?? 'proportional';
        $minPerTest = (int)($area['min_questions_per_test'] ?? 3);

        // Получаем количество вопросов в каждом тесте
        $placeholders = implode(',', array_fill(0, count($testIds), '?'));
        $stmt = $this->modx->prepare("
            SELECT test_id, COUNT(*) as count
            FROM {$this->prefix}test_questions
            WHERE test_id IN ($placeholders) AND published = 1
            GROUP BY test_id
        ");
        $stmt->execute($testIds);
        $testsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $testQuestionCounts = array();
        foreach ($testsData as $row) {
            $testQuestionCounts[(int)$row['test_id']] = (int)$row['count'];
        }

        // Рассчитываем распределение вопросов
        $distribution = array();

        if ($distributionMode === 'equal') {
            $questionsPerTest = floor($questionsLimit / count($testIds));
            $remainder = $questionsLimit % count($testIds);

            foreach ($testIds as $idx => $testId) {
                $allocated = $questionsPerTest;

                if ($idx < $remainder) {
                    $allocated++;
                }

                $available = $testQuestionCounts[$testId] ?? 0;
                $allocated = min($allocated, $available);
                $allocated = max($allocated, min($minPerTest, $available));

                $distribution[$testId] = $allocated;
            }
        } else {
            $totalAvailable = array_sum($testQuestionCounts);
            $allocated = 0;

            foreach ($testIds as $idx => $testId) {
                $available = $testQuestionCounts[$testId] ?? 0;

                if ($idx === count($testIds) - 1) {
                    $questionsFromTest = $questionsLimit - $allocated;
                } else {
                    $proportion = $totalAvailable > 0 ? $available / $totalAvailable : 0;
                    $questionsFromTest = round($questionsLimit * $proportion);
                }

                $questionsFromTest = min($questionsFromTest, $available);
                $questionsFromTest = max($questionsFromTest, min($minPerTest, $available));

                $allocated += $questionsFromTest;
                $distribution[$testId] = $questionsFromTest;
            }
        }

        // Собираем вопросы согласно распределению
        $allQuestionIds = array();

        foreach ($distribution as $testId => $count) {
            if ($count <= 0) continue;

            $testId = (int)$testId;
            $count = (int)$count;

            $stmt = $this->modx->prepare("
                SELECT id
                FROM {$this->prefix}test_questions
                WHERE test_id = ? AND published = 1
                ORDER BY RAND()
                LIMIT {$count}
            ");

            $stmt->execute(array($testId));
            $questions = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $allQuestionIds = array_merge($allQuestionIds, $questions);
        }

        shuffle($allQuestionIds);

        if (empty($allQuestionIds)) {
            throw new Exception('No questions found in selected tests');
        }

        // Создаем сессию с test_id = -1 (маркер области знаний)
        $stmt = $this->modx->prepare("
            INSERT INTO {$this->prefix}test_sessions
            (test_id, user_id, mode, question_order, status, started_at)
            VALUES (-1, ?, 'training', ?, 'active', NOW())
        ");

        if (!$stmt->execute(array($userId, json_encode($allQuestionIds)))) {
            throw new Exception('Failed to create session');
        }

        $sessionId = (int)$this->modx->lastInsertId();

        return $this->success(array(
            'session_id' => $sessionId,
            'area_name' => $area['name'],
            'total_questions' => count($allQuestionIds),
            'is_knowledge_area' => true,
            'distribution' => $distribution
        ));
    }
}
