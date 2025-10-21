<?php
/* TS USER MENU v1.2 CORRECT IDS */

$output = "<ul class=\"navbar-nav\">";

if ($modx->user->hasSessionContext("web") && $modx->user->id > 0) {
    $username = $modx->user->username;
    
    $output .= "<li class=\"nav-item dropdown\">";
    $output .= "<a class=\"nav-link dropdown-toggle\" href=\"#\" data-bs-toggle=\"dropdown\">";
    $output .= htmlspecialchars($username);
    $output .= "</a>";
    $output .= "<ul class=\"dropdown-menu dropdown-menu-end\">";
    
    $profileUrl = $modx->makeUrl(28);
    $leaderboardUrl = $modx->makeUrl(34);
    
    $output .= "<li><a class=\"dropdown-item\" href=\"" . $profileUrl . "\">Профиль</a></li>";
    $output .= "<li><a class=\"dropdown-item\" href=\"" . $leaderboardUrl . "\">Рейтинг</a></li>";
    
    $isExpert = $modx->user->isMember("LMS Experts");
    $isAdmin = $modx->user->isMember("LMS Admins") || $modx->user->id == 1;
    
    if ($isExpert || $isAdmin) {
        $output .= "<li><hr class=\"dropdown-divider\"></li>";
        
        $addTestUrl = $modx->makeUrl(36);
        $output .= "<li><a class=\"dropdown-item\" href=\"" . $addTestUrl . "\">Создать тест</a></li>";
        
        if ($isAdmin) {
            $manageCategoriesUrl = $modx->makeUrl(38);
            $manageUsersUrl = $modx->makeUrl(43);
            
            $output .= "<li><a class=\"dropdown-item\" href=\"" . $manageCategoriesUrl . "\">Управление категориями</a></li>";
            $output .= "<li><a class=\"dropdown-item\" href=\"" . $manageUsersUrl . "\">Управление пользователями</a></li>";
        }
    }
    
    $output .= "<li><hr class=\"dropdown-divider\"></li>";
    $output .= "<li>";
    $output .= "<form method=\"post\" class=\"px-3\">";
    $output .= "<input type=\"hidden\" name=\"login_logout\" value=\"1\">";
    $output .= "<button type=\"submit\" class=\"btn btn-sm btn-danger w-100\">Выйти</button>";
    $output .= "</form>";
    $output .= "</li>";
    
    $output .= "</ul>";
    $output .= "</li>";
    
} else {
    $authUrl = $modx->makeUrl(24);
    $output .= "<li class=\"nav-item\">";
    $output .= "<a class=\"nav-link btn btn-outline-primary\" href=\"" . $authUrl . "\">Войти</a>";
    $output .= "</li>";
}

$output .= "</ul>";

return $output;