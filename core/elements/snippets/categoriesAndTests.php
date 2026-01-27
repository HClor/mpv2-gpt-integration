<?php
/**
 * Сниппет: categoriesAndTests - Категории и тесты
 * Вызывается из: MODX ресурсов (главная страница тестов)
 * Назначение: Отображает дерево категорий и доступные тесты
 *
 * @package TestSystem
 * @version 2.0
 */

// Подключаем bootstrap
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

if (!$modx instanceof modX) {
    return '';
}

/**
 * Функция отрисовки карточки теста
 */
function renderTestCard($test, $modx, $testPageId, $currentUserId, $isAdmin, $isExpert) {
    $questionCount = (int)$test['question_count'];
    $questionsPerSession = (int)$test['questions_per_session'];
    $passScore = (int)$test['pass_score'];
    $testId = (int)$test['id'];
    $isOwner = ($test['created_by'] == $currentUserId);

    // Права доступа
    $canManage = $isAdmin || $isExpert || $isOwner;
    $canDelete = $isAdmin || $isOwner;

    $output = '<div class="test-card" data-test-id="' . $testId . '">';

    // Заголовок с меню
    $output .= '<div class="test-card-header">';
    $output .= '<h3 class="test-title">' . htmlspecialchars($test['title'], ENT_QUOTES, 'UTF-8') . '</h3>';

    if ($canManage) {
        $output .= '<div class="test-menu-toggle" data-test-id="' . $testId . '">⋮</div>';
        $output .= '<div class="test-menu-dropdown" id="menu-' . $testId . '" style="display: none;">';

        // Импорт CSV - используем Config
        $importPageId = Config::getPageId('import_csv', 29);
        if ($importPageId > 0) {
            $importUrl = htmlspecialchars($modx->makeUrl($importPageId, '', array('test_id' => $testId)), ENT_QUOTES, 'UTF-8');
            $output .= '<a href="' . $importUrl . '" class="menu-item">';
            $output .= '<i class="bi bi-file-earmark-arrow-down"></i> Импорт вопросов';
            $output .= '</a>';
        }

        // Вопросы - открывает список вопросов напрямую
        $questionsUrl = htmlspecialchars($modx->makeUrl($testPageId, '', array('testId' => $testId, 'view' => 'questions')), ENT_QUOTES, 'UTF-8');
        $output .= '<a href="' . $questionsUrl . '" class="menu-item">';
        $output .= '<i class="bi bi-question-circle"></i> Вопросы';
        $output .= '</a>';

        // Управление - открывает модальное окно управления
        $manageUrl = htmlspecialchars($modx->makeUrl($testPageId, '', array('testId' => $testId, 'view' => 'manage')), ENT_QUOTES, 'UTF-8');
        $output .= '<a href="' . $manageUrl . '" class="menu-item">';
        $output .= '<i class="bi bi-gear"></i> Управление';
        $output .= '</a>';

        // Удалить - JavaScript API вызов
        if ($canDelete) {
            $output .= '<a href="#" class="menu-item menu-item-danger" data-action="delete" data-test-id="' . $testId . '">';
            $output .= '<i class="bi bi-trash"></i> Удалить тест';
            $output .= '</a>';
        }

        $output .= '</div>';
    }

    $output .= '</div>';

    // Описание
    $description = !empty($test['description']) ? $test['description'] : 'Нет описания';
    $output .= '<p class="test-description">' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>';

    // Метаинформация
    $output .= '<div class="test-meta">';
    $output .= '<span class="meta-item">';
    $output .= '<span class="meta-icon">📊</span>';
    $output .= '<span class="meta-label">Вопросов в банке:</span>';
    $output .= '<span class="meta-value">' . $questionCount . '</span>';
    $output .= '</span>';
    $output .= '<span class="meta-item">';
    $output .= '<span class="meta-icon">📝</span>';
    $output .= '<span class="meta-label">Вопросов за попытку:</span>';
    $output .= '<span class="meta-value">' . $questionsPerSession . '</span>';
    $output .= '</span>';
    $output .= '<span class="meta-item">';
    $output .= '<span class="meta-icon">✅</span>';
    $output .= '<span class="meta-label">Проходной балл:</span>';
    $output .= '<span class="meta-value">' . $passScore . '%</span>';
    $output .= '</span>';
    $output .= '</div>';

    $output .= '<hr class="test-divider">';

    // Контролы запуска (для Тренировки)
    $output .= '<div class="test-training-controls">';
    $output .= '<label for="questions-count-' . $testId . '">Количество вопросов:</label>';
    $output .= '<div class="questions-input-group">';
    $output .= '<input type="number" id="questions-count-' . $testId . '" class="questions-count-input" ';
    $output .= 'min="1" max="' . $questionCount . '" value="' . min(20, $questionCount) . '" data-test-id="' . $testId . '">';
    $output .= '<button class="btn-all-questions" data-test-id="' . $testId . '" data-max="' . $questionCount . '">Все</button>';
    $output .= '</div>';
    $output .= '</div>';

    // Кнопки запуска
    $output .= '<div class="test-action-buttons">';
    $output .= '<button class="btn-start-training btn-large" data-test-id="' . $testId . '">';
    $output .= '<span class="btn-icon">🎓</span>';
    $output .= '<span class="btn-text">Тренировка</span>';
    $output .= '</button>';
    $output .= '<button class="btn-start-exam btn-large" data-test-id="' . $testId . '">';
    $output .= '<span class="btn-icon">🎯</span>';
    $output .= '<span class="btn-text">Экзамен</span>';
    $output .= '</button>';
    $output .= '</div>';

    $output .= '</div>';

    return $output;
}

/**
 * Функция формирования URL для пагинации
 */
function buildPaginationUrl($modx, $categoryId, $searchQuery, $page) {
    $params = array();
    if ($categoryId > 0) {
        $params['category'] = $categoryId;
    }
    if ($searchQuery) {
        $params['search'] = $searchQuery;
    }
    if ($page > 1) {
        $params['page'] = $page;
    }

    $url = $modx->makeUrl($modx->resource->id, '', $params);
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

$prefix = $modx->getOption('table_prefix');
$Tcats = $prefix . 'test_categories';
$Ttests = $prefix . 'test_tests';
$Tquestions = $prefix . 'test_questions';

$categoryId = (int)($modx->stripTags(isset($_GET['category']) ? $_GET['category'] : 0));
$searchQuery = trim($modx->stripTags(isset($_GET['search']) ? $_GET['search'] : ''));
$page = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
$perPage = 20;

// Получаем ID страницы тестов из настроек
$testPageId = (int)$modx->getOption('lms.test_page', null, 155);

// Получаем права пользователя для кнопок управления
$currentUserId = $modx->user->get('id');
$userGroups = array_keys($modx->user->getUserGroups());

// Проверяем роли - используем простые имена классов (см. bootstrap.php)
$groupAdmins = Config::getGroup('admins');
$groupExperts = Config::getGroup('experts');
$isAdmin = $modx->user->isMember($groupAdmins);
$isExpert = $modx->user->isMember($groupExperts);

$output = '<div class="container-fluid categories-tests-container">';
$output .= '<div class="row">';

// Left column: categories list
$output .= '<div class="col-md-4 col-lg-3 categories-sidebar">';
$output .= '<div class="card mb-3">';
$output .= '<div class="card-header bg-primary text-white">';
$output .= '<h5 class="mb-0"><i class="bi bi-folder-fill"></i> Категории</h5>';
$output .= '</div>';
$output .= '<div class="list-group list-group-flush">';

// Добавляем псевдо-категорию "Все тесты"
$allTestsActive = ($categoryId == 0) ? ' active' : '';
$allTestsUrl = htmlspecialchars($modx->makeUrl($modx->resource->id), ENT_QUOTES, 'UTF-8');
$output .= '<a class="list-group-item list-group-item-action' . $allTestsActive . '" href="' . $allTestsUrl . '">';
$output .= '<div class="d-flex w-100 justify-content-between align-items-center">';
$output .= '<span><i class="bi bi-grid-fill me-2"></i><strong>Все тесты</strong></span>';
$output .= '</div>';
$output .= '</a>';

// Кеширование списка категорий
$cacheKey = 'testsystem/categories_list';
$cacheTTL = (int)$modx->getOption('testsystem.cache_ttl', null, 3600);
$categories = $modx->cacheManager->get($cacheKey);

if ($categories === null) {
    $sql = "
        SELECT
            c.id,
            c.name,
            COUNT(DISTINCT CASE
                WHEN t.publication_status = 'public' AND t.is_active = 1
                THEN t.id
            END) AS test_count
        FROM `{$Tcats}` c
        LEFT JOIN `{$Ttests}` t ON t.category_id = c.id
        GROUP BY c.id, c.name
        HAVING test_count > 0
        ORDER BY c.sort_order
    ";

    $stmt = $modx->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $modx->cacheManager->set($cacheKey, $categories, $cacheTTL);
}

foreach ($categories as $cat) {
    $isActive = ($cat['id'] == $categoryId) ? ' active' : '';
    $categoryUrl = htmlspecialchars($modx->makeUrl($modx->resource->id) . '?category=' . $cat['id'], ENT_QUOTES, 'UTF-8');

    $output .= '<a class="list-group-item list-group-item-action' . $isActive . '" href="' . $categoryUrl . '">';
    $output .= '<div class="d-flex w-100 justify-content-between align-items-center">';
    $output .= '<span><i class="bi bi-folder me-2"></i>' . htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') . '</span>';
    $output .= '<span class="badge bg-primary rounded-pill">' . (int)$cat['test_count'] . '</span>';
    $output .= '</div>';
    $output .= '</a>';
}

// Кнопка добавления теста (если есть права)
$rightsRaw = $modx->runSnippet('getUserRights');
$rights = is_array($rightsRaw) ? $rightsRaw : [];

if (!empty($rights['canCreate'])) {
    $createTestPageId = (int)$modx->getOption('lms.create_test_page', null, 0);
    if ($createTestPageId > 0) {
        $addUrl = htmlspecialchars($modx->makeUrl($createTestPageId), ENT_QUOTES, 'UTF-8');
        $output .= '<a class="list-group-item list-group-item-action text-success" href="' . $addUrl . '">';
        $output .= '<i class="bi bi-plus-circle me-2"></i><strong>Создать тест</strong>';
        $output .= '</a>';
    }
}

$output .= '</div>';
$output .= '</div>';
$output .= '</div>';

// Right column: tests list
$output .= '<div class="col-md-8 col-lg-9 tests-content">';

// Поисковая строка
$output .= '<div class="mb-4">';
$output .= '<div class="input-group">';
$output .= '<input type="text" class="form-control" id="tests-search-input" placeholder="Поиск тестов по названию..." value="' . htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') . '">';
$output .= '<button class="btn btn-primary" type="button" id="tests-search-btn"><i class="bi bi-search"></i> Найти</button>';
if ($searchQuery) {
    $clearUrl = htmlspecialchars($modx->makeUrl($modx->resource->id) . ($categoryId ? '?category=' . $categoryId : ''), ENT_QUOTES, 'UTF-8');
    $output .= '<a href="' . $clearUrl . '" class="btn btn-outline-secondary"><i class="bi bi-x"></i> Очистить</a>';
}
$output .= '</div>';
$output .= '</div>';

// Формируем SQL запрос для получения тестов
$where = "t.publication_status = 'public' AND t.is_active = 1";
$params = array();

if ($categoryId > 0) {
    $where .= " AND t.category_id = ?";
    $params[] = $categoryId;
}

if ($searchQuery) {
    $where .= " AND t.title LIKE ?";
    $params[] = '%' . $searchQuery . '%';
}

// Подсчет общего количества тестов
$countSql = "SELECT COUNT(DISTINCT t.id) as total FROM `{$Ttests}` t WHERE {$where}";
$countStmt = $modx->prepare($countSql);
$countStmt->execute($params);
$totalTests = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalTests / $perPage);
$offset = ($page - 1) * $perPage;

// Получаем тесты с пагинацией
$sql = "
    SELECT
        t.id,
        t.title,
        t.description,
        t.mode,
        t.questions_per_session,
        t.pass_score,
        t.created_by,
        c.name as category_name,
        COUNT(DISTINCT q.id) AS question_count
    FROM `{$Ttests}` t
    LEFT JOIN `{$Tquestions}` q ON q.test_id = t.id AND q.published = 1
    LEFT JOIN `{$Tcats}` c ON c.id = t.category_id
    WHERE {$where}
    GROUP BY t.id, t.title, t.description, t.mode, t.questions_per_session, t.pass_score, t.created_by, c.name
    ORDER BY c.name ASC, t.title ASC
    LIMIT {$perPage} OFFSET {$offset}
";

$stmt = $modx->prepare($sql);
$stmt->execute($params);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$tests) {
    $output .= '<div class="alert alert-secondary">';
    $output .= '<p><i class="bi bi-inbox"></i> ';
    if ($searchQuery) {
        $output .= 'По вашему запросу ничего не найдено. Попробуйте изменить поисковый запрос.';
    } elseif ($categoryId) {
        $output .= 'В этой категории пока нет тестов.';
    } else {
        $output .= 'Тестов пока нет.';
    }
    $output .= '</p>';
    $output .= '</div>';
} else {
    // Группировка тестов по категориям (только если показываем все тесты)
    if ($categoryId == 0) {
        // Группируем тесты по категориям
        $testsByCategory = array();
        foreach ($tests as $test) {
            $catName = $test['category_name'] ? $test['category_name'] : 'Без категории';
            if (!isset($testsByCategory[$catName])) {
                $testsByCategory[$catName] = array();
            }
            $testsByCategory[$catName][] = $test;
        }

        // Отображаем тесты сгруппированные по категориям
        foreach ($testsByCategory as $catName => $categoryTests) {
            $output .= '<div class="category-group mb-4">';
            $output .= '<h3 class="category-group-title"><i class="bi bi-folder-open text-primary"></i> ' . htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') . '</h3>';
            $output .= '<div class="tests-grid">';

            foreach ($categoryTests as $test) {
                $output .= renderTestCard($test, $modx, $testPageId, $currentUserId, $isAdmin, $isExpert);
            }

            $output .= '</div>';
            $output .= '</div>';
        }
    } else {
        // Показываем заголовок выбранной категории
        $stmt = $modx->prepare("SELECT name, description FROM `{$Tcats}` WHERE id = ?");
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($category) {
            $output .= '<div class="category-header mb-4">';
            $output .= '<h2><i class="bi bi-folder-open text-primary"></i> ' . htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') . '</h2>';
            if (!empty($category['description'])) {
                $output .= '<p class="text-muted">' . htmlspecialchars($category['description'], ENT_QUOTES, 'UTF-8') . '</p>';
            }
            $output .= '</div>';
        }

        // Отображаем тесты без группировки
        $output .= '<div class="tests-grid">';
        foreach ($tests as $test) {
            $output .= renderTestCard($test, $modx, $testPageId, $currentUserId, $isAdmin, $isExpert);
        }
        $output .= '</div>';
    }

    // Пагинация
    if ($totalPages > 1) {
        $output .= '<nav aria-label="Page navigation" class="mt-4">';
        $output .= '<ul class="pagination justify-content-center">';

        // Предыдущая страница
        if ($page > 1) {
            $prevUrl = buildPaginationUrl($modx, $categoryId, $searchQuery, $page - 1);
            $output .= '<li class="page-item">';
            $output .= '<a class="page-link" href="' . $prevUrl . '">← Предыдущая</a>';
            $output .= '</li>';
        }

        // Номера страниц
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i == $page) ? ' active' : '';
            $pageUrl = buildPaginationUrl($modx, $categoryId, $searchQuery, $i);
            $output .= '<li class="page-item' . $active . '">';
            $output .= '<a class="page-link" href="' . $pageUrl . '">' . $i . '</a>';
            $output .= '</li>';
        }

        // Следующая страница
        if ($page < $totalPages) {
            $nextUrl = buildPaginationUrl($modx, $categoryId, $searchQuery, $page + 1);
            $output .= '<li class="page-item">';
            $output .= '<a class="page-link" href="' . $nextUrl . '">Следующая →</a>';
            $output .= '</li>';
        }

        $output .= '</ul>';
        $output .= '</nav>';
    }
}

$output .= '</div>';
$output .= '</div>';
$output .= '</div>';

// Подключение внешних ресурсов
$modx->regClientCSS('/assets/components/testsystem/css/categories-and-tests.css');
$modx->regClientScript('/assets/components/testsystem/js/test-cards.js');
$modx->regClientScript('/assets/components/testsystem/js/tests-search.js');

return $output;