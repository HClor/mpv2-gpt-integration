<?php
/**
 * getTopUsers - выводит топ пользователей по баллам
 */

if (!$modx instanceof modX) {
    return '';
}

$limit = (int)($scriptProperties['limit'] ?? 10);
$tpl = $scriptProperties['tpl'] ?? 'topUserRow';
$debug = !empty($scriptProperties['debug']);

$prefix = $modx->getOption('table_prefix');
$Tsessions = $prefix . 'test_sessions';
$Users = $prefix . 'users';
$UserProfile = $prefix . 'user_attributes';

$sql = "
    SELECT 
        u.id,
        u.username,
        COALESCE(up.fullname, u.username) as fullname,
        COUNT(DISTINCT s.id) as total_sessions,
        SUM(s.score) as total_score,
        AVG(s.score) as avg_score,
        MAX(s.score) as best_score,
        SUM(CASE WHEN s.passed = 1 THEN 1 ELSE 0 END) as passed_count
    FROM `{$Users}` u
    LEFT JOIN `{$UserProfile}` up ON up.internalKey = u.id
    LEFT JOIN `{$Tsessions}` s ON s.user_id = u.id AND s.status = 'completed'
    GROUP BY u.id, u.username, up.fullname
    HAVING total_sessions > 0
    ORDER BY total_score DESC, avg_score DESC
    LIMIT {$limit}
";

if ($debug) {
    $modx->log(modX::LOG_LEVEL_ERROR, "[getTopUsers] SQL: " . $sql);
}

$stmt = $modx->prepare($sql);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($debug) {
    $modx->log(modX::LOG_LEVEL_ERROR, "[getTopUsers] Found users: " . count($users));
    if (!empty($users)) {
        $modx->log(modX::LOG_LEVEL_ERROR, "[getTopUsers] First user: " . print_r($users[0], true));
    }
}

if (empty($users)) {
    return '<div class="alert alert-info">Пока нет результатов. Завершите хотя бы один тест!</div>';
}

$output = [];
$position = 1;

foreach ($users as $user) {
    // Определяем медаль для топ-3
    $medal = '';
    if ($position == 1) {
        $medal = '<i class="bi bi-trophy-fill text-warning"></i> ';
    } elseif ($position == 2) {
        $medal = '<i class="bi bi-trophy text-secondary"></i> ';
    } elseif ($position == 3) {
        $medal = '<i class="bi bi-trophy text-danger"></i> ';
    }
    
    $placeholders = [
        'position' => $position,
        'medal' => $medal,
        'id' => $user['id'],
        'username' => htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'),
        'fullname' => htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8'),
        'total_sessions' => (int)$user['total_sessions'],
        'total_score' => (int)$user['total_score'],
        'avg_score' => round($user['avg_score'], 1),
        'best_score' => (int)$user['best_score'],
        'passed_count' => (int)$user['passed_count']
    ];
    
    $output[] = $modx->getChunk($tpl, $placeholders);
    $position++;
}

return implode('', $output);