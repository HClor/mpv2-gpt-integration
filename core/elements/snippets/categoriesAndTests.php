<?php
/**
 * Categories and Tests v2.0 - Unified UI Style
 */

// Подключаем bootstrap
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

if (!$modx instanceof modX) {
    return '';
}

$prefix = $modx->getOption('table_prefix');
$Tcats = $prefix . 'test_categories';
$Ttests = $prefix . 'test_tests';
$Tquestions = $prefix . 'test_questions';

$categoryId = (int)($modx->stripTags($_GET['category'] ?? 0));

// Получаем ID страницы тестов из настроек
$testPageId = (int)$modx->getOption('lms.test_page', null, 155);

$output = '<div class="container-fluid categories-tests-container">';
$output .= '<div class="row">';

// Left column: categories list
$output .= '<div class="col-md-4 col-lg-3 categories-sidebar">';
$output .= '<div class="card mb-3">';
$output .= '<div class="card-header bg-primary text-white">';
$output .= '<h5 class="mb-0"><i class="bi bi-folder-fill"></i> Категории</h5>';
$output .= '</div>';
$output .= '<div class="list-group list-group-flush">';

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

if (!$categoryId) {
    $output .= '<div class="alert alert-info">';
    $output .= '<h4 class="alert-heading"><i class="bi bi-info-circle"></i> Выберите категорию</h4>';
    $output .= '<p>Выберите категорию слева, чтобы увидеть список тестов.</p>';
    $output .= '</div>';
} else {
    $stmt = $modx->prepare("SELECT name, description FROM `{$Tcats}` WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        $output .= '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Категория не найдена</div>';
    } else {
        $output .= '<div class="category-header mb-4">';
        $output .= '<h2><i class="bi bi-folder-open text-primary"></i> ' . htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') . '</h2>';
        if (!empty($category['description'])) {
            $output .= '<p class="text-muted">' . htmlspecialchars($category['description'], ENT_QUOTES, 'UTF-8') . '</p>';
        }
        $output .= '</div>';

        $sql = "
            SELECT
                t.id,
                t.title,
                t.description,
                t.mode,
                t.questions_per_session,
                t.pass_score,
                COUNT(q.id) AS question_count
            FROM `{$Ttests}` t
            LEFT JOIN `{$Tquestions}` q ON q.test_id = t.id
            WHERE t.category_id = ?
              AND t.publication_status = 'public'
              AND t.is_active = 1
            GROUP BY t.id, t.title, t.description, t.mode, t.questions_per_session, t.pass_score
            ORDER BY t.created_at DESC
        ";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$categoryId]);
        $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$tests) {
            $output .= '<div class="alert alert-secondary">';
            $output .= '<p><i class="bi bi-inbox"></i> В этой категории пока нет тестов</p>';
            if (!empty($rights['canCreate']) && !empty($createTestPageId)) {
                $addUrl = htmlspecialchars($modx->makeUrl($createTestPageId), ENT_QUOTES, 'UTF-8');
                $output .= '<a class="btn btn-success" href="' . $addUrl . '"><i class="bi bi-plus-circle"></i> Создать первый тест</a>';
            }
            $output .= '</div>';
        } else {
            $output .= '<div class="row">';
            foreach ($tests as $test) {
                $questionCount = (int)$test['question_count'];
                $testUrl = htmlspecialchars($modx->makeUrl($testPageId, '', ['testId' => $test['id']]), ENT_QUOTES, 'UTF-8');

                $output .= '<div class="col-md-6 col-lg-4 mb-4">';
                $output .= '<div class="card h-100 test-card">';
                $output .= '<div class="card-body d-flex flex-column">';
                $output .= '<h5 class="card-title">' . htmlspecialchars($test['title'], ENT_QUOTES, 'UTF-8') . '</h5>';
                $output .= '<p class="card-text text-muted flex-grow-1">' . htmlspecialchars($test['description'] ?: 'Нет описания', ENT_QUOTES, 'UTF-8') . '</p>';

                $output .= '<div class="test-meta mb-3">';
                $output .= '<span class="badge bg-info me-2"><i class="bi bi-question-circle"></i> ' . $questionCount . ' вопросов</span>';
                $output .= '<span class="badge bg-secondary"><i class="bi bi-clipboard-check"></i> ' . (int)$test['questions_per_session'] . ' за попытку</span>';
                $output .= '</div>';

                $output .= '<a class="btn btn-primary w-100" href="' . $testUrl . '">';
                $output .= '<i class="bi bi-play-fill"></i> Начать тест';
                $output .= '</a>';
                $output .= '</div>';
                $output .= '</div>';
                $output .= '</div>';
            }
            $output .= '</div>';
        }
    }
}

$output .= '</div>';
$output .= '</div>';
$output .= '</div>';

// Стили для единообразия
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

.test-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid #dee2e6;
}

.test-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.categories-sidebar .list-group-item.active {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
</style>';

return $output;