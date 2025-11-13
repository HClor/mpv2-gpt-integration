<?php
/**
 * getUserStats - статистика текущего пользователя
 */

if (!$modx instanceof modX) {
    return '';
}

$userId = $modx->user->id;

if (!$userId) {
    return '<p class="text-muted">Войдите, чтобы видеть статистику</p>';
}

$prefix = $modx->getOption('table_prefix');
$Tsessions = $prefix . 'test_sessions';

// Получаем статистику
$sql = "
    SELECT 
        COUNT(DISTINCT id) as total_sessions,
        SUM(score) as total_score,
        AVG(score) as avg_score,
        MAX(score) as best_score,
        SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed_count
    FROM `{$Tsessions}`
    WHERE user_id = :userId AND status = 'completed'
";

$stmt = $modx->prepare($sql);
$stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$stats || $stats['total_sessions'] == 0) {
    return '
        <p class="text-muted mb-3">Вы еще не прошли ни одного теста.</p>
        <a href="' . $modx->makeUrl(35) . '" class="btn btn-primary btn-sm">
            <i class="bi bi-play-circle"></i> Начать тестирование
        </a>
    ';
}

// Вычисляем позицию пользователя в рейтинге
$sqlRank = "
    SELECT COUNT(*) + 1 as user_rank
    FROM (
        SELECT u.id, SUM(s.score) as total_score
        FROM `{$prefix}users` u
        LEFT JOIN `{$Tsessions}` s ON s.user_id = u.id AND s.status = 'completed'
        GROUP BY u.id
        HAVING total_score > :userScore
    ) ranked
";

$stmtRank = $modx->prepare($sqlRank);
$stmtRank->bindValue(':userScore', $stats['total_score'], PDO::PARAM_INT);
$stmtRank->execute();
$rank = $stmtRank->fetchColumn();

$html = [];
$html[] = '<div class="mb-3 p-3 bg-light rounded text-center">';
$html[] = '<div class="display-6 fw-bold text-primary">#' . $rank . '</div>';
$html[] = '<small class="text-muted">Ваше место в рейтинге</small>';
$html[] = '</div>';

$html[] = '<ul class="list-unstyled mb-0">';
$html[] = '<li class="mb-2 d-flex justify-content-between">';
$html[] = '<span><i class="bi bi-clipboard-check text-primary"></i> Пройдено тестов:</span>';
$html[] = '<strong>' . (int)$stats['total_sessions'] . '</strong>';
$html[] = '</li>';

$html[] = '<li class="mb-2 d-flex justify-content-between">';
$html[] = '<span><i class="bi bi-check-circle-fill text-success"></i> Успешно:</span>';
$html[] = '<strong>' . (int)$stats['passed_count'] . '</strong>';
$html[] = '</li>';

$html[] = '<li class="mb-2 d-flex justify-content-between">';
$html[] = '<span><i class="bi bi-graph-up text-info"></i> Средний балл:</span>';
$html[] = '<strong>' . round($stats['avg_score'], 1) . '%</strong>';
$html[] = '</li>';

$html[] = '<li class="mb-2 d-flex justify-content-between">';
$html[] = '<span><i class="bi bi-trophy-fill text-warning"></i> Лучший результат:</span>';
$html[] = '<strong>' . (int)$stats['best_score'] . '%</strong>';
$html[] = '</li>';

$html[] = '<li class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">';
$html[] = '<span class="fw-bold">Всего баллов:</span>';
$html[] = '<span class="text-primary fw-bold fs-3">' . (int)$stats['total_score'] . '</span>';
$html[] = '</li>';

$html[] = '</ul>';

return implode('', $html);