<?php
/**
 * Сниппет: testRunner - Запуск и прохождение теста
 * Вызывается из: MODX ресурсов (страницы тестов)
 * Назначение: Отображает интерфейс прохождения теста, обрабатывает ответы
 *
 * @package TestSystem
 * @version 4.2
 */

// Подключаем bootstrap для CSRF защиты
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

if (!$modx instanceof modX) {
    return '<div class="ts-alert ts-alert-danger">MODX context required</div>';
}

// НОВАЯ ЛОГИКА: Проверяем наличие sessionId и view параметра
$sessionId = isset($_GET['sessionId']) ? (int)$_GET['sessionId'] : 0;
$viewParam = isset($_GET['view']) ? $_GET['view'] : '';
$skipModeSelection = false;

// Если есть view=questions или view=manage - пропускаем выбор режима (будет управление через JS)
if ($viewParam === 'questions' || $viewParam === 'manage') {
    $skipModeSelection = true;
}

// Если есть sessionId - загружаем сессию и пропускаем выбор режима
if ($sessionId > 0) {
    $sessionTable = '`' . $modx->getOption('table_prefix') . 'test_sessions`';
    $stmt = $modx->prepare("SELECT * FROM {$sessionTable} WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        return '<div class="ts-alert ts-alert-danger">Сессия не найдена</div>';
    }

    // Проверка прав доступа к сессии
    $userId = $modx->user->get('id');
    if ((int)$session['user_id'] != $userId) {
        return '<div class="ts-alert ts-alert-danger">Нет доступа к этой сессии</div>';
    }

    // Устанавливаем testId из сессии
    $testIdFromUrl = (int)$session['test_id'];
    $skipModeSelection = true;

    // Сохраняем данные сессии для использования в JS
    $sessionData = [
        'session_id' => $sessionId,
        'mode' => $session['mode'],
        'status' => $session['status']
    ];
} else {
    // ИСПРАВЛЕНО: Получаем testId из GET-параметра или ID текущего ресурса
    $testIdFromUrl = isset($_GET['testId']) ? (int)$_GET['testId'] : 0;

    // Если testId не передан, используем старый способ через resource_id
    $resourceId = 0;
    if ($modx->resource instanceof modResource) {
        $resourceId = (int)$modx->resource->get('id');
    }

    if ($testIdFromUrl <= 0 && $resourceId <= 0) {
        return '<div class="ts-alert ts-alert-danger">Ошибка: тест не указан</div>';
    }
}

$prefix = (string)$modx->getOption('table_prefix');
$tableTests = '`' . $prefix . 'test_tests`';
$tableQuestions = '`' . $prefix . 'test_questions`';
$tableMemberGroups = '`' . $prefix . 'member_groups`';
$tableMemberGroupNames = '`' . $prefix . 'membergroup_names`';



// ============================================
// ПОДДЕРЖКА ОБЛАСТЕЙ ЗНАНИЙ
// ============================================

$knowledgeAreaId = isset($_GET['knowledge_area']) ? (int)$_GET['knowledge_area'] : 0;


if ($knowledgeAreaId > 0) {
    
    // КРИТИЧНО: Проверяем что область принадлежит текущему пользователю
    if (!$modx->user->hasSessionContext('web')) {
        $authId = (int)$modx->getOption('lms.auth_page', null, 0);
        $authUrl = $authId > 0 ? rtrim($modx->makeUrl($authId, 'web', []), '/') : rtrim($modx->makeUrl($modx->resource->get('id'), 'web', []), '/');
        return '<div class="ts-alert ts-alert-warning auth-required-alert">
            <div class="d-flex align-items-center mb-3">
                <i class="bi bi-shield-lock me-2" style="font-size: 2rem;"></i>
                <h4 class="mb-0">Требуется авторизация</h4>
            </div>
            <p class="mb-3">Для прохождения области знаний необходимо войти в систему.</p>
            <a href="' . htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8') . '" class="ts-btn ts-btn-primary"><i class="bi bi-box-arrow-in-right me-2"></i>Войти в систему</a>
        </div>';
    }

    $userId = (int)$modx->user->get('id');

    // Загружаем область знаний
    $stmt = $modx->prepare("
        SELECT ka.name, ka.description, ka.test_ids, ka.questions_per_session, ka.user_id
        FROM {$prefix}test_knowledge_areas ka
        WHERE ka.id = ? AND ka.is_active = 1
    ");
    
    if (!$stmt) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[testRunner] Failed to prepare knowledge area query');
        return '<div class="ts-alert ts-alert-danger">Ошибка при загрузке области знаний</div>';
    }
    
    if (!$stmt->execute([$knowledgeAreaId])) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[testRunner] Failed to execute knowledge area query: ' . print_r($stmt->errorInfo(), true));
        return '<div class="ts-alert ts-alert-danger">Область знаний не найдена</div>';
    }
    
    $knowledgeArea = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$knowledgeArea) {
        return '<div class="ts-alert ts-alert-danger">Область знаний не найдена или неактивна</div>';
    }
    
    // КРИТИЧНО: Проверяем владельца
    if ((int)$knowledgeArea['user_id'] !== $userId) {
        $modx->log(modX::LOG_LEVEL_WARN, "[testRunner] User {$userId} tried to access knowledge area {$knowledgeAreaId} owned by {$knowledgeArea['user_id']}");
        return '<div class="ts-alert ts-alert-danger">Доступ запрещен. Это не ваша область знаний.</div>';
    }
    
    // Парсим список тестов
    $testIds = json_decode($knowledgeArea['test_ids'], true);
    
    if (!is_array($testIds) || empty($testIds)) {
        return '<div class="ts-alert ts-alert-warning">В области знаний нет выбранных тестов</div>';
    }
    
    // Подсчитываем общее количество вопросов
    $placeholders = implode(',', array_fill(0, count($testIds), '?'));
    $stmt = $modx->prepare("
        SELECT COUNT(*) 
        FROM {$tableQuestions} 
        WHERE test_id IN ($placeholders) AND published = 1
    ");
    
    if (!$stmt) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[testRunner] Failed to prepare questions count query for knowledge area');
        return '<div class="ts-alert ts-alert-danger">Ошибка при загрузке вопросов</div>';
    }
    
    if (!$stmt->execute($testIds)) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[testRunner] Failed to count questions for knowledge area: ' . print_r($stmt->errorInfo(), true));
        return '<div class="ts-alert ts-alert-danger">Ошибка при загрузке вопросов</div>';
    }
    
    $totalQuestions = (int)$stmt->fetchColumn();
    
    if ($totalQuestions === 0) {
        // КОНФИГУРАЦИЯ: ID страницы управления областями из конфигурации
        $manageAreasPageId = Config::getPageId('manage_areas', 125);
        $manageAreasPageUrl = rtrim($modx->makeUrl($manageAreasPageId, 'web', []), '/');
        
        return '<div class="ts-alert ts-alert-warning">
            <h4>В области знаний нет вопросов</h4>
            <p>Возможно, выбранные тесты пусты или все вопросы сняты с публикации.</p>
            <a href="' . htmlspecialchars($manageAreasPageUrl, ENT_QUOTES, 'UTF-8') . '" class="ts-btn ts-btn-primary mt-2">Вернуться к управлению областями</a>
        </div>';
    }
    
    $questionsPerSession = min((int)$knowledgeArea['questions_per_session'], $totalQuestions);

    // КОНФИГУРАЦИЯ: ID страницы управления областями из конфигурации
    $manageAreasPageId = Config::getPageId('manage_areas', 125);
    $manageAreasPageUrl = rtrim($modx->makeUrl($manageAreasPageId, 'web', []), '/');
    
    // Формируем вывод для области знаний
    $output = '<div id="test-container" data-test-id="0" data-knowledge-area-id="' . (int)$knowledgeAreaId . '" data-can-edit="0" data-test-mode="training">';
    $output .= '<div id="test-info" class="card mb-4">';
    $output .= '<div class="card-header">';
    $output .= '<div class="d-flex justify-content-between align-items-center">';
    $output .= '<h2><i class="bi bi-collection-fill text-primary"></i> ' . htmlspecialchars($knowledgeArea['name'], ENT_QUOTES, 'UTF-8') . '</h2>';
    $output .= '<a href="' . htmlspecialchars($manageAreasPageUrl, ENT_QUOTES, 'UTF-8') . '" class="ts-btn ts-btn-secondary ts-btn-sm">';
    $output .= '<i class="bi bi-arrow-left"></i> Назад';
    $output .= '</a>';
    $output .= '</div>';
    $output .= '</div>';
    
    $output .= '<div class="card-body">';
    
    if (!empty($knowledgeArea['description'])) {
        $output .= '<p class="lead">' . nl2br(htmlspecialchars($knowledgeArea['description'], ENT_QUOTES, 'UTF-8')) . '</p>';
    }
    
    $output .= '<div class="ts-alert ts-alert-info">';
    $output .= '<i class="bi bi-info-circle-fill"></i> ';
    $output .= '<strong>Комбинированный тест из вашей личной подборки</strong><br>';
    $output .= 'Вопросы случайным образом выбираются из ' . count($testIds) . ' выбранных вами тестов.';
    $output .= '</div>';
    
    $output .= '<ul class="list-unstyled">';
    $output .= '<li><strong>Тестов в подборке:</strong> ' . count($testIds) . '</li>';
    $output .= '<li><strong>Всего вопросов в банке:</strong> ' . (int)$totalQuestions . '</li>';
    $output .= '<li><strong>Вопросов за попытку:</strong> ' . (int)$questionsPerSession . '</li>';
    $output .= '<li><strong>Режим:</strong> Обучение (Training) - с объяснениями</li>';
    $output .= '</ul>';
    
    // Кнопка старта (только Training для областей знаний)
    $output .= '<hr>';
    $output .= '<div class="d-grid gap-2">';
    $output .= '<button class="ts-btn ts-btn-primary ts-btn-lg start-test-btn" data-mode="training">';
    $output .= '<i class="bi bi-play-fill"></i> Начать тест';
    $output .= '</button>';
    $output .= '</div>';
    
    $output .= '</div></div>';
    
    // Контейнер для вопросов (такой же как в обычном тесте)
    $output .= '<div id="question-container" style="display:none;">';
    
    // ПРОГРЕСС-БАР
    $output .= '<div class="progress mb-3" style="height: 8px; border-radius: 10px;">';
    $output .= '<div id="test-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>';
    $output .= '</div>';
    
    $output .= '<div class="card mb-3">';
    $output .= '<div class="card-header d-flex justify-content-between align-items-center">';
    $output .= '<span id="question-progress">Вопрос <span id="current-q">1</span> из <span id="total-q">' . (int)$questionsPerSession . '</span></span>';
    $output .= '<span class="ts-badge ts-badge-primary">TRAINING</span>';
    $output .= '</div>';
    $output .= '<div class="card-body">';
    $output .= '<h4 id="question-text"></h4>';
    $output .= '<div id="question-type-hint" style="display: none;"></div>';
    $output .= '<div id="answer-options" class="mt-3"></div>';
    $output .= '<div id="explanation-block" class="ts-alert ts-alert-info mt-3" style="display:none;"></div>';
    $output .= '</div>';
    
    $output .= '<div class="card-footer">';
    $output .= '<div class="d-flex justify-content-between align-items-center mb-2">';
    $output .= '<div class="d-flex align-items-center">';
    $output .= '<button id="submit-answer-btn" class="ts-btn ts-btn-primary" disabled>Ответить</button>';
    $output .= '<button id="next-question-btn" class="ts-btn ts-btn-success" style="display:none;">Следующий вопрос</button>';
    $output .= '</div>';
    $output .= '<div class="d-flex align-items-center gap-2">';
    $output .= '<button id="restart-test-btn" class="ts-btn ts-btn-secondary ts-btn-sm" style="display: none;">';
    $output .= '<i class="bi bi-arrow-counterclockwise"></i> Начать сначала';
    $output .= '</button>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div></div></div>';
    
    // Контейнер для результатов
    $output .= '<div id="results-container" style="display:none;">';
    $output .= '<div class="card">';
    $output .= '<div class="card-header"><h2>Результаты теста</h2></div>';
    $output .= '<div class="card-body text-center">';
    $output .= '<h3 id="final-score" class="mb-3"></h3>';
    $output .= '<p id="result-message" class="lead"></p>';
    $output .= '<div id="result-details" class="mt-3"></div>';
    
    $retryUrl = rtrim($modx->makeUrl($modx->resource->get('id'), 'web', ['knowledge_area' => $knowledgeAreaId]), '/');
    $output .= '<a href="' . htmlspecialchars($retryUrl, ENT_QUOTES, 'UTF-8') . '" class="ts-btn ts-btn-primary mt-3">Пройти еще раз</a>';
    $output .= '<a href="' . htmlspecialchars($manageAreasPageUrl, ENT_QUOTES, 'UTF-8') . '" class="ts-btn ts-btn-secondary mt-3">К списку областей</a>';
    
    $output .= '</div></div></div>';
    $output .= '</div>';
    
    // Подключение ресурсов
    $assetsUrl = $modx->getOption('assets_url', null, MODX_ASSETS_URL);
    $assetsUrl = rtrim($assetsUrl, '/') . '/';

    $categoriesAndTestsCssPath = $assetsUrl . 'components/testsystem/css/categories-and-tests.css';
    $modx->regClientCSS($categoriesAndTestsCssPath);

    $jsPath = $assetsUrl . 'components/testsystem/js/tsrunner.js';

    // ПРИМЕЧАНИЕ: CSRF токен и CSS уже подключены в tsHead.tpl
    // ПРИМЕЧАНИЕ: Bootstrap JS подключен в tsScripts.tpl

    // XSS Protection: Подключаем DOMPurify для санитизации HTML
    $output .= '<script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>';

    $output .= '<script src="' . htmlspecialchars($jsPath, ENT_QUOTES, 'UTF-8') . '"></script>';

    // Подключение Quill для редактирования вопросов
    $output .= '<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">';
    $output .= '<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>';

    return $output;
}

// ============================================
// ОБЫЧНЫЙ РЕЖИМ ТЕСТА (существующий код)
// ============================================










// ИСПРАВЛЕНО: Ищем тест либо по ID из URL, либо привязанный к этому ресурсу
if ($testIdFromUrl > 0) {
    // Загружаем тест по ID из GET-параметра
    $sql = "SELECT `id`, `resource_id`, `title`, `description`, `mode`, `time_limit`, `pass_score`, `questions_per_session`
        FROM {$tableTests}
        WHERE `is_active` = 1 AND `id` = ?
        LIMIT 1";
    $queryParam = $testIdFromUrl;
} else {
    // Загружаем тест по resource_id (старое поведение)
    $sql = "SELECT `id`, `resource_id`, `title`, `description`, `mode`, `time_limit`, `pass_score`, `questions_per_session`
        FROM {$tableTests}
        WHERE `is_active` = 1 AND `resource_id` = ?
        LIMIT 1";
    $queryParam = $resourceId;
}

$stmt = $modx->prepare($sql);
if (!$stmt) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[testRunner] Failed to prepare test query');
    return '<div class="ts-alert ts-alert-danger">Ошибка при загрузке теста</div>';
}

if (!$stmt->execute([$queryParam])) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[testRunner] Failed to execute test query: ' . print_r($stmt->errorInfo(), true));
    return '<div class="ts-alert ts-alert-danger">Тест не найден или неактивен</div>';
}

$test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    // Если тест не найден, показываем дружелюбное сообщение
    $canEdit = false;
    $currentUserId = (int)$modx->user->get('id');


    
    // Проверяем права
    $groupAdmins = Config::getGroup('admins');
    $groupExperts = Config::getGroup('experts');
    $roleStmt = $modx->prepare("SELECT mgn.`name` FROM {$tableMemberGroups} AS mg
        JOIN {$tableMemberGroupNames} AS mgn ON mgn.`id` = mg.`user_group`
        WHERE mg.`member` = :uid AND mgn.`name` IN (:group1, :group2)");

    if ($roleStmt && $roleStmt->execute([':uid' => $currentUserId, ':group1' => $groupAdmins, ':group2' => $groupExperts])) {
        $roleNames = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
        if (in_array($groupAdmins, $roleNames, true) || in_array($groupExperts, $roleNames, true)) {
            $canEdit = true;
        }
    }
    
    if ($canEdit) {
        $questionsManagerId = (int)$modx->getOption('lms.questions_manager_page', null, 0);
        $questionsUrl = $questionsManagerId > 0 ? $modx->makeUrl($questionsManagerId, 'web', [], 'full') : '#';
        
        return '<div class="ts-alert ts-alert-warning">
            <h4>Тест не найден</h4>
            <p>К этой странице не привязан тест. Вы можете создать тест через <a href="' . htmlspecialchars($questionsUrl, ENT_QUOTES, 'UTF-8') . '">панель управления</a>.</p>
        </div>';
    }
    
    return '<div class="ts-alert ts-alert-danger">Тест не найден или неактивен</div>';
}


$testId = (int)$test['id'];

// ДОБАВЛЕНО: Загружаем расширенную информацию о тесте для проверки доступа
$stmt = $modx->prepare("
    SELECT created_by, publication_status 
    FROM {$tableTests} 
    WHERE id = ?
");
$stmt->execute([$testId]);
$testAccess = $stmt->fetch(PDO::FETCH_ASSOC);

// Проверка авторизации
if (!$modx->user->hasSessionContext('web')) {
    $authId = (int)$modx->getOption('lms.auth_page', null, 0);
    $authUrl = $authId > 0 ? $modx->makeUrl($authId, 'web', '', 'full') : $modx->makeUrl($modx->resource->get('id'), 'web', '', 'full');
    return '<div class="ts-alert ts-alert-warning"><p>Для прохождения теста необходимо <a href="' . htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8') . '">войти в систему</a>.</p></div>';
}

$userId = (int)$modx->user->get('id');

// ДОБАВЛЕНО: Проверка доступа к тесту на основе publication_status
$hasAccess = false;
$publicationStatus = $testAccess['publication_status'] ?? 'public';
$createdBy = (int)($testAccess['created_by'] ?? 0);

// Определяем права пользователя
$groupAdmins = Config::getGroup('admins');
$groupExperts = Config::getGroup('experts');
$roleStmt = $modx->prepare("SELECT mgn.`name` FROM {$tableMemberGroups} AS mg
    JOIN {$tableMemberGroupNames} AS mgn ON mgn.`id` = mg.`user_group`
    WHERE mg.`member` = :uid AND mgn.`name` IN (:group1, :group2)");

$isAdmin = false;
$isExpert = false;
$isAdminOrExpert = false;
if ($roleStmt && $roleStmt->execute([':uid' => $userId, ':group1' => $groupAdmins, ':group2' => $groupExperts])) {
    $roleNames = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
    $isAdmin = in_array($groupAdmins, $roleNames, true);
    $isExpert = in_array($groupExperts, $roleNames, true);
    if ($isAdmin || $isExpert) {
        $isAdminOrExpert = true;
    }
}

// Проверяем доступ
if ($isAdminOrExpert) {
    // Админы и эксперты видят все
    $hasAccess = true;
} elseif ($createdBy === $userId) {
    // Владелец теста
    $hasAccess = true;
} elseif ($publicationStatus === 'public' || $publicationStatus === 'unlisted') {
    // Публичные и unlisted тесты
    $hasAccess = true;
} elseif ($publicationStatus === 'private') {
    // Проверяем permissions
    $stmt = $modx->prepare("
        SELECT COUNT(*) 
        FROM {$prefix}test_permissions 
        WHERE test_id = ? AND user_id = ?
    ");
    $stmt->execute([$testId, $userId]);
    $hasAccess = (int)$stmt->fetchColumn() > 0;
} elseif ($publicationStatus === 'draft') {
    // Черновики видит только создатель (уже проверили выше)
    $hasAccess = false;
}

// Если нет доступа - показываем сообщение
if (!$hasAccess) {
    $modx->log(modX::LOG_LEVEL_WARN, "[testRunner] User {$userId} denied access to test {$testId} (status: {$publicationStatus})");
    return '<div class="ts-alert ts-alert-danger">
        <h4>Доступ запрещен</h4>
        <p>Этот тест является приватным. Для доступа к нему требуется разрешение владельца.</p>
    </div>';
}

// УДАЛИТЬ старую проверку авторизации (она была выше), оставляем только код ниже


// Подсчет вопросов в тесте
$stmt = $modx->prepare("SELECT COUNT(*) FROM {$tableQuestions} WHERE `test_id` = ?");
$totalQuestions = 0;

if (!$stmt) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[testRunner] Failed to prepare questions count query for test ' . $testId);
    return '<div class="ts-alert ts-alert-danger">Ошибка при загрузке вопросов теста</div>';
}

if (!$stmt->execute([$testId])) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[testRunner] Failed to count questions for test ' . $testId . ': ' . print_r($stmt->errorInfo(), true));
    return '<div class="ts-alert ts-alert-danger">Ошибка при загрузке вопросов теста</div>';
}

$totalQuestions = (int)$stmt->fetchColumn();

// КРИТИЧЕСКАЯ ПРОВЕРКА: наличие вопросов
if ($totalQuestions === 0) {
    $modx->log(modX::LOG_LEVEL_WARN, '[testRunner] Test ' . $testId . ' has no questions');
    
    // Проверяем права на редактирование для показа ссылки на импорт
    $currentUserId = (int)$modx->user->get('id');
    $canEdit = false;

    $groupAdmins = Config::getGroup('admins');
    $groupExperts = Config::getGroup('experts');
    $roleStmt = $modx->prepare("SELECT mgn.`name` FROM {$tableMemberGroups} AS mg
        JOIN {$tableMemberGroupNames} AS mgn ON mgn.`id` = mg.`user_group`
        WHERE mg.`member` = :uid AND mgn.`name` IN (:group1, :group2)");

    if ($roleStmt && $roleStmt->execute([':uid' => $currentUserId, ':group1' => $groupAdmins, ':group2' => $groupExperts])) {
        $roleNames = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
        if (in_array($groupAdmins, $roleNames, true) || in_array($groupExperts, $roleNames, true)) {
            $canEdit = true;
        }
    }
    
    if ($canEdit) {
        // Показываем кнопку импорта CSV
        $importPageId = Config::getPageId('import_csv', 29);
        $importUrl = $modx->makeUrl($importPageId, 'web', ['test_id' => $testId], 'full');
        
        if (!empty($importUrl)) {
            return '<div class="ts-alert ts-alert-warning">
                <h4>В тесте нет вопросов</h4>
                <p>Добавьте вопросы через импорт CSV файла.</p>
                <a href="' . htmlspecialchars($importUrl, ENT_QUOTES, 'UTF-8') . '" class="ts-btn ts-btn-success mt-2">📤 Импортировать вопросы из CSV</a>
            </div>';
        }
        
        $questionsManagerId = (int)$modx->getOption('lms.questions_manager_page', null, 0);
        if ($questionsManagerId > 0) {
            $questionsUrl = $modx->makeUrl($questionsManagerId, 'web', ['test' => $testId], 'full');
            return '<div class="ts-alert ts-alert-warning">
                <h4>В тесте нет вопросов</h4>
                <p>Добавьте вопросы через <a href="' . htmlspecialchars($questionsUrl, ENT_QUOTES, 'UTF-8') . '" class="alert-link">панель управления вопросами</a>.</p>
            </div>';
        }
    }
    
    return '<div class="ts-alert ts-alert-danger">В тесте отсутствуют вопросы. Пожалуйста, обратитесь к администратору.</div>';
}

// Валидация questions_per_session
$questionsPerSession = (int)($test['questions_per_session'] ?? 0);
if ($questionsPerSession <= 0 || $questionsPerSession > $totalQuestions) {
    $questionsPerSession = $totalQuestions;
}

// Определение прав на управление тестом (как в categoriesAndTests)
$isOwner = ($createdBy === $userId);
$canManage = $isAdmin || $isExpert || $isOwner;
$canDelete = $isAdmin || $isOwner;

// Определение прав на редактирование
$canEditTest = $canManage;

// ДОБАВЛЕНО: Проверяем может ли пользователь редактировать через permissions
if (!$canEditTest && $publicationStatus === 'private') {
    $stmt = $modx->prepare("
        SELECT can_edit 
        FROM {$prefix}test_permissions 
        WHERE test_id = ? AND user_id = ?
    ");
    $stmt->execute([$testId, $userId]);
    $perm = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($perm && (int)$perm['can_edit'] === 1) {
        $canEditTest = true;
    }
}
$importPageId = Config::getPageId('import_csv', 29);
$importUrl = $modx->makeUrl($importPageId, 'web', ['test_id' => $testId], 'full');

$testCardMenu = '';
if ($canManage) {
    $testCardMenu .= '<div class="test-menu-toggle" data-test-id="' . (int)$testId . '">⋮</div>';
    $testCardMenu .= '<div class="test-menu-dropdown" id="menu-' . (int)$testId . '" style="display: none;">';

    if (!empty($importUrl)) {
        $testCardMenu .= '<a href="' . htmlspecialchars($importUrl, ENT_QUOTES, 'UTF-8') . '" class="menu-item">';
        $testCardMenu .= '<i class="bi bi-file-earmark-arrow-down"></i> Импорт вопросов';
        $testCardMenu .= '</a>';
    }

    $testCardMenu .= '<a href="javascript:void(0)" class="menu-item" onclick="event.preventDefault(); showAllQuestionsView(); return false;">';
    $testCardMenu .= '<i class="bi bi-question-circle"></i> Редактировать вопросы';
    $testCardMenu .= '</a>';

    $testCardMenu .= '<a href="javascript:void(0)" class="menu-item" onclick="event.preventDefault(); openTestManagementModal(' . (int)$testId . ', \'' . htmlspecialchars($publicationStatus, ENT_QUOTES, 'UTF-8') . '\'); return false;">';
    $testCardMenu .= '<i class="bi bi-gear"></i> Настройки теста';
    $testCardMenu .= '</a>';

    if ($canDelete) {
        $testCardMenu .= '<a href="javascript:void(0)" class="menu-item menu-item-danger" onclick="event.preventDefault(); deleteTestConfirm(' . (int)$testId . '); return false;">';
        $testCardMenu .= '<i class="bi bi-trash"></i> Удалить тест';
        $testCardMenu .= '</a>';
    }

    $testCardMenu .= '</div>';
}

// Валидация доступных режимов
// ВАЖНО: Всегда показываем оба режима для выбора пользователя
$testMode = 'both';

$passScore = (int)$test['pass_score'];
$timeLimit = (int)$test['time_limit'];

// Формирование HTML вывода
$viewDataAttr = $viewParam ? ' data-view="' . htmlspecialchars($viewParam, ENT_QUOTES, 'UTF-8') . '"' : '';
$output = '<div id="test-container" data-test-id="' . (int)$testId . '" data-can-edit="' . ($canEditTest ? '1' : '0') . '" data-can-delete="' . ($canDelete ? '1' : '0') . '" data-test-mode="' . htmlspecialchars($testMode, ENT_QUOTES, 'UTF-8') . '"' . $viewDataAttr . '>';
$output .= '<div class="tests-grid test-runner-launch-grid">';

// Скрываем test-info если view=questions или view=manage (для быстрой загрузки)
$testInfoStyle = ($viewParam === 'questions' || $viewParam === 'manage') ? ' style="display:none;"' : '';
$output .= '<div id="test-info" class="test-card mb-4"' . $testInfoStyle . '>';
$output .= '<div class="test-card-header">';
$output .= '<h3 class="test-title">' . htmlspecialchars($test['title'], ENT_QUOTES, 'UTF-8') . '</h3>';
$output .= $testCardMenu;
$output .= '</div>';

$description = !empty($test['description']) ? $test['description'] : 'Нет описания';
$output .= '<p class="test-description">' . nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) . '</p>';

$output .= '<div class="test-meta">';
$output .= '<span class="meta-item"><span class="meta-icon">📊</span><span class="meta-label">Вопросов в банке:</span><span class="meta-value">' . (int)$totalQuestions . '</span></span>';
$output .= '<span class="meta-item"><span class="meta-icon">📝</span><span class="meta-label">Вопросов за попытку:</span><span class="meta-value">' . (int)$questionsPerSession . '</span></span>';
$output .= '<span class="meta-item"><span class="meta-icon">✅</span><span class="meta-label">Проходной балл:</span><span class="meta-value">' . (int)$passScore . '%</span></span>';
if ($timeLimit > 0) {
    $output .= '<span class="meta-item"><span class="meta-icon">⏱️</span><span class="meta-label">Время:</span><span class="meta-value">' . (int)$timeLimit . ' мин</span></span>';
}
$output .= '</div>';

if (!$skipModeSelection && $testMode === 'both') {
    $output .= '<hr class="test-divider">';
    $output .= '<div class="test-training-controls">';
    $output .= '<label for="training-questions-count">Количество вопросов:</label>';
    $output .= '<div class="questions-input-group">';
    $output .= '<input type="number" id="training-questions-count" class="questions-count-input" min="1" max="' . (int)$totalQuestions . '" value="' . min(20, (int)$totalQuestions) . '" data-max="' . (int)$totalQuestions . '">';
    $output .= '<button class="btn-all-questions" type="button" id="training-all-questions" data-total="' . (int)$totalQuestions . '">Все</button>';
    $output .= '</div>';
    $output .= '</div>';

    $output .= '<div class="test-action-buttons">';
    $output .= '<button class="btn-start-training ts-btn start-test-btn" data-mode="training">';
    $output .= '<span class="btn-icon">🎓</span><span class="btn-text">Тренировка</span>';
    $output .= '</button>';
    $output .= '<button class="btn-start-exam ts-btn start-test-btn" data-mode="exam">';
    $output .= '<span class="btn-icon">🎯</span><span class="btn-text">Экзамен</span>';
    $output .= '</button>';
    $output .= '</div>';
} elseif ($testMode === 'training') {
    $output .= '<hr class="test-divider">';
    $output .= '<div class="test-action-buttons">';
    $output .= '<button class="btn-start-training ts-btn start-test-btn" data-mode="training">';
    $output .= '<span class="btn-icon">🎓</span><span class="btn-text">Тренировка</span>';
    $output .= '</button>';
    $output .= '</div>';
} elseif ($testMode === 'exam') {
    $output .= '<hr class="test-divider">';
    $output .= '<div class="test-action-buttons">';
    $output .= '<button class="btn-start-exam ts-btn start-test-btn" data-mode="exam">';
    $output .= '<span class="btn-icon">🎯</span><span class="btn-text">Экзамен</span>';
    $output .= '</button>';
    $output .= '</div>';
}

$output .= '</div></div></div>';

// Контейнер для вопросов
$output .= '<div id="question-container" style="display:none;">';

// ПРОГРЕСС-БАР
$output .= '<div class="progress mb-3" style="height: 8px; border-radius: 10px;">';
$output .= '<div id="test-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>';
$output .= '</div>';

$output .= '<div class="card mb-3">';
$output .= '<div class="card-header d-flex justify-content-between align-items-center">';
$output .= '<span id="question-progress">Вопрос <span id="current-q">1</span> из <span id="total-q">' . (int)$questionsPerSession . '</span></span>';
$output .= '<div class="d-flex align-items-center gap-2">';
$output .= '<span id="exam-timer" class="ts-badge ts-badge-warning" style="display:none;"><i class="bi bi-alarm me-1"></i><span id="timer-display">00:00</span></span>';
$output .= '<span id="mode-badge" class="ts-badge"></span>';
$output .= '</div>';
$output .= '</div>';
$output .= '<div class="card-body">';
$output .= '<h4 id="question-text"></h4>';
$output .= '<div id="question-type-hint" style="display: none;"></div>';
$output .= '<div id="answer-options" class="mt-3"></div>';
$output .= '<div id="explanation-block" class="ts-alert ts-alert-info mt-3" style="display:none;"></div>';
$output .= '</div>';

// ИСПРАВЛЕННЫЙ БЛОК С КНОПКАМИ:

// СТРОКА 1: Навигационные кнопки
$output .= '<div class="card-footer">';
$output .= '<div class="d-flex justify-content-between align-items-center mb-2">';
// СЛЕВА - кнопки навигации
$output .= '<div class="d-flex align-items-center">';
$output .= '<button id="submit-answer-btn" class="ts-btn ts-btn-primary" disabled>Ответить</button>';
$output .= '<button id="next-question-btn" class="ts-btn ts-btn-success" style="display:none;">Следующий вопрос</button>';
$output .= '</div>';
// СПРАВА - кнопка "К началу"
$output .= '<div class="d-flex align-items-center  gap-2">';
$output .= '<button id="restart-test-btn" class="ts-btn ts-btn-secondary ts-btn-sm" style="display: none;">';
$output .= '<i class="bi bi-arrow-counterclockwise"></i> Начать сначала';
$output .= '</button>';
$output .= '</div>';
$output .= '</div>';


// СТРОКА 2: Контейнер для кнопок редактирования (заполняется JS)
$output .= '<div id="edit-buttons-row" class="border-top pt-2" style="display: none;"></div>';

$output .= '</div></div></div>';

// Контейнер для результатов
$output .= '<div id="results-container" style="display:none;">';
$output .= '<div class="card">';
$output .= '<div class="card-header"><h2>Результаты теста</h2></div>';
$output .= '<div class="card-body text-center">';
$output .= '<h3 id="final-score" class="mb-3"></h3>';
$output .= '<p id="result-message" class="lead"></p>';
$output .= '<div id="result-details" class="mt-3"></div>';

$retryUrl = $modx->makeUrl($modx->resource->get('id'), 'web', '', 'full');
$output .= '<a href="' . htmlspecialchars($retryUrl, ENT_QUOTES, 'UTF-8') . '" class="ts-btn ts-btn-primary mt-3 me-2">Пройти еще раз</a>';

// Страница со списком тестов - жёсткий URL /tests (id 35)
$output .= '<a href="/tests" class="ts-btn ts-btn-secondary mt-3">К списку тестов</a>';

$output .= '</div></div></div>';
$output .= '</div>';

// Подключение ресурсов
$assetsUrl = $modx->getOption('assets_url', null, MODX_ASSETS_URL);
$assetsUrl = rtrim($assetsUrl, '/') . '/';

$categoriesAndTestsCssPath = $assetsUrl . 'components/testsystem/css/categories-and-tests.css';
$modx->regClientCSS($categoriesAndTestsCssPath);

$jsPath = $assetsUrl . 'components/testsystem/js/tsrunner.js';

// ПРИМЕЧАНИЕ: CSRF токен и CSS уже подключены в tsHead.tpl
// ПРИМЕЧАНИЕ: Bootstrap JS подключен в tsScripts.tpl

// XSS Protection: Подключаем DOMPurify для санитизации HTML
$output .= '<script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>';

$output .= '<script src="' . htmlspecialchars($jsPath, ENT_QUOTES, 'UTF-8') . '"></script>';

// Подключение Quill для редактирования вопросов
$output .= '<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">';
$output .= '<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>';

// Инициализация Bootstrap tooltips
$output .= '<script>
document.addEventListener("DOMContentLoaded", function() {
    // Инициализация tooltips для Bootstrap 5
    var tooltipTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="tooltip"]\'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>';

// Временный inline JS для проверки на Android
//$output .= '<script>
//(function() {
//    console.log("INLINE RESTART HANDLER LOADED");
//    
//    document.addEventListener("click", function(e) {
//        const target = e.target;
//        const restartBtn = target.closest("#restart-test-btn");
//        
//        if (restartBtn && window.getComputedStyle(restartBtn).display !== "none") {
//           e.preventDefault();
//            e.stopPropagation();
//            
//            console.log("RESTART BUTTON CLICKED!");
//            
//            if (confirm("Вы уверены, что хотите начать тест сначала?\\n\\nВесь прогресс будет потерян.")) {
//                console.log("CONFIRMED - RELOADING");
//                window.location.reload();
//            } else {
//                console.log("CANCELLED");
//            }
//            
//            return false;
//        }
//    }, true);
//    
//    console.log("RESTART HANDLER REGISTERED");
//})();
//</script>';

// Автозапуск теста при наличии sessionId
if ($skipModeSelection && isset($sessionData)) {
    $output .= '<script>
    // Данные существующей сессии
    window.existingSession = ' . json_encode($sessionData) . ';

    // Автоматический запуск теста после загрузки страницы
    document.addEventListener("DOMContentLoaded", function() {
        console.log("Auto-starting test with existing session:", window.existingSession);

        // Скрываем информацию о тесте
        const testInfo = document.getElementById("test-info");
        if (testInfo) {
            testInfo.style.display = "none";
        }

        // Показываем контейнер с вопросами
        const questionContainer = document.getElementById("question-container");
        if (questionContainer) {
            questionContainer.style.display = "block";
        }

        // Запускаем тест через существующую функцию из tsrunner.js
        if (typeof window.continueExistingSession === "function") {
            window.continueExistingSession(window.existingSession.session_id, window.existingSession.mode);
        } else {
            console.error("continueExistingSession function not found in tsrunner.js");
        }
    });
    </script>';
}

return $output;
