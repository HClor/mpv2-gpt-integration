<?php
/**
 * getTestInfoBatch - получает информацию о тестах одним запросом
 * Поддерживает pdoPage для пагинации
 * Учитывает права доступа и роли пользователей
 *
 * @version 2.0
 */
if (!$modx instanceof modX) {
    return '';
}

// Подключаем bootstrap для доступа к TestPermissionHelper
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

$parents = $scriptProperties['parents'] ?? $modx->resource->id;
$depth = (int)($scriptProperties['depth'] ?? 3);
$limit = (int)($scriptProperties['limit'] ?? 10);
$offset = (int)($scriptProperties['offset'] ?? 0);
$tpl = $scriptProperties['tpl'] ?? 'testCard';
$sortby = $scriptProperties['sortby'] ?? 'createdon';
$sortdir = strtoupper($scriptProperties['sortdir'] ?? 'DESC');
$totalVar = $scriptProperties['totalVar'] ?? 'total';
$showAllStatuses = (int)($scriptProperties['showAllStatuses'] ?? 0);

// Валидация sortdir
if (!in_array($sortdir, ['ASC', 'DESC'])) {
    $sortdir = 'DESC';
}

// Валидация sortby
$allowedSortFields = ['createdon', 'publishedon', 'pagetitle', 'menuindex', 'id'];
if (!in_array($sortby, $allowedSortFields)) {
    $sortby = 'createdon';
}

$prefix = $modx->getOption('table_prefix');
$Ttests = $prefix . 'test_tests';
$Tquestions = $prefix . 'test_questions';
$S = $prefix . 'site_content';

// Получаем все дочерние ID с учетом depth
// Используем прямой SQL запрос вместо getChildIds() для надежности
$parentIds = [$parents];

if ($depth > 0) {
    // Рекурсивный поиск дочерних страниц через SQL
    $tempParents = [$parents];

    for ($level = 1; $level <= $depth; $level++) {
        if (empty($tempParents)) break;

        $tempParentsStr = implode(',', $tempParents);
        $stmt = $modx->query("
            SELECT id
            FROM `{$S}`
            WHERE parent IN ({$tempParentsStr})
                AND published = 1
                AND deleted = 0
        ");

        $levelIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($levelIds)) {
            $parentIds = array_merge($parentIds, $levelIds);
            $tempParents = $levelIds; // Для следующего уровня
        } else {
            break;
        }
    }

    // Убираем дубликаты
    $parentIds = array_unique($parentIds);
}

$parentIdsStr = implode(',', $parentIds);

// Определяем фильтр по publication_status в зависимости от роли пользователя
// Проверяем авторизацию во фронтенде (контекст 'web')
$isAuthenticatedInFrontend = $modx->user->isAuthenticated('web');
$userId = $isAuthenticatedInFrontend ? $modx->user->id : 0;
$isAdminOrExpert = $userId > 0 ? TestPermissionHelper::isAdminOrExpert($modx, $userId) : false;

// Админы и эксперты видят все статусы (если showAllStatuses=1)
// Обычные пользователи видят только public тесты
if ($showAllStatuses && $isAdminOrExpert) {
    // Показываем все статусы
    $sql_where_publication = "";
} else {
    // Только public для обычных пользователей
    $sql_where_publication = "AND t.publication_status = 'public'";
}

// Получаем общее количество для пагинации
$sqlCount = "
    SELECT COUNT(DISTINCT sc.id) as total
    FROM `{$S}` sc
    INNER JOIN `{$Ttests}` t ON t.resource_id = sc.id
    WHERE sc.id IN ({$parentIdsStr})
        AND sc.published = 1
        AND sc.deleted = 0
        AND t.is_active = 1
        {$sql_where_publication}
";

$stmtCount = $modx->prepare($sqlCount);
$stmtCount->execute();
$totalCount = (int)$stmtCount->fetchColumn();

// Устанавливаем переменную для pdoPage
$modx->setPlaceholder($totalVar, $totalCount);

// Основной запрос с данными
$sql = "
    SELECT
        sc.id,
        sc.pagetitle,
        sc.longtitle,
        sc.introtext,
        sc.content,
        sc.uri,
        sc.createdon,
        sc.publishedon,
        t.id as test_id,
        t.questions_per_session,
        t.pass_score,
        t.mode,
        t.publication_status,
        t.created_by,
        t.updated_at,
        COUNT(q.id) as question_count
    FROM `{$S}` sc
    INNER JOIN `{$Ttests}` t ON t.resource_id = sc.id
    LEFT JOIN `{$Tquestions}` q ON q.test_id = t.id
    WHERE sc.id IN ({$parentIdsStr})
        AND sc.published = 1
        AND sc.deleted = 0
        AND t.is_active = 1
        {$sql_where_publication}
    GROUP BY sc.id, t.id
    ORDER BY sc.{$sortby} {$sortdir}
    LIMIT {$limit} OFFSET {$offset}
";

$stmt = $modx->prepare($sql);
$stmt->execute();
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tests)) {
    return '<div class="alert alert-info">Тесты не найдены</div>';
}

$output = [];
$idx = $offset + 1;

foreach ($tests as $test) {
    // Формируем URL
    $testUrl = $modx->makeUrl((int)$test['id']);
    
    // Форматируем дату
    $createdDate = $test['createdon'] ? date('d.m.Y', $test['createdon']) : '';
    $publishedDate = $test['publishedon'] ? date('d.m.Y', $test['publishedon']) : '';
    $updatedDate = $test['updated_at'] ? date('d.m.Y H:i', strtotime($test['updated_at'])) : '';

    // Проверяем права доступа текущего пользователя
    $testId = (int)$test['test_id'];
    $publicationStatus = $test['publication_status'];

    $canView = TestPermissionHelper::canView($modx, $testId, $publicationStatus, $userId);
    $canEdit = TestPermissionHelper::canEdit($modx, $testId, $publicationStatus, $userId);
    $canManageAccess = TestPermissionHelper::canManageAccess($modx, $testId, $userId);
    $canChangeStatus = TestPermissionHelper::canChangeStatus($modx, $testId, $userId);
    $userRole = TestPermissionHelper::getUserRole($modx, $testId, $userId) ?? 'none';
    $isCreator = TestPermissionHelper::isTestCreator($modx, $testId, $userId);

    // Формируем HTML для бейджа статуса
    $statusBadge = '';
    if ($publicationStatus === 'draft' && $isAdminOrExpert) {
        $statusBadge = '<span class="badge bg-warning text-dark" title="Черновик - только для админов и экспертов">
            <i class="bi bi-pencil-fill"></i> Черновик
        </span>';
    } elseif ($publicationStatus === 'private') {
        $statusBadge = '<span class="badge bg-secondary" title="Приватный - доступ только по приглашению">
            <i class="bi bi-lock-fill"></i> Приватный
        </span>';
    } elseif ($publicationStatus === 'unlisted' && $isAdminOrExpert) {
        $statusBadge = '<span class="badge bg-info" title="По ссылке - доступен всем, но не виден в списках">
            <i class="bi bi-link-45deg"></i> По ссылке
        </span>';
    }

    // Формируем HTML для dropdown управления
    $managementDropdown = '';
    if ($canEdit || $canManageAccess) {
        $dropdownItems = '';

        if ($canEdit) {
            $dropdownItems .= '<li>
                <a class="dropdown-item" href="' . htmlspecialchars($testUrl, ENT_QUOTES, 'UTF-8') . '?action=edit">
                    <i class="bi bi-pencil"></i> Редактировать
                </a>
            </li>';
        }

        if ($canManageAccess) {
            $dropdownItems .= '<li>
                <button class="dropdown-item" onclick="openAccessManagementModal(' . $testId . ')">
                    <i class="bi bi-people"></i> Управление доступом
                </button>
            </li>';
        }

        if ($canChangeStatus) {
            $dropdownItems .= '<li>
                <button class="dropdown-item" onclick="openPublicationModal(' . $testId . ', \'' . htmlspecialchars($publicationStatus, ENT_QUOTES, 'UTF-8') . '\')">
                    <i class="bi bi-globe"></i> Изменить статус
                </button>
            </li>';
        }

        $managementDropdown = '<div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-gear"></i> Управление
            </button>
            <ul class="dropdown-menu dropdown-menu-end">' . $dropdownItems . '</ul>
        </div>';
    }

    // Подготавливаем данные для чанка
    $placeholders = [
        'id' => $test['id'],
        'test_id' => $testId,
        'pagetitle' => $test['pagetitle'],
        'longtitle' => $test['longtitle'],
        'introtext' => $test['introtext'],
        'content' => $test['content'],
        'uri' => $test['uri'],
        'url' => $testUrl,
        'createdon' => $createdDate,
        'publishedon' => $publishedDate,
        'updatedon' => $updatedDate,
        'testQuestions' => (int)$test['question_count'],
        'testQuestionsPerSession' => (int)$test['questions_per_session'],
        'testPassScore' => (int)$test['pass_score'],
        'testMode' => $test['mode'],
        'publication_status' => $publicationStatus,
        'created_by' => (int)$test['created_by'],
        'idx' => $idx++,
        'total' => $totalCount,
        // Права доступа
        'canView' => $canView ? 1 : 0,
        'canEdit' => $canEdit ? 1 : 0,
        'canManageAccess' => $canManageAccess ? 1 : 0,
        'canChangeStatus' => $canChangeStatus ? 1 : 0,
        'userRole' => $userRole,
        'isCreator' => $isCreator ? 1 : 0,
        'isAdminOrExpert' => $isAdminOrExpert ? 1 : 0,
        // Готовые HTML фрагменты
        'statusBadge' => $statusBadge,
        'managementDropdown' => $managementDropdown
    ];
    
    $output[] = $modx->getChunk($tpl, $placeholders);
}

// Подключение ресурсов для модального окна управления доступом
$assetsUrl = $modx->getOption('assets_url', null, MODX_ASSETS_URL);
$assetsUrl = rtrim($assetsUrl, '/') . '/';

$cssPath = $assetsUrl . 'components/testsystem/css/tsrunner.css';
$jsPath = $assetsUrl . 'components/testsystem/js/tsrunner.js';

// CSRF Protection: Добавляем meta тег с токеном для JavaScript
$resources = CsrfProtection::getTokenMeta();

// XSS Protection: Подключаем DOMPurify для санитизации HTML
$resources .= '<script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>';

$resources .= '<link rel="stylesheet" href="' . htmlspecialchars($cssPath, ENT_QUOTES, 'UTF-8') . '">';
$resources .= '<script src="' . htmlspecialchars($jsPath, ENT_QUOTES, 'UTF-8') . '"></script>';

// Добавляем ресурсы только один раз
if (!isset($modx->testsystem_resources_loaded)) {
    $modx->testsystem_resources_loaded = true;
    array_unshift($output, $resources);
}

return implode('', $output);