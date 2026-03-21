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
        $modx->log(modX::LOG_LEVEL_WARN, '[logoutHandler] Logout blocked due to invalid CSRF token');
        return '';
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
