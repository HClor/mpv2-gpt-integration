<?php
/**
 * TS USER MENU v1.4 - Config Integration
 */

// Подключаем bootstrap для доступа к Config
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

// Обработка выхода
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_logout'])) {
    $modx->runProcessor('security/logout');
    // Перенаправляем на главную или страницу авторизации
    $modx->sendRedirect($modx->makeUrl($modx->getOption('site_start')));
    exit;
}

$output = '<ul class="navbar-nav">';

if ($modx->user->hasSessionContext('web') && $modx->user->id > 0) {
    $username = $modx->user->username;
    
    $output .= '<li class="nav-item dropdown">';
    $output .= '<a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">';
    $output .= htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $output .= '</a>';
    $output .= '<ul class="dropdown-menu dropdown-menu-end">';
    
    // Профиль и рейтинг
    $profileUrl = $modx->makeUrl(Config::getPageId('profile', 28));
    $leaderboardUrl = $modx->makeUrl(Config::getPageId('leaderboard', 34));
    
    $output .= '<li><a class="dropdown-item" href="' . $profileUrl . '">Профиль</a></li>';
    $output .= '<li><a class="dropdown-item" href="' . $leaderboardUrl . '">Рейтинг</a></li>';
    
    // Проверка ролей через getUserGroupNames()
    $userGroups = $modx->user->getUserGroupNames();
    $isExpert = in_array(Config::getGroup('experts'), $userGroups, true);
    $isAdmin = in_array(Config::getGroup('admins'), $userGroups, true) || $modx->user->id == 1;
    
    if ($isExpert || $isAdmin) {
        $output .= '<li><hr class="dropdown-divider"></li>';
        
        $addTestUrl = $modx->makeUrl(Config::getPageId('add_test', 36));
        $output .= '<li><a class="dropdown-item" href="' . $addTestUrl . '">Создать тест</a></li>';

        if ($isAdmin) {
            $manageCategoriesUrl = $modx->makeUrl(Config::getPageId('manage_categories', 38));
            $manageUsersUrl = $modx->makeUrl(Config::getPageId('manage_users', 43));
            
            $output .= '<li><a class="dropdown-item" href="' . $manageCategoriesUrl . '">Управление категориями</a></li>';
            $output .= '<li><a class="dropdown-item" href="' . $manageUsersUrl . '">Управление пользователями</a></li>';
        }
    }
    
    // Кнопка выхода
    $output .= '<li><hr class="dropdown-divider"></li>';
    $output .= '<li class="px-3 py-2">';
    $output .= '<form method="post" action="">';
    $output .= '<input type="hidden" name="login_logout" value="1">';
    $output .= '<button type="submit" class="btn btn-sm btn-danger w-100">Выйти</button>';
    $output .= '</form>';
    $output .= '</li>';
    
    $output .= '</ul>';
    $output .= '</li>';
    
} else {
    $authUrl = $modx->makeUrl(Config::getPageId('auth', 24));
    $output .= '<li class="nav-item">';
    $output .= '<a class="nav-link btn btn-outline-primary" href="' . $authUrl . '">Войти</a>';
    $output .= '</li>';
}

$output .= '</ul>';

return $output;