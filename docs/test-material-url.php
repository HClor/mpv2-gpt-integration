<?php
/**
 * Диагностический скрипт для тестирования создания URL учебных материалов
 *
 * Выполнить через: php docs/test-material-url.php
 * Или через консоль MODX
 */

define('MODX_API_MODE', true);
require_once dirname(__DIR__) . '/index.php';

$modx->getService('error', 'error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);

echo "=== ТЕСТ СОЗДАНИЯ URL ДЛЯ УЧЕБНЫХ МАТЕРИАЛОВ ===\n\n";

// 1. Тестовые данные
$parentId = 149; // ID корневой страницы учебных материалов
$testAlias = 'test-material-' . time();
$testPagetitle = 'Тестовый материал ' . date('Y-m-d H:i:s');

echo "1. Создание тестового ресурса...\n";
echo "   Parent ID: $parentId\n";
echo "   Alias: $testAlias\n";
echo "   Pagetitle: $testPagetitle\n\n";

// 2. Создаем ресурс
$resource = $modx->newObject('modResource');
$resource->set('pagetitle', $testPagetitle);
$resource->set('alias', $testAlias);
$resource->set('content', '<p>Это тестовый материал для проверки генерации URL</p>');
$resource->set('template', 6);
$resource->set('parent', $parentId);
$resource->set('published', 1);
$resource->set('createdby', 1);
$resource->set('createdon', time());
$resource->set('publishedon', time());
$resource->set('context_key', 'web');

if (!$resource->save()) {
    die("❌ Ошибка создания ресурса\n");
}

$resourceId = $resource->get('id');
echo "✅ Ресурс создан (ID: $resourceId)\n\n";

// 3. Тест 1: makeUrl БЕЗ очистки кеша
echo "2. Тест makeUrl БЕЗ очистки кеша:\n";
$urlBeforeCache = $modx->makeUrl($resourceId, 'web', '', 'full');
echo "   URL: $urlBeforeCache\n";
if (strpos($urlBeforeCache, $testAlias) !== false) {
    echo "   ✅ URL корректный (содержит alias)\n";
} else {
    echo "   ❌ URL НЕ корректный (не содержит alias - ведёт на главную!)\n";
}
echo "\n";

// 4. Очистка кеша
echo "3. Очистка кеша...\n";
$modx->cacheManager->refresh([
    'db' => [],
    'auto_publish' => ['contexts' => ['web']],
    'context_settings' => ['contexts' => ['web']],
    'resource' => ['contexts' => ['web']],
]);
echo "   ✅ Кеш очищен\n\n";

// 5. Тест 2: makeUrl ПОСЛЕ очистки кеша
echo "4. Тест makeUrl ПОСЛЕ очистки кеша:\n";
$urlAfterCache = $modx->makeUrl($resourceId, 'web', '', 'full');
echo "   URL: $urlAfterCache\n";
if (strpos($urlAfterCache, $testAlias) !== false) {
    echo "   ✅ URL корректный (содержит alias)\n";
} else {
    echo "   ❌ URL НЕ корректный (не содержит alias - ведёт на главную!)\n";
}
echo "\n";

// 6. Тест 3: Ручное построение URL (как в коде)
echo "5. Тест РУЧНОГО построения URL:\n";
$siteUrl = rtrim($modx->getOption('site_url'), '/');
$alias = $resource->get('alias');

if ($parentId > 0) {
    $parent = $modx->getObject('modResource', $parentId);
    if ($parent) {
        $parentUri = trim($parent->get('uri'), '/');
        $manualUrl = $siteUrl . '/' . $parentUri . '/' . $alias;
    } else {
        $manualUrl = $siteUrl . '/' . $alias;
    }
} else {
    $manualUrl = $siteUrl . '/' . $alias;
}

echo "   URL: $manualUrl\n";
if (strpos($manualUrl, $testAlias) !== false) {
    echo "   ✅ URL корректный (содержит alias)\n";
} else {
    echo "   ❌ URL НЕ корректный (не содержит alias)\n";
}
echo "\n";

// 7. Сравнение
echo "6. Сравнение результатов:\n";
echo "   makeUrl (до кеша):    $urlBeforeCache\n";
echo "   makeUrl (после кеша): $urlAfterCache\n";
echo "   Ручная сборка:        $manualUrl\n\n";

// 8. Удаляем тестовый ресурс
echo "7. Очистка тестового ресурса...\n";
if ($resource->remove()) {
    echo "   ✅ Тестовый ресурс удален (ID: $resourceId)\n";
} else {
    echo "   ❌ Ошибка удаления тестового ресурса\n";
}

// 9. Очистка кеша после удаления
$modx->cacheManager->refresh();
echo "   ✅ Кеш очищен\n\n";

echo "=== ТЕСТ ЗАВЕРШЕН ===\n\n";

echo "ВЫВОД:\n";
if (strpos($manualUrl, $testAlias) !== false) {
    echo "✅ Ручное построение URL работает корректно\n";
    echo "   Рекомендация: использовать ручное построение URL в saveMaterial\n";
} else {
    echo "❌ Проблема с построением URL\n";
    echo "   Проверьте parent resource и site_url\n";
}
