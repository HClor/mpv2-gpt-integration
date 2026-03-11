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
    'getCsrfToken',              // Обновление CSRF токена (для long-running UI)
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
    'getUnreadNotifications',       // Счётчик непрочитанных (JS alias tsScripts.tpl)
    'getUnreadNotificationsCount', // Счётчик непрочитанных (JS alias notifications.js)
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

        case 'getCsrfToken':
            $response = ResponseHelper::success([
                'csrf_token' => CsrfProtection::getToken()
            ], 'CSRF token refreshed');
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

$jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if ($jsonResponse === false) {
    error_log("[testsystem.php] json_encode failed: " . json_last_error_msg());
    die(json_encode(array('success' => false, 'message' => 'JSON encoding error: ' . json_last_error_msg())));
}

echo $jsonResponse;
