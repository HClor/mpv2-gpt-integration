<?php
/**
 * Сниппет: learningMaterials - Список учебных материалов
 * Вызывается из: learning-materials-template*.html
 * Назначение: Отображает список учебных материалов с вопросами для изучения
 *
 * @package TestSystem
 * @version 3.1
 */

$parentId = (int)$modx->getOption('parent', $scriptProperties, 35);
$categoryId = (int)($_GET['category'] ?? 0);

$prefix = $modx->getOption('table_prefix');
$tableTests = $prefix . 'test_tests';
$tableQuestions = $prefix . 'test_questions';
$tableCategories = $prefix . 'test_categories';

// Получаем тесты с обучающими вопросами
// ВАЖНО: не ограничиваемся resource_id, чтобы показывать тесты,
// созданные только в интерфейсе categoriesAndTests (без привязанной страницы-ресурса).
$stmt = $modx->prepare("
    SELECT DISTINCT
        t.id AS test_id,
        t.resource_id,
        t.title,
        t.description,
        c.name AS category_name
    FROM {$tableTests} t
    INNER JOIN {$tableQuestions} q ON q.test_id = t.id
    LEFT JOIN {$tableCategories} c ON c.id = t.category_id
    WHERE t.is_active = 1
    AND q.is_learning = 1
    AND q.published = 1
");
$stmt->execute();
$learningTests = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($learningTests)) {
    return '<div class="ts-alert ts-alert-info">
        <h5>Нет обучающих материалов</h5>
        <p>Добавьте вопросы в режим обучения, чтобы они отобразились здесь.</p>
    </div>';
}

// Начинаем вывод
$output = '';

// Группируем по категориям
$byParent = [];
foreach ($learningTests as $test) {
    $res = null;
    if (!empty($test['resource_id'])) {
        $res = $modx->getObject('modResource', (int)$test['resource_id']);
    }

    $parentName = !empty($test['category_name']) ? $test['category_name'] : 'Без категории';

    if (!isset($byParent[$parentName])) {
        $byParent[$parentName] = ['resources' => []];
    }

    // Считаем только вопросы с is_learning = 1 для этого теста
    $stmt = $modx->prepare("
        SELECT COUNT(*)
        FROM {$tableQuestions} q
        WHERE q.test_id = ?
        AND q.is_learning = 1
        AND q.published = 1
    ");
    $stmt->execute([$test['test_id']]);
    $questionsCount = (int)$stmt->fetchColumn();

    // Пропускаем если нет обучающих вопросов
    if ($questionsCount === 0) continue;

    $byParent[$parentName]['resources'][] = [
        'test_id' => $test['test_id'],
        'resource_id' => $test['resource_id'],
        'title' => $test['title'] ?: ($res ? $res->get('pagetitle') : ''),
        'description' => $test['description'] ?: ($res ? $res->get('introtext') : ''),
        'questions_count' => $questionsCount
    ];
}

// Выводим по группам (категориям)
foreach ($byParent as $catName => $data) {
    if (empty($data['resources'])) continue;

    $output .= '<div class="category-section mb-5">';
    $output .= '<h3 class="h4 mb-3 pb-2 border-bottom">';
    $output .= '<i class="bi bi-folder me-2 text-info"></i>';
    $output .= htmlspecialchars($catName);
    $output .= ' <span class="ts-badge ts-badge-neutral">' . count($data['resources']) . '</span>';
    $output .= '</h3>';
    $output .= '<div class="row">';

    foreach ($data['resources'] as $material) {
        // Генерируем URL для запуска теста в режиме обучения
        // Находим ресурс test-run (обычно это фиксированный ресурс)
        $testRunResource = $modx->getObject('modResource', ['uri' => 'test-run']);
        if (!$testRunResource) {
            // Резервный поиск по алиасу
            $testRunResource = $modx->getObject('modResource', ['alias' => 'test-run']);
        }

        if ($testRunResource) {
            $testRunUrl = $modx->makeUrl($testRunResource->get('id'));
            $viewUrl = $testRunUrl . '?testId=' . $material['test_id'] . '&view=learning';
        } else {
            // Если test-run не найден, используем прямой URL
            $viewUrl = $modx->getOption('site_url') . 'test-run?testId=' . $material['test_id'] . '&view=learning';
        }

        $desc = $material['description'] ?: 'Изучайте материал в формате карточек';
        $descShort = mb_strlen($desc) > 150 ? mb_substr($desc, 0, 150) . '...' : $desc;

        $output .= '<div class="col-lg-4 col-md-6 mb-4">';
        $output .= '<div class="card h-100 shadow-sm">';
        $output .= '<div class="card-body">';
        $output .= '<h5 class="card-title">' . htmlspecialchars($material['title']) . '</h5>';
        $output .= '<div class="mb-2"><span class="ts-badge ts-badge-primary"><i class="bi bi-collection me-1"></i>Вопросы из теста</span></div>';
        $output .= '<p class="card-text text-muted small">' . htmlspecialchars($descShort) . '</p>';
        $output .= '</div>';
        $output .= '<div class="card-footer bg-light d-flex justify-content-between align-items-center">';
        $output .= '<a href="' . $viewUrl . '" class="ts-btn ts-btn-sm ts-btn-ghost">';
        $output .= '<i class="bi bi-play-circle me-1"></i> Начать изучение';
        $output .= '</a>';
        $output .= '<small class="text-muted"><i class="bi bi-card-list me-1"></i>' . $material['questions_count'] . ' карточек</small>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';
    }

    $output .= '</div></div>';
}

return $output;
