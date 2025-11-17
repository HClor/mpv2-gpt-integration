<?php
/**
 * Test System - Installation Script for Snippets and Chunks
 *
 * Этот скрипт загружает содержимое snippets и chunks из файлов
 * и обновляет их в базе данных MODX.
 *
 * ВАЖНО: Запускать ПОСЛЕ выполнения MODX_INSTALL.sql
 *
 * Использование:
 *   php install_elements.php
 *
 * Или через браузер:
 *   http://your-domain.com/core/components/testsystem/install_elements.php
 *
 * @version 2.0
 * @date 2025-11-17
 */

// ============================================
// CONFIGURATION
// ============================================

// Определить путь к MODX
define('MODX_CORE_PATH', dirname(dirname(__DIR__)) . '/');
define('MODX_CONFIG_KEY', 'config');

// Попробовать подключить MODX
if (file_exists(MODX_CORE_PATH . 'model/modx/modx.class.php')) {
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    $modx = new modX();
    $modx->initialize('mgr');

    echo "MODX initialized successfully\n";
} else {
    die("ERROR: Cannot find MODX installation at: " . MODX_CORE_PATH . "\n");
}

// Пути к компонентам
$snippetsPath = dirname(__DIR__) . '/elements/snippets/';
$chunksPath = dirname(__DIR__) . '/elements/chunks/';

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Установить snippet из файла
 */
function installSnippet($modx, $name, $filePath, $categoryId) {
    if (!file_exists($filePath)) {
        echo "WARNING: Snippet file not found: $filePath\n";
        return false;
    }

    $content = file_get_contents($filePath);

    // Найти существующий snippet
    $snippet = $modx->getObject('modSnippet', array('name' => $name));

    if (!$snippet) {
        // Создать новый
        $snippet = $modx->newObject('modSnippet');
        $snippet->set('name', $name);
        echo "Creating new snippet: $name\n";
    } else {
        echo "Updating existing snippet: $name\n";
    }

    // Установить содержимое
    $snippet->set('snippet', $content);
    $snippet->set('category', $categoryId);
    $snippet->set('description', "Test System - $name");

    if ($snippet->save()) {
        echo "  ✓ Snippet '$name' installed successfully\n";
        return true;
    } else {
        echo "  ✗ ERROR: Failed to save snippet '$name'\n";
        return false;
    }
}

/**
 * Установить chunk из файла
 */
function installChunk($modx, $name, $filePath, $categoryId) {
    if (!file_exists($filePath)) {
        echo "WARNING: Chunk file not found: $filePath\n";
        return false;
    }

    $content = file_get_contents($filePath);

    // Найти существующий chunk
    $chunk = $modx->getObject('modChunk', array('name' => $name));

    if (!$chunk) {
        // Создать новый
        $chunk = $modx->newObject('modChunk');
        $chunk->set('name', $name);
        echo "Creating new chunk: $name\n";
    } else {
        echo "Updating existing chunk: $name\n";
    }

    // Установить содержимое
    $chunk->set('snippet', $content);
    $chunk->set('category', $categoryId);
    $chunk->set('description', "Test System - $name");

    if ($chunk->save()) {
        echo "  ✓ Chunk '$name' installed successfully\n";
        return true;
    } else {
        echo "  ✗ ERROR: Failed to save chunk '$name'\n";
        return false;
    }
}

// ============================================
// MAIN INSTALLATION
// ============================================

echo "\n";
echo "==============================================\n";
echo "Test System - Elements Installation\n";
echo "==============================================\n\n";

// Получить ID категории
$category = $modx->getObject('modCategory', array('category' => 'Test System'));
if (!$category) {
    die("ERROR: Category 'Test System' not found. Please run MODX_INSTALL.sql first!\n");
}
$categoryId = $category->get('id');
echo "Category 'Test System' found (ID: $categoryId)\n\n";

// ============================================
// INSTALL SNIPPETS
// ============================================

echo "Installing Snippets...\n";
echo "---------------------------------------------\n";

$snippets = array(
    'testRunner' => 'testRunner.php',
    'testsList' => 'testsList.php',
    'myTests' => 'myTests.php',
    'userProfile' => 'userProfile.php',
    'categoriesAndTests' => 'categoriesAndTests.php',
    'addTestForm' => 'addTestForm.php',
    'csvImportForm' => 'csvImportForm.php',
    'manageCategories' => 'manageCategories.php',
    'manageUsers' => 'manageUsers.php',
    'leaderboard' => 'leaderboard.php',
    'leaderboardCompact' => 'leaderboardCompact.php',
    'leaderboardCategories' => 'leaderboardCategories.php',
    'knowledgeAreasManager' => 'knowledgeAreasManager.php',
    'learningMaterials' => 'learningMaterials.php',
    'myFavorites' => 'myFavorites.php',
    'getUserStats' => 'getUserStats.php',
    'getSystemStats' => 'getSystemStats.php',
    'getTestCategories' => 'getTestCategories.php',
    'getTopUsers' => 'getTopUsers.php',
    'authHandler' => 'authHandler.php',
    'userMenu' => 'userMenu.php',
    'MenuWithACL' => 'MenuWithACL.php',
    'adminDataIntegrity' => 'adminDataIntegrity.php',
    'activateAccount' => 'activateAccount.php',
    'forgotPasswordHandler' => 'forgotPasswordHandler.php',
    'resetPasswordHandler' => 'resetPasswordHandler.php',
    'getLearningResourceIds' => 'getLearningResourceIds.php',
    'getTestInfoBatch' => 'getTestInfoBatch.php',
    'manageUsersRoleDetector' => 'manageUsersRoleDetector.php',
    'phpthumbon' => 'phpthumbon.php',
    'categoriesList' => 'categoriesList.php',
    'getUserRights' => 'getUserRights.php'
);

$successCount = 0;
$failCount = 0;

foreach ($snippets as $name => $file) {
    $filePath = $snippetsPath . $file;
    if (installSnippet($modx, $name, $filePath, $categoryId)) {
        $successCount++;
    } else {
        $failCount++;
    }
}

echo "\nSnippets: $successCount installed, $failCount failed\n\n";

// ============================================
// INSTALL CHUNKS
// ============================================

echo "Installing Chunks...\n";
echo "---------------------------------------------\n";

$chunks = array(
    'aside' => 'aside.tpl',
    'block.gallery' => 'block.gallery.tpl',
    'child_list' => 'child_list.tpl',
    'contact_form' => 'contact_form.tpl',
    'content' => 'content.tpl',
    'content_default' => 'content_default.tpl',
    'content_main' => 'content_main.tpl',
    'content_spec' => 'content_spec.tpl',
    'content_spec_list' => 'content_spec_list.tpl',
    'footer' => 'footer.tpl',
    'form.contact_form' => 'form.contact_form.tpl',
    'gallery' => 'gallery.tpl',
    'head' => 'head.tpl',
    'header' => 'header.tpl',
    'menu' => 'menu.tpl',
    'scripts' => 'scripts.tpl',
    'tpl.contact_form' => 'tpl.contact_form.tpl'
);

$chunkSuccessCount = 0;
$chunkFailCount = 0;

foreach ($chunks as $name => $file) {
    $filePath = $chunksPath . $file;
    if (installChunk($modx, $name, $filePath, $categoryId)) {
        $chunkSuccessCount++;
    } else {
        $chunkFailCount++;
    }
}

echo "\nChunks: $chunkSuccessCount installed, $chunkFailCount failed\n\n";

// ============================================
// FINALIZATION
// ============================================

echo "==============================================\n";
echo "Installation Summary\n";
echo "==============================================\n";
echo "Snippets installed: $successCount\n";
echo "Snippets failed: $failCount\n";
echo "Chunks installed: $chunkSuccessCount\n";
echo "Chunks failed: $chunkFailCount\n";
echo "\n";

if ($failCount > 0 || $chunkFailCount > 0) {
    echo "⚠ WARNING: Some elements failed to install. Check the log above.\n";
} else {
    echo "✓ All elements installed successfully!\n";
}

echo "\n";
echo "NEXT STEPS:\n";
echo "1. Clear MODX cache (Manager -> Clear Cache)\n";
echo "2. Create test pages in MODX\n";
echo "3. Configure system settings (System -> System Settings -> Filter by 'testsystem')\n";
echo "\n";

// Очистить кеш MODX
echo "Clearing MODX cache...\n";
$modx->cacheManager->refresh();
echo "✓ Cache cleared\n";

echo "\n==============================================\n";
echo "Installation complete!\n";
echo "==============================================\n\n";
