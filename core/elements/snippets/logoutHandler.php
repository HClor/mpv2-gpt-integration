<?php
/**
 * Сниппет: logoutHandler - Обработчик выхода
 * Вызывается из: TestSystem.tpl (в начале шаблона)
 * Назначение: Завершает сессию пользователя в контексте web
 *
 * @package TestSystem
 */

// ДИАГНОСТИКА: логируем каждый вызов сниппета
$modx->log(modX::LOG_LEVEL_INFO, '[logoutHandler] Snippet called. Method: ' . $_SERVER['REQUEST_METHOD'] . ', POST keys: ' . implode(',', array_keys($_POST ?: [])));

// Обработка выхода пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_logout'])) {
    // CSRF Protection
    require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

    // Диагностика: логируем попытку logout
    $modx->log(modX::LOG_LEVEL_INFO, '[logoutHandler] Logout attempt. POST: ' . json_encode($_POST));

    if (!CsrfProtection::validateRequest($_POST)) {
        $modx->log(modX::LOG_LEVEL_WARN, '[logoutHandler] CSRF validation failed');
        return ''; // Тихо игнорируем невалидный запрос
    }

    $modx->log(modX::LOG_LEVEL_INFO, '[logoutHandler] CSRF valid, proceeding with logout');

    // Завершаем сессию пользователя в контексте web
    if ($modx->user->hasSessionContext('web')) {
        $modx->user->removeSessionContext('web');
        $modx->log(modX::LOG_LEVEL_INFO, '[logoutHandler] Session context removed');
    }

    // Перенаправляем на главную страницу
    $modx->sendRedirect($modx->makeUrl($modx->getOption('site_start')));
    exit;
}

// Сниппет не возвращает никакого вывода - только обрабатывает logout
return '';