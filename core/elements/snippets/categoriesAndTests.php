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

        $myTestsPageId = (int)$modx->getOption('lms.my_tests_page', null, 0);
        $importPageId = (int)$modx->getOption('lms.import_csv_page', null, 0);

        if ($importPageId > 0) {
            $importUrl = htmlspecialchars($modx->makeUrl($importPageId, '', ['testId' => $testId]), ENT_QUOTES, 'UTF-8');
            $output .= '<a href="' . $importUrl . '" class="menu-item">';
            $output .= '<i class="bi bi-file-earmark-arrow-down"></i> Импорт вопросов';
            $output .= '</a>';
        }

        if ($myTestsPageId > 0) {
            $questionsUrl = htmlspecialchars($modx->makeUrl($myTestsPageId, '', ['action' => 'questions', 'testId' => $testId]), ENT_QUOTES, 'UTF-8');
            $output .= '<a href="' . $questionsUrl . '" class="menu-item">';
            $output .= '<i class="bi bi-question-circle"></i> Управление вопросами';
            $output .= '</a>';

            $manageUrl = htmlspecialchars($modx->makeUrl($myTestsPageId, '', ['action' => 'edit', 'testId' => $testId]), ENT_QUOTES, 'UTF-8');
            $output .= '<a href="' . $manageUrl . '" class="menu-item">';
            $output .= '<i class="bi bi-gear"></i> Настройки теста';
            $output .= '</a>';
        }

        if ($canDelete) {
            $output .= '<a href="#" class="menu-item menu-item-danger" data-action="delete" data-test-id="' . $testId . '">';
            $output .= '<i class="bi bi-trash"></i> Удалить тест';
            $output .= '</a>';
        }

        $output .= '</div>';
    }

    $output .= '</div>';

    // Описание
    $description = $test['description'] ?: 'Нет описания';
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
    $params = [];
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

$categoryId = (int)($modx->stripTags($_GET['category'] ?? 0));
$searchQuery = trim($modx->stripTags($_GET['search'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Получаем ID страницы тестов из настроек
$testPageId = (int)$modx->getOption('lms.test_page', null, 155);

// Получаем права пользователя для кнопок управления
$currentUserId = $modx->user->get('id');
$userGroups = array_keys($modx->user->getUserGroups());

// Проверяем роли
use TestSystem\Helpers\Config as ConfigHelper;
$configHelper = new ConfigHelper($modx);
$isAdmin = in_array($configHelper->getUserGroupId('admins'), $userGroups);
$isExpert = in_array($configHelper->getUserGroupId('experts'), $userGroups);

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
$params = [];

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
        $testsByCategory = [];
        foreach ($tests as $test) {
            $catName = $test['category_name'] ?: 'Без категории';
            if (!isset($testsByCategory[$catName])) {
                $testsByCategory[$catName] = [];
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

// Подключение JavaScript модуля для карточек
$modx->regClientScript('/assets/components/testsystem/js/test-cards.js');

// JavaScript для поиска
$output .= '<script>
(function() {
    const searchBtn = document.getElementById("tests-search-btn");
    const searchInput = document.getElementById("tests-search-input");

    if (searchBtn && searchInput) {
        searchBtn.addEventListener("click", function() {
            performSearch();
        });

        searchInput.addEventListener("keypress", function(e) {
            if (e.key === "Enter") {
                performSearch();
            }
        });

        function performSearch() {
            const query = searchInput.value.trim();
            const url = new URL(window.location.href);
            if (query) {
                url.searchParams.set("search", query);
            } else {
                url.searchParams.delete("search");
            }
            url.searchParams.delete("page");
            window.location.href = url.toString();
        }
    }
})();
</script>';

// Стили для новых элементов
$output .= '<style>
.categories-tests-container .btn {
    background-image: none !important;
    box-shadow: none !important;
}

.categories-tests-container .btn-primary {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.categories-tests-container .btn-primary:hover {
    background-color: #0b5ed7;
    border-color: #0a58ca;
}

.categories-tests-container .btn-success {
    background-color: #198754;
    border-color: #198754;
}

.categories-tests-container .btn-success:hover {
    background-color: #157347;
    border-color: #146c43;
}

.categories-sidebar .list-group-item.active {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

/* Группировка категорий */
.category-group {
    margin-bottom: 2rem;
}

.category-group-title {
    font-size: 1.5rem;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e0e0e0;
}

/* Сетка тестов */
.tests-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Карточка теста */
.test-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    background: #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    position: relative;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.test-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.test-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    position: relative;
}

.test-title {
    margin: 0;
    font-size: 1.3em;
    font-weight: 600;
    flex: 1;
    padding-right: 10px;
}

.test-menu-toggle {
    cursor: pointer;
    font-size: 24px;
    padding: 0 8px;
    user-select: none;
    color: #666;
    line-height: 1;
}

.test-menu-toggle:hover {
    color: #333;
}

.test-menu-dropdown {
    position: absolute;
    top: 30px;
    right: 0;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 100;
    min-width: 220px;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    text-decoration: none;
    color: #333;
    transition: background 0.2s;
    border-bottom: 1px solid #f0f0f0;
}

.menu-item:last-child {
    border-bottom: none;
}

.menu-item:hover {
    background: #f5f5f5;
    text-decoration: none;
    color: #333;
}

.menu-item-danger {
    color: #d32f2f !important;
}

.menu-item-danger:hover {
    background: #ffebee !important;
    color: #d32f2f !important;
}

.test-description {
    color: #666;
    margin-bottom: 16px;
    line-height: 1.5;
}

.test-meta {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 16px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95em;
}

.meta-icon {
    font-size: 1.1em;
}

.meta-label {
    color: #666;
}

.meta-value {
    font-weight: 600;
    color: #333;
    margin-left: auto;
}

.test-divider {
    border: 0;
    border-top: 1px solid #e0e0e0;
    margin: 16px 0;
}

.test-training-controls {
    margin-bottom: 16px;
}

.test-training-controls label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.questions-input-group {
    display: flex;
    gap: 8px;
}

.questions-count-input {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1em;
}

.btn-all-questions {
    padding: 10px 20px;
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-all-questions:hover {
    background: #e0e0e0;
}

.test-action-buttons {
    display: flex;
    gap: 12px;
}

.btn-large {
    flex: 1;
    padding: 16px;
    font-size: 1.1em;
    font-weight: 600;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s;
}

.btn-start-training {
    background: #4caf50;
    color: white;
}

.btn-start-training:hover {
    background: #45a049;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(76, 175, 80, 0.3);
}

.btn-start-exam {
    background: #2196f3;
    color: white;
}

.btn-start-exam:hover {
    background: #1976d2;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(33, 150, 243, 0.3);
}

.btn-icon {
    font-size: 1.2em;
}

/* Адаптивность */
@media (max-width: 768px) {
    .tests-grid {
        grid-template-columns: 1fr;
    }

    .test-action-buttons {
        flex-direction: column;
    }

    .test-meta {
        font-size: 0.9em;
    }
}
</style>';

return $output;