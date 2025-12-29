<?php
/**
 * Сниппет: homePage - Главная страница системы тестирования
 * Вызывается из: MODX ресурсов (главная страница, ID 187)
 * Назначение: Отображает обзор системы: статистику, тесты, материалы, лидеров, траектории
 *
 * @package TestSystem
 * @version 1.0
 */

if (!$modx instanceof modX) {
    return '';
}

$prefix = $modx->getOption('table_prefix');
$isLoggedIn = $modx->user->id > 0;

// URLs для кнопок
$testsUrl = $modx->makeUrl(35);
$materialsUrl = $modx->makeUrl(149);
$leaderboardUrl = $modx->makeUrl(159);
$pathsUrl = $modx->makeUrl(193);
$loginUrl = $modx->makeUrl(24);

$output = [];

// ===== HERO SECTION =====
$output[] = '<div class="bg-primary text-white py-5 mb-4 rounded-3">';
$output[] = '<div class="container text-center">';
$output[] = '<h1 class="display-5 fw-bold mb-3"><i class="fas fa-graduation-cap me-2"></i>Система тестирования</h1>';
$output[] = '<p class="lead mb-4">Проверьте свои знания, изучайте материалы и получайте сертификаты</p>';
$output[] = '<div class="d-flex justify-content-center gap-3 flex-wrap">';
$output[] = '<a href="' . $testsUrl . '" class="btn btn-light btn-lg"><i class="fas fa-tasks me-2"></i>Пройти тест</a>';
$output[] = '<a href="' . $materialsUrl . '" class="btn btn-outline-light btn-lg"><i class="fas fa-book me-2"></i>Учебные материалы</a>';
$output[] = '</div>';
$output[] = '</div>';
$output[] = '</div>';

// ===== STATISTICS =====
// Кешируем статистику на 5 минут
$cacheKey = 'testsystem/homepage_stats';
$cacheTTL = 300;
$stats = $modx->cacheManager->get($cacheKey);

if ($stats === null) {
    // Количество тестов
    $stmt = $modx->query("SELECT COUNT(*) FROM {$prefix}test_tests WHERE is_active = 1 AND publication_status = 'public'");
    $testsCount = $stmt ? (int)$stmt->fetchColumn() : 0;

    // Количество пользователей с активностью
    $stmt = $modx->query("SELECT COUNT(DISTINCT user_id) FROM {$prefix}test_sessions WHERE status = 'completed'");
    $usersCount = $stmt ? (int)$stmt->fetchColumn() : 0;

    // Количество пройденных тестов
    $stmt = $modx->query("SELECT COUNT(*) FROM {$prefix}test_sessions WHERE status = 'completed'");
    $completedCount = $stmt ? (int)$stmt->fetchColumn() : 0;

    // Количество траекторий
    $stmt = $modx->query("SELECT COUNT(*) FROM {$prefix}learning_paths WHERE status = 'published'");
    $pathsCount = $stmt ? (int)$stmt->fetchColumn() : 0;

    $stats = [
        'tests' => $testsCount,
        'users' => $usersCount,
        'completed' => $completedCount,
        'paths' => $pathsCount
    ];

    $modx->cacheManager->set($cacheKey, $stats, $cacheTTL);
}

$output[] = '<div class="row g-3 mb-4">';

$statsItems = [
    ['icon' => 'fa-clipboard-list', 'value' => $stats['tests'], 'label' => 'Тестов', 'color' => 'primary'],
    ['icon' => 'fa-users', 'value' => $stats['users'], 'label' => 'Участников', 'color' => 'success'],
    ['icon' => 'fa-check-circle', 'value' => number_format($stats['completed'], 0, '', ' '), 'label' => 'Пройдено', 'color' => 'info'],
    ['icon' => 'fa-route', 'value' => $stats['paths'], 'label' => 'Траекторий', 'color' => 'warning'],
];

foreach ($statsItems as $item) {
    $output[] = '<div class="col-6 col-md-3">';
    $output[] = '<div class="card text-center h-100 border-0 shadow-sm">';
    $output[] = '<div class="card-body py-4">';
    $output[] = '<i class="fas ' . $item['icon'] . ' fa-2x text-' . $item['color'] . ' mb-2"></i>';
    $output[] = '<h3 class="fw-bold mb-0">' . $item['value'] . '</h3>';
    $output[] = '<p class="text-muted mb-0 small">' . $item['label'] . '</p>';
    $output[] = '</div>';
    $output[] = '</div>';
    $output[] = '</div>';
}

$output[] = '</div>';

// ===== THREE COLUMNS: TESTS, MATERIALS, LEADERBOARD =====
$output[] = '<div class="row g-4 mb-4">';

// --- Column 1: Popular Tests ---
$output[] = '<div class="col-md-4">';
$output[] = '<div class="card h-100 shadow-sm">';
$output[] = '<div class="card-header bg-white border-bottom">';
$output[] = '<h5 class="mb-0"><i class="fas fa-fire text-danger me-2"></i>Популярные тесты</h5>';
$output[] = '</div>';
$output[] = '<div class="list-group list-group-flush">';

// Получаем топ-5 тестов по количеству прохождений
$sqlTests = "
    SELECT
        t.id,
        t.title,
        COUNT(s.id) as attempts,
        AVG(s.score) as avg_score
    FROM {$prefix}test_tests t
    LEFT JOIN {$prefix}test_sessions s ON s.test_id = t.id AND s.status = 'completed'
    WHERE t.is_active = 1 AND t.publication_status = 'public'
    GROUP BY t.id
    ORDER BY attempts DESC, t.created_at DESC
    LIMIT 5
";

$stmtTests = $modx->query($sqlTests);
$popularTests = $stmtTests ? $stmtTests->fetchAll(PDO::FETCH_ASSOC) : [];

if (empty($popularTests)) {
    $output[] = '<div class="list-group-item text-muted">Пока нет тестов</div>';
} else {
    foreach ($popularTests as $test) {
        $testUrl = $modx->makeUrl(155, '', ['test_id' => $test['id']]);
        $avgScore = $test['avg_score'] ? round($test['avg_score']) : 0;
        $badgeClass = $avgScore >= 80 ? 'success' : ($avgScore >= 60 ? 'warning' : 'secondary');

        $output[] = '<a href="' . $testUrl . '" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">';
        $output[] = '<div class="text-truncate me-2">';
        $output[] = '<i class="fas fa-file-alt text-muted me-2"></i>';
        $output[] = htmlspecialchars($test['title']);
        $output[] = '</div>';
        if ($test['attempts'] > 0) {
            $output[] = '<span class="badge bg-' . $badgeClass . '">' . $avgScore . '%</span>';
        }
        $output[] = '</a>';
    }
}

$output[] = '</div>';
$output[] = '<div class="card-footer bg-white">';
$output[] = '<a href="' . $testsUrl . '" class="btn btn-outline-primary btn-sm w-100">Все тесты <i class="fas fa-arrow-right ms-1"></i></a>';
$output[] = '</div>';
$output[] = '</div>';
$output[] = '</div>';

// --- Column 2: Learning Materials ---
$output[] = '<div class="col-md-4">';
$output[] = '<div class="card h-100 shadow-sm">';
$output[] = '<div class="card-header bg-white border-bottom">';
$output[] = '<h5 class="mb-0"><i class="fas fa-book-open text-primary me-2"></i>Учебные материалы</h5>';
$output[] = '</div>';
$output[] = '<div class="list-group list-group-flush">';

// Получаем последние 5 материалов через pdoResources
$materials = $modx->runSnippet('pdoResources', [
    'parents' => 149,
    'limit' => 5,
    'depth' => 2,
    'showHidden' => 0,
    'sortby' => 'publishedon',
    'sortdir' => 'DESC',
    'return' => 'data',
    'select' => 'id,pagetitle,uri'
]);

if (empty($materials)) {
    $output[] = '<div class="list-group-item text-muted">Пока нет материалов</div>';
} else {
    foreach ($materials as $material) {
        $output[] = '<a href="' . $modx->makeUrl($material['id']) . '" class="list-group-item list-group-item-action">';
        $output[] = '<i class="fas fa-file-alt text-muted me-2"></i>';
        $output[] = htmlspecialchars($material['pagetitle']);
        $output[] = '</a>';
    }
}

$output[] = '</div>';
$output[] = '<div class="card-footer bg-white">';
$output[] = '<a href="' . $materialsUrl . '" class="btn btn-outline-primary btn-sm w-100">Все материалы <i class="fas fa-arrow-right ms-1"></i></a>';
$output[] = '</div>';
$output[] = '</div>';
$output[] = '</div>';

// --- Column 3: Leaderboard ---
$output[] = '<div class="col-md-4">';
$output[] = '<div class="card h-100 shadow-sm">';
$output[] = '<div class="card-header bg-white border-bottom">';
$output[] = '<h5 class="mb-0"><i class="fas fa-trophy text-warning me-2"></i>Лидеры</h5>';
$output[] = '</div>';
$output[] = '<div class="list-group list-group-flush">';

// Получаем топ-5 лидеров
$sqlLeaders = "
    SELECT
        u.username,
        s.tests_completed,
        s.avg_score_pct
    FROM {$prefix}test_user_stats s
    JOIN {$prefix}users u ON u.id = s.user_id
    WHERE s.tests_completed > 0
    ORDER BY s.avg_score_pct DESC, s.tests_completed DESC
    LIMIT 5
";

$stmtLeaders = $modx->query($sqlLeaders);
$leaders = $stmtLeaders ? $stmtLeaders->fetchAll(PDO::FETCH_ASSOC) : [];

if (empty($leaders)) {
    $output[] = '<div class="list-group-item text-muted">Пока нет результатов</div>';
} else {
    $medals = ['🥇', '🥈', '🥉', '4', '5'];
    $pos = 0;
    foreach ($leaders as $leader) {
        $score = round($leader['avg_score_pct']);
        $badgeClass = $score >= 90 ? 'success' : ($score >= 70 ? 'primary' : 'secondary');

        $output[] = '<div class="list-group-item d-flex justify-content-between align-items-center">';
        $output[] = '<div>';
        $output[] = '<span class="me-2">' . $medals[$pos] . '</span>';
        $output[] = '<strong>' . htmlspecialchars($leader['username']) . '</strong>';
        $output[] = '<small class="text-muted ms-2">' . $leader['tests_completed'] . ' тестов</small>';
        $output[] = '</div>';
        $output[] = '<span class="badge bg-' . $badgeClass . '">' . $score . '%</span>';
        $output[] = '</div>';
        $pos++;
    }
}

$output[] = '</div>';
$output[] = '<div class="card-footer bg-white">';
$output[] = '<a href="' . $leaderboardUrl . '" class="btn btn-outline-warning btn-sm w-100">Весь рейтинг <i class="fas fa-arrow-right ms-1"></i></a>';
$output[] = '</div>';
$output[] = '</div>';
$output[] = '</div>';

$output[] = '</div>'; // end row

// ===== LEARNING PATHS =====
$sqlPaths = "
    SELECT
        lp.id,
        lp.name,
        lp.description,
        lp.difficulty_level,
        (SELECT COUNT(*) FROM {$prefix}learning_path_steps WHERE path_id = lp.id) as steps_count,
        (SELECT COUNT(*) FROM {$prefix}learning_path_enrollments WHERE path_id = lp.id) as enrolled_count
    FROM {$prefix}learning_paths lp
    WHERE lp.status = 'published'
    ORDER BY enrolled_count DESC, lp.created_at DESC
    LIMIT 4
";

$stmtPaths = $modx->query($sqlPaths);
$paths = $stmtPaths ? $stmtPaths->fetchAll(PDO::FETCH_ASSOC) : [];

if (!empty($paths)) {
    $difficultyLabels = [
        'beginner' => ['label' => 'Начальный', 'class' => 'success'],
        'intermediate' => ['label' => 'Средний', 'class' => 'warning'],
        'advanced' => ['label' => 'Продвинутый', 'class' => 'danger'],
        'expert' => ['label' => 'Эксперт', 'class' => 'dark']
    ];

    $output[] = '<div class="card shadow-sm mb-4">';
    $output[] = '<div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">';
    $output[] = '<h5 class="mb-0"><i class="fas fa-route text-info me-2"></i>Траектории обучения</h5>';
    $output[] = '<a href="' . $pathsUrl . '" class="btn btn-outline-info btn-sm">Все траектории</a>';
    $output[] = '</div>';
    $output[] = '<div class="card-body">';
    $output[] = '<div class="row g-3">';

    foreach ($paths as $path) {
        $pathUrl = $modx->makeUrl(195, '', ['id' => $path['id']]);
        $diff = $difficultyLabels[$path['difficulty_level']] ?? ['label' => $path['difficulty_level'], 'class' => 'secondary'];

        $output[] = '<div class="col-md-6 col-lg-3">';
        $output[] = '<div class="card h-100 border">';
        $output[] = '<div class="card-body">';
        $output[] = '<h6 class="card-title">' . htmlspecialchars($path['name']) . '</h6>';
        $output[] = '<p class="card-text small text-muted text-truncate">' . htmlspecialchars($path['description'] ?: 'Описание отсутствует') . '</p>';
        $output[] = '<div class="d-flex justify-content-between align-items-center">';
        $output[] = '<span class="badge bg-' . $diff['class'] . '">' . $diff['label'] . '</span>';
        $output[] = '<small class="text-muted"><i class="fas fa-list-ol me-1"></i>' . $path['steps_count'] . ' шагов</small>';
        $output[] = '</div>';
        $output[] = '</div>';
        $output[] = '<div class="card-footer bg-white">';
        $output[] = '<a href="' . $pathUrl . '" class="btn btn-sm btn-outline-primary w-100">Подробнее</a>';
        $output[] = '</div>';
        $output[] = '</div>';
        $output[] = '</div>';
    }

    $output[] = '</div>';
    $output[] = '</div>';
    $output[] = '</div>';
}

// ===== CTA для незарегистрированных =====
if (!$isLoggedIn) {
    $output[] = '<div class="bg-light rounded-3 p-4 text-center">';
    $output[] = '<h4 class="mb-3">Присоединяйтесь к обучению!</h4>';
    $output[] = '<p class="text-muted mb-3">Зарегистрируйтесь, чтобы отслеживать прогресс и получать сертификаты</p>';
    $output[] = '<a href="' . $loginUrl . '" class="btn btn-primary"><i class="fas fa-user-plus me-2"></i>Войти / Регистрация</a>';
    $output[] = '</div>';
}

return implode("\n", $output);
