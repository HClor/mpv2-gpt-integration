<?php
if (!$modx instanceof modX) {
    return '';
}

$prefix = $modx->getOption('table_prefix');
$Tcats = $prefix . 'test_categories';
$Ttests = $prefix . 'test_tests';
$Tquestions = $prefix . 'test_questions';
$S = $prefix . 'site_content';

$categoryId = (int)($modx->stripTags($_GET['category'] ?? 0));

$html = [];
$html[] = '<div class="row categories-tests">';

// Left column: categories list with counts based on published resources
$html[] = '<div class="col-md-4 categories-list">';
$html[] = '<div class="card mb-3">';
$html[] = '<div class="card-header">Категории</div>';
$html[] = '<div class="list-group list-group-flush">';

// ИСПРАВЛЕНО: JOIN по resource_id вместо alias
$sql = "
    SELECT
        c.id,
        c.name,
        COUNT(DISTINCT CASE 
            WHEN sc.published = 1 AND sc.deleted = 0 
            THEN t.id 
        END) AS test_count
    FROM `{$Tcats}` c
    LEFT JOIN `{$Ttests}` t ON t.category_id = c.id AND t.is_active = 1
    LEFT JOIN `{$S}` sc ON sc.id = t.resource_id
    GROUP BY c.id, c.name
    ORDER BY c.sort_order
";

$stmt = $modx->prepare($sql);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($categories as $cat) {
    $isActive = ($cat['id'] == $categoryId) ? ' active' : '';
    $categoryUrl = htmlspecialchars($modx->makeUrl($modx->resource->id) . '?category=' . $cat['id'], ENT_QUOTES, 'UTF-8');

    $html[] = '<a class="list-group-item list-group-item-action' . $isActive . '" href="' . $categoryUrl . '">';
    $html[] = '<div class="d-flex w-100 justify-content-between">';
    $html[] = '<span>' . htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') . '</span>';
    $html[] = '<span class="badge bg-secondary">' . (int)$cat['test_count'] . '</span>';
    $html[] = '</div>';
    $html[] = '</a>';
}

$rightsRaw = $modx->runSnippet('getUserRights');
$rights = is_array($rightsRaw) ? $rightsRaw : [];
$addUrl = '';

if (!empty($rights['canCreate'])) {
    $addResourceId = (int)$modx->getOption('lms.add_test_page', null, 0);
    if ($addResourceId > 0) {
        $addUrl = htmlspecialchars($modx->makeUrl($addResourceId), ENT_QUOTES, 'UTF-8');
        $html[] = '<a class="list-group-item list-group-item-action text-success" href="' . $addUrl . '">+ Добавить тест</a>';
    }
}

$html[] = '</div>';
$html[] = '</div>';
$html[] = '</div>';

// Right column: tests list for selected category
$html[] = '<div class="col-md-8 category-tests">';

if (!$categoryId) {
    $html[] = '<div class="alert alert-info">';
    $html[] = '<h5 class="alert-heading">Выберите категорию</h5>';
    $html[] = '<p>Выберите категорию слева, чтобы увидеть список тестов.</p>';
    $html[] = '</div>';
} else {
    $stmt = $modx->prepare("SELECT name, description FROM `{$Tcats}` WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        $html[] = '<div class="alert alert-warning">Категория не найдена</div>';
    } else {
        $html[] = '<div class="category-header mb-3">';
        $html[] = '<h3>' . htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') . '</h3>';
        $html[] = '<p>' . htmlspecialchars($category['description'], ENT_QUOTES, 'UTF-8') . '</p>';
        $html[] = '</div>';

        // ИСПРАВЛЕНО: JOIN по resource_id вместо alias
        $sql = "
            SELECT
                t.id,
                t.title,
                t.description,
                t.mode,
                t.questions_per_session,
                t.pass_score,
                t.resource_id,
                sc.id AS resource_id_check
            FROM `{$Ttests}` t
            LEFT JOIN `{$S}` sc ON sc.id = t.resource_id
                AND sc.published = 1
                AND sc.deleted = 0
            WHERE t.category_id = ?
              AND t.is_active = 1
              AND t.resource_id IS NOT NULL
              AND sc.id IS NOT NULL
            ORDER BY t.created_at DESC
        ";

        $stmt = $modx->prepare($sql);
        $stmt->execute([$categoryId]);
        $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$tests) {
            $html[] = '<div class="alert alert-secondary">В этой категории пока нет тестов</div>';
            if (!empty($rights['canCreate']) && !empty($addUrl)) {
                $html[] = '<a class="btn btn-outline-success" href="' . $addUrl . '">Создать первый тест</a>';
            }
        } else {
            foreach ($tests as $test) {
                $questionStmt = $modx->prepare("SELECT COUNT(*) FROM `{$Tquestions}` WHERE test_id = ?");
                $questionStmt->execute([$test['id']]);
                $questionCount = (int)$questionStmt->fetchColumn();

                // ИСПРАВЛЕНО: Используем resource_id из таблицы test_tests
                $testUrl = htmlspecialchars($modx->makeUrl((int)$test['resource_id']), ENT_QUOTES, 'UTF-8');
                
                // Если makeUrl вернул пустую строку, логируем проблему
                if (empty($testUrl)) {
                    $modx->log(modX::LOG_LEVEL_ERROR, "[categoriesAndTests] Empty URL for test ID={$test['id']}, resource_id={$test['resource_id']}");
                    $testUrl = '#'; // Временная заглушка
                }

                $html[] = '<div class="card mb-3">';
                $html[] = '<div class="card-body">';
                $html[] = '<h4 class="card-title">' . htmlspecialchars($test['title'], ENT_QUOTES, 'UTF-8') . '</h4>';
                $html[] = '<p class="card-text">' . htmlspecialchars($test['description'], ENT_QUOTES, 'UTF-8') . '</p>';
                $html[] = '<ul class="list-unstyled">';
                $html[] = '<li>Вопросов: ' . $questionCount . '</li>';
                $html[] = '<li>За попытку: ' . (int)$test['questions_per_session'] . '</li>';
                $html[] = '<li>Проходной балл: ' . (int)$test['pass_score'] . '%</li>';
                $html[] = '</ul>';
                $html[] = '<a class="btn btn-primary" href="' . $testUrl . '">Начать тест</a>';
                $html[] = '</div>';
                $html[] = '</div>';
            }
        }
    }
}

$html[] = '</div>';
$html[] = '</div>';

return implode('', $html);