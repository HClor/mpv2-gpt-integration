<?php
/**
 * Диагностический скрипт для проверки работы learningMaterialsTemplate
 * Запускать в MODX Console или через браузер
 */

echo "=== ДИАГНОСТИКА learningMaterialsTemplate ===\n\n";

// 1. Проверка окружения
echo "1. ПРОВЕРКА ОКРУЖЕНИЯ:\n";
echo "   MODX_CORE_PATH: " . MODX_CORE_PATH . "\n";
echo "   PHP версия: " . PHP_VERSION . "\n\n";

// 2. Проверка наличия файлов
echo "2. ПРОВЕРКА ФАЙЛОВ:\n";
$files = [
    'Config' => MODX_CORE_PATH . 'components/testsystem/helpers/Config.php',
    'PermissionHelper' => MODX_CORE_PATH . 'components/testsystem/helpers/PermissionHelper.php',
    'learningMaterialsTemplate' => MODX_CORE_PATH . 'elements/snippets/learningMaterialsTemplate.php',
    'learningMaterials' => MODX_CORE_PATH . 'elements/snippets/learningMaterials.php'
];

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    echo "   ✓ {$name}: " . ($exists ? "✓ НАЙДЕН" : "✗ НЕ НАЙДЕН") . "\n";
    echo "     Путь: {$path}\n";
}
echo "\n";

// 3. Проверка загрузки классов
echo "3. ПРОВЕРКА ЗАГРУЗКИ КЛАССОВ:\n";
try {
    require_once MODX_CORE_PATH . 'components/testsystem/helpers/Config.php';
    echo "   ✓ Config загружен\n";

    require_once MODX_CORE_PATH . 'components/testsystem/helpers/PermissionHelper.php';
    echo "   ✓ PermissionHelper загружен\n";

    // Проверка методов Config
    echo "   ✓ Config::getGroup() доступен: " . (method_exists('Config', 'getGroup') ? 'ДА' : 'НЕТ') . "\n";

    // Проверка методов PermissionHelper
    echo "   ✓ PermissionHelper::isAuthenticated() доступен: " . (method_exists('PermissionHelper', 'isAuthenticated') ? 'ДА' : 'НЕТ') . "\n";
    echo "   ✓ PermissionHelper::isAdmin() доступен: " . (method_exists('PermissionHelper', 'isAdmin') ? 'ДА' : 'НЕТ') . "\n";

} catch (Exception $e) {
    echo "   ✗ ОШИБКА: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Проверка конфигурации
echo "4. ПРОВЕРКА КОНФИГУРАЦИИ:\n";
try {
    $configPath = MODX_CORE_PATH . 'components/testsystem/config/site.config.php';
    if (file_exists($configPath)) {
        echo "   ✓ Конфиг найден: {$configPath}\n";
        $config = require $configPath;

        if (isset($config['groups'])) {
            echo "   ✓ Группы настроены:\n";
            foreach ($config['groups'] as $key => $value) {
                echo "     - {$key}: {$value}\n";
            }
        } else {
            echo "   ✗ Секция 'groups' не найдена в конфиге\n";
        }
    } else {
        echo "   ✗ Конфиг НЕ найден: {$configPath}\n";
    }
} catch (Exception $e) {
    echo "   ✗ ОШИБКА: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Проверка текущего пользователя
echo "5. ПРОВЕРКА ПОЛЬЗОВАТЕЛЯ:\n";
try {
    $userId = (int)$modx->user->get('id');
    $username = $modx->user->get('username');
    $isAuthWeb = $modx->user->isAuthenticated('web');
    $isAuthMgr = $modx->user->isAuthenticated('mgr');
    $hasWebSession = $modx->user->hasSessionContext('web');
    $hasMgrSession = $modx->user->hasSessionContext('mgr');

    echo "   User ID: {$userId}\n";
    echo "   Username: {$username}\n";
    echo "\n";
    echo "   КОНТЕКСТ MGR (админка):\n";
    echo "     - isAuthenticated('mgr'): " . ($isAuthMgr ? 'ДА' : 'НЕТ') . "\n";
    echo "     - hasSessionContext('mgr'): " . ($hasMgrSession ? 'ДА' : 'НЕТ') . "\n";
    echo "\n";
    echo "   КОНТЕКСТ WEB (фронт):\n";
    echo "     - isAuthenticated('web'): " . ($isAuthWeb ? 'ДА' : 'НЕТ') . "\n";
    echo "     - hasSessionContext('web'): " . ($hasWebSession ? 'ДА' : 'НЕТ') . "\n";

    if (!$isAuthWeb && $userId > 0) {
        echo "\n   ⚠️  ВНИМАНИЕ: Вы авторизованы в админке, но НЕ на фронте!\n";
        echo "       Сниппет работает в контексте WEB и требует авторизации там.\n";
        echo "       Авторизуйтесь на фронте сайта (ID 2 или группа LMS Admins)\n";
    }

    if ($hasWebSession || $userId > 0) {
        $userGroups = $modx->user->getUserGroupNames();
        echo "\n   Группы пользователя: " . (empty($userGroups) ? 'НЕТ' : implode(', ', $userGroups)) . "\n";

        // Проверка прав через PermissionHelper
        if (class_exists('PermissionHelper')) {
            echo "\n   Проверка прав через PermissionHelper:\n";
            $isAdmin = PermissionHelper::isAdmin($modx);
            $isExpert = PermissionHelper::isExpert($modx);
            $isAuth = PermissionHelper::isAuthenticated($modx);
            echo "     - isAuthenticated(): " . ($isAuth ? 'ДА' : 'НЕТ') . "\n";
            echo "     - isAdmin(): " . ($isAdmin ? 'ДА' : 'НЕТ') . "\n";
            echo "     - isExpert(): " . ($isExpert ? 'ДА' : 'НЕТ') . "\n";

            if (!$isAuth) {
                echo "\n   ⚠️  PermissionHelper НЕ видит авторизацию!\n";
                echo "       Это значит $canEdit = false в сниппете.\n";
            }
        }
    }
} catch (Exception $e) {
    echo "   ✗ ОШИБКА: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Проверка ресурса "Учебные материалы" (ID 149)
echo "6. ПРОВЕРКА РЕСУРСА ID=149:\n";
try {
    $resource = $modx->getObject('modResource', 149);
    if ($resource) {
        echo "   ✓ Ресурс найден\n";
        echo "     Заголовок: " . $resource->get('pagetitle') . "\n";
        echo "     Шаблон ID: " . $resource->get('template') . "\n";
        echo "     Опубликован: " . ($resource->get('published') ? 'ДА' : 'НЕТ') . "\n";
        echo "     Создан: " . $resource->get('createdby') . "\n";

        // Получаем содержимое шаблона
        $template = $modx->getObject('modTemplate', $resource->get('template'));
        if ($template) {
            $content = $template->get('content');
            echo "     Шаблон содержит [[!learningMaterialsTemplate]]: " .
                 (strpos($content, '[[!learningMaterialsTemplate]]') !== false ? 'ДА' : 'НЕТ') . "\n";
        }
    } else {
        echo "   ✗ Ресурс с ID=149 НЕ найден\n";
    }
} catch (Exception $e) {
    echo "   ✗ ОШИБКА: " . $e->getMessage() . "\n";
}
echo "\n";

// 7. Проверка сниппета в базе
echo "7. ПРОВЕРКА СНИППЕТА В БД:\n";
try {
    $snippet = $modx->getObject('modSnippet', ['name' => 'learningMaterialsTemplate']);
    if ($snippet) {
        echo "   ✓ Сниппет найден в БД\n";
        echo "     ID: " . $snippet->get('id') . "\n";
        echo "     Категория: " . $snippet->get('category') . "\n";

        $snippetContent = $snippet->get('snippet');
        echo "     Содержит Config.php: " . (strpos($snippetContent, 'Config.php') !== false ? 'ДА' : 'НЕТ') . "\n";
        echo "     Содержит PermissionHelper.php: " . (strpos($snippetContent, 'PermissionHelper.php') !== false ? 'ДА' : 'НЕТ') . "\n";
    } else {
        echo "   ✗ Сниппет НЕ найден в БД\n";
        echo "   ! Нужно обновить сниппет через MODX Manager или запустить build скрипт\n";
    }
} catch (Exception $e) {
    echo "   ✗ ОШИБКА: " . $e->getMessage() . "\n";
}
echo "\n";

// 8. Тестовый запуск сниппета
echo "8. ТЕСТОВЫЙ ЗАПУСК СНИППЕТА:\n";
try {
    // Сохраняем текущий ресурс
    $currentResource = $modx->resource;

    // Устанавливаем ресурс 149 как текущий
    $testResource = $modx->getObject('modResource', 149);
    if ($testResource) {
        $modx->resource = $testResource;

        echo "   Запуск сниппета...\n";
        $output = $modx->runSnippet('learningMaterialsTemplate');

        if (empty($output)) {
            echo "   ✗ Сниппет вернул ПУСТОЙ результат\n";
            echo "   ! Проверьте PHP error log для деталей\n";
        } else {
            $outputLength = mb_strlen($output);
            echo "   ✓ Сниппет вернул результат\n";
            echo "     Длина вывода: {$outputLength} символов\n";
            echo "     Содержит вкладки: " . (strpos($output, 'nav-tabs') !== false ? 'ДА' : 'НЕТ') . "\n";
            echo "     Содержит модальное окно: " . (strpos($output, 'materialEditorModal') !== false ? 'ДА' : 'НЕТ') . "\n";

            // Первые 500 символов вывода
            echo "\n   Первые 500 символов вывода:\n";
            echo "   " . str_replace("\n", "\n   ", mb_substr($output, 0, 500)) . "...\n";
        }

        // Восстанавливаем текущий ресурс
        $modx->resource = $currentResource;
    } else {
        echo "   ✗ Ресурс 149 не найден для тестирования\n";
    }
} catch (Exception $e) {
    echo "   ✗ ОШИБКА при запуске: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}
echo "\n";

// 9. Проверка error log
echo "9. ПРОВЕРКА PHP ERROR LOG:\n";
$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    echo "   Путь: {$errorLog}\n";
    echo "   Последние 10 строк:\n";
    $lines = file($errorLog);
    $lastLines = array_slice($lines, -10);
    foreach ($lastLines as $line) {
        echo "   " . trim($line) . "\n";
    }
} else {
    echo "   Error log не настроен или не найден\n";
}
echo "\n";

// 10. Проверка дочерних ресурсов (учебные статьи)
echo "10. ПРОВЕРКА ДОЧЕРНИХ РЕСУРСОВ (Учебные статьи):\n";
try {
    $children = $modx->getCollection('modResource', [
        'parent' => 149,
        'deleted' => 0
    ]);

    if (count($children) > 0) {
        echo "   ✓ Найдено дочерних ресурсов: " . count($children) . "\n";
        foreach ($children as $child) {
            echo "     - ID {$child->get('id')}: {$child->get('pagetitle')} ";
            echo "(шаблон: {$child->get('template')}, опубликован: " . ($child->get('published') ? 'ДА' : 'НЕТ') . ")\n";
        }
    } else {
        echo "   ! Дочерних ресурсов не найдено\n";
    }
} catch (Exception $e) {
    echo "   ✗ ОШИБКА: " . $e->getMessage() . "\n";
}
echo "\n";

// 11. Рекомендации
echo "11. РЕКОМЕНДАЦИИ ПО РЕШЕНИЮ ПРОБЛЕМ:\n";

$hasIssues = false;

// Проверка авторизации
try {
    $isAuthWeb = $modx->user->isAuthenticated('web');
    $userId = (int)$modx->user->get('id');

    if (!$isAuthWeb && $userId > 0) {
        echo "\n   ⚠️  ПРОБЛЕМА: Не авторизованы в WEB контексте\n";
        echo "   РЕШЕНИЕ:\n";
        echo "   1. Авторизуйтесь на фронте сайта через форму входа\n";
        echo "   2. Используйте пользователя с ID=2 или из группы 'LMS Admins'\n";
        echo "   3. Или измените проверку прав в сниппете (см. ниже)\n\n";
        $hasIssues = true;
    }

    if ($isAuthWeb) {
        $userGroups = $modx->user->getUserGroupNames();
        $config = require MODX_CORE_PATH . 'components/testsystem/config/site.config.php';
        $isInAdminGroup = in_array($config['groups']['admins'], $userGroups);
        $isInExpertGroup = in_array($config['groups']['experts'], $userGroups);

        if (!$isInAdminGroup && !$isInExpertGroup && $userId !== 1 && $userId !== 2) {
            echo "\n   ⚠️  ПРОБЛЕМА: У пользователя нет прав админа/эксперта\n";
            echo "   РЕШЕНИЕ:\n";
            echo "   1. Добавьте пользователя в группу 'LMS Admins' или 'LMS Experts'\n";
            echo "   2. В MODX Manager: Безопасность → Управление пользователями → Группы\n\n";
            $hasIssues = true;
        }
    }
} catch (Exception $e) {
    // ignore
}

// Проверка сниппета в БД
try {
    $snippet = $modx->getObject('modSnippet', ['name' => 'learningMaterialsTemplate']);
    if (!$snippet) {
        echo "\n   ⚠️  ПРОБЛЕМА: Сниппет не найден в БД\n";
        echo "   РЕШЕНИЕ:\n";
        echo "   1. Создайте сниппет в MODX Manager: Элементы → Сниппеты → Создать\n";
        echo "   2. Или запустите build скрипт для установки элементов\n\n";
        $hasIssues = true;
    } else {
        $content = $snippet->get('snippet');
        if (strpos($content, 'Config.php') === false) {
            echo "\n   ⚠️  ПРОБЛЕМА: В сниппете нет подключения Config.php\n";
            echo "   РЕШЕНИЕ:\n";
            echo "   1. Обновите сниппет из файла:\n";
            echo "      " . MODX_CORE_PATH . "elements/snippets/learningMaterialsTemplate.php\n";
            echo "   2. В MODX Manager: Элементы → Сниппеты → learningMaterialsTemplate → Редактировать\n\n";
            $hasIssues = true;
        }
    }
} catch (Exception $e) {
    // ignore
}

if (!$hasIssues) {
    echo "\n   ✅ Критических проблем не обнаружено!\n";
    echo "   Сниппет должен работать корректно.\n\n";
}

echo "\n=== ДОПОЛНИТЕЛЬНАЯ ИНФОРМАЦИЯ ===\n\n";
echo "Альтернативное решение - изменить проверку прав:\n";
echo "Если хотите, чтобы админы из mgr могли редактировать материалы,\n";
echo "измените в learningMaterialsTemplate.php строку 17:\n\n";
echo "БЫЛО:\n";
echo "if (PermissionHelper::isAuthenticated(\$modx)) {\n\n";
echo "СТАЛО:\n";
echo "if (PermissionHelper::isAuthenticated(\$modx) || \$modx->user->isAuthenticated('mgr')) {\n\n";
echo "Это позволит редактировать материалы пользователям,\n";
echo "авторизованным в админке (mgr контексте).\n";

echo "\n=== ДИАГНОСТИКА ЗАВЕРШЕНА ===\n";
