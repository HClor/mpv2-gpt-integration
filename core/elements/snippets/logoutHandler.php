<?php
/**
 * Сниппет: logoutHandler - Обработчик выхода
 * Вызывается из: TestSystem.tpl (в начале шаблона)
 * Назначение: Завершает сессию пользователя в контексте web
 *
 * @package TestSystem
 */

// Обработка выхода пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_logout'])) {
    // CSRF Protection
    require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';
    if (!CsrfProtection::validateRequest($_POST)) {
        return ''; // Тихо игнорируем невалидный запрос
    }

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