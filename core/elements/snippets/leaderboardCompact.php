<?php
/**
 * leaderboardCompact - компактная версия рейтинга для главной
 */

if (!$modx instanceof modX) {
    return '';
}

$limit = (int)($scriptProperties['limit'] ?? 10);
$prefix = $modx->getOption('table_prefix');

$stmt = $modx->query("
    SELECT 
        u.id,
        u.username,
        s.tests_completed,
        s.tests_passed,
        s.avg_score_pct,
        s.current_streak
    FROM {$prefix}test_user_stats s
    JOIN {$prefix}users u ON u.id = s.user_id
    WHERE s.tests_completed > 0
    ORDER BY s.avg_score_pct DESC, s.tests_completed DESC
    LIMIT {$limit}
");

$leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($leaders)) {
    return '<div class="alert alert-info">Пока нет результатов</div>';
}

$output = [];
$position = 1;

foreach ($leaders as $leader) {
    $medal = '';
    if ($position == 1) $medal = '🥇 ';
    elseif ($position == 2) $medal = '🥈 ';
    elseif ($position == 3) $medal = '🥉 ';
    
    $score = round($leader['avg_score_pct']);
    $badgeClass = 'secondary';
    if ($score >= 90) $badgeClass = 'success';
    elseif ($score >= 70) $badgeClass = 'primary';
    
    $output[] = '<div class="d-flex align-items-center p-3 mb-2 bg-light rounded">';
    $output[] = '<div class="me-3"><span class="fs-5">' . ($medal ?: $position) . '</span></div>';
    $output[] = '<div class="flex-grow-1">';
    $output[] = '<h6 class="mb-0">' . htmlspecialchars($leader['username']) . '</h6>';
    $output[] = '<small class="text-muted">';
    $output[] = $leader['tests_completed'] . ' тестов | ';
    $output[] = 'Успешно: ' . $leader['tests_passed'];
    $output[] = '</small>';
    $output[] = '</div>';
    $output[] = '<div class="text-end">';
    $output[] = '<span class="badge bg-' . $badgeClass . ' fs-6">' . $score . '%</span>';
    $output[] = '</div>';
    $output[] = '</div>';
    
    $position++;
}

return implode('', $output);