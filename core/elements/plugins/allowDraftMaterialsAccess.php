<?php
/**
 * Allow Draft Materials Access Plugin
 *
 * Разрешает авторам, администраторам и экспертам просматривать неопубликованные учебные материалы
 *
 * @name AllowDraftMaterialsAccess
 * @events OnPageNotFound
 * @package TestSystem
 * @version 1.0
 * @created 2026-01-13
 *
 * УСТАНОВКА:
 * 1. Создайте новый плагин в MODX Manager
 * 2. Скопируйте содержимое этого файла в код плагина
 * 3. Подпишите плагин на событие: OnPageNotFound
 * 4. Сохраните и активируйте плагин
 *
 * ОПИСАНИЕ:
 * Когда пользователь пытается открыть неопубликованную страницу (published=0),
 * MODX обычно показывает ошибку 404. Этот плагин перехватывает такие запросы
 * и проверяет:
 * - Является ли страница учебным материалом (template=6)
 * - Имеет ли пользователь права на просмотр (автор, администратор или эксперт)
 * Если условия выполнены, плагин разрешает доступ к странице.
 */

// Получаем событие
$eventName = $modx->event->name;

if ($eventName !== 'OnPageNotFound') {
    return;
}

// Подключаем необходимые хелперы
require_once MODX_CORE_PATH . 'components/testsystem/helpers/Config.php';
require_once MODX_CORE_PATH . 'components/testsystem/helpers/PermissionHelper.php';

// Проверяем, авторизован ли пользователь
if (!PermissionHelper::isAuthenticated($modx) && !$modx->user->isAuthenticated('mgr')) {
    // Неавторизованные пользователи не могут видеть черновики
    return;
}

// Получаем ID запрошенной страницы из URL
$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Пытаемся найти ресурс по URL
$resourceId = $modx->findResource($requestPath);

if (!$resourceId) {
    // Если не нашли по алиасу, пробуем найти по ID в query string
    if (isset($_GET['id'])) {
        $resourceId = (int)$_GET['id'];
    }
}

if (!$resourceId) {
    // Не смогли определить ID ресурса
    return;
}

// Загружаем ресурс напрямую, игнорируя published статус
$resource = $modx->getObject('modResource', $resourceId);

if (!$resource) {
    // Ресурс не существует
    return;
}

// Проверяем, что это неопубликованный учебный материал
$isUnpublished = $resource->get('published') == 0;
$isLearningMaterial = $resource->get('template') == 6; // Template ID для учебных материалов
$isDeleted = $resource->get('deleted') == 1;

if (!$isUnpublished || !$isLearningMaterial || $isDeleted) {
    // Это не неопубликованный учебный материал или он удален
    return;
}

// Проверяем права доступа
$userId = PermissionHelper::getCurrentUserId($modx);
$isAdmin = PermissionHelper::isAdmin($modx) || $modx->user->isAuthenticated('mgr');
$isExpert = PermissionHelper::isExpert($modx);
$isAuthor = ($resource->get('createdby') == $userId);

if (!$isAuthor && !$isAdmin && !$isExpert) {
    // Пользователь не имеет прав на просмотр этого черновика
    return;
}

// РАЗРЕШАЕМ ДОСТУП: устанавливаем текущий ресурс и отменяем 404
$modx->resource = $resource;
$modx->resourceIdentifier = $resourceId;

// Отправляем корректный HTTP статус
header('HTTP/1.1 200 OK');

// Останавливаем обработку события PageNotFound
$modx->event->stopPropagation();

// Подготавливаем ресурс к выводу
$modx->resource->_output = $modx->resource->process();

// Логируем доступ к черновику (опционально)
$modx->log(modX::LOG_LEVEL_INFO,
    "AllowDraftMaterialsAccess: User #{$userId} accessed draft material #{$resourceId} - " .
    $resource->get('pagetitle')
);

return;
