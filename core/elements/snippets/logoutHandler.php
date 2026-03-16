<?php
/**
 * Сниппет: logoutHandler - Обработчик выхода
 * Вызывается из: TestSystem.tpl (некэшируемый: [[!logoutHandler]])
 * Назначение: Завершает сессию пользователя в контексте web
 *
 * @package TestSystem
 */

// Обработка выхода пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_logout'])) {
    require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

    // CSRF Protection
    if (!CsrfProtection::validateRequest($_POST)) {
        // Для logout не блокируем выход полностью: токен может быть устаревшим из кешированной страницы
        $modx->log(modX::LOG_LEVEL_WARN, '[logoutHandler] Logout with invalid CSRF token, proceeding with web context logout');
    }

    // Завершаем сессию пользователя в контексте web
    if ($modx->user->hasSessionContext('web')) {
        $modx->user->removeSessionContext('web');
    }

    // Перенаправляем на главную страницу
    $modx->sendRedirect($modx->makeUrl($modx->getOption('site_start')));
    exit;
}

return '';