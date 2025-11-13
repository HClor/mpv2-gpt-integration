<?php
/**
 * learningMaterials v3.1 - Only learning questions
 */

$parentId = (int)$modx->getOption('parent', $scriptProperties, 35);
$categoryId = (int)($_GET['category'] ?? 0);

$prefix = $modx->getOption('table_prefix');
$tableTests = $prefix . 'test_tests';
$tableQuestions = $prefix . 'test_questions';

// ИСПРАВЛЕНИЕ: Получаем только тесты с вопросами is_learning = 1
$stmt = $modx->prepare("
    SELECT DISTINCT t.resource_id 
    FROM {$tableTests} t
    INNER JOIN {$tableQuestions} q ON q.test_id = t.id
    WHERE t.is_active = 1 
    AND t.resource_id IS NOT NULL
    AND q.is_learning = 1
    AND q.published = 1
");
$stmt->execute();
$learningResourceIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($learningResourceIds)) {
    return '<div class="alert alert-info">
        <h5>Нет обучающих материалов</h5>
        <p>Добавьте вопросы в режим обучения, чтобы они отобразились здесь.</p>
    </div>';
}

// Получаем категории с обучающими материалами
$categoryStats = [];
foreach ($learningResourceIds as $resId) {
    $resource = $modx->getObject('modResource', $resId);
    if ($resource) {
        $parentId = $resource->get('parent');
        if (!isset($categoryStats[$parentId])) {
            $categoryStats[$parentId] = 0;
        }
        $categoryStats[$parentId]++;
    }
}

// Начинаем вывод
$output = '<div class="learning-materials-page">';
$output .= '<div class="container-fluid">';
$output .= '<div class="row">';

// ЛЕВАЯ КОЛОНКА
$output .= '<div class="col-lg-3 col-md-4 mb-4">';
$output .= '<div class="card shadow-sm sticky-top" style="top: 20px;">';
$output .= '<div class="card-header bg-primary text-white">';
$output .= '<h5 class="mb-0"><i class="bi bi-list-ul"></i> Разделы</h5>';
$output .= '</div>';
$output .= '<div class="list-group list-group-flush">';

$currentUrl = $modx->makeUrl($modx->resource->id);
$isAllActive = !$categoryId ? 'active' : '';
$totalCount = count($learningResourceIds);

$output .= '<a href="' . $currentUrl . '" class="list-group-item list-group-item-action ' . $isAllActive . '">';
$output .= '<div class="d-flex justify-content-between align-items-center">';
$output .= '<span><i class="bi bi-grid-3x3-gap-fill me-2"></i> Все материалы</span>';
$output .= '<span class="badge bg-primary rounded-pill">' . $totalCount . '</span>';
$output .= '</div>';
$output .= '</a>';

// Категории
foreach ($categoryStats as $catId => $count) {
    $category = $modx->getObject('modResource', $catId);
    if (!$category) continue;
    
    $catUrl = $modx->makeUrl($modx->resource->id, '', ['category' => $catId]);
    $isActive = $categoryId === $catId ? 'active' : '';
    
    $output .= '<a href="' . $catUrl . '" class="list-group-item list-group-item-action ' . $isActive . '">';
    $output .= '<div class="d-flex justify-content-between align-items-center">';
    $output .= '<span><i class="bi bi-folder me-2"></i> ' . htmlspecialchars($category->get('pagetitle')) . '</span>';
    $output .= '<span class="badge bg-secondary rounded-pill">' . $count . '</span>';
    $output .= '</div>';
    $output .= '</a>';
}

$output .= '</div></div></div>';

// ПРАВАЯ КОЛОНКА
$output .= '<div class="col-lg-9 col-md-8">';
$output .= '<div class="learning-header mb-4">';
$output .= '<h1 class="display-5"><i class="bi bi-book-half text-primary"></i> Обучающие материалы</h1>';
$output .= '<p class="lead text-muted">Изучайте материалы в удобном режиме просмотра карточек</p>';
$output .= '</div>';

// Фильтруем по категории
$filteredIds = $learningResourceIds;
if ($categoryId > 0) {
    $filteredIds = [];
    foreach ($learningResourceIds as $resId) {
        $res = $modx->getObject('modResource', $resId);
        if ($res && $res->get('parent') == $categoryId) {
            $filteredIds[] = $resId;
        }
    }
}

if (empty($filteredIds)) {
    $output .= '<div class="alert alert-info">В этой категории нет материалов</div>';
} else {
    // Группируем по родителям
    $byParent = [];
    foreach ($filteredIds as $resId) {
        $res = $modx->getObject('modResource', $resId);
        if (!$res) continue;
        
        $parentId = $res->get('parent');
        $parent = $modx->getObject('modResource', $parentId);
        $parentName = $parent ? $parent->get('pagetitle') : 'Без категории';
        
        if (!isset($byParent[$parentName])) {
            $byParent[$parentName] = ['resources' => []];
        }
        
        // ИСПРАВЛЕНИЕ: Считаем только вопросы с is_learning = 1
        $stmt = $modx->prepare("
            SELECT COUNT(*) 
            FROM {$tableQuestions} q
            JOIN {$tableTests} t ON t.id = q.test_id
            WHERE t.resource_id = ? 
            AND q.is_learning = 1 
            AND q.published = 1
        ");
        $stmt->execute([$resId]);
        $questionsCount = (int)$stmt->fetchColumn();
        
        // Пропускаем если нет обучающих вопросов
        if ($questionsCount === 0) continue;
        
        $byParent[$parentName]['resources'][] = [
            'id' => $resId,
            'title' => $res->get('pagetitle'),
            'description' => $res->get('introtext'),
            'questions_count' => $questionsCount
        ];
    }
    
    // Выводим по группам
    foreach ($byParent as $catName => $data) {
        if (empty($data['resources'])) continue;
        
        $output .= '<div class="category-section mb-4">';
        $output .= '<h2 class="h4 mb-3 border-bottom pb-2">';
        $output .= '<i class="bi bi-folder me-2"></i>';
        $output .= htmlspecialchars($catName);
        $output .= ' <small class="text-muted fs-6">(' . count($data['resources']) . ')</small>';
        $output .= '</h2>';
        $output .= '<div class="row g-3">';
        
        foreach ($data['resources'] as $material) {
            $viewUrl = $modx->makeUrl($material['id']) . '?view=learning';
            $desc = $material['description'] ?: 'Изучайте материал в формате карточек';
            $descShort = mb_strlen($desc) > 100 ? mb_substr($desc, 0, 100) . '...' : $desc;
            
            $output .= '<div class="col-lg-6 col-xl-4">';
            $output .= '<div class="card h-100 learning-card">';
            $output .= '<div class="card-body">';
            $output .= '<h6 class="card-title">' . htmlspecialchars($material['title']) . '</h6>';
            $output .= '<p class="card-text text-muted small">' . $descShort . '</p>';
            $output .= '<a href="' . $viewUrl . '" class="btn btn-sm btn-primary stretched-link">';
            $output .= '<i class="bi bi-play-circle me-1"></i> Начать изучение';
            $output .= '</a>';
            $output .= '</div>';
            $output .= '<div class="card-footer bg-light small text-muted">';
            $output .= '<i class="bi bi-card-list me-1"></i>' . $material['questions_count'] . ' карточек';
            $output .= '</div>';
            $output .= '</div>';
            $output .= '</div>';
        }
        
        $output .= '</div></div>';
    }
}

$output .= '</div></div></div></div>';

return $output;