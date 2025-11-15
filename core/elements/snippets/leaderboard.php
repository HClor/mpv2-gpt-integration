<?php
/* TS LEADERBOARD v3.0 - Gamification */

$userId = $modx->user->id;
$prefix = $modx->getOption('table_prefix');

// Функция определения ранга
function getUserRank($testsCompleted) {
    if ($testsCompleted >= 51) return ['title' => '🏆 Мастер', 'class' => 'danger', 'level' => 5];
    if ($testsCompleted >= 31) return ['title' => '⭐ Эксперт', 'class' => 'warning', 'level' => 4];
    if ($testsCompleted >= 16) return ['title' => '💼 Специалист', 'class' => 'info', 'level' => 3];
    if ($testsCompleted >= 6) return ['title' => '📚 Ученик', 'class' => 'primary', 'level' => 2];
    return ['title' => '🌱 Новичок', 'class' => 'secondary', 'level' => 1];
}

// Обновляем streak для текущего пользователя
if ($userId > 0) {
    $stmt = $modx->prepare("SELECT last_activity_date, current_streak FROM {$prefix}test_user_stats WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $today = date('Y-m-d');
    $lastDate = $stats['last_activity_date'] ?? null;
    $currentStreak = (int)($stats['current_streak'] ?? 0);
    
    // Проверяем активность сегодня
    $stmtToday = $modx->prepare("
        SELECT COUNT(*) FROM {$prefix}test_sessions 
        WHERE user_id = ? AND DATE(started_at) = ? AND status = 'completed'
    ");
    $stmtToday->execute([$userId, $today]);
    $hasActivityToday = (int)$stmtToday->fetchColumn() > 0;
    
    if ($hasActivityToday && $lastDate !== $today) {
        // Есть активность сегодня
        if ($lastDate === date('Y-m-d', strtotime('-1 day'))) {
            // Вчера тоже была активность - продолжаем streak
            $currentStreak++;
        } else {
            // Streak прерван - начинаем заново
            $currentStreak = 1;
        }
        
        $stmt = $modx->prepare("
            UPDATE {$prefix}test_user_stats 
            SET last_activity_date = ?,
                current_streak = ?,
                best_streak = GREATEST(best_streak, ?)
            WHERE user_id = ?
        ");
        $stmt->execute([$today, $currentStreak, $currentStreak, $userId]);
    } elseif ($lastDate && $lastDate < date('Y-m-d', strtotime('-1 day'))) {
        // Streak прерван (не было активности вчера)
        $stmt = $modx->prepare("UPDATE {$prefix}test_user_stats SET current_streak = 0 WHERE user_id = ?");
        $stmt->execute([$userId]);
        $currentStreak = 0;
    }
}

// Получаем статистику пользователя
$myStats = null;
if ($userId > 0) {
    $stmt = $modx->prepare("
        SELECT tests_completed, tests_passed, avg_score_pct, current_streak, best_streak
        FROM {$prefix}test_user_stats
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $myStats = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Получаем общий рейтинг (с кешированием)
$cacheKey = 'testsystem/leaderboard_top50';
$cacheTTL = Config::getCacheTTL('leaderboard_ttl', 300);

$leaders = $modx->cacheManager->get($cacheKey);

if ($leaders === null) {
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
        LIMIT 50
    ");

    $leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Сохраняем в кеш
    $modx->cacheManager->set($cacheKey, $leaders, $cacheTTL);
}

$output = "";

// КАРТОЧКА ПОЛЬЗОВАТЕЛЯ
if ($userId > 0 && $myStats) {
    $testsCompleted = (int)$myStats["tests_completed"];
    
    if ($testsCompleted > 0) {
        $testsPassed = (int)$myStats["tests_passed"];
        $avgScore = round((float)$myStats["avg_score_pct"]);
        $currentStreak = (int)$myStats["current_streak"];
        $bestStreak = (int)$myStats["best_streak"];
        
        $rank = getUserRank($testsCompleted);
        
        $output .= '<div class="card mb-4 border-' . $rank['class'] . ' shadow">';
        $output .= '<div class="card-header bg-' . $rank['class'] . ' text-white">';
        $output .= '<div class="d-flex justify-content-between align-items-center">';
        $output .= '<h5 class="mb-0">Ваша статистика</h5>';
        $output .= '<span class="badge bg-white text-' . $rank['class'] . ' fs-6">' . $rank['title'] . '</span>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '<div class="card-body">';
        
        // Прогресс до следующего ранга
        $nextLevelTests = [6, 16, 31, 51, 999];
        $currentLevel = $rank['level'];
        $nextLevel = $nextLevelTests[$currentLevel - 1];
        
        if ($currentLevel < 5) {
            $progress = round(($testsCompleted / $nextLevel) * 100);
            $remaining = $nextLevel - $testsCompleted;
            
            $output .= '<div class="mb-3">';
            $output .= '<small class="text-muted">До следующего ранга: ' . $remaining . ' тестов</small>';
            $output .= '<div class="progress" style="height: 20px;">';
            $output .= '<div class="progress-bar bg-' . $rank['class'] . '" style="width: ' . $progress . '%">' . $progress . '%</div>';
            $output .= '</div>';
            $output .= '</div>';
        }
        
        $output .= '<div class="row text-center g-3">';
        
        $output .= '<div class="col-md-3">';
        $output .= '<h3 class="text-primary mb-0">' . $testsCompleted . '</h3>';
        $output .= '<p class="text-muted mb-0 small">Пройдено тестов</p>';
        $output .= '</div>';
        
        $output .= '<div class="col-md-3">';
        $output .= '<h3 class="text-success mb-0">' . $testsPassed . '</h3>';
        $output .= '<p class="text-muted mb-0 small">Сдано успешно</p>';
        $output .= '</div>';
        
        $output .= '<div class="col-md-3">';
        $output .= '<h3 class="text-warning mb-0">' . $avgScore . '%</h3>';
        $output .= '<p class="text-muted mb-0 small">Средний балл</p>';
        $output .= '</div>';
        
        $output .= '<div class="col-md-3">';
        $streakClass = $currentStreak >= 7 ? 'danger' : ($currentStreak >= 3 ? 'warning' : 'secondary');
        $output .= '<h3 class="text-' . $streakClass . ' mb-0">🔥 ' . $currentStreak . '</h3>';
        $output .= '<p class="text-muted mb-0 small">Дней подряд</p>';
        if ($bestStreak > $currentStreak) {
            $output .= '<small class="text-muted">(рекорд: ' . $bestStreak . ')</small>';
        }
        $output .= '</div>';
        
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';
    } else {
        $testsUrl = $modx->makeUrl(35);
        $output .= '<div class="alert alert-info">';
        $output .= '<h5>🌱 Начните свой путь обучения!</h5>';
        $output .= '<p>Пройдите первый тест и получите ранг "Новичок"</p>';
        $output .= '<a href="' . $testsUrl . '" class="btn btn-primary mt-2">Начать тестирование</a>';
        $output .= '</div>';
    }
}

// ТАБЛИЦА ЛИДЕРОВ
$output .= '<div class="card shadow">';
$output .= '<div class="card-header">';
$output .= '<h5 class="mb-0">🏆 Таблица лидеров</h5>';
$output .= '</div>';
$output .= '<div class="card-body p-0">';

if (empty($leaders)) {
    $output .= '<div class="p-4 text-center">';
    $output .= '<p class="text-muted mb-3">Таблица рейтинга пока пуста</p>';
    $output .= '</div>';
} else {
    $output .= '<div class="table-responsive">';
    $output .= '<table class="table table-hover mb-0">';
    $output .= '<thead class="table-light">';
    $output .= '<tr>';
    $output .= '<th style="width: 50px;">#</th>';
    $output .= '<th>Пользователь</th>';
    $output .= '<th style="width: 150px;">Ранг</th>';
    $output .= '<th style="width: 100px;">Пройдено</th>';
    $output .= '<th style="width: 100px;">Сдано</th>';
    $output .= '<th style="width: 120px;">Средний балл</th>';
    $output .= '<th style="width: 80px;">Streak</th>';
    $output .= '</tr>';
    $output .= '</thead>';
    $output .= '<tbody>';
    
    $rank = 1;
    foreach ($leaders as $leader) {
        $isCurrentUser = ($userId > 0 && (int)$leader["id"] == $userId);
        $rowClass = $isCurrentUser ? 'table-primary' : '';
        
        $userRank = getUserRank((int)$leader["tests_completed"]);
        
        $output .= '<tr class="' . $rowClass . '">';
        
        // Место
        $output .= '<td>';
        if ($rank <= 3) {
            $medals = ['🥇', '🥈', '🥉'];
            $output .= '<span class="fs-4">' . $medals[$rank - 1] . '</span>';
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
        
        // Ранг
        $output .= '<td>';
        $output .= '<span class="badge bg-' . $userRank['class'] . '">' . $userRank['title'] . '</span>';
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
        
        // Streak
        $streak = (int)($leader["current_streak"] ?? 0);
        $output .= '<td>';
        if ($streak > 0) {
            $streakColor = $streak >= 7 ? 'danger' : ($streak >= 3 ? 'warning' : 'secondary');
            $output .= '<span class="text-' . $streakColor . '">🔥 ' . $streak . '</span>';
        } else {
            $output .= '<span class="text-muted">—</span>';
        }
        $output .= '</td>';
        
        $output .= '</tr>';
        
        $rank++;
    }
    
    $output .= '</tbody>';
    $output .= '</table>';
    $output .= '</div>';
}

$output .= '</div>';
$output .= '</div>';

return $output;