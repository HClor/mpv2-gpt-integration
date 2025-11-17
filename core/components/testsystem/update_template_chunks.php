<?php
/**
 * Test System - Update Template and Chunks from Files
 *
 * Этот скрипт загружает содержимое шаблона и chunks из файлов
 * и обновляет их в базе данных MODX.
 *
 * Использование:
 *   php update_template_chunks.php
 *
 * @version 2.0
 * @date 2025-11-17
 */

// Определить путь к MODX
define('MODX_CORE_PATH', dirname(dirname(__DIR__)) . '/');
define('MODX_CONFIG_KEY', 'config');

// Попробовать подключить MODX
if (file_exists(MODX_CORE_PATH . 'model/modx/modx.class.php')) {
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    $modx = new modX();
    $modx->initialize('mgr');
    echo "MODX initialized successfully\n\n";
} else {
    die("ERROR: Cannot find MODX installation at: " . MODX_CORE_PATH . "\n");
}

// Пути к файлам
$corePath = dirname(dirname(__DIR__)) . '/';
$templatePath = $corePath . 'elements/templates/TestSystem.tpl';
$chunksPath = $corePath . 'elements/chunks/';

echo "==============================================\n";
echo "Test System - Template & Chunks Update\n";
echo "==============================================\n\n";

// Получить ID категории
$category = $modx->getObject('modCategory', array('category' => 'Test System'));
if (!$category) {
    die("ERROR: Category 'Test System' not found!\n");
}
$categoryId = $category->get('id');
echo "Category 'Test System' found (ID: $categoryId)\n\n";

// ============================================
// ОБНОВИТЬ ШАБЛОН
// ============================================

echo "Updating Template...\n";
echo "---------------------------------------------\n";

if (!file_exists($templatePath)) {
    echo "ERROR: Template file not found: $templatePath\n";
} else {
    $templateContent = file_get_contents($templatePath);

    // Найти или создать шаблон
    $template = $modx->getObject('modTemplate', array('templatename' => 'TestSystem'));

    if (!$template) {
        $template = $modx->newObject('modTemplate');
        $template->set('templatename', 'TestSystem');
        $template->set('description', 'Шаблон для системы тестирования Test System v2.0');
        $template->set('category', $categoryId);
        echo "Creating new template: TestSystem\n";
    } else {
        echo "Updating existing template: TestSystem\n";
    }

    $template->set('content', $templateContent);

    if ($template->save()) {
        echo "  ✓ Template 'TestSystem' updated successfully\n";
    } else {
        echo "  ✗ ERROR: Failed to save template\n";
    }
}

echo "\n";

// ============================================
// ОБНОВИТЬ CHUNKS
// ============================================

echo "Updating Chunks...\n";
echo "---------------------------------------------\n";

$chunks = array(
    'tsHead' => 'tsHead.tpl',
    'tsHeader' => 'tsHeader.tpl',
    'tsFooter' => 'tsFooter.tpl',
    'tsScripts' => 'tsScripts.tpl'
);

$successCount = 0;
$failCount = 0;

foreach ($chunks as $name => $file) {
    $filePath = $chunksPath . $file;

    if (!file_exists($filePath)) {
        echo "WARNING: Chunk file not found: $filePath\n";
        $failCount++;
        continue;
    }

    $content = file_get_contents($filePath);

    // Найти или создать chunk
    $chunk = $modx->getObject('modChunk', array('name' => $name));

    if (!$chunk) {
        $chunk = $modx->newObject('modChunk');
        $chunk->set('name', $name);
        $chunk->set('category', $categoryId);
        echo "Creating new chunk: $name\n";
    } else {
        echo "Updating existing chunk: $name\n";
    }

    $chunk->set('snippet', $content);
    $chunk->set('description', "Test System - $name");

    if ($chunk->save()) {
        echo "  ✓ Chunk '$name' updated successfully\n";
        $successCount++;
    } else {
        echo "  ✗ ERROR: Failed to save chunk '$name'\n";
        $failCount++;
    }
}

echo "\nChunks: $successCount updated, $failCount failed\n\n";

// ============================================
// ОЧИСТИТЬ КЕШ
// ============================================

echo "Clearing MODX cache...\n";
$modx->cacheManager->refresh();
echo "✓ Cache cleared\n\n";

// ============================================
// ИТОГИ
// ============================================

echo "==============================================\n";
echo "Update Summary\n";
echo "==============================================\n";
echo "Template: TestSystem updated\n";
echo "Chunks updated: $successCount\n";
echo "Chunks failed: $failCount\n";
echo "\n";

if ($failCount > 0) {
    echo "⚠ WARNING: Some chunks failed to update\n";
} else {
    echo "✓ All elements updated successfully!\n";
}

echo "\n";
echo "NEXT STEPS:\n";
echo "1. Go to MODX Manager -> Elements -> Templates\n";
echo "2. Check that 'TestSystem' template exists\n";
echo "3. Create a test page with this template\n";
echo "4. Visit the page to see the result\n";
echo "\n";

echo "==============================================\n";
echo "Update complete!\n";
echo "==============================================\n\n";
