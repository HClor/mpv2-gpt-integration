<?php
/* TS API v3.4.1 - FIXED getNextQuestion response structure */


error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/testsystem_errors.log');

 
$configPath = dirname(dirname(dirname(dirname(__FILE__)))) . '/config.core.php';

if (!file_exists($configPath)) {
    $configPath = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.core.php';
}

if (!file_exists($configPath)) {
    die(json_encode(['success' => false, 'message' => 'Config file not found']));
}

require_once $configPath;
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('web');
$modx->getService('error','error.modError');

// Подключаем bootstrap для автозагрузки классов безопасности
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

$prefix = $modx->getOption('table_prefix', null, 'modx_');

header('Content-Type: application/json; charset=utf-8');

$input = file_get_contents('php://input');
$request = json_decode($input, true);

$action = $request['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';
$data = $request['data'] ?? [];

if (empty($data)) {
    if ($action === 'getQuestion' && isset($_GET['question_id'])) {
        $data['question_id'] = $_GET['question_id'];
    }
    if ($action === 'deleteQuestion' && isset($_GET['question_id'])) {
        $data['question_id'] = $_GET['question_id'];
    }
    if ($action === 'getTestSettings' && isset($_GET['test_id'])) {
        $data['test_id'] = $_GET['test_id'];
    }
}

$response = ['success' => false, 'message' => 'Unknown action'];

// ============================================
// CSRF PROTECTION
// ============================================
// Список actions, которые НЕ требуют CSRF проверки (только чтение данных)
$csrfExemptActions = [
    'getTestInfo',
    'getQuestion',
    'getTestSettings',
    'checkEditRights',
    'getUserTestHistory',
    'getDetailedResults',
    'getKnowledgeAreas',
    'getTestPermissions',
    'getAllQuestionsForTest',
    'getTestAccess'
];

// Если это POST запрос и action требует CSRF проверки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, $csrfExemptActions, true)) {
    try {
        // Проверяем CSRF токен
        CsrfProtection::requireValidToken($data);
    } catch (Exception $e) {
        // CSRF токен невалиден
        die(json_encode([
            'success' => false,
            'message' => 'CSRF token validation failed. Please refresh the page and try again.'
        ]));
    }
}

/**
 * Legacy функция для обратной совместимости
 * @deprecated Используйте PermissionHelper::getUserRights() напрямую
 */
function checkUserRights($modx) {
    return PermissionHelper::getUserRights($modx);
}

/**
 * Legacy функция для обратной совместимости
 * IDOR Protection: проверяет владение тестом и явные разрешения
 * @deprecated Используйте PermissionHelper::canEditTest() напрямую
 */
function canUserEditTest($modx, $testId) {
    return PermissionHelper::canEditTest($modx, $testId);
}

try {
    
    switch ($action) {
        

        case 'getTestInfo':
            // Валидация входных данных
            $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');

            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);
            
            // Загружаем тест
            $stmt = $modx->prepare("
                SELECT id, title, description, mode, time_limit, pass_score, 
                       questions_per_session, created_by, publication_status
                FROM modx_test_tests 
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$testId]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$test) {
                throw new Exception('Test not found');
            }

            // Проверка доступа с использованием PermissionHelper
            $access = PermissionHelper::requireTestAccess($modx, $test, 'Access denied');
            $canEdit = $access['canEdit'];
            
            // Подсчет вопросов
            $stmt = $modx->prepare("SELECT COUNT(*) FROM modx_test_questions WHERE test_id = ? AND published = 1");
            $stmt->execute([$testId]);
            $test['total_questions'] = (int)$stmt->fetchColumn();
            $test['can_edit'] = $canEdit;

            $response = ResponseHelper::success($test);
            break;



        case 'createQuestion':
            // Проверка прав доступа
            PermissionHelper::requireEditRights($modx, 'No permission to create questions');

            // Валидация входных данных
            $testId = ValidationHelper::requireInt($data, 'test_id', 'Test ID is required');
            $questionText = ValidationHelper::requireString($data, 'question_text', 'Question text is required');
            $questionType = ValidationHelper::optionalString($data, 'question_type', 'single');
            $explanation = ValidationHelper::optionalString($data, 'explanation');
            $questionImage = ValidationHelper::optionalString($data, 'question_image');
            $explanationImage = ValidationHelper::optionalString($data, 'explanation_image');
            $published = ValidationHelper::optionalInt($data, 'published', 1);
            $isLearning = ValidationHelper::optionalInt($data, 'is_learning', 0);
            $answers = ValidationHelper::requireArray($data, 'answers', 2, 'At least 2 answers required');

            // Валидация типа вопроса
            $questionType = ValidationHelper::validateQuestionType($questionType);

            // Проверка наличия правильного ответа
            ValidationHelper::requireCorrectAnswer($answers);
            
            try {
                // Получаем максимальный sort_order
                $stmt = $modx->prepare("SELECT MAX(sort_order) FROM modx_test_questions WHERE test_id = ?");
                $stmt->execute([$testId]);
                $maxSort = (int)$stmt->fetchColumn();
                $newSort = $maxSort + 1;
                
                // Создаем вопрос (БЕЗ created_at)
                $sql = "INSERT INTO modx_test_questions 
                        (test_id, question_text, question_type, explanation, question_image, explanation_image, published, is_learning, sort_order)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $modx->prepare($sql);
                
                if (!$stmt) {
                    throw new Exception('Database error: failed to prepare statement');
                }
                
                $executeResult = $stmt->execute([
                    $testId,
                    $questionText,
                    $questionType,
                    $explanation,
                    $questionImage,
                    $explanationImage,
                    $published,
                    $isLearning,
                    $newSort
                ]);
                
                if (!$executeResult) {
                    $errorInfo = $stmt->errorInfo();
                    throw new Exception('Database error: ' . $errorInfo[2]);
                }
                
                $questionId = (int)$modx->lastInsertId();
                
                if ($questionId <= 0) {
                    throw new Exception('Failed to get new question ID');
                }
                
                // Добавляем ответы
                $sortOrder = 1;
                $addedAnswers = 0;
                
                foreach ($answers as $answer) {
                    $answerText = trim($answer['text'] ?? '');
                    if (empty($answerText)) {
                        continue;
                    }
                    
                    $isCorrect = (int)($answer['is_correct'] ?? 0);
                    
                    $stmt = $modx->prepare("
                        INSERT INTO modx_test_answers 
                        (question_id, answer_text, is_correct, sort_order)
                        VALUES (?, ?, ?, ?)
                    ");
                    
                    if (!$stmt) {
                        throw new Exception('Database error: failed to prepare answer insert');
                    }
                    
                    $answerResult = $stmt->execute([
                        $questionId,
                        $answerText,
                        $isCorrect,
                        $sortOrder
                    ]);
                    
                    if (!$answerResult) {
                        $errorInfo = $stmt->errorInfo();
                        throw new Exception('Failed to insert answer: ' . $errorInfo[2]);
                    }
                    
                    $addedAnswers++;
                    $sortOrder++;
                }
                
                if ($addedAnswers < 2) {
                    // Откатываем создание вопроса
                    $modx->prepare("DELETE FROM modx_test_questions WHERE id = ?")->execute([$questionId]);
                    throw new Exception('Failed to add minimum 2 answers');
                }

                $response = ResponseHelper::success(
                    ['question_id' => $questionId],
                    'Question created successfully'
                );
                
            } catch (PDOException $e) {
                throw new Exception('Database error: ' . $e->getMessage());
            }
            break;
        




        case 'startSession':
            // Валидация входных данных
            $testId = ValidationHelper::requireInt($data, 'test_id', 'Test ID required');
            $mode = ValidationHelper::optionalString($data, 'mode', 'training');
            $requestedCount = isset($data['questions_count']) ? ValidationHelper::requireInt($data, 'questions_count', null, false, 1) : null;

            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Please login first');

            $userId = PermissionHelper::getCurrentUserId($modx);

            // Используем SessionService для запуска сессии
            $sessionData = SessionService::startSession($modx, $testId, $userId, $mode, $requestedCount);

            $response = ResponseHelper::success($sessionData);
            break;

        case 'cleanupOldSessions':
            // Удаляем сессии старше 24 часов
            $modx->exec("
                UPDATE {$prefix}test_sessions
                SET status = 'expired'
                WHERE status = 'active'
                AND started_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");

            $response = ResponseHelper::success();
            break;
        

        case 'getNextQuestion':
            // Валидация входных данных
            $sessionId = ValidationHelper::requireInt($data, 'session_id', 'Session ID required');
            
            // ИСПРАВЛЕНИЕ: Добавляем поддержку областей знаний (test_id = -1)
            $stmt = $modx->prepare("
                SELECT s.test_id, s.mode, s.status, s.question_order, 
                       COALESCE(t.randomize_answers, 1) as randomize_answers
                FROM modx_test_sessions s
                LEFT JOIN modx_test_tests t ON t.id = s.test_id
                WHERE s.id = ?
            ");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session || $session['status'] !== 'active') {
                throw new Exception('Invalid or completed session');
            }
            
            $questionOrder = json_decode($session['question_order'], true);
            
            $stmt = $modx->prepare("
                SELECT question_id 
                FROM modx_test_user_answers 
                WHERE session_id = ?
            ");
            $stmt->execute([$sessionId]);
            $answeredIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $nextQuestionId = null;
            foreach ($questionOrder as $qid) {
                if (!in_array($qid, $answeredIds)) {
                    $nextQuestionId = $qid;
                    break;
                }
            }
            
            if (!$nextQuestionId) {
                $response = ResponseHelper::success(['finished' => true]);
                break;
            }
            

            // ИСПРАВЛЕНИЕ: Для областей знаний добавляем название теста
            $isKnowledgeArea = ((int)$session['test_id'] === -1);
            
            if ($isKnowledgeArea) {
                // Получаем вопрос ВМЕСТЕ с названием теста
                $stmt = $modx->prepare("
                    SELECT q.id, q.question_text, q.question_type, q.question_image, q.test_id,
                           t.title as test_title
                    FROM modx_test_questions q
                    LEFT JOIN modx_test_tests t ON t.id = q.test_id
                    WHERE q.id = ? AND q.published = 1
                ");
            } else {
                // Обычный тест - без названия
                $stmt = $modx->prepare("
                    SELECT id, question_text, question_type, question_image 
                    FROM modx_test_questions 
                    WHERE id = ? AND published = 1
                ");
            }
            
            $stmt->execute([$nextQuestionId]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);

            $sql = "
                SELECT id, answer_text 
                FROM modx_test_answers 
                WHERE question_id = ?
            ";
            if ($session['randomize_answers']) {
                $sql .= " ORDER BY RAND()";
            } else {
                $sql .= " ORDER BY sort_order";
            }
            
            $stmt = $modx->prepare($sql);
            $stmt->execute([$nextQuestionId]);
            $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ИСПРАВЛЕНИЕ: правильная структура ответа
            $response = ResponseHelper::success([
                'question' => $question,
                'answers' => $answers,
                'current' => count($answeredIds) + 1,
                'total' => count($questionOrder)
            ]);
            break;
        
        case 'togglePublished':
            // Проверка прав доступа
            PermissionHelper::requireEditRights($modx, 'No permission');

            // Валидация входных данных
            $questionId = ValidationHelper::requireQuestionId($data['question_id'] ?? 0, 'Question ID required');
            
            // Получаем текущий статус
            $stmt = $modx->prepare("SELECT published FROM modx_test_questions WHERE id = ?");
            $stmt->execute([$questionId]);
            $current = (int)$stmt->fetchColumn();
            
            // Переключаем
            $newStatus = $current ? 0 : 1;
            $stmt = $modx->prepare("UPDATE modx_test_questions SET published = ? WHERE id = ?");
            $stmt->execute([$newStatus, $questionId]);

            $response = ResponseHelper::success(['published' => $newStatus]);
            break;

        case 'toggleLearning':
            // Проверка прав доступа
            PermissionHelper::requireEditRights($modx, 'No permission');

            // Валидация входных данных
            $questionId = ValidationHelper::requireQuestionId($data['question_id'] ?? 0, 'Question ID required');
            
            // Получаем текущий статус
            $stmt = $modx->prepare("SELECT is_learning FROM modx_test_questions WHERE id = ?");
            $stmt->execute([$questionId]);
            $current = (int)$stmt->fetchColumn();
            
            // Переключаем
            $newStatus = $current ? 0 : 1;
            $stmt = $modx->prepare("UPDATE modx_test_questions SET is_learning = ? WHERE id = ?");
            $stmt->execute([$newStatus, $questionId]);

            $response = ResponseHelper::success(['is_learning' => $newStatus]);
            break;
        
        

        case 'submitAnswer':
            // Валидация входных данных
            $sessionId = ValidationHelper::requireInt($data, 'session_id', 'Session ID required');
            $questionId = ValidationHelper::requireInt($data, 'question_id', 'Question ID required');
            $answerIds = $data['answer_ids'] ?? [];

            // Используем SessionService для отправки ответа
            $responseData = SessionService::submitAnswer($modx, $sessionId, $questionId, $answerIds);

            $response = ResponseHelper::success($responseData);
            break;

        case 'finishTest':
            // Валидация входных данных
            $sessionId = ValidationHelper::requireInt($data, 'session_id', 'Session ID required');
            
            $stmt = $modx->prepare("
                SELECT s.test_id, s.mode, s.status, t.pass_score
                FROM modx_test_sessions s
                JOIN modx_test_tests t ON t.id = s.test_id
                WHERE s.id = ?
            ");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                throw new Exception('Session not found');
            }
            
            $stmt = $modx->prepare("
                SELECT COUNT(*) as total,
                       SUM(is_correct) as correct
                FROM modx_test_user_answers
                WHERE session_id = ?
            ");
            $stmt->execute([$sessionId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $total = (int)$stats['total'];
            $correct = (int)$stats['correct'];
            $score = $total > 0 ? round(($correct / $total) * 100) : 0;
            $passed = $score >= (int)$session['pass_score'];
            
            $stmt = $modx->prepare("
                UPDATE modx_test_sessions 
                SET status = 'completed', 
                    score = ?, 
                    passed = ?,
                    completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$score, $passed ? 1 : 0, $sessionId]);

            
            // ДОБАВИТЬ ПОСЛЕ finishTest (около строки 420)
            // Обновляем статистику по категориям
            $stmtTest = $modx->prepare("SELECT resource_id FROM modx_test_tests WHERE id = ?");
            $stmtTest->execute([$session['test_id']]);
            $testData = $stmtTest->fetch(PDO::FETCH_ASSOC);
            
            if ($testData && $testData['resource_id']) {
                // Получаем категорию теста (parent ресурса)
                $stmtRes = $modx->prepare("SELECT parent FROM modx_site_content WHERE id = ?");
                $stmtRes->execute([$testData['resource_id']]);
                $resData = $stmtRes->fetch(PDO::FETCH_ASSOC);
                
                if ($resData && $resData['parent']) {
                    $categoryId = (int)$resData['parent'];
                    
                    // Обновляем/создаем статистику по категории
                    $stmtCatStats = $modx->prepare("
                        INSERT INTO modx_test_category_stats 
                        (user_id, category_id, tests_completed, tests_passed, avg_score_pct)
                        VALUES (?, ?, 1, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            tests_completed = tests_completed + 1,
                            tests_passed = tests_passed + ?,
                            avg_score_pct = (avg_score_pct * tests_completed + ?) / (tests_completed + 1)
                    ");
                    $stmtCatStats->execute([
                        $modx->user->id,
                        $categoryId,
                        $passed ? 1 : 0,
                        $score,
                        $passed ? 1 : 0,
                        $score
                    ]);
                }
            }

            $response = ResponseHelper::success([
                'score' => $score,
                'passed' => $passed,
                'correct_count' => $correct,
                'incorrect_count' => $total - $correct,
                'total_count' => $total,
                'pass_score' => (int)$session['pass_score']
            ]);
            break;



        case 'getAllQuestions':
            // Валидация входных данных
            $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');

            // ВАЖНО: НЕ фильтруем по is_learning здесь, показываем ВСЕ вопросы
            $stmt = $modx->prepare("
                SELECT id, question_text, explanation, question_type, published, is_learning
                FROM modx_test_questions 
                WHERE test_id = ?
                ORDER BY sort_order
            ");
            $stmt->execute([$testId]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Логирование для отладки
            //error_log('getAllQuestions: testId=' . $testId . ', count=' . count($questions));

            $response = ResponseHelper::success($questions);
            break;


        case 'checkEditRights':
            $rights = PermissionHelper::getUserRights($modx);

            $response = ResponseHelper::success($rights);
            break;
        
        case 'getQuestion':
            // Проверка прав доступа
            PermissionHelper::requireEditRights($modx, 'No permission to edit questions');

            // Валидация входных данных
            $questionId = ValidationHelper::requireQuestionId($data['question_id'] ?? 0, 'Question ID required');
            
            $stmt = $modx->prepare("
                SELECT id, question_text, question_type, explanation, test_id,
                        question_image, explanation_image, published, is_learning
                FROM modx_test_questions 
                WHERE id = ?
            ");
            
            if (!$stmt) {
                throw new Exception('Database error');
            }
            
            $stmt->execute([$questionId]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$question) {
                throw new Exception('Question not found');
            }

            // IDOR Protection: проверяем право редактировать тест, которому принадлежит вопрос
            if (!canUserEditTest($modx, $question['test_id'])) {
                throw new Exception('You do not have permission to edit this test');
            }

            $stmt = $modx->prepare("
                SELECT id, answer_text, is_correct, sort_order
                FROM modx_test_answers 
                WHERE question_id = ?
                ORDER BY sort_order
            ");
            $stmt->execute([$questionId]);
            $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $question['answers'] = $answers;

            $response = ResponseHelper::success($question);
            break;
        


        case 'updateQuestion':
            // Проверка прав доступа
            PermissionHelper::requireEditRights($modx, 'No permission to edit questions');

            // Валидация входных данных
            $questionId = ValidationHelper::requireQuestionId($data['question_id'] ?? 0, 'Question ID required');
            $questionText = ValidationHelper::requireString($data, 'question_text', 'Question text is required');
            $questionType = ValidationHelper::optionalString($data, 'question_type', 'single');
            $explanation = ValidationHelper::optionalString($data, 'explanation');
            $questionImage = ValidationHelper::optionalString($data, 'question_image');
            $explanationImage = ValidationHelper::optionalString($data, 'explanation_image');
            $published = ValidationHelper::optionalInt($data, 'published', 1);
            $isLearning = ValidationHelper::optionalInt($data, 'is_learning', 0);
            $answers = $data['answers'] ?? [];

            // Валидация типа вопроса
            $questionType = ValidationHelper::validateQuestionType($questionType);
            
            $stmt = $modx->prepare("
                UPDATE modx_test_questions 
                SET question_text = ?, question_type = ?, explanation = ?,
                    question_image = ?, explanation_image = ?, published = ?, is_learning = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $questionText, 
                $questionType, 
                $explanation, 
                $questionImage, 
                $explanationImage,
                $published,
                $isLearning,
                $questionId
            ]);
            
            foreach ($answers as $answer) {
                if (empty($answer['id'])) continue;

                $stmt = $modx->prepare("
                    UPDATE modx_test_answers
                    SET answer_text = ?, is_correct = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $answer['text'],
                    $answer['is_correct'],
                    $answer['id']
                ]);
            }

            $response = ResponseHelper::success(null, 'Question updated');
            break;
        


        case 'deleteQuestion':
            // Проверка прав доступа
            PermissionHelper::requireEditRights($modx, 'No permission to delete questions');

            // Валидация входных данных
            $questionId = ValidationHelper::requireQuestionId($data['question_id'] ?? 0, 'Question ID required');
            $sessionId = ValidationHelper::optionalInt($data, 'session_id', 0);

            // IDOR Protection: получаем test_id вопроса и проверяем право на редактирование
            $stmt = $modx->prepare("SELECT test_id FROM modx_test_questions WHERE id = ?");
            if (!$stmt || !$stmt->execute([$questionId])) {
                throw new Exception('Database error');
            }

            $question = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$question) {
                throw new Exception('Question not found');
            }

            // Проверяем право редактировать тест, которому принадлежит вопрос
            if (!canUserEditTest($modx, $question['test_id'])) {
                throw new Exception('You do not have permission to delete questions from this test');
            }

            if ($sessionId > 0) {
                $stmt = $modx->prepare("SELECT question_order FROM modx_test_sessions WHERE id = ?");
                $stmt->execute([$sessionId]);
                $session = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($session) {
                    $questionOrder = json_decode($session['question_order'], true);
                    $questionOrder = array_values(array_filter($questionOrder, function($qid) use ($questionId) {
                        return $qid != $questionId;
                    }));
                    
                    $stmt = $modx->prepare("UPDATE modx_test_sessions SET question_order = ? WHERE id = ?");
                    $stmt->execute([json_encode($questionOrder), $sessionId]);
                }
            }
            
            $stmt = $modx->prepare("DELETE FROM modx_test_user_answers WHERE question_id = ?");
            $stmt->execute([$questionId]);
            
            $stmt = $modx->prepare("DELETE FROM modx_test_answers WHERE question_id = ?");
            $stmt->execute([$questionId]);
            
            $stmt = $modx->prepare("DELETE FROM modx_test_questions WHERE id = ?");
            $stmt->execute([$questionId]);

            $response = ResponseHelper::success(null, 'Question deleted');
            break;

        case 'getTestSettings':
            // Проверка прав доступа
            PermissionHelper::requireEditRights($modx, 'No permission to edit test settings');

            // Валидация входных данных
            $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');
            
            $stmt = $modx->prepare("
                SELECT id, title, description, is_active, is_learning_material,
                       mode, time_limit, pass_score, questions_per_session,
                       randomize_questions, randomize_answers
                FROM modx_test_tests 
                WHERE id = ?
            ");
            $stmt->execute([$testId]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$test) {
                throw new Exception('Test not found');
            }

            $response = ResponseHelper::success($test);
            break;



        case 'getQuestionAnswers':
            // Валидация входных данных
            $questionId = ValidationHelper::requireQuestionId($data['question_id'] ?? 0, 'Question ID required');
            $sessionId = ValidationHelper::requireInt($data, 'session_id', 'Session ID required');
            
            // Получаем сессию для проверки режима
            $stmt = $modx->prepare("
                SELECT s.mode, t.randomize_answers 
                FROM modx_test_sessions s
                JOIN modx_test_tests t ON t.id = s.test_id
                WHERE s.id = ?
            ");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                throw new Exception('Session not found');
            }
            
            // Загружаем ответы
            $sql = "
                SELECT id, answer_text 
                FROM modx_test_answers 
                WHERE question_id = ?
                ORDER BY sort_order
            ";
            
            $stmt = $modx->prepare($sql);
            $stmt->execute([$questionId]);
            $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Проверяем, был ли уже дан ответ на этот вопрос
            $stmt = $modx->prepare("
                SELECT DISTINCT answer_id, is_correct
                FROM modx_test_user_answers
                WHERE session_id = ? AND question_id = ?
            ");
            $stmt->execute([$sessionId, $questionId]);
            $userAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $responseData = [
                'answers' => $answers,
                'user_answered' => count($userAnswers) > 0
            ];
            
            // Если пользователь уже ответил и режим тренинга - возвращаем feedback
            if (count($userAnswers) > 0 && $session['mode'] === 'training') {
                $userAnswerIds = array_column($userAnswers, 'answer_id');
                
                // Получаем правильные ответы
                $stmt = $modx->prepare("
                    SELECT id 
                    FROM modx_test_answers 
                    WHERE question_id = ? AND is_correct = 1
                ");
                $stmt->execute([$questionId]);
                $correctIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Получаем объяснение
                $stmt = $modx->prepare("
                    SELECT explanation, explanation_image 
                    FROM modx_test_questions 
                    WHERE id = ?
                ");
                $stmt->execute([$questionId]);
                $qData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // ИСПРАВЛЕНИЕ: Убедимся что все данные корректны
                $responseData['feedback'] = [
                    'user_answer_ids' => array_values(array_unique(array_map('intval', $userAnswerIds))),
                    'correct_answer_ids' => array_values(array_map('intval', $correctIds)),
                    'explanation' => $qData['explanation'] ?? '',
                    'explanation_image' => $qData['explanation_image'] ?? '',
                    'is_correct' => (count(array_diff($correctIds, $userAnswerIds)) === 0 && count(array_diff($userAnswerIds, $correctIds)) === 0) ? 1 : 0
                ];
                
                // Логирование для отладки
                //error_log('getQuestionAnswers feedback: ' . json_encode($responseData['feedback']));
            }
            
            $response = ResponseHelper::success($responseData);
            break;


        case 'updateTestSettings':
            // Проверка прав доступа
            PermissionHelper::requireEditRights($modx, 'No permission to edit test settings');

            // Валидация входных данных
            $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');
            $title = ValidationHelper::requireString($data, 'title', 'Title is required');
            $description = ValidationHelper::optionalString($data, 'description');
            $isActive = ValidationHelper::optionalInt($data, 'is_active', 1);
            $isLearningMaterial = ValidationHelper::optionalInt($data, 'is_learning_material', 0);
            
            $stmt = $modx->prepare("
                UPDATE modx_test_tests 
                SET title = ?, 
                    description = ?, 
                    is_active = ?,
                    is_learning_material = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $isActive, $isLearningMaterial, $testId]);

            $response = ResponseHelper::success(null, 'Test settings updated');
            break;

        case 'toggleFavorite':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            // Валидация входных данных
            $questionId = ValidationHelper::requireQuestionId($data['question_id'] ?? 0, 'Question ID required');
            $userId = PermissionHelper::getCurrentUserId($modx);
            
            // Проверяем существование
            $stmt = $modx->prepare("
                SELECT COUNT(*) FROM modx_test_favorites 
                WHERE user_id = ? AND question_id = ?
            ");
            $stmt->execute([$userId, $questionId]);
            $exists = (int)$stmt->fetchColumn() > 0;
            
            if ($exists) {
                // Удаляем из избранного
                $stmt = $modx->prepare("
                    DELETE FROM modx_test_favorites 
                    WHERE user_id = ? AND question_id = ?
                ");
                $stmt->execute([$userId, $questionId]);
                $isFavorite = false;
            } else {
                // Добавляем в избранное
                $stmt = $modx->prepare("
                    INSERT INTO modx_test_favorites (user_id, question_id) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$userId, $questionId]);
                $isFavorite = true;
            }

            $response = ResponseHelper::success(['is_favorite' => $isFavorite]);
            break;

        case 'getFavoriteStatus':
            if (!$modx->user->hasSessionContext('web')) {
                $response = ResponseHelper::success(['is_favorite' => false]);
                break;
            }

            // Валидация входных данных
            $questionId = ValidationHelper::requireQuestionId($data['question_id'] ?? 0, 'Question ID required');
            $userId = PermissionHelper::getCurrentUserId($modx);
            
            $stmt = $modx->prepare("
                SELECT COUNT(*) FROM modx_test_favorites 
                WHERE user_id = ? AND question_id = ?
            ");
            $stmt->execute([$userId, $questionId]);
            $isFavorite = (int)$stmt->fetchColumn() > 0;

            $response = ResponseHelper::success(['is_favorite' => $isFavorite]);
            break;

        case 'getFavoriteQuestions':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Требуется авторизация');

            $userId = PermissionHelper::getCurrentUserId($modx);
            
            $stmt = $modx->prepare("
                SELECT 
                    q.id,
                    q.question_text,
                    q.question_type,
                    q.question_image,
                    q.explanation,
                    q.explanation_image,
                    t.title as test_title,
                    t.resource_id
                FROM modx_test_favorites f
                INNER JOIN modx_test_questions q ON q.id = f.question_id
                INNER JOIN modx_test_tests t ON t.id = q.test_id
                WHERE f.user_id = ?
                ORDER BY f.added_at DESC
            ");
            
            $stmt->execute([$userId]);
            $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = ResponseHelper::success($favorites);
            break;


        // ============================================
        // KNOWLEDGE AREAS API
        // ============================================

        case 'getKnowledgeAreas':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);
            
            $stmt = $modx->prepare("
                SELECT ka.id, ka.name, ka.description, ka.test_ids, 
                       ka.questions_per_session, ka.created_at, ka.updated_at
                FROM modx_test_knowledge_areas ka
                WHERE ka.user_id = ? AND ka.is_active = 1
                ORDER BY ka.updated_at DESC
            ");
            $stmt->execute([$userId]);
            $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Подсчитываем количество вопросов в каждой области
            foreach ($areas as &$area) {
                $testIds = json_decode($area['test_ids'], true);
                if (!is_array($testIds) || empty($testIds)) {
                    $area['tests_count'] = 0;
                    $area['questions_count'] = 0;
                    continue;
                }
                
                $area['tests_count'] = count($testIds);
                
                // Подсчет вопросов
                $placeholders = implode(',', array_fill(0, count($testIds), '?'));
                $stmt = $modx->prepare("
                    SELECT COUNT(*) 
                    FROM modx_test_questions 
                    WHERE test_id IN ($placeholders) AND published = 1
                ");
                $stmt->execute($testIds);
                $area['questions_count'] = (int)$stmt->fetchColumn();
            }
            
            $response = ResponseHelper::success($areas);
            break;

        case 'createKnowledgeArea':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);

            // Валидация входных данных
            $name = ValidationHelper::requireString($data, 'name', 'Name is required');
            $description = ValidationHelper::optionalString($data, 'description');
            $testIds = ValidationHelper::requireArray($data, 'test_ids', 1, 'At least one test must be selected');
            $questionsPerSession = ValidationHelper::optionalInt($data, 'questions_per_session', 20);
            
            // Валидация: все тесты должны существовать и быть активными
            $placeholders = implode(',', array_fill(0, count($testIds), '?'));
            $stmt = $modx->prepare("
                SELECT COUNT(*) 
                FROM modx_test_tests 
                WHERE id IN ($placeholders) AND is_active = 1
            ");
            $stmt->execute($testIds);
            $validCount = (int)$stmt->fetchColumn();
            
            if ($validCount !== count($testIds)) {
                throw new Exception('Some tests are invalid or inactive');
            }
            
            // Ограничение: максимум 20 областей на пользователя
            $stmt = $modx->prepare("
                SELECT COUNT(*) 
                FROM modx_test_knowledge_areas 
                WHERE user_id = ? AND is_active = 1
            ");
            $stmt->execute([$userId]);
            $areasCount = (int)$stmt->fetchColumn();
            
            if ($areasCount >= 20) {
                throw new Exception('Maximum 20 knowledge areas per user');
            }
            
            // Создаем область знаний
            $stmt = $modx->prepare("
                INSERT INTO modx_test_knowledge_areas 
                (user_id, name, description, test_ids, questions_per_session)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $name,
                $description,
                json_encode(array_map('intval', $testIds)),
                $questionsPerSession
            ]);
            
            $areaId = $modx->lastInsertId();

            $response = ResponseHelper::success(
                ['area_id' => $areaId],
                'Knowledge area created'
            );
            break;

        case 'updateKnowledgeArea':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);

            // Валидация входных данных
            $areaId = ValidationHelper::requireInt($data, 'area_id', 'Area ID required');
            $name = ValidationHelper::requireString($data, 'name', 'Name is required');
            $description = ValidationHelper::optionalString($data, 'description');
            $testIds = ValidationHelper::requireArray($data, 'test_ids', 1, 'At least one test must be selected');
            $questionsPerSession = ValidationHelper::optionalInt($data, 'questions_per_session', 20);
            $distributionMode = ValidationHelper::optionalString($data, 'question_distribution_mode', 'proportional');
            $minQuestionsPerTest = ValidationHelper::optionalInt($data, 'min_questions_per_test', 3);

            // Валидация режима распределения
            if (!in_array($distributionMode, ['proportional', 'equal'], true)) {
                $distributionMode = 'proportional';
            }

                    // Проверяем что область принадлежит текущему пользователю
            $stmt = $modx->prepare("
                SELECT user_id
                FROM modx_test_knowledge_areas
                WHERE id = ?
            ");
            $stmt->execute([$areaId]);
            $ownerId = (int)$stmt->fetchColumn();

            if ($ownerId !== $userId) {
                throw new Exception('Access denied');
            }

            // Валидация тестов
            $placeholders = implode(',', array_fill(0, count($testIds), '?'));
            $stmt = $modx->prepare("
                SELECT COUNT(*)
                FROM modx_test_tests
                WHERE id IN ($placeholders) AND is_active = 1
            ");
            $stmt->execute($testIds);
            $validCount = (int)$stmt->fetchColumn();

            if ($validCount !== count($testIds)) {
                throw new Exception('Some tests are invalid or inactive');
            }

            // Обновляем
            $stmt = $modx->prepare("
                UPDATE modx_test_knowledge_areas
                SET name = ?, description = ?, test_ids = ?, questions_per_session = ?,
                    question_distribution_mode = ?, min_questions_per_test = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $name,
                $description,
                json_encode(array_map('intval', $testIds)),
                $questionsPerSession,
                $distributionMode,
                $minQuestionsPerTest,
                $areaId,
                $userId
            ]);

            $response = ResponseHelper::success(null, 'Knowledge area updated');
            break;
    
    
        case 'deleteKnowledgeArea':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);

            // Валидация входных данных
            $areaId = ValidationHelper::requireInt($data, 'area_id', 'Area ID required');

            // КРИТИЧНО: Проверяем владельца
            $stmt = $modx->prepare("
                SELECT user_id 
                FROM modx_test_knowledge_areas 
                WHERE id = ?
            ");
            $stmt->execute([$areaId]);
            $ownerId = (int)$stmt->fetchColumn();
            
            if ($ownerId !== $userId) {
                throw new Exception('Access denied');
            }
            
            // Мягкое удаление (is_active = 0)
            $stmt = $modx->prepare("
                UPDATE modx_test_knowledge_areas
                SET is_active = 0
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$areaId, $userId]);

            $response = ResponseHelper::success(null, 'Knowledge area deleted');
            break;

        case 'getKnowledgeAreaDetails':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);

            // Валидация входных данных
            $areaId = ValidationHelper::requireInt($data, 'area_id', 'Area ID required');
            
            // КРИТИЧНО: Проверяем владельца

            $stmt = $modx->prepare("
                SELECT ka.*, 
                       (SELECT COUNT(*) FROM modx_test_questions q 
                        WHERE FIND_IN_SET(q.test_id, REPLACE(REPLACE(ka.test_ids, '[', ''), ']', '')) 
                        AND q.published = 1) as total_questions
                FROM modx_test_knowledge_areas ka
                WHERE ka.id = ? AND ka.user_id = ? AND ka.is_active = 1
            ");

            $stmt->execute([$areaId, $userId]);
            $area = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$area) {
                $modx->log(modX::LOG_LEVEL_ERROR, "[KA Debug 1501] Area not found for areaId={$areaId}, userId={$userId}");
                throw new Exception('Knowledge area not found or access denied');
            }
            
            // Получаем информацию о тестах
            $testIds = json_decode($area['test_ids'], true);
            if (is_array($testIds) && !empty($testIds)) {
                $placeholders = implode(',', array_fill(0, count($testIds), '?'));
                $stmt = $modx->prepare("
                    SELECT t.id, t.title, t.resource_id,
                           (SELECT COUNT(*) FROM modx_test_questions WHERE test_id = t.id AND published = 1) as questions_count
                    FROM modx_test_tests t
                    WHERE t.id IN ($placeholders) AND t.is_active = 1
                    ORDER BY t.title
                ");
                $stmt->execute($testIds);
                $area['tests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $area['tests'] = [];
            }

            $response = ResponseHelper::success($area);
            break;


        case 'getAvailableTestsTree':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);
            
            // Получаем тесты с учетом publication_status
            $stmt = $modx->prepare("
                SELECT DISTINCT
                    t.id as test_id,
                    t.title as test_title,
                    t.resource_id,
                    t.publication_status,
                    t.created_by,
                    r.parent as category_id,
                    rc.pagetitle as category_title,
                    (SELECT COUNT(*) FROM modx_test_questions WHERE test_id = t.id AND published = 1) as questions_count,
                    CASE 
                        WHEN t.created_by = ? THEN 'owner'
                        WHEN t.publication_status IN ('public', 'unlisted') THEN 'public'
                        WHEN p.user_id IS NOT NULL THEN 'shared'
                        ELSE NULL
                    END as access_type
                FROM modx_test_tests t
                LEFT JOIN modx_site_content r ON r.id = t.resource_id
                LEFT JOIN modx_site_content rc ON rc.id = r.parent
                LEFT JOIN modx_test_permissions p ON p.test_id = t.id AND p.user_id = ?
                WHERE t.is_active = 1
                AND (
                    t.publication_status IN ('public', 'unlisted')
                    OR t.created_by = ?
                    OR p.user_id = ?
                )
                HAVING access_type IS NOT NULL
                ORDER BY rc.pagetitle, t.title
            ");
            
            $stmt->execute([$userId, $userId, $userId, $userId]);
            $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Группируем по категориям
            $tree = [];
            
            foreach ($tests as $test) {
                $categoryId = (int)($test['category_id'] ?? 0);
                
                // Для приватных тестов создаем отдельную категорию
                if ($test['access_type'] === 'owner' && !in_array($test['publication_status'], ['public', 'unlisted'])) {
                    $categoryId = -1; // "Мои тесты"
                    $categoryTitle = '📁 Мои приватные тесты';
                } elseif ($test['access_type'] === 'shared') {
                    $categoryId = -2; // "Доступные мне"
                    $categoryTitle = '🔗 Доступные мне';
                } else {
                    $categoryTitle = $test['category_title'] ?? 'Без категории';
                }
                
                if (!isset($tree[$categoryId])) {
                    $tree[$categoryId] = [
                        'category_id' => $categoryId,
                        'category_title' => $categoryTitle,
                        'tests' => []
                    ];
                }
                
                $testTitle = $test['test_title'];
                
                // Иконки для статуса
                if ($test['publication_status'] === 'draft') {
                    $testTitle = '📝 ' . $testTitle;
                } elseif ($test['publication_status'] === 'private') {
                    $testTitle = '🔒 ' . $testTitle;
                } elseif ($test['publication_status'] === 'unlisted') {
                    $testTitle = '🔗 ' . $testTitle;
                } elseif ($test['access_type'] === 'shared') {
                    $testTitle = '🤝 ' . $testTitle;
                }
                
                $tree[$categoryId]['tests'][] = [
                    'test_id' => (int)$test['test_id'],
                    'test_title' => $testTitle,
                    'questions_count' => (int)$test['questions_count'],
                    'access_type' => $test['access_type'],
                    'publication_status' => $test['publication_status']
                ];
            }
            
            // Сортируем категории
            uksort($tree, function($a, $b) {
                if ($a < 0 && $b >= 0) return -1;
                if ($a >= 0 && $b < 0) return 1;
                if ($a === -1) return -1;
                if ($b === -1) return 1;
                return 0;
            });
            
            $response = ResponseHelper::success(array_values($tree));
            break;


        case 'startKnowledgeAreaSession':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);
            $areaId = ValidationHelper::requireInt($data, 'area_id', 'Area ID required');

            // Загружаем область знаний
            $stmt = $modx->prepare("
                SELECT test_ids, questions_per_session, name, question_distribution_mode, 
                       min_questions_per_test, user_id, is_active
                FROM {$prefix}test_knowledge_areas
                WHERE id = ?
            ");
            $stmt->execute([$areaId]);
            $area = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$area) {
                throw new Exception('Knowledge area not found');
            }
            
            if ((int)$area['is_active'] !== 1) {
                throw new Exception('Knowledge area is inactive');
            }
            
            if ((int)$area['user_id'] !== $userId) {
                $modx->log(modX::LOG_LEVEL_WARN, "[KA] Access denied: area {$areaId} belongs to user {$area['user_id']}, not {$userId}");
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
            $stmt = $modx->prepare("
                SELECT test_id, COUNT(*) as count 
                FROM {$prefix}test_questions 
                WHERE test_id IN ($placeholders) AND published = 1
                GROUP BY test_id
            ");
            $stmt->execute($testIds);
            $testsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $testQuestionCounts = [];
            foreach ($testsData as $row) {
                $testQuestionCounts[(int)$row['test_id']] = (int)$row['count'];
            }
            
            // Рассчитываем распределение вопросов
            $distribution = [];
            
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
$allQuestionIds = [];

foreach ($distribution as $testId => $count) {
    if ($count <= 0) continue;
    
    $testId = (int)$testId;
    $count = (int)$count;
    
    // ИСПРАВЛЕНО: LIMIT без placeholder
    $stmt = $modx->prepare("
        SELECT id 
        FROM {$prefix}test_questions 
        WHERE test_id = ? AND published = 1
        ORDER BY RAND()
        LIMIT {$count}
    ");
    
    $stmt->execute([$testId]);
    $questions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $modx->log(modX::LOG_LEVEL_ERROR, "[KA] Test {$testId}: requested {$count}, found " . count($questions));
    
    $allQuestionIds = array_merge($allQuestionIds, $questions);
}

// ДИАГНОСТИКА
$modx->log(modX::LOG_LEVEL_ERROR, "[KA] Distribution: " . json_encode($distribution));
$modx->log(modX::LOG_LEVEL_ERROR, "[KA] Total questions collected: " . count($allQuestionIds));

shuffle($allQuestionIds);

if (empty($allQuestionIds)) {
    throw new Exception('No questions found in selected tests');
}

            // Создаем сессию с test_id = -1 (маркер области знаний)
            $stmt = $modx->prepare("
                INSERT INTO {$prefix}test_sessions 
                (test_id, user_id, mode, question_order, status, started_at)
                VALUES (-1, ?, 'training', ?, 'active', NOW())
            ");
            
            if (!$stmt->execute([$userId, json_encode($allQuestionIds)])) {
                throw new Exception('Failed to create session');
            }
            
            $sessionId = (int)$modx->lastInsertId();

            $response = ResponseHelper::success([
                'session_id' => $sessionId,
                'area_name' => $area['name'],
                'total_questions' => count($allQuestionIds),
                'is_knowledge_area' => true,
                'distribution' => $distribution
            ]);
            break;

        case 'publishTest':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $currentUserId = PermissionHelper::getCurrentUserId($modx);

            // Валидация входных данных
            $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');
            $publicationStatus = ValidationHelper::optionalString($data, 'status', 'private');

            // Используем TestService для публикации
            $result = TestService::publishTest($modx, $testId, $publicationStatus, $currentUserId);

            $response = ResponseHelper::success($result, 'Publication status updated');
            break;


        case 'getPublicTestBySlug':
            // Валидация входных данных
            $slug = ValidationHelper::requireString($data, 'slug', 'Slug required');
            
            // Загружаем тест
            $stmt = $modx->prepare("
                SELECT id, title, description, mode, time_limit, pass_score, 
                       questions_per_session, publication_status, created_by
                FROM modx_test_tests 
                WHERE public_url_slug = ? 
                AND publication_status IN ('unlisted', 'public')
                AND is_active = 1
            ");
            $stmt->execute([$slug]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$test) {
                throw new Exception('Test not found or not available');
            }
            
            // Подсчет вопросов
            $stmt = $modx->prepare("
                SELECT COUNT(*) 
                FROM modx_test_questions 
                WHERE test_id = ? AND published = 1
            ");
            $stmt->execute([$test['id']]);
            $test['total_questions'] = (int)$stmt->fetchColumn();
            
            // Статистика прохождений (опционально)
            $stmt = $modx->prepare("
                SELECT 
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(*) as total_attempts,
                    AVG(score) as avg_score
                FROM modx_test_sessions 
                WHERE test_id = ? AND status = 'completed'
            ");
            $stmt->execute([$test['id']]);
            $test['statistics'] = $stmt->fetch(PDO::FETCH_ASSOC);

            $response = ResponseHelper::success($test);
            break;            

        // ДИАГНОСТИКА: Проверяем права пользователя
        case 'checkResourcePermissions':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Not logged in');

            $userId = PermissionHelper::getCurrentUserId($modx);
            $user = $modx->getObject('modUser', $userId);

            if (!$user) {
                throw new Exception('User not found');
            }

            // Проверяем может ли пользователь создавать документы
            $canCreate = $user->hasPermission('new_document');
            $canSave = $user->hasPermission('save_document');

            $response = ResponseHelper::success([
                'user_id' => $userId,
                'can_create' => $canCreate,
                'can_save' => $canSave,
                'groups' => array_keys($user->getUserGroups())
            ]);
            break;

        case 'createTestWithPage':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);

            // Валидация входных данных
            $title = ValidationHelper::requireString($data, 'title', 'Title is required');
            $description = ValidationHelper::optionalString($data, 'description');
            $publicationStatus = ValidationHelper::optionalString($data, 'publication_status', 'draft');

            // Используем TestService для создания теста со страницей
            $result = TestService::createTestWithPage($modx, $title, $description, $publicationStatus, $userId);

            // ДИАГНОСТИКА: Логируем результат создания теста
            $modx->log(modX::LOG_LEVEL_ERROR, '[createTestWithPage] Result: ' . print_r($result, true));
            $modx->log(modX::LOG_LEVEL_ERROR, '[createTestWithPage] test_id: ' . ($result['test_id'] ?? 'MISSING'));
            $modx->log(modX::LOG_LEVEL_ERROR, '[createTestWithPage] csv_import_url: ' . ($result['csv_import_url'] ?? 'MISSING'));

            $response = ResponseHelper::success($result, 'Test and page created successfully');

            // ДИАГНОСТИКА: Логируем финальный response
            $modx->log(modX::LOG_LEVEL_ERROR, '[createTestWithPage] Final response: ' . print_r($response, true));
            break;

        case 'createTest':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);

            // Валидация входных данных
            $title = ValidationHelper::requireString($data, 'title', 'Title required');
            $description = ValidationHelper::optionalString($data, 'description');
            $publicationStatus = ValidationHelper::optionalString($data, 'publication_status', 'private');

            $rights = PermissionHelper::getUserRights($modx);

            // Обычные пользователи могут создавать только private и draft
            if (!$rights['canEdit'] && !in_array($publicationStatus, ['private', 'draft'])) {
                $publicationStatus = 'private';
            }
            
            // Ограничение для обычных пользователей
            if (!$rights['canEdit']) {
                $stmt = $modx->prepare("
                    SELECT COUNT(*) 
                    FROM modx_test_tests 
                    WHERE created_by = ? AND publication_status = 'private'
                ");
                $stmt->execute([$userId]);
                $userTestsCount = (int)$stmt->fetchColumn();
                
                if ($userTestsCount >= 10) {
                    throw new Exception('Maximum 10 private tests per user');
                }
            }
            
            // ИСПРАВЛЕНО: Добавляем created_by при создании
            $stmt = $modx->prepare("
                INSERT INTO modx_test_tests 
                (title, description, created_by, publication_status, 
                 mode, pass_score, questions_per_session, 
                 randomize_questions, randomize_answers, is_active, created_at)
                VALUES (?, ?, ?, ?, 'training', 70, 20, 1, 1, 1, NOW())
            ");
            
            $stmt->execute([
                $title, 
                $description, 
                $userId,  // КРИТИЧНО: устанавливаем владельца
                $publicationStatus
            ]);
            
            $testId = (int)$modx->lastInsertId();

            $response = ResponseHelper::success(['test_id' => $testId], 'Test created');
            break;


        case 'createTestPage':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);

            // Валидация входных данных
            $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');
            
            // Проверяем владельца теста + ЗАГРУЖАЕМ resource_id
            $stmt = $modx->prepare("
                SELECT title, created_by, publication_status, resource_id
                FROM {$prefix}test_tests 
                WHERE id = ?
            ");
            
            if (!$stmt) {
                throw new Exception('Database error');
            }
            
            $stmt->execute([$testId]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$test) {
                throw new Exception('Test not found');
            }
            
            if ((int)$test['created_by'] !== $userId) {
                throw new Exception('Access denied: not test owner');
            }
            
            // Проверяем, не создана ли уже страница
            $existingResourceId = (int)($test['resource_id'] ?? 0);
            if ($existingResourceId > 0) {
                // ИСПРАВЛЕНО: используем ручное построение URL
                $resource = $modx->getObject('modResource', $existingResourceId);
                
                if ($resource) {
                    $siteUrl = rtrim($modx->getOption('site_url'), '/');
                    $alias = $resource->get('alias');
                    $parentId = (int)$resource->get('parent');
                    
                    if ($parentId > 0) {
                        $parent = $modx->getObject('modResource', $parentId);
                        if ($parent) {
                            $parentUri = trim($parent->get('uri'), '/');
                            $existingUrl = $siteUrl . '/' . $parentUri . '/' . $alias;
                        } else {
                            $existingUrl = $siteUrl . '/' . $alias;
                        }
                    } else {
                        $existingUrl = $siteUrl . '/' . $alias;
                    }
                } else {
                    $existingUrl = $modx->getOption('site_url') . 'resource-' . $existingResourceId;
                }
                
                $response = ResponseHelper::success([
                    'test_url' => $existingUrl,
                    'resource_id' => $existingResourceId
                ], 'Page already exists');
                break;
            }
            
            try {
                // Определяем родительскую папку для тестов
                $testsParentId = (int)$modx->getOption('lms.user_tests_folder', null, 129);
                
                // Генерируем уникальный alias
                $baseAlias = $modx->filterPathSegment($test['title']);
                $baseAlias = preg_replace('/[^a-z0-9-]/', '', strtolower(transliterate($baseAlias)));
                
                if (empty($baseAlias)) {
                    $baseAlias = 'test-' . $testId;
                }
                
                $alias = $baseAlias;
                $counter = 1;
                
                // Проверяем уникальность alias
                while (true) {
                    $checkStmt = $modx->prepare("
                        SELECT COUNT(*) 
                        FROM {$prefix}site_content 
                        WHERE alias = ? AND parent = ?
                    ");
                    $checkStmt->execute([$alias, $testsParentId]);
                    
                    if ((int)$checkStmt->fetchColumn() === 0) {
                        break;
                    }
                    
                    $alias = $baseAlias . '-' . $counter;
                    $counter++;
                    
                    if ($counter > 100) {
                        throw new Exception('Failed to generate unique alias');
                    }
                }
                
                // Получаем ID шаблона
                $templateId = (int)$modx->getOption('lms.test_template', null, 0);
                if ($templateId === 0) {
                    $templateId = (int)$modx->getOption('default_template', null, 1);
                }
                
                // Создаём ресурс
                $resource = $modx->newObject('modResource');
                
                if (!$resource) {
                    throw new Exception('Failed to create resource object');
                }
                
                $resource->set('pagetitle', $test['title']);
                $resource->set('longtitle', $test['title']);
                $resource->set('alias', $alias);
                $resource->set('content', '[[!testRunner]]');
                $resource->set('published', 1);
                $resource->set('parent', $testsParentId);
                $resource->set('template', $templateId);
                $resource->set('richtext', 0);
                $resource->set('searchable', 1);
                $resource->set('cacheable', 1);
                $resource->set('createdby', $userId);
                $resource->set('createdon', time());
                
                if (!$resource->save()) {
                    throw new Exception('Failed to save resource');
                }
                
                $resourceId = (int)$resource->get('id');
                
                if ($resourceId <= 0) {
                    throw new Exception('Invalid resource ID after save');
                }
                
                // Привязываем тест к странице
                $updateStmt = $modx->prepare("
                    UPDATE {$prefix}test_tests 
                    SET resource_id = ? 
                    WHERE id = ?
                ");
                
                if (!$updateStmt) {
                    throw new Exception('Failed to prepare update statement');
                }
                
                if (!$updateStmt->execute([$resourceId, $testId])) {
                    throw new Exception('Failed to link test to page');
                }
                
                // Очищаем кеш
                $modx->cacheManager->refresh([
                    'db' => [],
                    'auto_publish' => ['contexts' => ['web']],
                    'context_settings' => ['contexts' => ['web']],
                    'resource' => ['contexts' => ['web']],
                ]);
                
                // ИСПРАВЛЕНО: ручное построение URL вместо makeUrl()
                $siteUrl = rtrim($modx->getOption('site_url'), '/');
                
                if ($testsParentId > 0) {
                    $parent = $modx->getObject('modResource', $testsParentId);
                    if ($parent) {
                        $parentUri = trim($parent->get('uri'), '/');
                        $testUrl = $siteUrl . '/' . $parentUri . '/' . $alias;
                    } else {
                        $testUrl = $siteUrl . '/' . $alias;
                    }
                } else {
                    $testUrl = $siteUrl . '/' . $alias;
                }
                
                $response = ResponseHelper::success([
                    'resource_id' => $resourceId,
                    'test_url' => $testUrl
                ], 'Test page created successfully');
                
            } catch (Exception $e) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[createTestPage] Error: ' . $e->getMessage());
                throw new Exception('Failed to create test page: ' . $e->getMessage());
            }
            break;

        case 'getMyTests':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);
            
            $stmt = $modx->prepare("
                SELECT 
                    t.id, t.title, t.description, t.publication_status, t.created_at, t.resource_id,
                    (SELECT COUNT(*) FROM modx_test_questions WHERE test_id = t.id) as questions_count,
                    (SELECT COUNT(*) FROM modx_test_permissions WHERE test_id = t.id) as shared_with_count
                FROM modx_test_tests t
                WHERE t.created_by = ? AND t.is_active = 1
                ORDER BY t.created_at DESC
            ");
            
            $stmt->execute([$userId]);
            $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Добавляем URL для каждого теста
            UrlHelper::addUrlsToTests($modx, $tests);

            $response = ResponseHelper::success($tests);
            break;


        case 'getSharedWithMe':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);
            
            $stmt = $modx->prepare("
                SELECT 
                    t.id, t.title, t.description, t.publication_status, t.resource_id,
                    p.can_edit, p.granted_at,
                    u.username as owner_name,
                    (SELECT COUNT(*) FROM modx_test_questions WHERE test_id = t.id) as questions_count
                FROM modx_test_permissions p
                INNER JOIN modx_test_tests t ON t.id = p.test_id
                LEFT JOIN modx_users u ON u.id = t.created_by
                WHERE p.user_id = ? AND t.is_active = 1
                ORDER BY p.granted_at DESC
            ");
            
            $stmt->execute([$userId]);
            $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Добавляем URL для каждого теста
            UrlHelper::addUrlsToTests($modx, $tests);

            $response = ResponseHelper::success($tests);
            break;
    
        case 'searchUsers':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $currentUserId = PermissionHelper::getCurrentUserId($modx);
            $rights = PermissionHelper::getUserRights($modx);

            // Валидация входных данных
            $query = ValidationHelper::requireString($data, 'query', 'Search query required');
            $testId = ValidationHelper::optionalInt($data, 'test_id', 0);

            if (strlen($query) < 2) {
                throw new Exception('Search query too short (min 2 chars)');
            }
            
            if ($testId > 0) {
                $testOwnerId = TestRepository::getTestOwner($modx, $testId);

                if ($testOwnerId === false) {
                    throw new Exception('Test not found');
                }

                $canSearch = $rights['canEdit'] || ($testOwnerId === $currentUserId);

                if (!$canSearch) {
                    throw new Exception('Permission denied');
                }
            } elseif (!$rights['canEdit']) {
                throw new Exception('Permission denied');
            }
            
            $searchPattern = '%' . $query . '%';
            
            $stmt = $modx->prepare("
                SELECT u.id, u.username, up.email, up.fullname
                FROM modx_users u
                LEFT JOIN modx_user_attributes up ON up.internalKey = u.id
                WHERE (u.username LIKE ? OR up.email LIKE ? OR up.fullname LIKE ?)
                AND u.id != ?
                ORDER BY u.username
                LIMIT 20
            ");
            
            $stmt->execute([$searchPattern, $searchPattern, $searchPattern, $currentUserId]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($testId > 0 && !empty($users)) {
                $userIds = array_column($users, 'id');
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $stmt = $modx->prepare("
                    SELECT user_id, can_edit 
                    FROM modx_test_permissions 
                    WHERE test_id = ? AND user_id IN ($placeholders)
                ");
                $params = array_merge([$testId], $userIds);
                $stmt->execute($params);
                $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $permMap = [];
                foreach ($permissions as $perm) {
                    $permMap[(int)$perm['user_id']] = (bool)$perm['can_edit'];
                }
                
                foreach ($users as &$user) {
                    $user['has_access'] = isset($permMap[(int)$user['id']]);
                    $user['can_edit'] = $permMap[(int)$user['id']] ?? false;
                }
            }
            
            $response = ResponseHelper::success($users);
            break;

        case 'grantAccess':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $currentUserId = PermissionHelper::getCurrentUserId($modx);
            $rights = PermissionHelper::getUserRights($modx);

            // Валидация входных данных
            $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');
            $targetUserId = ValidationHelper::requireInt($data, 'user_id', 'User ID required');
            $canEdit = ValidationHelper::optionalInt($data, 'can_edit', 0);
            
            $stmt = $modx->prepare("SELECT created_by, title FROM modx_test_tests WHERE id = ?");
            $stmt->execute([$testId]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$test) {
                throw new Exception('Test not found');
            }
            
            $canGrant = $rights['canEdit'] || ((int)$test['created_by'] === $currentUserId);
            
            if (!$canGrant) {
                throw new Exception('Permission denied');
            }
            
            $stmt = $modx->prepare("
                INSERT INTO modx_test_permissions (test_id, user_id, granted_by, can_edit)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE can_edit = VALUES(can_edit), granted_by = VALUES(granted_by), granted_at = NOW()
            ");
            
            $stmt->execute([$testId, $targetUserId, $currentUserId, $canEdit]);
            
            $stmt = $modx->prepare("SELECT username FROM modx_users WHERE id = ?");
            $stmt->execute([$currentUserId]);
            $initiatorName = $stmt->fetchColumn();
            
            $editText = $canEdit ? ' с правами редактирования' : '';
            $message = "Пользователь {$initiatorName} предоставил вам доступ{$editText} к тесту \"{$test['title']}\"";
            
            $stmt = $modx->prepare("
                INSERT INTO modx_test_notifications (user_id, type, test_id, initiator_id, message)
                VALUES (?, 'access_granted', ?, ?, ?)
            ");
            $stmt->execute([$targetUserId, $testId, $currentUserId, $message]);

            $response = ResponseHelper::success(null, 'Access granted');
            break;

        case 'revokeAccess':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $currentUserId = PermissionHelper::getCurrentUserId($modx);
            $rights = PermissionHelper::getUserRights($modx);

            // Валидация входных данных
            $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');
            $targetUserId = ValidationHelper::requireInt($data, 'user_id', 'User ID required');

            $testOwnerId = TestRepository::getTestOwner($modx, $testId);

            if ($testOwnerId === false) {
                throw new Exception('Test not found');
            }

            $canRevoke = $rights['canEdit'] || ($testOwnerId === $currentUserId);

            if (!$canRevoke) {
                throw new Exception('Permission denied');
            }
            
            $stmt = $modx->prepare("DELETE FROM modx_test_permissions WHERE test_id = ? AND user_id = ?");
            $stmt->execute([$testId, $targetUserId]);

            $response = ResponseHelper::success(null, 'Access revoked');
            break;

        case 'getTestPermissions':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $currentUserId = PermissionHelper::getCurrentUserId($modx);
            $rights = PermissionHelper::getUserRights($modx);

            // Валидация входных данных
            $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');

            $testOwnerId = TestRepository::getTestOwner($modx, $testId);

            if ($testOwnerId === false) {
                throw new Exception('Test not found');
            }

            $canView = $rights['canEdit'] || ($testOwnerId === $currentUserId);

            if (!$canView) {
                throw new Exception('Permission denied');
            }
            
            $stmt = $modx->prepare("
                SELECT 
                    p.id, p.user_id, p.can_edit, p.granted_at,
                    u.username, ua.email, ua.fullname,
                    granter.username as granted_by_name
                FROM modx_test_permissions p
                INNER JOIN modx_users u ON u.id = p.user_id
                LEFT JOIN modx_user_attributes ua ON ua.internalKey = u.id
                LEFT JOIN modx_users granter ON granter.id = p.granted_by
                WHERE p.test_id = ?
                ORDER BY p.granted_at DESC
            ");
            
            $stmt->execute([$testId]);
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = ResponseHelper::success($permissions);
            break;

        case 'getNotifications':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);

            // Валидация входных данных
            $unreadOnly = (bool)($data['unread_only'] ?? false);
            $limit = min((int)($data['limit'] ?? 20), 50);
            
            $sql = "
                SELECT n.id, n.type, n.test_id, n.message, n.is_read, n.created_at,
                       t.title as test_title, u.username as initiator_name
                FROM modx_test_notifications n
                LEFT JOIN modx_test_tests t ON t.id = n.test_id
                LEFT JOIN modx_users u ON u.id = n.initiator_id
                WHERE n.user_id = ?
            ";
            
            if ($unreadOnly) {
                $sql .= " AND n.is_read = 0";
            }
            
            $sql .= " ORDER BY n.created_at DESC LIMIT ?";
            
            $stmt = $modx->prepare($sql);
            $stmt->execute([$userId, $limit]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $modx->prepare("SELECT COUNT(*) FROM modx_test_notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            $unreadCount = (int)$stmt->fetchColumn();

            $response = ResponseHelper::success([
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]);
            break;
        
        case 'markNotificationRead':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);

            // Валидация входных данных
            $notificationId = ValidationHelper::requireInt($data, 'notification_id', 'Notification ID required');
            
            $stmt = $modx->prepare("UPDATE modx_test_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $userId]);

            $response = ResponseHelper::success(null, 'Notification marked as read');
            break;

        case 'markAllNotificationsRead':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);

            $stmt = $modx->prepare("UPDATE modx_test_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);

            $response = ResponseHelper::success(null, 'All notifications marked as read');
            break;

        case 'checkSiteSettings':
            $response = ResponseHelper::success([
                'site_url' => $modx->getOption('site_url'),
                'base_url' => $modx->getOption('base_url'),
                'friendly_urls' => $modx->getOption('friendly_urls'),
                'use_alias_path' => $modx->getOption('use_alias_path'),
                'site_start' => $modx->getOption('site_start')
            ]);
            break;

        case 'getParentUri':
            // Валидация входных данных
            $resourceId = ValidationHelper::requireInt($data, 'resource_id', 'Resource ID required');
            
            $resource = $modx->getObject('modResource', $resourceId);
            
            if (!$resource) {
                throw new Exception('Resource not found');
            }

            $response = ResponseHelper::success([
                'id' => $resource->get('id'),
                'pagetitle' => $resource->get('pagetitle'),
                'alias' => $resource->get('alias'),
                'uri' => $resource->get('uri'),
                'parent' => $resource->get('parent')
            ]);
            break;
            
            

        /**
         * Экшен для удаления теста
         * Добавить в testsystem.php в секцию switch($action)
         * 
         * case 'deleteTest':
         *     include 'actions/deleteTest.php';
         *     break;
         */

        case 'deleteTest':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Требуется авторизация');

            $userId = PermissionHelper::getCurrentUserId($modx);

            // Валидация входных данных
            $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Не указан ID теста');

            // Проверяем права владельца и получаем данные теста
            $test = TestRepository::requireTestOwner($modx, $testId, $userId, 'У вас нет прав на удаление этого теста');

            // Удаляем тест и все связанные данные
            $success = TestRepository::deleteTest($modx, $testId);

            if (!$success) {
                throw new Exception('Произошла ошибка при удалении теста');
            }

            // Удаляем страницу MODX если она существует
            if (!empty($test['resource_id'])) {
                $resourceId = (int)$test['resource_id'];
                $modx->exec("DELETE FROM {$prefix}site_content WHERE id = {$resourceId}");
            }

            $response = ResponseHelper::success(null, 'Тест успешно удален');
            break;

        case 'updateTest':
            // Проверка авторизации
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);

            // Валидация входных данных
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
            $test = TestRepository::requireTestOwner($modx, $testId, $userId, 'Access denied: not test owner');
            
            // Обновляем тест
            $stmt = $modx->prepare("
                UPDATE {$prefix}test_tests 
                SET title = ?, description = ?, publication_status = ?
                WHERE id = ?
            ");
            
            if (!$stmt || !$stmt->execute([$title, $description, $publicationStatus, $testId])) {
                throw new Exception('Failed to update test');
            }

            // Обновляем pagetitle страницы если она есть
            if (!empty($test['resource_id'])) {
                $resourceId = (int)$test['resource_id'];
                $stmt = $modx->prepare("UPDATE {$prefix}site_content SET pagetitle = ? WHERE id = ?");
                $stmt->execute([$title, $resourceId]);
            }

            $response = ResponseHelper::success(null, 'Test updated successfully');
            break;

        // ============================================
        // УПРАВЛЕНИЕ ДОСТУПОМ К ТЕСТАМ
        // ============================================

        case 'getTestAccess':
            // Получить список пользователей с доступом к тесту
            try {
                $modx->log(modX::LOG_LEVEL_ERROR, '[getTestAccess] Start');

                PermissionHelper::requireAuthentication($modx, 'Login required');
                $modx->log(modX::LOG_LEVEL_ERROR, '[getTestAccess] Auth OK');

                $userId = PermissionHelper::getCurrentUserId($modx);
                $modx->log(modX::LOG_LEVEL_ERROR, '[getTestAccess] User ID: ' . $userId);

                $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');
                $modx->log(modX::LOG_LEVEL_ERROR, '[getTestAccess] Test ID: ' . $testId);

                // Проверяем права на управление доступом
                $stmt = $modx->prepare("SELECT id, title, publication_status FROM {$prefix}test_tests WHERE id = ?");
                $stmt->execute([$testId]);
                $test = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$test) {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[getTestAccess] Test not found: ' . $testId);
                    throw new Exception('Test not found');
                }
                $modx->log(modX::LOG_LEVEL_ERROR, '[getTestAccess] Test found: ' . $test['title']);

                $publicationStatus = $test['publication_status'];

                if (!TestPermissionHelper::canManageAccess($modx, $testId, $userId)) {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[getTestAccess] Access denied for user ' . $userId);
                    throw new Exception('Access denied: you cannot manage access to this test');
                }
                $modx->log(modX::LOG_LEVEL_ERROR, '[getTestAccess] Access granted');

                // Получаем список пользователей с доступом
                $users = TestPermissionHelper::getTestUsers($modx, $testId);
                $modx->log(modX::LOG_LEVEL_ERROR, '[getTestAccess] Users count: ' . count($users));

                // Получаем список доступных пользователей (все кроме уже имеющих доступ)
                $existingUserIds = array_column($users, 'user_id');
                $existingUserIds[] = $userId; // Исключаем текущего пользователя

                $placeholders = implode(',', array_fill(0, count($existingUserIds), '?'));
                $stmt = $modx->prepare("
                    SELECT u.id, u.username,
                           COALESCE(p.fullname, u.username) as fullname
                    FROM {$prefix}users u
                    LEFT JOIN {$prefix}user_attributes p ON p.internalKey = u.id
                    WHERE u.id NOT IN ($placeholders)
                    ORDER BY u.username
                    LIMIT 100
                ");
                $stmt->execute($existingUserIds);
                $availableUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $modx->log(modX::LOG_LEVEL_ERROR, '[getTestAccess] Available users count: ' . count($availableUsers));

                $response = ResponseHelper::success([
                    'users' => $users,
                    'available_users' => $availableUsers,
                    'test_title' => $test['title']
                ]);
                $modx->log(modX::LOG_LEVEL_ERROR, '[getTestAccess] Success');

            } catch (Exception $e) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[getTestAccess] Exception: ' . $e->getMessage());
                $modx->log(modX::LOG_LEVEL_ERROR, '[getTestAccess] Trace: ' . $e->getTraceAsString());
                throw $e;
            }
            break;

        case 'grantTestAccess':
            // Предоставить доступ пользователю
            try {
                $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Start');

                PermissionHelper::requireAuthentication($modx, 'Login required');
                $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Auth OK');

                $currentUserId = PermissionHelper::getCurrentUserId($modx);
                $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Current user ID: ' . $currentUserId);

                $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');
                $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Test ID: ' . $testId);

                $targetUserId = ValidationHelper::requireInt($data, 'user_id', 'User ID required');
                $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Target user ID: ' . $targetUserId);

                $role = ValidationHelper::requireString($data, 'role', 'Role required');
                $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Role: ' . $role);

                // Валидация роли
                $allowedRoles = [
                    TestPermissionHelper::ROLE_AUTHOR,
                    TestPermissionHelper::ROLE_EDITOR,
                    TestPermissionHelper::ROLE_VIEWER
                ];

                if (!in_array($role, $allowedRoles, true)) {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Invalid role: ' . $role);
                    throw new Exception('Invalid role: ' . $role);
                }

                // Проверяем что тест существует
                $stmt = $modx->prepare("SELECT id FROM {$prefix}test_tests WHERE id = ?");
                $stmt->execute([$testId]);
                if (!$stmt->fetch()) {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Test not found: ' . $testId);
                    throw new Exception('Test not found');
                }
                $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Test exists');

                // Проверяем права на управление доступом
                if (!TestPermissionHelper::canManageAccess($modx, $testId, $currentUserId)) {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Access denied for user: ' . $currentUserId);
                    throw new Exception('Access denied: you cannot manage access to this test');
                }
                $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Can manage access');

                // Проверяем что целевой пользователь существует
                $stmt = $modx->prepare("SELECT id FROM {$prefix}users WHERE id = ?");
                $stmt->execute([$targetUserId]);
                if (!$stmt->fetch()) {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Target user not found: ' . $targetUserId);
                    throw new Exception('Target user not found');
                }
                $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Target user exists');

                // Предоставляем доступ
                $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Calling TestPermissionHelper::grantAccess');
                $success = TestPermissionHelper::grantAccess($modx, $testId, $targetUserId, $role, $currentUserId);
                $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] grantAccess returned: ' . ($success ? 'true' : 'false'));

                if (!$success) {
                    throw new Exception('Failed to grant access');
                }

                $response = ResponseHelper::success(null, 'Access granted successfully');
                $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Success');

            } catch (Exception $e) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Exception: ' . $e->getMessage());
                $modx->log(modX::LOG_LEVEL_ERROR, '[grantTestAccess] Trace: ' . $e->getTraceAsString());
                throw $e;
            }
            break;

        case 'revokeTestAccess':
            // Удалить доступ пользователя
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $currentUserId = PermissionHelper::getCurrentUserId($modx);
            $testId = ValidationHelper::requireTestId($data['test_id'] ?? 0, 'Test ID required');
            $targetUserId = ValidationHelper::requireInt($data, 'user_id', 'User ID required');

            // Проверяем что тест существует
            $stmt = $modx->prepare("SELECT id FROM {$prefix}test_tests WHERE id = ?");
            $stmt->execute([$testId]);
            if (!$stmt->fetch()) {
                throw new Exception('Test not found');
            }

            // Проверяем права на управление доступом
            if (!TestPermissionHelper::canManageAccess($modx, $testId, $currentUserId)) {
                throw new Exception('Access denied: you cannot manage access to this test');
            }

            // Удаляем доступ
            $success = TestPermissionHelper::revokeAccess($modx, $testId, $targetUserId);

            if (!$success) {
                throw new Exception('Failed to revoke access');
            }

            $response = ResponseHelper::success(null, 'Access revoked successfully');
            break;


    default:
                throw new Exception('Unknown action: ' . $action);
        }
        
    } catch (TestSystemException $e) {
        // Специализированные исключения с правильными HTTP кодами
        http_response_code($e->getHttpCode());
        $response = $e->toArray();
    } catch (Exception $e) {
        // Обработка неожиданных исключений
        http_response_code(500);
        $response = ResponseHelper::error('Internal server error');
        $modx->log(modX::LOG_LEVEL_ERROR, '[testsystem.php] Unexpected error: ' . $e->getMessage());
    }

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// ============================================
// ВСПОМОГАТЕЛЬНАЯ ФУНКЦИЯ ТРАНСЛИТЕРАЦИИ
// ============================================
function transliterate($str) {
    $ru = ['а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я',
           'А','Б','В','Г','Д','Е','Ё','Ж','З','И','Й','К','Л','М','Н','О','П','Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ы','Ь','Э','Ю','Я'];
    $en = ['a','b','v','g','d','e','e','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','c','ch','sh','sch','','y','','e','yu','ya',
           'A','B','V','G','D','E','E','Zh','Z','I','Y','K','L','M','N','O','P','R','S','T','U','F','H','C','Ch','Sh','Sch','','Y','','E','Yu','Ya'];
    
    $str = str_replace($ru, $en, $str);
    $str = preg_replace('/[^a-zA-Z0-9-]/', '', $str);
    $str = mb_strtolower($str);
    
    return $str;
}