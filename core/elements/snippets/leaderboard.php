<?php
/* TS LEADERBOARD v2.4 FINAL */

$userId = $modx->user->id;

$myStats = null;
if ($userId > 0) {
    $stmt = $modx->prepare("
        SELECT tests_completed, tests_passed, avg_score_pct
        FROM modx_test_user_stats
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $myStats = $stmt->fetch(PDO::FETCH_ASSOC);
}

$stmt = $modx->query("
    SELECT 
        u.id,
        u.username,
        s.tests_completed,
        s.tests_passed,
        s.avg_score_pct
    FROM modx_test_user_stats s
    JOIN modx_users u ON u.id = s.user_id
    WHERE s.tests_completed > 0
    ORDER BY s.avg_score_pct DESC, s.tests_completed DESC
    LIMIT 50
");

$leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$output = "";

if ($userId > 0) {
    $testsCompleted = $myStats ? (int)$myStats["tests_completed"] : 0;
    
    if ($testsCompleted > 0) {
        $testsPassed = (int)$myStats["tests_passed"];
        $avgScore = round((float)$myStats["avg_score_pct"]);
        
        $output .= "<div class=\"card mb-4 border-primary\">";
        $output .= "<div class=\"card-header bg-primary text-white\">";
        $output .= "<h5 class=\"mb-0\">Ваша статистика</h5>";
        $output .= "</div>";
        $output .= "<div class=\"card-body\">";
        
        $output .= "<div class=\"row text-center\">";
        
        $output .= "<div class=\"col-md-4\">";
        $output .= "<h3 class=\"text-primary mb-0\">" . $testsCompleted . "</h3>";
        $output .= "<p class=\"text-muted mb-0\">Пройдено тестов</p>";
        $output .= "</div>";
        
        $output .= "<div class=\"col-md-4\">";
        $output .= "<h3 class=\"text-success mb-0\">" . $testsPassed . "</h3>";
        $output .= "<p class=\"text-muted mb-0\">Сдано успешно</p>";
        $output .= "</div>";
        
        $output .= "<div class=\"col-md-4\">";
        $output .= "<h3 class=\"text-warning mb-0\">" . $avgScore . "%</h3>";
        $output .= "<p class=\"text-muted mb-0\">Средний балл</p>";
        $output .= "</div>";
        
        $output .= "</div>";
        
        $output .= "</div>";
        $output .= "</div>";
    } else {
        $testsUrl = $modx->makeUrl(35);
        
        $output .= "<div class=\"alert alert-info\">";
        $output .= "<h5>Вы ещё не проходили ни одного теста</h5>";
        $output .= "<a href=\"" . $testsUrl . "\" class=\"btn btn-primary mt-2\">Начать тестирование</a>";
        $output .= "</div>";
    }
}

$output .= "<div class=\"card\">";
$output .= "<div class=\"card-header\">";
$output .= "<h5 class=\"mb-0\">Таблица рейтинга</h5>";
$output .= "</div>";
$output .= "<div class=\"card-body p-0\">";

if (empty($leaders)) {
    $testsUrl = $modx->makeUrl(35);
    
    $output .= "<div class=\"p-4 text-center\">";
    $output .= "<p class=\"text-muted mb-3\">Таблица рейтинга пока пуста</p>";
    $output .= "<a href=\"" . $testsUrl . "\" class=\"btn btn-primary\">Перейти к тестам</a>";
    $output .= "</div>";
} else {
    $output .= "<div class=\"table-responsive\">";
    $output .= "<table class=\"table table-hover mb-0\">";
    $output .= "<thead class=\"table-light\">";
    $output .= "<tr>";
    $output .= "<th style=\"width: 50px;\">#</th>";
    $output .= "<th>Пользователь</th>";
    $output .= "<th style=\"width: 150px;\">Пройдено</th>";
    $output .= "<th style=\"width: 150px;\">Сдано</th>";
    $output .= "<th style=\"width: 150px;\">Средний балл</th>";
    $output .= "</tr>";
    $output .= "</thead>";
    $output .= "<tbody>";
    
    $rank = 1;
    foreach ($leaders as $leader) {
        $isCurrentUser = ($userId > 0 && (int)$leader["id"] == $userId);
        $rowClass = $isCurrentUser ? "table-primary" : "";
        
        $output .= "<tr class=\"" . $rowClass . "\">";
        
        $output .= "<td>";
        if ($rank <= 3) {
            $badgeColors = ["warning", "secondary", "danger"];
            $output .= "<span class=\"badge bg-" . $badgeColors[$rank - 1] . "\">" . $rank . "</span>";
        } else {
            $output .= $rank;
        }
        $output .= "</td>";
        
        $output .= "<td>";
        $output .= "<strong>" . htmlspecialchars($leader["username"]) . "</strong>";
        if ($isCurrentUser) {
            $output .= " <span class=\"badge bg-primary\">Вы</span>";
        }
        $output .= "</td>";
        
        $output .= "<td>" . (int)$leader["tests_completed"] . "</td>";
        
        $output .= "<td class=\"text-success\">" . (int)$leader["tests_passed"] . "</td>";
        
        $score = round((float)$leader["avg_score_pct"]);
        $badgeClass = "secondary";
        if ($score >= 90) $badgeClass = "success";
        elseif ($score >= 70) $badgeClass = "primary";
        elseif ($score >= 50) $badgeClass = "warning";
        else $badgeClass = "danger";
        
        $output .= "<td>";
        $output .= "<span class=\"badge bg-" . $badgeClass . " fs-6\">" . $score . "%</span>";
        $output .= "</td>";
        
        $output .= "</tr>";
        
        $rank++;
    }
    
    $output .= "</tbody>";
    $output .= "</table>";
    $output .= "</div>";
}

$output .= "</div>";
$output .= "</div>";

return $output;