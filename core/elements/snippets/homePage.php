<?php
/**
 * Сниппет: homePage - Главная страница системы тестирования
 * Вызывается из: MODX ресурсов (главная страница, ID 187)
 * Назначение: Витрина LMS с акцентом на прохождение тестов
 *
 * @package TestSystem
 * @version 2.0
 */

if (!$modx instanceof modX) {
    return '';
}

$prefix = $modx->getOption('table_prefix');

$testsUrl = $modx->makeUrl(35);
$materialsUrl = $modx->makeUrl(149);
$leaderboardUrl = $modx->makeUrl(159);
$loginUrl = $modx->makeUrl(24);

$output = [];

// ===== STATISTICS =====
$cacheKey = 'testsystem/homepage_stats';
$cacheTTL = 300;
$stats = $modx->cacheManager->get($cacheKey);

if ($stats === null) {
    $stmt = $modx->query("SELECT COUNT(*) FROM {$prefix}test_tests WHERE is_active = 1 AND publication_status = 'public'");
    $testsCount = $stmt ? (int)$stmt->fetchColumn() : 0;

    $stmt = $modx->query("SELECT COUNT(DISTINCT user_id) FROM {$prefix}test_sessions WHERE status = 'completed'");
    $usersCount = $stmt ? (int)$stmt->fetchColumn() : 0;

    $stmt = $modx->query("SELECT COUNT(*) FROM {$prefix}test_sessions WHERE status = 'completed'");
    $completedCount = $stmt ? (int)$stmt->fetchColumn() : 0;

    $stmt = $modx->query("SELECT COUNT(*) FROM {$prefix}learning_paths WHERE status = 'published'");
    $pathsCount = $stmt ? (int)$stmt->fetchColumn() : 0;

    $stats = [
        'tests' => $testsCount,
        'users' => $usersCount,
        'completed' => $completedCount,
        'paths' => $pathsCount,
    ];

    $modx->cacheManager->set($cacheKey, $stats, $cacheTTL);
}

// ===== POPULAR TESTS =====
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
    LIMIT 4
";

$stmtTests = $modx->query($sqlTests);
$popularTests = $stmtTests ? $stmtTests->fetchAll(PDO::FETCH_ASSOC) : [];

// ===== LEADERS =====
$sqlLeaders = "
    SELECT
        u.username,
        s.tests_completed,
        s.avg_score_pct
    FROM {$prefix}test_user_stats s
    JOIN {$prefix}users u ON u.id = s.user_id
    WHERE s.tests_completed > 0
    ORDER BY s.avg_score_pct DESC, s.tests_completed DESC
    LIMIT 4
";

$stmtLeaders = $modx->query($sqlLeaders);
$leaders = $stmtLeaders ? $stmtLeaders->fetchAll(PDO::FETCH_ASSOC) : [];

// ===== MATERIALS =====
$materials = $modx->runSnippet('pdoResources', [
    'parents' => 149,
    'limit' => 3,
    'depth' => 2,
    'showHidden' => 0,
    'sortby' => 'publishedon',
    'sortdir' => 'DESC',
    'return' => 'data',
    'select' => 'id,pagetitle'
]);

$output[] = '<div class="ts-home">';

// HERO
$output[] = '<section class="ts-home-hero">';
$output[] = '<div class="ts-home-hero-grid">';
$output[] = '<div class="ts-home-hero-content">';
$output[] = '<div class="ts-home-hero-eyebrow">LMS Обучение</div>';
$output[] = '<h1 class="ts-home-hero-title">Система тестирования и обучения</h1>';
$output[] = '<p class="ts-home-hero-text">Проходите тесты, изучайте материалы и отслеживайте прогресс в единой образовательной среде.</p>';
$output[] = '<div class="ts-home-hero-actions">';
$output[] = '<a href="' . $testsUrl . '" class="ts-btn ts-btn-primary ts-btn-lg"><i class="bi bi-card-checklist me-2"></i>Пройти тест</a>';
$output[] = '<a href="' . $materialsUrl . '" class="ts-btn ts-btn-ghost ts-btn-lg">Учебные материалы</a>';
$output[] = '</div>';
$output[] = '</div>';
$output[] = '<aside class="ts-card ts-home-hero-side">';
$output[] = '<div class="ts-card-header"><h2 class="ts-card-title">Кратко о платформе</h2></div>';
$output[] = '<div class="ts-card-body">';
$output[] = '<ul class="ts-home-summary-list">';
$output[] = '<li>Тесты по ключевым темам</li>';
$output[] = '<li>Понятные результаты и динамика</li>';
$output[] = '<li>Лидеры и учебные траектории</li>';
$output[] = '</ul>';
$output[] = '</div>';
$output[] = '</aside>';
$output[] = '</div>';
$output[] = '</section>';

// KPI
$output[] = '<section class="ts-home-kpis">';
$output[] = '<div class="ts-home-kpi-row">';

$statsItems = [
    ['value' => number_format($stats['tests'], 0, '', ' '), 'label' => 'Тестов'],
    ['value' => number_format($stats['users'], 0, '', ' '), 'label' => 'Участников'],
    ['value' => number_format($stats['completed'], 0, '', ' '), 'label' => 'Прохождений'],
    ['value' => number_format($stats['paths'], 0, '', ' '), 'label' => 'Траекторий'],
];

foreach ($statsItems as $item) {
    $output[] = '<div class="ts-kpi-card">';
    $output[] = '<div class="ts-kpi-value">' . $item['value'] . '</div>';
    $output[] = '<div class="ts-kpi-label">' . $item['label'] . '</div>';
    $output[] = '</div>';
}

$output[] = '</div>';
$output[] = '</section>';

// POPULAR TESTS
$output[] = '<section class="ts-home-section ts-home-tests">';
$output[] = '<div class="ts-section-header">';
$output[] = '<div>';
$output[] = '<h2 class="ts-section-title">Популярные тесты</h2>';
$output[] = '<p class="ts-section-text">Начните с наиболее востребованных тестов платформы.</p>';
$output[] = '</div>';
$output[] = '<a href="' . $testsUrl . '" class="ts-btn ts-btn-ghost ts-btn-sm">Все тесты</a>';
$output[] = '</div>';

if (empty($popularTests)) {
    $output[] = '<div class="ts-card"><div class="ts-card-body"><div class="ts-empty-state">';
    $output[] = '<div class="ts-empty-state-title">Тесты пока не опубликованы</div>';
    $output[] = '<div class="ts-empty-state-text">Когда тесты будут доступны, они появятся в этом разделе.</div>';
    $output[] = '</div></div></div>';
} else {
    $output[] = '<div class="ts-test-card-grid">';
    foreach ($popularTests as $test) {
        $testUrl = $modx->makeUrl(155, '', ['testId' => $test['id']]);
        $avgScore = $test['avg_score'] ? round($test['avg_score']) : null;

        $output[] = '<article class="ts-card ts-test-card">';
        $output[] = '<div class="ts-card-body">';
        $output[] = '<div class="ts-test-card-meta">Тест</div>';
        $output[] = '<h3 class="ts-test-card-title">' . htmlspecialchars($test['title']) . '</h3>';
        $output[] = '<p class="ts-test-card-subtitle">' . (int)$test['attempts'] . ' прохождений</p>';
        $output[] = '<div class="ts-test-card-footer">';
        if ($avgScore !== null) {
            $output[] = '<div class="ts-test-score"><span class="ts-test-score-label">Средний результат</span><span class="ts-test-score-value">' . $avgScore . '%</span></div>';
        } else {
            $output[] = '<div class="ts-test-score"><span class="ts-test-score-label">Доступно для прохождения</span></div>';
        }
        $output[] = '<a href="' . $testUrl . '" class="ts-btn ts-btn-ghost ts-btn-sm">Открыть тест</a>';
        $output[] = '</div></div></article>';
    }
    $output[] = '</div>';
}

$output[] = '</section>';

// SECONDARY BLOCK
$output[] = '<section class="ts-home-section ts-home-secondary">';
$output[] = '<div class="ts-home-secondary-grid">';

$output[] = '<section class="ts-card ts-leaders-widget">';
$output[] = '<div class="ts-card-header ts-section-header ts-section-header-compact">';
$output[] = '<div><h2 class="ts-section-title">Лидеры</h2><p class="ts-section-text">Пользователи с лучшими результатами.</p></div>';
$output[] = '<a href="' . $leaderboardUrl . '" class="ts-btn ts-btn-ghost ts-btn-sm">Весь рейтинг</a>';
$output[] = '</div>';
$output[] = '<div class="ts-card-body">';

if (empty($leaders)) {
    $output[] = '<div class="ts-empty-state">';
    $output[] = '<div class="ts-empty-state-title">Пока нет данных рейтинга</div>';
    $output[] = '<div class="ts-empty-state-text">После завершения первых тестов здесь появится рейтинг.</div>';
    $output[] = '</div>';
} else {
    $position = 1;
    foreach ($leaders as $leader) {
        $score = round($leader['avg_score_pct']);
        $avatar = mb_strtoupper(mb_substr($leader['username'], 0, 1));

        $output[] = '<div class="ts-leader-row">';
        $output[] = '<div class="ts-leader-user">';
        $output[] = '<div class="ts-user-avatar">' . htmlspecialchars($avatar) . '</div>';
        $output[] = '<div class="ts-leader-user-text">';
        $output[] = '<div class="ts-leader-name">' . $position . '. ' . htmlspecialchars($leader['username']) . '</div>';
        $output[] = '<div class="ts-leader-meta">' . (int)$leader['tests_completed'] . ' тестов</div>';
        $output[] = '</div></div>';
        $output[] = '<div class="ts-leader-score">' . $score . '%</div>';
        $output[] = '</div>';
        $position++;
    }
}

$output[] = '</div>';
$output[] = '</section>';

$output[] = '<section class="ts-card ts-empty-state-card">';
$output[] = '<div class="ts-card-body">';

if (empty($materials)) {
    $output[] = '<div class="ts-empty-state">';
    $output[] = '<div class="ts-empty-state-title">Учебные материалы пока не добавлены</div>';
    $output[] = '<div class="ts-empty-state-text">Когда материалы появятся, они будут доступны в соответствующем разделе.</div>';
    $output[] = '<div class="ts-empty-state-actions"><a href="' . $materialsUrl . '" class="ts-btn ts-btn-ghost ts-btn-sm">Открыть раздел</a></div>';
    $output[] = '</div>';
} else {
    $output[] = '<div class="ts-section-header ts-section-header-compact mb-3">';
    $output[] = '<div><h2 class="ts-section-title">Учебные материалы</h2><p class="ts-section-text">Последние добавленные материалы.</p></div>';
    $output[] = '<a href="' . $materialsUrl . '" class="ts-btn ts-btn-ghost ts-btn-sm">Все материалы</a>';
    $output[] = '</div>';
    $output[] = '<div class="ts-material-list">';
    foreach ($materials as $material) {
        $output[] = '<a href="' . $modx->makeUrl($material['id']) . '" class="ts-material-link">' . htmlspecialchars($material['pagetitle']) . '</a>';
    }
    $output[] = '</div>';
}

$output[] = '</div>';
$output[] = '</section>';

$output[] = '</div>';
$output[] = '</section>';

// CTA
$output[] = '<section class="ts-home-cta">';
$output[] = '<div class="ts-cta-panel ts-cta-panel-hidden" id="home-cta-block">';
$output[] = '<div class="ts-cta-content">';
$output[] = '<h2 class="ts-cta-title">Начните обучение в системе</h2>';
$output[] = '<p class="ts-cta-text">Войдите в аккаунт, чтобы проходить тесты, видеть прогресс и работать с результатами.</p>';
$output[] = '</div>';
$output[] = '<div class="ts-cta-actions">';
$output[] = '<a href="' . $loginUrl . '" class="ts-btn ts-btn-primary">Войти / Регистрация</a>';
$output[] = '</div>';
$output[] = '</div>';
$output[] = '</section>';

$output[] = '</div>';

$output[] = '<script>document.addEventListener("DOMContentLoaded",function(){var guestMode=!document.getElementById("userMenu");if(guestMode){var el=document.getElementById("home-cta-block");if(el){el.classList.remove("ts-cta-panel-hidden");}}});</script>';

return implode("\n", $output);
