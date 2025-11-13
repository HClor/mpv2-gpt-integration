<?php
/* Leaderboard by Categories v1.0 */

$prefix = $modx->getOption('table_prefix');
$categoryId = (int)($_GET['cat'] ?? 0);

// Получаем категории с тестами
$stmt = $modx->query("
    SELECT DISTINCT 
        sc.id,
        sc.pagetitle,
        COUNT(DISTINCT cs.user_id) as users_count
    FROM {$prefix}test_category_stats cs
    JOIN {$prefix}site_content sc ON sc.id = cs.category_id
    WHERE cs.tests_completed > 0
    GROUP BY sc.id
    ORDER BY sc.pagetitle
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($categories)) {
    return '<div class="alert alert-info">Статистика по категориям пока пуста</div>';
}

$output = '<div class="card shadow">';
$output .= '<div class="card-header">';
$output .= '<h5 class="mb-0">? Рейтинг по категориям</h5>';
$output .= '</div>';
$output .= '<div class="card-body">';

// Вкладки категорий
$output .= '<ul class="nav nav-tabs mb-3" role="tablist">';
foreach ($categories as $idx => $cat) {
    $isActive = ($categoryId == 0 && $idx == 0) || $categoryId == $cat['id'] ? 'active' : '';
    $output .= '<li class="nav-item">';
    $output .= '<a class="nav-link ' . $isActive . '" href="?cat=' . $cat['id'] . '">';
    $output .= htmlspecialchars($cat['pagetitle']);
    $output .= ' <span class="badge bg-secondary">' . (int)$cat['users_count'] . '</span>';
    $output .= '</a>';
    $output .= '</li>';
}
$output .= '</ul>';

// Определяем какую категорию показывать
$activeCatId = $categoryId > 0 ? $categoryId : (int)$categories[0]['id'];

// Получаем лидеров по выбранной категории
$stmt = $modx->prepare("
    SELECT 
        u.id,
        u.username,
        cs.tests_completed,
        cs.tests_passed,
        cs.avg_score_pct
    FROM {$prefix}test_category_stats cs
    JOIN {$prefix}users u ON u.id = cs.user_id
    WHERE cs.category_id = ?
    ORDER BY cs.avg_score_pct DESC, cs.tests_completed DESC
    LIMIT 20
");
$stmt->execute([$activeCatId]);
$leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Таблица лидеров
$output .= '<div class="table-responsive">';
$output .= '<table class="table table-hover mb-0">';
$output .= '<thead class="table-light">';
$output .= '<tr>';
$output .= '<th style="width: 50px;">#</th>';
$output .= '<th>Пользователь</th>';
$output .= '<th style="width: 120px;">Пройдено</th>';
$output .= '<th style="width: 120px;">Сдано</th>';
$output .= '<th style="width: 120px;">Средний балл</th>';
$output .= '</tr>';
$output .= '</thead>';
$output .= '<tbody>';

$rank = 1;
$userId = $modx->user->id;

foreach ($leaders as $leader) {
    $isCurrentUser = ($userId > 0 && (int)$leader["id"] == $userId);
    $rowClass = $isCurrentUser ? 'table-primary' : '';
    
    $output .= '<tr class="' . $rowClass . '">';
    
    // Место
    $output .= '<td>';
    if ($rank <= 3) {
        $medals = ['?', '?', '?'];
        $output .= '<span class="fs-5">' . $medals[$rank - 1] . '</span>';
    } else {
        $output .= $rank;
    }
    $output .= '</td>';
    
    // Имя
    $output .= '<td>';
    $output .= '<strong>' . htmlspecialchars($leader["username"]) . '</strong>';
    if ($isCurrentUser) {
        $output .= ' <span class="badge bg-primary">Вы</span>';
    }
    $output .= '</td>';
    
    // Пройдено
    $output .= '<td>' . (int)$leader["tests_completed"] . '</td>';
    
    // Сдано
    $output .= '<td class="text-success">' . (int)$leader["tests_passed"] . '</td>';
    
    // Балл
    $score = round((float)$leader["avg_score_pct"]);
    $badgeClass = 'secondary';
    if ($score >= 90) $badgeClass = 'success';
    elseif ($score >= 70) $badgeClass = 'primary';
    elseif ($score >= 50) $badgeClass = 'warning';
    else $badgeClass = 'danger';
    
    $output .= '<td>';
    $output .= '<span class="badge bg-' . $badgeClass . ' fs-6">' . $score . '%</span>';
    $output .= '</td>';
    
    $output .= '</tr>';
    
    $rank++;
}

$output .= '</tbody>';
$output .= '</table>';
$output .= '</div>';

$output .= '</div>';
$output .= '</div>';

return $output;