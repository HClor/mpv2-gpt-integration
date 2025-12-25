<?php
/**
 * Установка сниппета logoutHandler в MODX Revolution
 *
 * Использование:
 * 1. Загрузите этот файл в корень сайта MODX
 * 2. Откройте в браузере: http://ваш-сайт.ru/install_logout_handler.php
 * 3. После успешной установки удалите этот файл
 */

// Подключаем MODX
define('MODX_API_MODE', true);
require_once dirname(__FILE__) . '/index.php';

// Проверяем, авторизован ли пользователь как админ
$modx->getService('error', 'error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('HTML');

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Установка logoutHandler</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; background: #e8f5e9; padding: 15px; border-radius: 5px; }
        .error { color: red; background: #ffebee; padding: 15px; border-radius: 5px; }
        .info { color: blue; background: #e3f2fd; padding: 15px; border-radius: 5px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Установка сниппета logoutHandler</h1>
";

try {
    // Проверяем, существует ли уже сниппет
    $existingSnippet = $modx->getObject('modSnippet', array('name' => 'logoutHandler'));
    if ($existingSnippet) {
        echo "<div class='info'>ℹ️ Сниппет logoutHandler уже существует (ID: {$existingSnippet->id}). Обновляю...</div>";
        $snippet = $existingSnippet;
    } else {
        echo "<div class='info'>ℹ️ Создаю новый сниппет logoutHandler...</div>";
        $snippet = $modx->newObject('modSnippet');
        $snippet->set('name', 'logoutHandler');
    }

    // Читаем код сниппета из файла
    $snippetFile = dirname(__FILE__) . '/core/elements/snippets/logoutHandler.php';

    if (!file_exists($snippetFile)) {
        throw new Exception("Файл сниппета не найден: {$snippetFile}");
    }

    $snippetCode = file_get_contents($snippetFile);

    // Убираем открывающий тег PHP
    $snippetCode = preg_replace('/^<\?php\s*/i', '', $snippetCode);

    // Устанавливаем свойства сниппета
    $snippet->set('description', 'Глобальный обработчик выхода пользователя для фронтэнда (контекст web)');
    $snippet->set('snippet', $snippetCode);

    // Ищем категорию Test System
    $category = $modx->getObject('modCategory', array('category' => 'Test System'));
    if ($category) {
        $snippet->set('category', $category->id);
        echo "<div class='info'>ℹ️ Категория: Test System (ID: {$category->id})</div>";
    } else {
        $snippet->set('category', 0);
        echo "<div class='info'>ℹ️ Категория: без категории</div>";
    }

    // Сохраняем сниппет
    if ($snippet->save()) {
        echo "<div class='success'>✅ Сниппет logoutHandler успешно установлен (ID: {$snippet->id})</div>";

        // Показываем код сниппета
        echo "<h2>Код сниппета:</h2>";
        echo "<pre>" . htmlspecialchars($snippetCode) . "</pre>";

        echo "<h2>Следующие шаги:</h2>";
        echo "<ol>";
        echo "<li><strong>Очистите кеш MODX:</strong> Админка → Управление → Очистить кеш</li>";
        echo "<li><strong>Проверьте tsHeader.tpl:</strong> Убедитесь, что в начале есть вызов <code>[[!logoutHandler]]</code></li>";
        echo "<li><strong>Удалите этот файл:</strong> <code>install_logout_handler.php</code> (из соображений безопасности)</li>";
        echo "<li><strong>Проверьте работу:</strong> Попробуйте выйти из аккаунта на фронтенде</li>";
        echo "</ol>";

        // Очищаем кеш
        $modx->cacheManager->refresh();
        echo "<div class='success'>✅ Кеш MODX очищен автоматически</div>";

    } else {
        throw new Exception("Не удалось сохранить сниппет в базу данных");
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ Ошибка: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='error'><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></div>";
}

echo "</body></html>";
