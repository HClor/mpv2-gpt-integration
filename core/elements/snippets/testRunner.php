<?php
/**
 * TS TEST RUNNER v4.2 - WITH CSV IMPORT BUTTON + CSRF PROTECTION
 * Теперь тест определяется автоматически по resource_id текущей страницы
 */

// Подключаем bootstrap для CSRF защиты
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

if (!$modx instanceof modX) {
    return '<div class="alert alert-danger">MODX context required</div>';
}

// Получаем ID текущего ресурса
$resourceId = 0;
if ($modx->resource instanceof modResource) {
    $resourceId = (int)$modx->resource->get('id');
}

if ($resourceId <= 0) {
    return '<div class="alert alert-danger">Ошибка: ресурс не определен</div>';
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
        return '<div class="alert alert-warning auth-required-alert">
            <div class="d-flex align-items-center mb-3">
                <i class="bi bi-shield-lock me-2" style="font-size: 2rem;"></i>
                <h4 class="mb-0">Требуется авторизация</h4>
            </div>
            <p class="mb-3">Для прохождения области знаний необходимо войти в систему.</p>
            <a href="' . htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-2"></i>Войти в систему</a>
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
        return '<div class="alert alert-danger">Ошибка при загрузке области знаний</div>';
    }
    
    if (!$stmt->execute([$knowledgeAreaId])) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[testRunner] Failed to execute knowledge area query: ' . print_r($stmt->errorInfo(), true));
        return '<div class="alert alert-danger">Область знаний не найдена</div>';
    }
    
    $knowledgeArea = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$knowledgeArea) {
        return '<div class="alert alert-danger">Область знаний не найдена или неактивна</div>';
    }
    
    // КРИТИЧНО: Проверяем владельца
    if ((int)$knowledgeArea['user_id'] !== $userId) {
        $modx->log(modX::LOG_LEVEL_WARN, "[testRunner] User {$userId} tried to access knowledge area {$knowledgeAreaId} owned by {$knowledgeArea['user_id']}");
        return '<div class="alert alert-danger">Доступ запрещен. Это не ваша область знаний.</div>';
    }
    
    // Парсим список тестов
    $testIds = json_decode($knowledgeArea['test_ids'], true);
    
    if (!is_array($testIds) || empty($testIds)) {
        return '<div class="alert alert-warning">В области знаний нет выбранных тестов</div>';
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
        return '<div class="alert alert-danger">Ошибка при загрузке вопросов</div>';
    }
    
    if (!$stmt->execute($testIds)) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[testRunner] Failed to count questions for knowledge area: ' . print_r($stmt->errorInfo(), true));
        return '<div class="alert alert-danger">Ошибка при загрузке вопросов</div>';
    }
    
    $totalQuestions = (int)$stmt->fetchColumn();
    
    if ($totalQuestions === 0) {
        // КОНФИГУРАЦИЯ: ID страницы управления областями
        $manageAreasPageId = 125;
        $manageAreasPageUrl = rtrim($modx->makeUrl($manageAreasPageId, 'web', []), '/');
        
        return '<div class="alert alert-warning">
            <h4>В области знаний нет вопросов</h4>
            <p>Возможно, выбранные тесты пусты или все вопросы сняты с публикации.</p>
            <a href="' . htmlspecialchars($manageAreasPageUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary mt-2">Вернуться к управлению областями</a>
        </div>';
    }
    
    $questionsPerSession = min((int)$knowledgeArea['questions_per_session'], $totalQuestions);
    
    // КОНФИГУРАЦИЯ: ID страницы управления областями
    $manageAreasPageId = 125;
    $manageAreasPageUrl = rtrim($modx->makeUrl($manageAreasPageId, 'web', []), '/');
    
    // Формируем вывод для области знаний
    $output = '<div id="test-container" data-test-id="0" data-knowledge-area-id="' . (int)$knowledgeAreaId . '" data-can-edit="0" data-test-mode="training">';
    $output .= '<div id="test-info" class="card mb-4">';
    $output .= '<div class="card-header">';
    $output .= '<div class="d-flex justify-content-between align-items-center">';
    $output .= '<h2><i class="bi bi-collection-fill text-primary"></i> ' . htmlspecialchars($knowledgeArea['name'], ENT_QUOTES, 'UTF-8') . '</h2>';
    $output .= '<a href="' . htmlspecialchars($manageAreasPageUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-outline-secondary btn-sm">';
    $output .= '<i class="bi bi-arrow-left"></i> Назад';
    $output .= '</a>';
    $output .= '</div>';
    $output .= '</div>';
    
    $output .= '<div class="card-body">';
    
    if (!empty($knowledgeArea['description'])) {
        $output .= '<p class="lead">' . nl2br(htmlspecialchars($knowledgeArea['description'], ENT_QUOTES, 'UTF-8')) . '</p>';
    }
    
    $output .= '<div class="alert alert-info">';
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
    $output .= '<button class="btn btn-primary btn-lg start-test-btn" data-mode="training">';
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
    $output .= '<span class="badge bg-primary">TRAINING</span>';
    $output .= '</div>';
    $output .= '<div class="card-body">';
    $output .= '<h4 id="question-text"></h4>';
    $output .= '<div id="question-type-hint" style="display: none;"></div>';
    $output .= '<div id="answer-options" class="mt-3"></div>';
    $output .= '<div id="explanation-block" class="alert alert-info mt-3" style="display:none;"></div>';
    $output .= '</div>';
    
    $output .= '<div class="card-footer">';
    $output .= '<div class="d-flex justify-content-between align-items-center mb-2">';
    $output .= '<div class="d-flex align-items-center">';
    $output .= '<button id="submit-answer-btn" class="btn btn-primary" disabled>Ответить</button>';
    $output .= '<button id="next-question-btn" class="btn btn-success" style="display:none;">Следующий вопрос</button>';
    $output .= '</div>';
    $output .= '<div class="d-flex align-items-center gap-2">';
    $output .= '<button id="restart-test-btn" class="btn btn-outline-secondary btn-sm" style="display: none;">';
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
    $output .= '<a href="' . htmlspecialchars($retryUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary mt-3">Пройти еще раз</a>';
    $output .= '<a href="' . htmlspecialchars($manageAreasPageUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-secondary mt-3">К списку областей</a>';
    
    $output .= '</div></div></div>';
    $output .= '</div>';
    
    // Подключение ресурсов
    $assetsUrl = $modx->getOption('assets_url', null, MODX_ASSETS_URL);
    $assetsUrl = rtrim($assetsUrl, '/') . '/';

    $cssPath = $assetsUrl . 'components/testsystem/css/tsrunner.css';
    $jsPath = $assetsUrl . 'components/testsystem/js/tsrunner.js';

    // CSRF Protection: Добавляем meta тег с токеном для JavaScript
    $output .= CsrfProtection::getTokenMeta();

    // XSS Protection: Подключаем DOMPurify для санитизации HTML
    $output .= '<script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>';

    $output .= '<link rel="stylesheet" href="' . htmlspecialchars($cssPath, ENT_QUOTES, 'UTF-8') . '">';
    $output .= '<script src="' . htmlspecialchars($jsPath, ENT_QUOTES, 'UTF-8') . '"></script>';

    // Подключение Quill (если нужно для редактирования)
    $output .= '<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">';
    $output .= '<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>';

    return $output;
}

// ============================================
// ОБЫЧНЫЙ РЕЖИМ ТЕСТА (существующий код)
// ============================================










// Ищем тест, привязанный к этому ресурсу
$sql = "SELECT `id`, `resource_id`, `title`, `description`, `mode`, `time_limit`, `pass_score`, `questions_per_session`
    FROM {$tableTests}
    WHERE `is_active` = 1 AND `resource_id` = ?
    LIMIT 1";

$stmt = $modx->prepare($sql);
if (!$stmt) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[testRunner] Failed to prepare test query');
    return '<div class="alert alert-danger">Ошибка при загрузке теста</div>';
}

if (!$stmt->execute([$resourceId])) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[testRunner] Failed to execute test query: ' . print_r($stmt->errorInfo(), true));
    return '<div class="alert alert-danger">Тест не найден или неактивен</div>';
}

$test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    // Если тест не найден, показываем дружелюбное сообщение
    $canEdit = false;
    $currentUserId = (int)$modx->user->get('id');


    
    // Проверяем права
    $roleStmt = $modx->prepare("SELECT mgn.`name` FROM {$tableMemberGroups} AS mg
        JOIN {$tableMemberGroupNames} AS mgn ON mgn.`id` = mg.`user_group`
        WHERE mg.`member` = :uid AND mgn.`name` IN ('LMS Admins', 'LMS Experts')");
    
    if ($roleStmt && $roleStmt->execute([':uid' => $currentUserId])) {
        $roleNames = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('LMS Admins', $roleNames, true) || in_array('LMS Experts', $roleNames, true)) {
            $canEdit = true;
        }
    }
    
    if ($canEdit) {
        $questionsManagerId = (int)$modx->getOption('lms.questions_manager_page', null, 0);
        $questionsUrl = $questionsManagerId > 0 ? $modx->makeUrl($questionsManagerId, 'web', [], 'full') : '#';
        
        return '<div class="alert alert-warning">
            <h4>Тест не найден</h4>
            <p>К этой странице не привязан тест. Вы можете создать тест через <a href="' . htmlspecialchars($questionsUrl, ENT_QUOTES, 'UTF-8') . '">панель управления</a>.</p>
        </div>';
    }
    
    return '<div class="alert alert-danger">Тест не найден или неактивен</div>';
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
    return '<div class="alert alert-warning"><p>Для прохождения теста необходимо <a href="' . htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8') . '">войти в систему</a>.</p></div>';
}

$userId = (int)$modx->user->get('id');

// ДОБАВЛЕНО: Проверка доступа к тесту на основе publication_status
$hasAccess = false;
$publicationStatus = $testAccess['publication_status'] ?? 'public';
$createdBy = (int)($testAccess['created_by'] ?? 0);

// Определяем права пользователя
$roleStmt = $modx->prepare("SELECT mgn.`name` FROM {$tableMemberGroups} AS mg
    JOIN {$tableMemberGroupNames} AS mgn ON mgn.`id` = mg.`user_group`
    WHERE mg.`member` = :uid AND mgn.`name` IN ('LMS Admins', 'LMS Experts')");

$isAdminOrExpert = false;
if ($roleStmt && $roleStmt->execute([':uid' => $userId])) {
    $roleNames = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('LMS Admins', $roleNames, true) || in_array('LMS Experts', $roleNames, true)) {
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
    return '<div class="alert alert-danger">
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
    return '<div class="alert alert-danger">Ошибка при загрузке вопросов теста</div>';
}

if (!$stmt->execute([$testId])) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[testRunner] Failed to count questions for test ' . $testId . ': ' . print_r($stmt->errorInfo(), true));
    return '<div class="alert alert-danger">Ошибка при загрузке вопросов теста</div>';
}

$totalQuestions = (int)$stmt->fetchColumn();

// КРИТИЧЕСКАЯ ПРОВЕРКА: наличие вопросов
if ($totalQuestions === 0) {
    $modx->log(modX::LOG_LEVEL_WARN, '[testRunner] Test ' . $testId . ' has no questions');
    
    // Проверяем права на редактирование для показа ссылки на импорт
    $currentUserId = (int)$modx->user->get('id');
    $canEdit = false;
    
    $roleStmt = $modx->prepare("SELECT mgn.`name` FROM {$tableMemberGroups} AS mg
        JOIN {$tableMemberGroupNames} AS mgn ON mgn.`id` = mg.`user_group`
        WHERE mg.`member` = :uid AND mgn.`name` IN ('LMS Admins', 'LMS Experts')");
    
    if ($roleStmt && $roleStmt->execute([':uid' => $currentUserId])) {
        $roleNames = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('LMS Admins', $roleNames, true) || in_array('LMS Experts', $roleNames, true)) {
            $canEdit = true;
        }
    }
    
    if ($canEdit) {
        // Показываем кнопку импорта CSV
        $importPageId = 29; // ID страницы импорта CSV
        $importUrl = $modx->makeUrl($importPageId, 'web', ['test_id' => $testId], 'full');
        
        if (!empty($importUrl)) {
            return '<div class="alert alert-warning">
                <h4>В тесте нет вопросов</h4>
                <p>Добавьте вопросы через импорт CSV файла.</p>
                <a href="' . htmlspecialchars($importUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-success mt-2">📤 Импортировать вопросы из CSV</a>
            </div>';
        }
        
        $questionsManagerId = (int)$modx->getOption('lms.questions_manager_page', null, 0);
        if ($questionsManagerId > 0) {
            $questionsUrl = $modx->makeUrl($questionsManagerId, 'web', ['test' => $testId], 'full');
            return '<div class="alert alert-warning">
                <h4>В тесте нет вопросов</h4>
                <p>Добавьте вопросы через <a href="' . htmlspecialchars($questionsUrl, ENT_QUOTES, 'UTF-8') . '" class="alert-link">панель управления вопросами</a>.</p>
            </div>';
        }
    }
    
    return '<div class="alert alert-danger">В тесте отсутствуют вопросы. Пожалуйста, обратитесь к администратору.</div>';
}

// Валидация questions_per_session
$questionsPerSession = (int)($test['questions_per_session'] ?? 0);
if ($questionsPerSession <= 0 || $questionsPerSession > $totalQuestions) {
    $questionsPerSession = $totalQuestions;
}

// Определение прав на редактирование
// ИСПРАВЛЕНО: используем уже определенные переменные из проверки доступа выше
$canEditTest = $isAdminOrExpert || ($createdBy === $userId);

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



// Формирование ссылок на редактирование - АДАПТИВНЫЕ
$managerUrl = defined('MODX_MANAGER_URL') ? MODX_MANAGER_URL : $modx->getOption('manager_url', null, '/manager/');
$managerUrl = rtrim($managerUrl, '/') . '/';

$editLinks = '';
if ($canEditTest) {
    $editLinks .= '<div class="alert alert-light border">';
    $editLinks .= '<div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2">';
    $editLinks .= '<span class="text-muted small"><strong>Режим редактирования</strong></span>';
    
    // ДЕСКТОП
    $editLinks .= '<div class="btn-group btn-group-sm d-none d-md-flex" role="group">';
    
    // Редактировать страницу
    $resourceEditUrl = $managerUrl . '?a=resource/update&id=' . (int)$resourceId;
    $editLinks .= '<a class="btn btn-outline-primary" href="' . htmlspecialchars($resourceEditUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener"><i class="bi bi-pencil"></i> Страница</a>';
    
    // Импорт CSV
    $importPageId = 29;
    $importUrl = $modx->makeUrl($importPageId, 'web', ['test_id' => $testId], 'full');
    if (!empty($importUrl)) {
        $editLinks .= '<a class="btn btn-outline-success" href="' . htmlspecialchars($importUrl, ENT_QUOTES, 'UTF-8') . '"><i class="bi bi-upload"></i> CSV</a>';
    }

    $editLinks .= '<button class="btn btn-outline-info" type="button" onclick="showAllQuestionsView()"><i class="bi bi-list-ul"></i> Список</button>';
    $editLinks .= '<button class="btn btn-outline-warning" type="button" onclick="openTestSettingsModal(' . (int)$testId . ')"><i class="bi bi-gear"></i> Настройки</button>';
    
    // ДОБАВИТЬ: Кнопка управления доступом для приватных тестов
    if ($publicationStatus === 'private') {
        $editLinks .= '<button class="btn btn-outline-secondary" type="button" onclick="openAccessManagementModal(' . (int)$testId . ')"><i class="bi bi-people"></i> Доступ</button>';
    }
    
    // ДОБАВИТЬ: Кнопка миграции для админов
    if ($isAdminOrExpert && $publicationStatus !== 'public') {
        $editLinks .= '<button class="btn btn-outline-primary" type="button" onclick="openPublicationModal(' . (int)$testId . ', \'' . $publicationStatus . '\')"><i class="bi bi-globe"></i> Публикация</button>';
    }
    
    $editLinks .= '</div>';
    
    // МОБИЛЬНАЯ версия (вертикальные кнопки) - аналогично
    $editLinks .= '<div class="btn-group-vertical btn-group-sm d-md-none" role="group">';
    // ... те же кнопки ...
    $editLinks .= '</div>';
    
    $editLinks .= '</div></div>';
}

// Валидация доступных режимов
// ВАЖНО: Всегда показываем оба режима для выбора пользователя
$testMode = 'both';

$passScore = (int)$test['pass_score'];
$timeLimit = (int)$test['time_limit'];

// Формирование HTML вывода
$output = '<div id="test-container" data-test-id="' . (int)$testId . '" data-can-edit="' . ($canEditTest ? '1' : '0') . '" data-test-mode="' . htmlspecialchars($testMode, ENT_QUOTES, 'UTF-8') . '">';
$output .= '<div id="test-info" class="card mb-4">';

// Заголовок с бейджем статуса
$output .= '<div class="card-header">';
$output .= '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">';
$output .= '<h2 class="mb-0">' . htmlspecialchars($test['title'], ENT_QUOTES, 'UTF-8') . '</h2>';

// Показываем статус только для draft, private и unlisted (public не показываем - это норма)
$showStatus = false;
$statusLabel = '';
$statusClass = '';
$statusIcon = 'bi-lock-fill';

if ($publicationStatus === 'draft' && $isAdminOrExpert) {
    $showStatus = true;
    $statusLabel = 'Черновик';
    $statusClass = 'bg-warning text-dark';
    $statusIcon = 'bi-pencil-fill';
} elseif ($publicationStatus === 'private') {
    $showStatus = true;
    $statusLabel = 'Приватный';
    $statusClass = 'bg-secondary';
    $statusIcon = 'bi-lock-fill';
} elseif ($publicationStatus === 'unlisted' && $isAdminOrExpert) {
    $showStatus = true;
    $statusLabel = 'По ссылке';
    $statusClass = 'bg-info';
    $statusIcon = 'bi-link-45deg';
}

if ($showStatus) {
    $output .= '<span class="badge ' . $statusClass . ' fs-6"><i class="' . $statusIcon . '"></i> ' . $statusLabel . '</span>';
}

$output .= '</div>';
$output .= '</div>';
$output .= '<div class="card-body">';
$output .= '<p>' . nl2br(htmlspecialchars($test['description'], ENT_QUOTES, 'UTF-8')) . '</p>';
$output .= '<ul class="list-unstyled">';
$output .= '<li><strong>Вопросов в банке:</strong> ' . (int)$totalQuestions . '</li>';
$output .= '<li><strong>Вопросов за попытку:</strong> ' . (int)$questionsPerSession . '</li>';
$output .= '<li><strong>Проходной балл:</strong> ' . (int)$passScore . '%</li>';
if ($timeLimit > 0) {
    $output .= '<li><strong>Время:</strong> ' . (int)$timeLimit . ' минут</li>';
}
$output .= '</ul>';

// Интерфейс выбора режима
if ($testMode === 'both') {
    $output .= '<hr>';
    $output .= '<div class="test-mode-selector">';
    $output .= '<h5 class="mb-3 text-center">Выберите режим прохождения:</h5>';
    
    $output .= '<div class="mode-toggle-wrapper">';
    $output .= '<div class="mode-info-cards mb-3">';
    $output .= '<div class="row g-2">';
    
    // Карточка Training - КОМПАКТНАЯ
    $output .= '<div class="col-md-6">';
    $output .= '<div class="mode-card mode-card-compact" id="training-card">';
    $output .= '<div class="d-flex align-items-center gap-2">';
    $output .= '<div class="mode-card-icon-small">📚</div>';
    $output .= '<div class="flex-grow-1">';
    $output .= '<h6 class="mode-card-title-compact mb-1">Обучение (Training)</h6>';
    $output .= '<small class="text-muted d-block">С объяснениями и подсказками</small>';
    $output .= '</div>';
    $output .= '</div>';
    
    // Контролы выбора количества вопросов - ВАЖНО: оставляем старый класс!
    $output .= '<div class="training-questions-control mt-2">';
    $output .= '<div class="input-group input-group-sm">';
    $output .= '<input type="number" class="form-control form-control-sm" id="training-questions-count" min="1" max="' . (int)$totalQuestions . '" value="' . min(20, (int)$totalQuestions) . '" data-max="' . (int)$totalQuestions . '" style="max-width: 80px;">';
    $output .= '<button class="btn btn-outline-secondary btn-sm" type="button" id="training-all-questions" data-total="' . (int)$totalQuestions . '">Все</button>';
    $output .= '</div>';
    $output .= '<small class="form-text text-muted d-block mt-1">Макс: ' . (int)$totalQuestions . '</small>';
    $output .= '</div>';
    
    $output .= '</div></div>';    

    
    // Карточка Exam - КОМПАКТНАЯ
    $output .= '<div class="col-md-6">';
    $output .= '<div class="mode-card mode-card-compact" id="exam-card">';
    $output .= '<div class="d-flex align-items-center gap-2">';
    $output .= '<div class="mode-card-icon-small">🎓</div>';
    $output .= '<div class="flex-grow-1">';
    $output .= '<h6 class="mode-card-title-compact mb-1">Экзамен (Exam)</h6>';
    $output .= '<small class="text-muted d-block">Строгая проверка знаний</small>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div></div>';
    
    $output .= '</div></div>';

    // Переключатель режима - ВАЖНО: оставляем старый класс mode-switch-container!
    $output .= '<div class="mode-switch-container mode-switch-container-compact text-center mb-3">';
    $output .= '<div class="mode-labels">';
    $output .= '<span class="mode-label mode-label-training active">Training</span>';
    $output .= '<label class="mode-switch">';
    $output .= '<input type="checkbox" id="mode-toggle" value="exam">';
    $output .= '<span class="mode-slider"></span>';
    $output .= '</label>';
    $output .= '<span class="mode-label mode-label-exam">Exam</span>';
    $output .= '</div>';
    $output .= '</div>';
    
    $output .= '</div>';
    
    // Единая кнопка старта
    $output .= '<div class="text-center mb-3">';
    $output .= '<button class="btn btn-primary start-test-btn start-test-btn-compact" id="start-test-unified" data-mode="training">';
    $output .= '<span id="start-btn-text">Начать Training</span>';
    $output .= '</button>';
    $output .= '</div>';
    
    $output .= '</div>';

} elseif ($testMode === 'training') {
    // Только режим Training
    $output .= '<hr>';
    $output .= '<div class="alert alert-info">';
    $output .= '<strong>Режим:</strong> Обучение (Training) - будут показаны правильные ответы и объяснения';
    $output .= '</div>';
    $output .= '<button class="btn btn-primary btn-lg w-100 start-test-btn" data-mode="training">Начать тест</button>';
} elseif ($testMode === 'exam') {
    // Только режим Exam
    $output .= '<hr>';
    $output .= '<div class="alert alert-warning">';
    $output .= '<strong>Режим:</strong> Экзамен (Exam) - результат будет показан только в конце';
    $output .= '</div>';
    $output .= '<button class="btn btn-danger btn-lg w-100 start-test-btn" data-mode="exam">Начать тест</button>';
}

if ($editLinks !== '') {
    $output .= '<hr>';
    $output .= $editLinks;
}

$output .= '</div></div>';

// Контейнер для вопросов
$output .= '<div id="question-container" style="display:none;">';

// ПРОГРЕСС-БАР
$output .= '<div class="progress mb-3" style="height: 8px; border-radius: 10px;">';
$output .= '<div id="test-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>';
$output .= '</div>';

$output .= '<div class="card mb-3">';
$output .= '<div class="card-header d-flex justify-content-between align-items-center">';
$output .= '<span id="question-progress">Вопрос <span id="current-q">1</span> из <span id="total-q">' . (int)$questionsPerSession . '</span></span>';
$output .= '<span id="mode-badge" class="badge"></span>';
$output .= '</div>';
$output .= '<div class="card-body">';
$output .= '<h4 id="question-text"></h4>';
$output .= '<div id="question-type-hint" style="display: none;"></div>';
$output .= '<div id="answer-options" class="mt-3"></div>';
$output .= '<div id="explanation-block" class="alert alert-info mt-3" style="display:none;"></div>';
$output .= '</div>';

// ИСПРАВЛЕННЫЙ БЛОК С КНОПКАМИ:

// СТРОКА 1: Навигационные кнопки
$output .= '<div class="card-footer">';
$output .= '<div class="d-flex justify-content-between align-items-center mb-2">';
// СЛЕВА - кнопки навигации
$output .= '<div class="d-flex align-items-center">';
$output .= '<button id="submit-answer-btn" class="btn btn-primary" disabled>Ответить</button>';
$output .= '<button id="next-question-btn" class="btn btn-success" style="display:none;">Следующий вопрос</button>';
$output .= '</div>';
// СПРАВА - кнопка "К началу"
$output .= '<div class="d-flex align-items-center  gap-2">';
$output .= '<button id="restart-test-btn" class="btn btn-outline-secondary btn-sm" style="display: none;">';
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
$output .= '<a href="' . htmlspecialchars($retryUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary mt-3">Пройти еще раз</a>';

$testPageId = (int)$modx->getOption('lms.test_page', null, 0);
$testPageUrl = $testPageId > 0 ? $modx->makeUrl($testPageId, 'web', '', 'full') : $retryUrl;
$output .= '<a href="' . htmlspecialchars($testPageUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-secondary mt-3">К списку тестов</a>';

$output .= '</div></div></div>';
$output .= '</div>';

// Подключение ресурсов
$assetsUrl = $modx->getOption('assets_url', null, MODX_ASSETS_URL);
$assetsUrl = rtrim($assetsUrl, '/') . '/';

$cssPath = $assetsUrl . 'components/testsystem/css/tsrunner.css';
$jsPath = $assetsUrl . 'components/testsystem/js/tsrunner.js';

// CSRF Protection: Добавляем meta тег с токеном для JavaScript
$output .= CsrfProtection::getTokenMeta();

// XSS Protection: Подключаем DOMPurify для санитизации HTML
$output .= '<script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>';

$output .= '<link rel="stylesheet" href="' . htmlspecialchars($cssPath, ENT_QUOTES, 'UTF-8') . '">';
$output .= '<script src="' . htmlspecialchars($jsPath, ENT_QUOTES, 'UTF-8') . '"></script>';

// Подключение Quill
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

return $output;