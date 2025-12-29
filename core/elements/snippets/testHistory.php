<?php
/**
 * Сниппет: testHistory - История тестов пользователя
 * Вызывается из: MODX ресурсов (страница истории)
 * Назначение: Отображает список всех пройденных тестов пользователем
 *
 * @package TestSystem
 */

if (!$modx instanceof modX) {
    return '';
}

$userId = $modx->user->id;

if (!$userId) {
    return '<p class="text-muted">Войдите, чтобы видеть историю тестов</p>';
}

$prefix = $modx->getOption('table_prefix');

// Получаем параметры фильтрации
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Получаем историю пройденных тестов
// Показываем сессии где есть finished_at (завершённые) или score (есть результат)
$sql = "
    SELECT
        ts.id as session_id,
        ts.test_id,
        ts.score,
        ts.passed,
        ts.started_at,
        ts.finished_at,
        TIMESTAMPDIFF(SECOND, ts.started_at, ts.finished_at) as time_spent,
        tt.title as test_title,
        tc.id as category_id,
        tc.name as category_title,
        tt.pass_score as passing_score
    FROM `{$prefix}test_sessions` ts
    LEFT JOIN `{$prefix}test_tests` tt ON tt.id = ts.test_id
    LEFT JOIN `{$prefix}test_categories` tc ON tc.id = tt.category_id
    WHERE ts.user_id = " . (int)$userId . "
      AND (ts.status = 'completed' OR ts.finished_at IS NOT NULL OR ts.score IS NOT NULL)
      AND ts.test_id > 0
    ORDER BY COALESCE(ts.finished_at, ts.started_at) DESC
    LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

$stmt = $modx->query($sql);

if ($stmt === false) {
    return '<p class="alert alert-danger">Ошибка при получении истории тестов</p>';
}

$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($sessions)) {
    return '
        <p class="text-muted mb-3">История тестов пуста.</p>
        <a href="' . $modx->makeUrl(35) . '" class="btn btn-primary btn-sm">
            <i class="bi bi-play-circle"></i> Пройти первый тест
        </a>
    ';
}

// Получаем общее количество сессий для пагинации
$sqlCount = "
    SELECT COUNT(*) as total
    FROM `{$prefix}test_sessions` ts
    WHERE ts.user_id = " . (int)$userId . "
      AND (ts.status = 'completed' OR ts.finished_at IS NOT NULL OR ts.score IS NOT NULL)
      AND ts.test_id > 0
";

$stmtCount = $modx->query($sqlCount);
$totalCount = 0;

if ($stmtCount !== false) {
    $row = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $totalCount = (int)$row['total'];
}

$totalPages = ceil($totalCount / $limit);

$html = [];
$html[] = '<div class="test-history-container">';

// Таблица истории
$html[] = '<div class="table-responsive">';
$html[] = '<table class="table table-hover table-striped">';
$html[] = '<thead class="table-light">';
$html[] = '<tr>';
$html[] = '<th>Тест</th>';
$html[] = '<th>Категория</th>';
$html[] = '<th class="text-center">Результат</th>';
$html[] = '<th class="text-center">Статус</th>';
$html[] = '<th>Дата</th>';
$html[] = '<th>Время</th>';
$html[] = '<th></th>';
$html[] = '</tr>';
$html[] = '</thead>';
$html[] = '<tbody>';

foreach ($sessions as $session) {
    $statusBadge = $session['passed'] ?
        '<span class="badge bg-success">Пройден</span>' :
        '<span class="badge bg-danger">Не пройден</span>';

    $timeSpent = $session['time_spent'] ?
        gmdate('i:s', $session['time_spent']) :
        'Не указано';

    $resultClass = $session['passed'] ? 'text-success fw-bold' : 'text-danger fw-bold';

    $html[] = '<tr>';
    $html[] = '<td><strong>' . htmlspecialchars($session['test_title']) . '</strong></td>';
    $html[] = '<td><small class="text-muted">' . htmlspecialchars($session['category_title'] ?? 'Нет категории') . '</small></td>';
    $html[] = '<td class="text-center"><span class="' . $resultClass . '">' . (int)$session['score'] . '%</span></td>';
    $html[] = '<td class="text-center">' . $statusBadge . '</td>';
    $finishedAt = $session['finished_at'] ?: $session['started_at'];
    $html[] = '<td><small>' . date('d.m.Y H:i', strtotime($finishedAt)) . '</small></td>';
    $html[] = '<td><small>' . $timeSpent . '</small></td>';
    $html[] = '<td><a href="' . $modx->makeUrl(156, '', ['session_id' => $session['session_id']]) . '" class="btn btn-sm btn-outline-primary">Подробно</a></td>';
    $html[] = '</tr>';
}

$html[] = '</tbody>';
$html[] = '</table>';
$html[] = '</div>';

// Пагинация
if ($totalPages > 1) {
    $html[] = '<nav aria-label="История тестов">';
    $html[] = '<ul class="pagination justify-content-center">';

    for ($p = 1; $p <= $totalPages; $p++) {
        $activeClass = $p === $page ? 'active' : '';
        $html[] = '<li class="page-item ' . $activeClass . '">';
        $html[] = '<a class="page-link" href="' . $modx->makeUrl(157, '', ['page' => $p]) . '">' . $p . '</a>';
        $html[] = '</li>';
    }

    $html[] = '</ul>';
    $html[] = '</nav>';
}

$html[] = '</div>';

return implode('', $html);
