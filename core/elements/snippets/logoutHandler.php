<?php
/**
 * Сниппет: logoutHandler - Обработчик выхода
 * Вызывается из: tsHeader.tpl (через POST)
 * Назначение: Завершает сессию пользователя в контексте web
 *
 * @package TestSystem
 */

// Обработка выхода пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_logout'])) {
    // Завершаем сессию пользователя в контексте web
    if ($modx->user->hasSessionContext('web')) {
        $modx->user->removeSessionContext('web');
    }

    // Перенаправляем на главную страницу
    $modx->sendRedirect($modx->makeUrl($modx->getOption('site_start')));
    exit;
}

// Сниппет не возвращает никакого вывода - только обрабатывает logout
return '';