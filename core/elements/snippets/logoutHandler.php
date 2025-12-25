<?php
/**
 * Logout Handler - глобальный обработчик выхода для фронтэнда (контекст web)
 * Вызывается в tsHeader.tpl для обработки POST запросов logout
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
