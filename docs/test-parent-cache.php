<?php
/**
 * Диагностика кэша родительской страницы при создании учебного материала
 *
 * Проверяет:
 * 1. Очищается ли кэш родителя после создания дочернего ресурса
 * 2. Как влияет очистка кэша родителя на отображение списка материалов
 */

define('MODX_API_MODE', true);
require_once dirname(__DIR__) . '/index.php';

$modx->getService('error','error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

echo "\n=== ДИАГНОСТИКА КЭША РОДИТЕЛЬСКОЙ СТРАНИЦЫ ===\n\n";

// ID родительской страницы для учебных материалов (обычно это страница со списком)
$parentId = 42; // TODO: заменить на реальный ID родительской страницы

echo "1. Проверяем родительскую страницу (ID: {$parentId})\n";
$parent = $modx->getObject('modResource', $parentId);

if (!$parent) {
    echo "   ❌ Родительская страница не найдена!\n";
    echo "\n   Найдем все возможные родители учебных материалов:\n";

    // Ищем все ресурсы с template=6 (учебные материалы)
    $stmt = $modx->prepare("
        SELECT DISTINCT parent, COUNT(*) as cnt
        FROM " . $modx->getTableName('modResource') . "
        WHERE template = 6 AND deleted = 0
        GROUP BY parent
        ORDER BY cnt DESC
    ");
    $stmt->execute();
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($parents as $p) {
        $parentRes = $modx->getObject('modResource', $p['parent']);
        $title = $parentRes ? $parentRes->get('pagetitle') : 'Неизвестно';
        echo "   - ID {$p['parent']}: \"{$title}\" (материалов: {$p['cnt']})\n";
    }
    exit;
}

echo "   ✓ Родитель: {$parent->get('pagetitle')}\n";
echo "   ✓ URI: {$parent->get('uri')}\n";
echo "   ✓ Published: " . ($parent->get('published') ? 'Да' : 'Нет') . "\n";

// Получаем ключ кэша родителя
$parentCacheKey = $parent->getCacheKey();
echo "   ✓ Cache Key: {$parentCacheKey}\n\n";

echo "2. Проверяем кэш родительской страницы\n";
$cache = $modx->cacheManager->getCacheProvider($modx->getOption('cache_resource_key', null, 'resource'));
$parentCache = $cache->get($parentCacheKey);

if ($parentCache) {
    echo "   ✓ Кэш родителя существует\n";
    echo "   ✓ Размер кэша: " . strlen(serialize($parentCache)) . " байт\n";
} else {
    echo "   ⚠ Кэш родителя пуст (будет создан при первом обращении)\n";
}

echo "\n3. Создаем тестовый материал\n";
$testResource = $modx->newObject('modResource');
$testResource->set('pagetitle', 'ТЕСТ: Проверка кэша родителя ' . date('H:i:s'));
$testResource->set('alias', 'test-parent-cache-' . time());
$testResource->set('content', 'Тестовый материал для проверки очистки кэша родителя');
$testResource->set('template', 6);
$testResource->set('parent', $parentId);
$testResource->set('published', 1);
$testResource->set('context_key', 'web');

if ($testResource->save()) {
    $testId = $testResource->get('id');
    echo "   ✓ Материал создан (ID: {$testId})\n";

    echo "\n4. Проверяем кэш ДО очистки родителя\n";
    $parentCacheAfter = $cache->get($parentCacheKey);
    if ($parentCacheAfter) {
        echo "   ⚠ Кэш родителя ОСТАЛСЯ СТАРЫМ (не обновился автоматически)\n";
    } else {
        echo "   ✓ Кэш родителя уже сброшен\n";
    }

    echo "\n5. Очищаем кэш ТОЛЬКО созданного материала\n";
    $testResource->clearCache();
    echo "   ✓ clearCache() вызван на материале\n";

    echo "\n6. Проверяем кэш родителя ПОСЛЕ clearCache() материала\n";
    $parentCacheAfter2 = $cache->get($parentCacheKey);
    if ($parentCacheAfter2) {
        echo "   ❌ КЭШ РОДИТЕЛЯ НЕ ОЧИСТИЛСЯ! (это проблема)\n";
        echo "   Кэш родительской страницы остался старым,\n";
        echo "   поэтому новый материал может не появиться в списке!\n";
    } else {
        echo "   ✓ Кэш родителя очищен\n";
    }

    echo "\n7. Очищаем кэш родителя вручную\n";
    $parent->clearCache();
    echo "   ✓ clearCache() вызван на родителе\n";

    $parentCacheAfter3 = $cache->get($parentCacheKey);
    if ($parentCacheAfter3) {
        echo "   ⚠ Кэш родителя всё ещё есть\n";
    } else {
        echo "   ✓ Кэш родителя очищен\n";
    }

    echo "\n8. Получаем URL созданного материала\n";

    // Пробуем разные способы
    $url1 = $modx->makeUrl($testId, '', '', 'full');
    echo "   makeUrl(): {$url1}\n";

    // Обновляем контекст
    $modx->cacheManager->refresh([
        'resource' => ['contexts' => ['web']],
    ]);

    $url2 = $modx->makeUrl($testId, '', '', 'full');
    echo "   makeUrl() после refresh: {$url2}\n";

    echo "\n9. Удаляем тестовый материал\n";
    $testResource->remove();
    echo "   ✓ Тестовый материал удален\n";

} else {
    echo "   ❌ Ошибка создания материала\n";
}

echo "\n=== РЕКОМЕНДАЦИИ ===\n";
echo "Если кэш родителя НЕ очищался автоматически, нужно:\n";
echo "1. После создания материала вызывать \$parent->clearCache()\n";
echo "2. Или очищать весь контекст: \$modx->cacheManager->refresh(['resource' => ...])\n";
echo "\n";
