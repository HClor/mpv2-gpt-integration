<?php
/**
 * Сниппет: achievements - Достижения пользователя
 * Вызывается из: MODX ресурсов (страница достижений)
 * Назначение: Отображает полученные достижения, значки и статистику
 *
 * @package TestSystem
 */

if (!$modx instanceof modX) {
    return '';
}

$userId = $modx->user->id;

if (!$userId) {
    return '<p class="text-muted">Войдите, чтобы видеть достижения</p>';
}

$prefix = $modx->getOption('table_prefix');

// Определяем уровни и значки
$badges = [
    'first_test' => ['title' => '🎯 Первый шаг', 'description' => 'Пройти первый тест'],
    'five_tests' => ['title' => '📚 Ученик', 'description' => 'Пройти 5 тестов'],
    'ten_tests' => ['title' => '🔥 Прилежный', 'description' => 'Пройти 10 тестов'],
    'thirty_tests' => ['title' => '⭐ Эксперт', 'description' => 'Пройти 30 тестов'],
    'perfect_score' => ['title' => '💯 Идеально', 'description' => 'Получить 100% на тесте'],
    'five_consecutive' => ['title' => '🏆 Мастер', 'description' => 'Пройти 5 тестов подряд с результатом >= 80%'],
    'leaderboard' => ['title' => '🥇 Чемпион', 'description' => 'Быть в топ-10 лучших'],
    'streak_week' => ['title' => '📅 Неделя активности', 'description' => 'Решать тесты 7 дней подряд'],
];

// Получаем статистику пользователя
$sql = "
    SELECT
        COUNT(DISTINCT ts.id) as total_tests,
        SUM(CASE WHEN ts.passed = 1 THEN 1 ELSE 0 END) as passed_tests,
        SUM(CASE WHEN ts.score = 100 THEN 1 ELSE 0 END) as perfect_scores,
        AVG(ts.score) as avg_score,
        MAX(ts.score) as best_score,
        MIN(ts.created_at) as first_test_date,
        COUNT(DISTINCT DATE(ts.completed_at)) as activity_days
    FROM `{$prefix}test_sessions` ts
    WHERE ts.user_id = " . (int)$userId . " AND ts.status = 'completed'
";

$stmt = $modx->query($sql);

if ($stmt === false) {
    return '<p class="ts-alert ts-alert-danger">Ошибка при получении данных</p>';
}

$stats = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$stats || $stats['total_tests'] == 0) {
    return '
        <p class="text-muted mb-3">У вас еще нет достижений. Начните решать тесты!</p>
        <a href="' . $modx->makeUrl(35) . '" class="ts-btn ts-btn-primary ts-btn-sm">
            <i class="bi bi-play-circle"></i> Начать тестирование
        </a>
    ';
}

// Определяем полученные достижения
$earned = [];

// Первый тест
if ($stats['total_tests'] >= 1) {
    $earned['first_test'] = true;
}

// 5 тестов
if ($stats['total_tests'] >= 5) {
    $earned['five_tests'] = true;
}

// 10 тестов
if ($stats['total_tests'] >= 10) {
    $earned['ten_tests'] = true;
}

// 30 тестов
if ($stats['total_tests'] >= 30) {
    $earned['thirty_tests'] = true;
}

// Идеальный результат (100%)
if ($stats['perfect_scores'] >= 1) {
    $earned['perfect_score'] = true;
}

// 5 тестов подряд с результатом >= 80%
$sqlConsecutive = "
    SELECT COUNT(*) as cnt
    FROM (
        SELECT ts.id
        FROM `{$prefix}test_sessions` ts
        WHERE ts.user_id = " . (int)$userId . " AND ts.score >= 80 AND ts.status = 'completed'
        ORDER BY ts.completed_at DESC
        LIMIT 5
    ) as recent
";

$stmtConsecutive = $modx->query($sqlConsecutive);
if ($stmtConsecutive !== false) {
    $consecutive = $stmtConsecutive->fetch(PDO::FETCH_ASSOC);
    if ($consecutive['cnt'] >= 5) {
        $earned['five_consecutive'] = true;
    }
}

// В топ-10
$sqlRank = "
    SELECT COUNT(*) + 1 as rank
    FROM (
        SELECT u.id
        FROM `{$prefix}users` u
        LEFT JOIN (
            SELECT user_id, SUM(score) as total_score
            FROM `{$prefix}test_sessions`
            WHERE status = 'completed'
            GROUP BY user_id
        ) ts ON ts.user_id = u.id
        WHERE COALESCE(ts.total_score, 0) > (
            SELECT COALESCE(SUM(score), 0)
            FROM `{$prefix}test_sessions`
            WHERE user_id = " . (int)$userId . " AND status = 'completed'
        )
        GROUP BY u.id
    ) ranked
";

$stmtRank = $modx->query($sqlRank);
if ($stmtRank !== false) {
    $rank = $stmtRank->fetch(PDO::FETCH_ASSOC);
    if ($rank['rank'] <= 10) {
        $earned['leaderboard'] = true;
    }
}

// Неделя активности (7 дней подряд)
if ($stats['activity_days'] >= 7) {
    $earned['streak_week'] = true;
}

// Генерируем HTML
$html = [];
$html[] = '<div class="achievements-container">';

// Статистика вверху
$html[] = '<div class="row mb-4">';
$html[] = '<div class="col-md-3">';
$html[] = '<div class="card text-center">';
$html[] = '<div class="card-body">';
$html[] = '<h3 class="card-title text-primary">' . $stats['total_tests'] . '</h3>';
$html[] = '<p class="card-text text-muted">Тестов пройдено</p>';
$html[] = '</div>';
$html[] = '</div>';
$html[] = '</div>';

$html[] = '<div class="col-md-3">';
$html[] = '<div class="card text-center">';
$html[] = '<div class="card-body">';
$html[] = '<h3 class="card-title text-success">' . round($stats['avg_score'], 0) . '%</h3>';
$html[] = '<p class="card-text text-muted">Средний результат</p>';
$html[] = '</div>';
$html[] = '</div>';
$html[] = '</div>';

$html[] = '<div class="col-md-3">';
$html[] = '<div class="card text-center">';
$html[] = '<div class="card-body">';
$html[] = '<h3 class="card-title text-warning">' . $stats['perfect_scores'] . '</h3>';
$html[] = '<p class="card-text text-muted">Идеальных результатов</p>';
$html[] = '</div>';
$html[] = '</div>';
$html[] = '</div>';

$html[] = '<div class="col-md-3">';
$html[] = '<div class="card text-center">';
$html[] = '<div class="card-body">';
$html[] = '<h3 class="card-title text-info">' . count($earned) . '</h3>';
$html[] = '<p class="card-text text-muted">Достижений получено</p>';
$html[] = '</div>';
$html[] = '</div>';
$html[] = '</div>';
$html[] = '</div>';

// Достижения
$html[] = '<div class="row">';
foreach ($badges as $key => $badge) {
    $isEarned = isset($earned[$key]);
    $opacity = $isEarned ? '' : 'opacity-50';
    $icon = $badge['title'];

    $html[] = '<div class="col-md-4 col-lg-3 mb-3">';
    $html[] = '<div class="card h-100 ' . $opacity . '" data-bs-toggle="tooltip" title="' . htmlspecialchars($badge['description']) . '">';
    $html[] = '<div class="card-body text-center">';
    $html[] = '<div class="fs-1 mb-2">' . $icon . '</div>';
    $html[] = '<h6 class="card-title">' . htmlspecialchars($badge['title']) . '</h6>';
    $html[] = '<p class="card-text small text-muted">' . htmlspecialchars($badge['description']) . '</p>';

    if ($isEarned) {
        $html[] = '<span class="ts-badge ts-badge-success mt-2">✓ Получено</span>';
    } else {
        $html[] = '<span class="ts-badge ts-badge-neutral mt-2">Заблокировано</span>';
    }

    $html[] = '</div>';
    $html[] = '</div>';
    $html[] = '</div>';
}
$html[] = '</div>';

$html[] = '</div>';

// Добавляем скрипт для инициализации подсказок
$html[] = '<script>';
$html[] = 'if (typeof bootstrap !== "undefined") {';
$html[] = '  var tooltipTriggerList = [].slice.call(document.querySelectorAll("[data-bs-toggle=\'tooltip\']"))';
$html[] = '  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {';
$html[] = '    return new bootstrap.Tooltip(tooltipTriggerEl)';
$html[] = '  })';
$html[] = '}';
$html[] = '</script>';

return implode('', $html);
