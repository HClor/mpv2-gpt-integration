<?php
/**
 * ДИАГНОСТИКА ОБЛАСТЕЙ ЗНАНИЙ - Выполните в консоли MODX
 * Скопируйте код в MODX Console (Manage > System > System Logs > Console)
 */

// 1. ПРОВЕРКА КОНФИГУРАЦИИ
echo "=== ПРОВЕРКА КОНФИГУРАЦИИ ===\n";
$config = include MODX_CORE_PATH . 'components/testsystem/config/site.config.php';
echo "Knowledge Area Page ID: " . $config['pages']['knowledge_area'] . "\n";
echo "Manage Areas Page ID: " . $config['pages']['manage_areas'] . "\n\n";

// 2. ПРОВЕРКА РЕСУРСОВ MODX
echo "=== ПРОВЕРКА РЕСУРСОВ MODX ===\n";
$knowledgeAreaPage = $modx->getObject('modResource', $config['pages']['knowledge_area']);
if ($knowledgeAreaPage) {
    echo "✓ Страница 'Область знаний' (ID {$config['pages']['knowledge_area']}):\n";
    echo "  - Название: " . $knowledgeAreaPage->get('pagetitle') . "\n";
    echo "  - URL: " . $modx->makeUrl($config['pages']['knowledge_area'], '', '', 'full') . "\n";
    echo "  - Alias: " . $knowledgeAreaPage->get('alias') . "\n";
    echo "  - Published: " . ($knowledgeAreaPage->get('published') ? 'Да' : 'Нет') . "\n";
    $content = $knowledgeAreaPage->get('content');
    echo "  - Сниппет testRunner: " . (strpos($content, 'testRunner') !== false ? '✓ Есть' : '✗ НЕТ!') . "\n";
} else {
    echo "✗ ОШИБКА: Страница 'Область знаний' (ID {$config['pages']['knowledge_area']}) НЕ НАЙДЕНА!\n";
}
echo "\n";

$manageAreasPage = $modx->getObject('modResource', $config['pages']['manage_areas']);
if ($manageAreasPage) {
    echo "✓ Страница 'Мои области знаний' (ID {$config['pages']['manage_areas']}):\n";
    echo "  - Название: " . $manageAreasPage->get('pagetitle') . "\n";
    echo "  - URL: " . $modx->makeUrl($config['pages']['manage_areas'], '', '', 'full') . "\n";
    echo "  - Alias: " . $manageAreasPage->get('alias') . "\n";
    echo "  - Published: " . ($manageAreasPage->get('published') ? 'Да' : 'Нет') . "\n";
    $content = $manageAreasPage->get('content');
    echo "  - Сниппет knowledgeAreasManager: " . (strpos($content, 'knowledgeAreasManager') !== false ? '✓ Есть' : '✗ НЕТ!') . "\n";
} else {
    echo "✗ ОШИБКА: Страница 'Мои области знаний' (ID {$config['pages']['manage_areas']}) НЕ НАЙДЕНА!\n";
}
echo "\n";

// 3. ПРОВЕРКА СНИППЕТОВ
echo "=== ПРОВЕРКА СНИППЕТОВ ===\n";
$snippets = ['knowledgeAreasManager', 'testRunner'];
foreach ($snippets as $snippetName) {
    $snippet = $modx->getObject('modSnippet', ['name' => $snippetName]);
    if ($snippet) {
        echo "✓ Сниппет '$snippetName' найден (ID: {$snippet->get('id')})\n";
    } else {
        echo "✗ ОШИБКА: Сниппет '$snippetName' НЕ НАЙДЕН!\n";
    }
}
echo "\n";

// 4. ПРОВЕРКА БАЗЫ ДАННЫХ
echo "=== ПРОВЕРКА БАЗЫ ДАННЫХ ===\n";
$prefix = $modx->getOption('table_prefix');

// Проверка таблицы
$stmt = $modx->prepare("SHOW TABLES LIKE '{$prefix}test_knowledge_areas'");
$stmt->execute();
if ($stmt->rowCount() > 0) {
    echo "✓ Таблица {$prefix}test_knowledge_areas существует\n";

    // Структура таблицы
    $stmt = $modx->prepare("DESCRIBE {$prefix}test_knowledge_areas");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $requiredFields = ['id', 'user_id', 'name', 'description', 'test_ids',
                       'questions_per_session', 'question_distribution_mode',
                       'min_questions_per_test', 'is_active', 'created_at', 'updated_at'];

    echo "  Проверка полей:\n";
    foreach ($requiredFields as $field) {
        if (in_array($field, $columns)) {
            echo "    ✓ $field\n";
        } else {
            echo "    ✗ ОТСУТСТВУЕТ: $field\n";
        }
    }

    // Количество записей
    $stmt = $modx->prepare("SELECT COUNT(*) FROM {$prefix}test_knowledge_areas WHERE is_active = 1");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "  Активных областей знаний: $count\n";

    // Показать первые 3 записи
    if ($count > 0) {
        echo "  Примеры областей:\n";
        $stmt = $modx->prepare("SELECT id, name, user_id, test_ids FROM {$prefix}test_knowledge_areas WHERE is_active = 1 LIMIT 3");
        $stmt->execute();
        $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($areas as $area) {
            $testIds = json_decode($area['test_ids'], true);
            echo "    - ID {$area['id']}: {$area['name']} (User {$area['user_id']}, " . count($testIds) . " тестов)\n";
        }
    }
} else {
    echo "✗ ОШИБКА: Таблица {$prefix}test_knowledge_areas НЕ НАЙДЕНА!\n";
}
echo "\n";

// 5. ПРОВЕРКА AJAX ENDPOINT
echo "=== ПРОВЕРКА AJAX ENDPOINT ===\n";
$ajaxPath = MODX_ASSETS_PATH . 'components/testsystem/ajax/testsystem.php';
if (file_exists($ajaxPath)) {
    echo "✓ AJAX endpoint существует: $ajaxPath\n";
    $content = file_get_contents($ajaxPath);
    $methods = ['getKnowledgeAreas', 'createKnowledgeArea', 'updateKnowledgeArea',
                'deleteKnowledgeArea', 'getKnowledgeAreaDetails', 'getAvailableTestsTree'];
    echo "  Проверка методов:\n";
    foreach ($methods as $method) {
        if (strpos($content, "case '$method':") !== false) {
            echo "    ✓ $method\n";
        } else {
            echo "    ✗ ОТСУТСТВУЕТ: $method\n";
        }
    }
} else {
    echo "✗ ОШИБКА: AJAX endpoint НЕ НАЙДЕН!\n";
}
echo "\n";

// 6. ПРОВЕРКА JS ФАЙЛОВ
echo "=== ПРОВЕРКА JS ФАЙЛОВ ===\n";
$jsPath = MODX_ASSETS_PATH . 'components/testsystem/js/knowledge-areas.js';
if (file_exists($jsPath)) {
    echo "✓ JS файл существует: $jsPath\n";
    echo "  Размер: " . round(filesize($jsPath) / 1024, 2) . " KB\n";
} else {
    echo "✗ ОШИБКА: JS файл НЕ НАЙДЕН!\n";
}
echo "\n";

// 7. ТЕСТОВЫЙ URL
echo "=== ТЕСТОВЫЕ URL ===\n";
if ($manageAreasPage) {
    echo "Управление областями:\n";
    echo "  " . $modx->makeUrl($config['pages']['manage_areas'], '', '', 'full') . "\n\n";
}
if ($knowledgeAreaPage) {
    echo "Запуск теста из области (пример с ID=1):\n";
    echo "  " . $modx->makeUrl($config['pages']['knowledge_area'], '', ['knowledge_area' => 1], 'full') . "\n\n";
}

echo "=== ДИАГНОСТИКА ЗАВЕРШЕНА ===\n";
return 'OK';
