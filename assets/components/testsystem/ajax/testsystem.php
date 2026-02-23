<?php
/* TS API v3.4.2 - FIXED deleteMaterial with direct SQL - BUILD 20251203-2145 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Логи пишутся в core/cache/logs/ (за пределами webroot)
$logDir = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/core/cache/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/testsystem_errors.log');

 
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

// Подключаем сервисы
require_once MODX_CORE_PATH . 'components/testsystem/services/AccessService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/AnalyticsService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/AuthService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/CategoryPermissionService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/CertificateService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/DataIntegrityService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/GamificationService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/LearningMaterialService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/LearningPathService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/NotificationService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/QuestionTypeService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/ReportService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/SessionService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/TestService.php';

// Подключаем контроллеры
require_once dirname(__DIR__) . '/controllers/BaseController.php';
require_once dirname(__DIR__) . '/controllers/SessionController.php';
require_once dirname(__DIR__) . '/controllers/FavoriteController.php';
require_once dirname(__DIR__) . '/controllers/QuestionController.php';
require_once dirname(__DIR__) . '/controllers/TestController.php';
require_once dirname(__DIR__) . '/controllers/AdminController.php';
require_once dirname(__DIR__) . '/controllers/MaterialController.php';
require_once dirname(__DIR__) . '/controllers/CategoryController.php';
require_once dirname(__DIR__) . '/controllers/LearningPathController.php';
require_once dirname(__DIR__) . '/controllers/SpecialQuestionController.php';
require_once dirname(__DIR__) . '/controllers/GamificationController.php';
require_once dirname(__DIR__) . '/controllers/NotificationController.php';
require_once dirname(__DIR__) . '/controllers/AnalyticsController.php';
require_once dirname(__DIR__) . '/controllers/CertificateController.php';
require_once dirname(__DIR__) . '/controllers/KnowledgeAreaController.php';
require_once dirname(__DIR__) . '/controllers/ControllerFactory.php';

$prefix = $modx->getOption('table_prefix', null, 'modx_');

header('Content-Type: application/json; charset=utf-8');

$input = file_get_contents('php://input');
$inputLength = strlen($input);

// Диагностика: логируем размер входных данных
if ($inputLength > 50000) {
    error_log("[testsystem.php] Large POST body: " . round($inputLength/1024, 2) . " KB");
}

$request = json_decode($input, true);

// Проверяем результат декодирования
if ($request === null && $inputLength > 0) {
    $jsonError = json_last_error_msg();
    error_log("[testsystem.php] JSON decode error: $jsonError, input length: $inputLength bytes");
    die(json_encode([
        'success' => false,
        'message' => 'JSON decode error: ' . $jsonError,
        'debug' => [
            'input_length' => $inputLength,
            'input_preview' => substr($input, 0, 200)
        ]
    ]));
}

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
    // Диагностика траекторий (временно)
    if ($action === 'debugPathProgress' || $action === 'testCompleteStep') {
        if (isset($_GET['path_id'])) $data['path_id'] = (int)$_GET['path_id'];
        if (isset($_GET['step_id'])) $data['step_id'] = (int)$_GET['step_id'];
        if (isset($_GET['progress_id'])) $data['progress_id'] = (int)$_GET['progress_id'];
    }
}

$response = ['success' => false, 'message' => 'Unknown action'];

// ============================================
// CSRF PROTECTION
// ============================================
// Список actions, которые НЕ требуют CSRF проверки (только чтение данных)
$csrfExemptActions = [
    'getApiVersion',             // Информация о версии API
    'getTestInfo',
    'getQuestion',
    'getTestSettings',
    'checkEditRights',
    'getUserTestHistory',
    'getDetailedResults',
    'getKnowledgeAreas',
    'getTestPermissions',
    'getAvailableUsersForTest', // Список пользователей для предоставления доступа
    'checkTestAccess',          // Проверка доступа к тесту
    'getAllQuestionsForTest',
    'getMyTests',                // Получение личных тестов
    'getSharedWithMe',           // Получение тестов, доступных пользователю
    'getPublicTests',            // Получение публичных тестов
    'getKnowledgeAreaDetails',   // Детали области знаний
    'getAvailableTestsTree',     // Дерево доступных тестов
    'getFavoriteStatus',         // Статус избранного
    'getFavoriteQuestions',      // Список избранных вопросов
    'getNotifications',          // Список уведомлений (legacy)
    'getRecentNotifications',    // Список уведомлений (JS alias)
    'getAllNotifications',       // Все уведомления (JS alias)
    'getUnreadNotificationsCount', // Счётчик непрочитанных (JS alias)
    'getNotificationSettings',   // Настройки уведомлений (JS alias)
    'getAllQuestions',           // Все вопросы теста
    'checkResourcePermissions',  // Проверка прав на ресурсы
    'checkSiteSettings',         // Проверка настроек сайта
    'getParentUri',              // Получение URI родителя
    'getPublicTestBySlug',       // Публичный тест по slug
    'getQuestionAnswers',        // Ответы на вопрос (для просмотра)
    'getMaterialsList',          // Список учебных материалов (только чтение)
    'getMaterial',               // Получение одного материала (только чтение)
    'assignCategoryExpert',      // Назначить эксперта на категорию
    'removeCategoryExpert',      // Убрать эксперта из категории
    'getCategoryExperts',        // Получить экспертов категории
    'getUserCategories',         // Категории пользователя (где он эксперт)
    'checkCategoryPermission',   // Проверить права на категорию
    'getAvailableExperts',       // Список доступных экспертов
    'debugPathProgress',         // Диагностика прогресса траектории (временно)
    'testCompleteStep'           // Тест UPDATE SQL (временно)
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
    // Инициализируем ControllerFactory
    $controllerFactory = new ControllerFactory($modx);

    // Если действие можно обработать через контроллер, делаем это
    if ($controllerFactory->canHandle($action)) {
        $response = $controllerFactory->handle($action, $data);
    } else {
        // Иначе используем старый switch для обратной совместимости

    switch ($action) {

        case 'getApiVersion':
            // Проверка версии API и информации о файле
            $response = ResponseHelper::success([
                'version' => '3.4.2',
                'build' => '20251203-2145',
                'file' => __FILE__,
                'mtime' => date('Y-m-d H:i:s', filemtime(__FILE__)),
                'deleteMaterial_fixed' => true
            ], 'API version info');
            break;

        case 'getSessionInfo':
            $sessionId = ValidationHelper::requireInt($data, 'session_id', 'Session ID required');

            $stmt = $modx->prepare("
                SELECT s.id, s.test_id, s.mode, s.status, s.question_order,
                       s.started_at, t.title as test_title, t.time_limit
                FROM {$prefix}test_sessions s
                LEFT JOIN {$prefix}test_tests t ON t.id = s.test_id
                WHERE s.id = ?
            ");
            $stmt->execute(array($sessionId));
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                throw new Exception('Session not found');
            }

            $questionOrder = json_decode($session['question_order'], true);
            $totalQuestions = is_array($questionOrder) ? count($questionOrder) : 0;

            // Считаем уже отвеченные вопросы
            $stmt = $modx->prepare("
                SELECT COUNT(*) as answered
                FROM {$prefix}test_user_answers
                WHERE session_id = ?
            ");
            $stmt->execute(array($sessionId));
            $answeredRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $answeredCount = (int)$answeredRow['answered'];

            $response = ResponseHelper::success(array(
                'session_id' => (int)$session['id'],
                'test_id' => (int)$session['test_id'],
                'test_title' => $session['test_title'],
                'mode' => $session['mode'],
                'status' => $session['status'],
                'total_questions' => $totalQuestions,
                'current_question_number' => $answeredCount,
                'time_limit' => (int)$session['time_limit'],
                'started_at' => $session['started_at']
            ));
            break;

        case 'checkEditRights':
            $rights = PermissionHelper::getUserRights($modx);

            $response = ResponseHelper::success($rights);
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
            
        // ============================================
        // УЧЕБНЫЕ МАТЕРИАЛЫ (Learning Materials as MODX Resources)
        // ============================================

        case 'getMaterialsList':
            // Получить список учебных материалов (ресурсы MODX с template_id = 6)
            // Доступен всем, авторизация не требуется

            $templateId = 6; // Template "LMS Bootstrap 5 - учебные материалы"
            $parentId = ValidationHelper::optionalInt($data, 'parent_id', 0);

            $sql = "
                SELECT
                    id, pagetitle, longtitle, description, introtext,
                    parent, createdby, createdon, publishedon, published
                FROM {$prefix}site_content
                WHERE template = ?
                    AND deleted = 0
            ";

            $params = [$templateId];

            if ($parentId > 0) {
                $sql .= " AND parent = ?";
                $params[] = $parentId;
            }

            $sql .= " ORDER BY menuindex ASC, createdon DESC";

            $stmt = $modx->prepare($sql);
            $stmt->execute($params);
            $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Проверяем права редактирования (только для авторизованных)
            $isAuthenticated = PermissionHelper::isAuthenticated($modx);
            $userId = $isAuthenticated ? PermissionHelper::getCurrentUserId($modx) : 0;
            $isAdmin = $isAuthenticated ? PermissionHelper::isAdmin($modx) : false;

            foreach ($materials as &$material) {
                $material['can_edit'] = $isAuthenticated &&
                    ((int)$material['createdby'] === $userId || $isAdmin);
                // Формируем URL через MODX makeUrl
                $material['url'] = $modx->makeUrl($material['id'], '', '', 'full');
            }

            $response = ResponseHelper::success($materials);
            break;

        case 'getMaterial':
            // Получить один материал по ID (для редактирования)
            $materialId = ValidationHelper::requireInt($data, 'material_id', 'ID материала не указан');

            // Диагностика: проверяем существование ресурса через прямой SQL запрос
            $stmt = $modx->prepare("SELECT id, pagetitle, deleted FROM {$prefix}site_content WHERE id = ?");
            $stmt->execute([$materialId]);
            $checkResource = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$checkResource) {
                throw new Exception('Material not found in database (ID: ' . $materialId . ')');
            }

            if ($checkResource['deleted'] == 1) {
                throw new Exception('Material is deleted (ID: ' . $materialId . ')');
            }

            $resource = $modx->getObject('modResource', $materialId);

            if (!$resource) {
                throw new Exception('Material found in DB but getObject failed (ID: ' . $materialId . ', Title: ' . $checkResource['pagetitle'] . ')');
            }

            // Проверяем права просмотра (поддержка web и mgr контекстов)
            $isAuthenticated = PermissionHelper::isAuthenticated($modx) || $modx->user->isAuthenticated('mgr');
            $userId = $isAuthenticated ? (PermissionHelper::isAuthenticated($modx) ? PermissionHelper::getCurrentUserId($modx) : $modx->user->get('id')) : 0;
            $isAdmin = $isAuthenticated ? (PermissionHelper::isAdmin($modx) || $modx->user->isAuthenticated('mgr')) : false;

            $materialData = [
                'id' => $resource->get('id'),
                'pagetitle' => $resource->get('pagetitle'),
                'longtitle' => $resource->get('longtitle'),
                'content' => $resource->get('content'),
                'introtext' => $resource->get('introtext'),
                'published' => $resource->get('published'),
                'parent' => $resource->get('parent'),
                'createdby' => $resource->get('createdby'),
                'category_id' => $resource->getTVValue('category_id'),
                'can_edit' => $isAuthenticated && ((int)$resource->get('createdby') === $userId || $isAdmin)
            ];

            $response = ResponseHelper::success($materialData);
            break;

        case 'saveMaterial':
            // Создать или обновить учебный материал (ресурс MODX)
            PermissionHelper::requireAuthentication($modx, 'Требуется авторизация');

            $userId = PermissionHelper::getCurrentUserIdWithMgr($modx);

            // Проверяем права
            $isAdmin = PermissionHelper::isAdmin($modx);
            $isExpert = PermissionHelper::isExpert($modx);

            if (!$isAdmin && !$isExpert) {
                throw new Exception('Нет прав для создания материалов');
            }

            $materialId = ValidationHelper::optionalInt($data, 'material_id', 0);
            $pagetitle = ValidationHelper::requireString($data, 'pagetitle', 'Не указано название');
            $content = ValidationHelper::optionalString($data, 'content', '');
            $introtext = ValidationHelper::optionalString($data, 'introtext', '');
            $parentId = ValidationHelper::optionalInt($data, 'parent', 0);
            $published = ValidationHelper::optionalInt($data, 'published', 1);
            $template = ValidationHelper::optionalInt($data, 'template', 6);
            $categoryId = ValidationHelper::optionalString($data, 'category_id', '');

            if ($materialId > 0) {
                // === ОБНОВЛЕНИЕ ===
                $resource = $modx->getObject('modResource', $materialId);

                if (!$resource) {
                    throw new Exception('Материал не найден');
                }

                $canEdit = ((int)$resource->get('createdby') === $userId) || $isAdmin;
                if (!$canEdit) {
                    throw new Exception('Нет прав для редактирования');
                }

                // Запоминаем оригинального родителя для проверки изменения
                $originalParent = $resource->get('parent');

                $resource->set('pagetitle', $pagetitle);
                $resource->set('longtitle', $pagetitle);
                $resource->set('content', $content);
                $resource->set('introtext', $introtext);
                $resource->set('published', $published);
                $resource->set('editedon', time());
                $resource->set('editedby', $userId);

                if ($parentId > 0 && $parentId != $originalParent) {
                    $resource->set('parent', $parentId);

                    // ВАЖНО: сбрасываем URI, чтобы MODX пересобрал путь
                    $resource->set('uri', '');
                    $resource->set('uri_override', 0);
                }

                // TV нужно ставить ДО save()
                if ($categoryId !== '') {
                    $resource->setTVValue('category_id', $categoryId);
                }

                if (!$resource->save()) {
                    throw new Exception('Ошибка обновления материала');
                }

                // === ПРАВИЛЬНЫЙ ПОРЯДОК + УСИЛЕННАЯ ОЧИСТКА КЭША ===

                // 1. СНАЧАЛА обновляем кэш - это заставит MODX пересчитать и записать uri в БД
                $modx->cacheManager->refresh([
                    'db' => [],
                    'auto_publish' => ['contexts' => ['web']],
                    'context_settings' => ['contexts' => ['web']],
                    'resource' => ['contexts' => ['web']]
                ]);

                // 2. Удаляем файловый кэш контекста web (включая resourceMap)
                $cachePath = $modx->getOption('cache_path');
                if ($cachePath) {
                    $modx->cacheManager->deleteTree($cachePath . 'resource/web/');
                }

                // 3. ТОЛЬКО ПОСЛЕ refresh перезагружаем ресурс - uri уже обновлён в БД
                $resource = $modx->getObject('modResource', $materialId);
                if (!$resource) {
                    throw new Exception('Не удалось перезагрузить материал после сохранения');
                }

                // 4. Очищаем кэш родителей
                if ($originalParent > 0) {
                    $oldParent = $modx->getObject('modResource', $originalParent);
                    if ($oldParent) {
                        $oldParent->clearCache();
                    }
                }
                if ($parentId > 0 && $parentId != $originalParent) {
                    $newParent = $modx->getObject('modResource', $parentId);
                    if ($newParent) {
                        $newParent->clearCache();
                    }
                }

                // 5. Получаем гарантированно актуальный URI
                $uri = $resource->get('uri');
                $url = rtrim($modx->getOption('site_url'), '/') . '/' . ltrim($uri, '/');

                $response = ResponseHelper::success([
                    'material_id' => $materialId,
                    'url' => $url
                ], 'Материал обновлен');

            } else {
                // === СОЗДАНИЕ ===
                $resource = $modx->newObject('modResource');
                $resource->set('pagetitle', $pagetitle);
                $resource->set('longtitle', $pagetitle);

                // Генерируем alias из pagetitle
                $alias = $modx->filterPathSegment($pagetitle);
                if (empty($alias)) {
                    $alias = 'material-' . time();
                }
                $resource->set('alias', $alias);
                $resource->set('content', $content);
                $resource->set('introtext', $introtext);
                $resource->set('template', $template);
                $resource->set('parent', $parentId);
                $resource->set('published', $published);
                $resource->set('createdby', $userId);
                $resource->set('createdon', time());
                $resource->set('publishedon', $published ? time() : 0);
                $resource->set('context_key', 'web');

                // TV нужно ставить ДО save()
                if ($categoryId !== '') {
                    $resource->setTVValue('category_id', $categoryId);
                }

                if (!$resource->save()) {
                    throw new Exception('Ошибка создания материала');
                }

                $materialId = $resource->get('id');

                // === ПРАВИЛЬНЫЙ ПОРЯДОК + УСИЛЕННАЯ ОЧИСТКА КЭША ===

                // 1. СНАЧАЛА обновляем кэш - это заставит MODX пересчитать и записать uri в БД
                $modx->cacheManager->refresh([
                    'db' => [],
                    'auto_publish' => ['contexts' => ['web']],
                    'context_settings' => ['contexts' => ['web']],
                    'resource' => ['contexts' => ['web']]
                ]);

                // 2. Удаляем файловый кэш контекста web (включая resourceMap)
                // Это критично для того, чтобы makeUrl() на следующем запросе нашел новый ресурс
                $cachePath = $modx->getOption('cache_path');
                if ($cachePath) {
                    $modx->cacheManager->deleteTree($cachePath . 'resource/web/');
                }

                // 3. ТОЛЬКО ПОСЛЕ refresh перезагружаем ресурс - uri уже обновлён в БД
                $resource = $modx->getObject('modResource', $materialId);
                if (!$resource) {
                    throw new Exception('Не удалось перезагрузить материал после сохранения');
                }

                // 4. Очищаем кэш родителя
                if ($parentId > 0) {
                    $parent = $modx->getObject('modResource', $parentId);
                    if ($parent) {
                        $parent->clearCache();
                    }
                }

                // 5. Получаем гарантированно актуальный URI
                $uri = $resource->get('uri');
                $url = rtrim($modx->getOption('site_url'), '/') . '/' . ltrim($uri, '/');

                $response = ResponseHelper::success([
                    'material_id' => $materialId,
                    'url' => $url
                ], 'Материал создан');
            }
            break;

        case 'uploadImage':
            // Загрузка изображения для учебных материалов
            PermissionHelper::requireAuthentication($modx, 'Требуется авторизация');

            // Проверяем права
            $isAdmin = PermissionHelper::isAdmin($modx);
            $isExpert = PermissionHelper::isExpert($modx);

            if (!$isAdmin && !$isExpert) {
                throw new Exception('Нет прав для загрузки изображений');
            }

            // Получаем base64 данные и resource_id
            $imageData = ValidationHelper::requireString($data, 'image', 'Изображение не предоставлено');
            $resourceId = ValidationHelper::optionalInt($data, 'resource_id', 0);

            // Парсим base64 data URL
            if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $imageData, $matches)) {
                throw new Exception('Неверный формат изображения');
            }

            $imageType = $matches[1];
            $imageBase64 = $matches[2];

            // Проверяем допустимые типы
            $allowedTypes = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
            if (!in_array(strtolower($imageType), $allowedTypes)) {
                throw new Exception('Недопустимый тип изображения. Разрешены: ' . implode(', ', $allowedTypes));
            }

            // Декодируем base64
            $imageContent = base64_decode($imageBase64);
            if ($imageContent === false) {
                throw new Exception('Ошибка декодирования изображения');
            }

            // Создаем папку с учетом resource_id
            $uploadDir = MODX_BASE_PATH . 'assets/uploads/images/';
            if ($resourceId > 0) {
                $uploadDir .= $resourceId . '/';
            }

            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception('Не удалось создать папку для загрузки');
                }
            }

            // Генерируем уникальное имя файла
            $fileName = uniqid('img_', true) . '.' . $imageType;
            $filePath = $uploadDir . $fileName;

            // Сохраняем файл
            if (file_put_contents($filePath, $imageContent) === false) {
                throw new Exception('Ошибка сохранения файла');
            }

            // Возвращаем URL изображения
            $imageUrl = $modx->getOption('site_url') . 'assets/uploads/images/';
            if ($resourceId > 0) {
                $imageUrl .= $resourceId . '/';
            }
            $imageUrl .= $fileName;

            $response = ResponseHelper::success([
                'url' => $imageUrl,
                'filename' => $fileName
            ], 'Изображение загружено');
            break;

        case 'uploadDocument':
            error_log("[uploadDocument] START");

            // Загрузка документа (PDF, DOC, DOCX и т.д.) для учебных материалов
            PermissionHelper::requireAuthentication($modx, 'Требуется авторизация');
            error_log("[uploadDocument] Auth OK");

            // Проверяем права
            $isAdmin = PermissionHelper::isAdmin($modx);
            $isExpert = PermissionHelper::isExpert($modx);
            error_log("[uploadDocument] isAdmin=$isAdmin, isExpert=$isExpert");

            if (!$isAdmin && !$isExpert) {
                throw new Exception('Нет прав для загрузки документов');
            }

            // Получаем base64 данные, имя файла и resource_id
            error_log("[uploadDocument] Getting document data...");
            $documentData = ValidationHelper::requireString($data, 'document', 'Документ не предоставлен');
            error_log("[uploadDocument] Document data length: " . strlen($documentData));

            $originalName = ValidationHelper::optionalString($data, 'filename', 'document');
            error_log("[uploadDocument] Original name: $originalName");

            $resourceId = ValidationHelper::optionalInt($data, 'resource_id', 0);
            error_log("[uploadDocument] Resource ID: $resourceId");

            // Парсим base64 data URL
            error_log("[uploadDocument] Parsing base64 data URL...");
            if (!preg_match('/^data:([^;]+);base64,(.+)$/', $documentData, $matches)) {
                error_log("[uploadDocument] ERROR: Invalid data URL format");
                throw new Exception('Неверный формат документа');
            }

            $mimeType = $matches[1];
            $documentBase64 = $matches[2];
            error_log("[uploadDocument] MIME type: $mimeType, base64 length: " . strlen($documentBase64));

            // Определяем расширение по MIME типу
            $mimeToExt = [
                'application/pdf' => 'pdf',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.ms-excel' => 'xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/vnd.ms-powerpoint' => 'ppt',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                'text/plain' => 'txt',
                'application/zip' => 'zip',
                'application/x-rar-compressed' => 'rar',
            ];

            $extension = $mimeToExt[$mimeType] ?? null;

            // Если не нашли по MIME, пробуем извлечь из имени файла
            if (!$extension && preg_match('/\.([a-z0-9]+)$/i', $originalName, $extMatches)) {
                $extension = strtolower($extMatches[1]);
            }

            if (!$extension) {
                throw new Exception('Неподдерживаемый тип документа');
            }

            // Проверяем допустимые расширения
            $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar'];
            if (!in_array(strtolower($extension), $allowedExtensions)) {
                throw new Exception('Недопустимый тип документа. Разрешены: ' . implode(', ', $allowedExtensions));
            }

            // Декодируем base64
            error_log("[uploadDocument] Decoding base64...");
            $documentContent = base64_decode($documentBase64);
            if ($documentContent === false) {
                error_log("[uploadDocument] ERROR: base64_decode failed");
                throw new Exception('Ошибка декодирования документа');
            }
            error_log("[uploadDocument] Decoded size: " . strlen($documentContent) . " bytes");

            // Проверка размера (макс 20MB)
            if (strlen($documentContent) > 20 * 1024 * 1024) {
                error_log("[uploadDocument] ERROR: Document too large");
                throw new Exception('Размер документа не должен превышать 20MB');
            }

            // Создаем папку с учетом resource_id
            $uploadDir = MODX_BASE_PATH . 'assets/uploads/documents/';
            if ($resourceId > 0) {
                $uploadDir .= $resourceId . '/';
            }
            error_log("[uploadDocument] Upload dir: $uploadDir");

            if (!is_dir($uploadDir)) {
                error_log("[uploadDocument] Creating directory...");
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log("[uploadDocument] ERROR: mkdir failed");
                    throw new Exception('Не удалось создать папку для загрузки');
                }
            }

            // Очищаем имя файла и генерируем безопасное имя
            error_log("[uploadDocument] Cleaning filename...");
            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            error_log("[uploadDocument] Original basename: $baseName");

            // Заменяем пробелы на подчеркивания
            $baseName = str_replace(' ', '_', $baseName);

            // Транслитерируем кириллицу в латиницу
            $safeName = transliterate($baseName);

            // Дополнительная очистка - оставляем только буквы, цифры, дефис и подчеркивание
            $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $safeName);

            // Убираем повторяющиеся подчеркивания
            $safeName = preg_replace('/_+/', '_', $safeName);

            // Ограничиваем длину
            $safeName = mb_substr($safeName, 0, 100);

            // Генерируем финальное имя файла
            $fileName = $safeName . '_' . uniqid() . '.' . $extension;
            $filePath = $uploadDir . $fileName;
            error_log("[uploadDocument] Transliterated filename: $fileName");
            error_log("[uploadDocument] File path: $filePath");

            // Сохраняем файл
            error_log("[uploadDocument] Saving file...");
            if (file_put_contents($filePath, $documentContent) === false) {
                error_log("[uploadDocument] ERROR: file_put_contents failed");
                throw new Exception('Ошибка сохранения файла');
            }
            error_log("[uploadDocument] File saved successfully");

            // Возвращаем URL документа
            $documentUrl = $modx->getOption('site_url') . 'assets/uploads/documents/';
            if ($resourceId > 0) {
                $documentUrl .= $resourceId . '/';
            }
            $documentUrl .= $fileName;
            error_log("[uploadDocument] Document URL: $documentUrl");

            error_log("[uploadDocument] Creating response...");
            $response = ResponseHelper::success([
                'url' => $documentUrl,
                'filename' => $fileName,
                'original_name' => $originalName,
                'extension' => $extension
            ], 'Документ загружен');
            error_log("[uploadDocument] SUCCESS - END");
            break;

        // УДАЛЕНО: дубликат case 'deleteMaterial' (использовался старый код с $resource->save())
        // Теперь используется только один case 'deleteMaterial' на строке 2530 с SQL запросом

        case 'cleanupResourceFiles':
            // Очистка файлов для конкретного ресурса (можно вызвать вручную)
            PermissionHelper::requireAuthentication($modx, 'Требуется авторизация');

            $isAdmin = PermissionHelper::isAdmin($modx);
            if (!$isAdmin) {
                throw new Exception('Требуются права администратора');
            }

            $resourceId = ValidationHelper::requireInt($data, 'resource_id', 'Не указан ID ресурса');

            $deletedFiles = 0;
            $imagePath = MODX_BASE_PATH . 'assets/uploads/images/' . $resourceId . '/';
            $documentPath = MODX_BASE_PATH . 'assets/uploads/documents/' . $resourceId . '/';

            // Рекурсивное удаление папки
            $deleteDirectory = function($dir) use (&$deleteDirectory, &$deletedFiles) {
                if (!is_dir($dir)) return false;
                $files = array_diff(scandir($dir), ['.', '..']);
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

            $response = ResponseHelper::success([
                'deleted_files' => $deletedFiles,
                'images_folder_deleted' => $imageDeleted,
                'documents_folder_deleted' => $documentDeleted
            ], 'Файлы очищены');
            break;

        case 'diagnoseMaterialsAuth':
            // Диагностика аутентификации для учебных материалов
            $diagnosis = [
                'user' => [
                    'id' => $modx->user->get('id'),
                    'username' => $modx->user->get('username'),
                ],
                'authentication' => [
                    'web' => $modx->user->isAuthenticated('web'),
                    'mgr' => $modx->user->isAuthenticated('mgr'),
                    'helper_isAuthenticated' => PermissionHelper::isAuthenticated($modx),
                    'helper_requireAuthentication_would_pass' => false,
                ],
                'permissions' => [
                    'isAdmin' => PermissionHelper::isAdmin($modx),
                    'isExpert' => method_exists('PermissionHelper', 'isExpert') ? PermissionHelper::isExpert($modx) : 'method not exists',
                ],
                'methods_exist' => [
                    'getCurrentUserIdWithMgr' => method_exists('PermissionHelper', 'getCurrentUserIdWithMgr'),
                ],
            ];

            // Проверяем requireAuthentication
            try {
                PermissionHelper::requireAuthentication($modx, 'Test');
                $diagnosis['authentication']['helper_requireAuthentication_would_pass'] = true;
            } catch (Exception $e) {
                $diagnosis['authentication']['helper_requireAuthentication_error'] = $e->getMessage();
            }

            // Проверяем getCurrentUserIdWithMgr если метод существует
            if (method_exists('PermissionHelper', 'getCurrentUserIdWithMgr')) {
                $diagnosis['user']['id_with_mgr'] = PermissionHelper::getCurrentUserIdWithMgr($modx);
            }

            $response = ResponseHelper::success($diagnosis, 'Диагностика завершена');
            break;


    default:
                throw new Exception('Unknown action: ' . $action);
        }
    } // Закрываем else блок для switch

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

// Финальная проверка перед отправкой
error_log("[testsystem.php] Encoding response to JSON...");
$jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if ($jsonResponse === false) {
    error_log("[testsystem.php] ERROR: json_encode failed - " . json_last_error_msg());
    error_log("[testsystem.php] Response data: " . print_r($response, true));
    die(json_encode(['success' => false, 'message' => 'JSON encoding error: ' . json_last_error_msg()]));
}

error_log("[testsystem.php] JSON length: " . strlen($jsonResponse) . " bytes");
echo $jsonResponse;
error_log("[testsystem.php] Response sent successfully");

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