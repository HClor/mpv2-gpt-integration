<?php
/**
 * Диагностический скрипт для manageCategories
 *
 * ИНСТРУКЦИЯ:
 * 1. Скопируйте этот файл в корень MODX (рядом с index.php)
 * 2. Откройте в браузере: https://mpv2.lmix.ru/diagnose-manage-categories.php
 * 3. Скопируйте весь вывод и отправьте разработчику
 * 4. УДАЛИТЕ файл после диагностики!
 */

// Инициализация MODX
define('MODX_API_MODE', true);
require_once dirname(__FILE__) . '/index.php';

$modx->getService('error','error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('HTML');

echo "<h1>🔍 Диагностика manageCategories.php</h1>";
echo "<pre style='background: #f5f5f5; padding: 20px; border-radius: 5px;'>";

// ==================== 1. ПРОВЕРКА ОКРУЖЕНИЯ ====================
echo "\n========== 1. ПРОВЕРКА ОКРУЖЕНИЯ ==========\n";
echo "PHP версия: " . PHP_VERSION . "\n";
echo "MODX версия: " . $modx->getVersionData()['full_version'] . "\n";
echo "MODX_CORE_PATH: " . MODX_CORE_PATH . "\n";
echo "Table prefix: " . $modx->getOption('table_prefix') . "\n";

// ==================== 2. ПРОВЕРКА ФАЙЛОВ ====================
echo "\n========== 2. ПРОВЕРКА ФАЙЛОВ ==========\n";

$files = [
    'bootstrap' => MODX_CORE_PATH . 'components/testsystem/bootstrap.php',
    'manageCategories' => MODX_CORE_PATH . 'elements/snippets/manageCategories.php',
    'CsrfProtection' => MODX_CORE_PATH . 'components/testsystem/security/CsrfProtection.php',
    'PermissionHelper' => MODX_CORE_PATH . 'components/testsystem/helpers/PermissionHelper.php',
    'AuthenticationException' => MODX_CORE_PATH . 'components/testsystem/exceptions/AuthenticationException.php',
];

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $readable = $exists ? is_readable($path) : false;
    echo sprintf("%-25s: %s %s\n",
        $name,
        $exists ? '✓ Exists' : '✗ NOT FOUND',
        $exists && !$readable ? '✗ NOT READABLE' : ''
    );
}

// ==================== 3. ПРОВЕРКА BOOTSTRAP ====================
echo "\n========== 3. ПРОВЕРКА BOOTSTRAP ==========\n";
try {
    require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';
    echo "✓ Bootstrap загружен успешно\n";

    // Проверяем доступность классов
    $classes = [
        'CsrfProtection',
        'PermissionHelper',
        'AuthenticationException',
        'Config',
        'ValidationHelper'
    ];

    foreach ($classes as $class) {
        if (class_exists($class)) {
            echo "✓ Класс $class доступен\n";
        } else {
            echo "✗ Класс $class НЕ НАЙДЕН\n";
        }
    }
} catch (Exception $e) {
    echo "✗ ОШИБКА при загрузке bootstrap:\n";
    echo "  " . $e->getMessage() . "\n";
    echo "  " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// ==================== 4. ПРОВЕРКА АВТОРИЗАЦИИ ====================
echo "\n========== 4. ПРОВЕРКА АВТОРИЗАЦИИ ==========\n";
echo "User ID: " . $modx->user->get('id') . "\n";
echo "Username: " . $modx->user->get('username') . "\n";
echo "Is authenticated: " . ($modx->user->hasSessionContext('web') ? 'YES' : 'NO') . "\n";

if ($modx->user->get('id') > 0) {
    // Проверяем группы
    $userGroups = $modx->user->getUserGroups();
    echo "User groups: " . print_r($userGroups, true) . "\n";

    // Проверяем админа через PermissionHelper
    if (class_exists('PermissionHelper')) {
        try {
            $isAdmin = PermissionHelper::isAdmin($modx);
            echo "Is Admin (via PermissionHelper): " . ($isAdmin ? 'YES' : 'NO') . "\n";
        } catch (Exception $e) {
            echo "✗ ОШИБКА при проверке isAdmin(): " . $e->getMessage() . "\n";
        }
    }
}

// ==================== 5. ПРОВЕРКА ТАБЛИЦ БД ====================
echo "\n========== 5. ПРОВЕРКА ТАБЛИЦ БД ==========\n";
$prefix = $modx->getOption('table_prefix');

$tables = [
    'test_categories' => "{$prefix}test_categories",
    'test_tests' => "{$prefix}test_tests",
];

foreach ($tables as $name => $table) {
    try {
        $stmt = $modx->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $exists = $stmt->fetch();

        if ($exists) {
            echo "✓ Таблица $table существует\n";

            // Проверяем структуру
            $stmt = $modx->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "  Колонки: " . implode(', ', $columns) . "\n";

            // Проверяем количество записей
            $stmt = $modx->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "  Записей: $count\n";
        } else {
            echo "✗ Таблица $table НЕ НАЙДЕНА\n";
        }
    } catch (Exception $e) {
        echo "✗ ОШИБКА при проверке таблицы $table: " . $e->getMessage() . "\n";
    }
}

// ==================== 6. ТЕСТОВЫЙ ЗАПУСК СНИППЕТА ====================
echo "\n========== 6. ТЕСТОВЫЙ ЗАПУСК СНИППЕТА ==========\n";
echo "Попытка выполнить сниппет manageCategories...\n\n";

try {
    ob_start();
    $snippetPath = MODX_CORE_PATH . 'elements/snippets/manageCategories.php';

    if (!file_exists($snippetPath)) {
        throw new Exception("Файл сниппета не найден: $snippetPath");
    }

    include $snippetPath;
    $output = ob_get_clean();

    echo "✓ Сниппет выполнен БЕЗ ОШИБОК\n";
    echo "Длина вывода: " . strlen($output) . " байт\n";
    echo "Первые 500 символов:\n";
    echo substr($output, 0, 500) . "...\n";

} catch (Error $e) {
    ob_end_clean();
    echo "✗ ФАТАЛЬНАЯ ОШИБКА PHP:\n";
    echo "  Тип: " . get_class($e) . "\n";
    echo "  Сообщение: " . $e->getMessage() . "\n";
    echo "  Файл: " . $e->getFile() . "\n";
    echo "  Строка: " . $e->getLine() . "\n";
    echo "  Трейс:\n";
    echo $e->getTraceAsString() . "\n";
} catch (Exception $e) {
    ob_end_clean();
    echo "✗ ИСКЛЮЧЕНИЕ:\n";
    echo "  Тип: " . get_class($e) . "\n";
    echo "  Сообщение: " . $e->getMessage() . "\n";
    echo "  Файл: " . $e->getFile() . "\n";
    echo "  Строка: " . $e->getLine() . "\n";
    echo "  Трейс:\n";
    echo $e->getTraceAsString() . "\n";
}

// ==================== 7. ПРОВЕРКА PHP ERROR LOG ====================
echo "\n========== 7. ПОСЛЕДНИЕ ОШИБКИ PHP ==========\n";
$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    echo "Error log: $errorLog\n";
    $lines = file($errorLog);
    $lastLines = array_slice($lines, -20);
    echo "Последние 20 строк:\n";
    echo implode('', $lastLines);
} else {
    echo "Error log не найден или не настроен\n";
}

// ==================== 8. ПРОВЕРКА MODX ERROR LOG ====================
echo "\n========== 8. ПОСЛЕДНИЕ ОШИБКИ MODX ==========\n";
$modxErrorLog = MODX_CORE_PATH . 'cache/logs/error.log';
if (file_exists($modxErrorLog)) {
    $lines = file($modxErrorLog);
    $lastLines = array_slice($lines, -20);
    echo "Последние 20 строк:\n";
    echo implode('', $lastLines);
} else {
    echo "MODX error log не найден\n";
}

echo "\n========== ДИАГНОСТИКА ЗАВЕРШЕНА ==========\n";
echo "\n⚠️ ВАЖНО: Удалите этот файл после диагностики!\n";
echo "</pre>";

echo "<h2>Дополнительные команды для консоли MODX</h2>";
echo "<pre style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "// Если у вас есть доступ к консоли MODX, выполните:\n\n";
echo "// 1. Проверить сниппет напрямую\n";
echo "\$snippet = \$modx->getObject('modSnippet', ['name' => 'manageCategories']);\n";
echo "if (\$snippet) {\n";
echo "    echo 'Snippet ID: ' . \$snippet->get('id');\n";
echo "    echo 'Content length: ' . strlen(\$snippet->get('snippet'));\n";
echo "} else {\n";
echo "    echo 'Snippet NOT FOUND in database!';\n";
echo "}\n\n";

echo "// 2. Проверить ресурс страницы\n";
echo "\$resource = \$modx->getObject('modResource', 168);\n";
echo "if (\$resource) {\n";
echo "    echo 'Page title: ' . \$resource->get('pagetitle');\n";
echo "    echo 'Content: ' . \$resource->get('content');\n";
echo "} else {\n";
echo "    echo 'Resource 168 NOT FOUND!';\n";
echo "}\n";
echo "</pre>";
