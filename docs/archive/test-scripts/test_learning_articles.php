<?php
// Тестовый скрипт для проверки learningArticles
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Путь к MODX
$configPath = dirname(__FILE__) . '/config.core.php';
if (!file_exists($configPath)) {
    die("Config file not found at: $configPath\n");
}

// Эмуляция работы сниппета без MODX
echo "=== Проверка learningArticles ===\n\n";

// Проверяем существование файла сниппета
$snippetPath = __DIR__ . '/core/elements/snippets/learningArticles.php';
echo "Путь к сниппету: $snippetPath\n";
echo "Файл существует: " . (file_exists($snippetPath) ? "ДА" : "НЕТ") . "\n\n";

if (file_exists($snippetPath)) {
    echo "Содержимое файла (первые 500 символов):\n";
    echo substr(file_get_contents($snippetPath), 0, 500) . "...\n\n";
}

// Проверяем структуру директорий
echo "=== Структура core/elements/snippets ===\n";
$snippetsDir = __DIR__ . '/core/elements/snippets/';
if (is_dir($snippetsDir)) {
    $files = scandir($snippetsDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "- $file\n";
        }
    }
} else {
    echo "Директория не существует!\n";
}

echo "\n=== Проверка learningMaterialsTemplate.php ===\n";
$templatePath = __DIR__ . '/core/elements/snippets/learningMaterialsTemplate.php';
if (file_exists($templatePath)) {
    $content = file_get_contents($templatePath);
    // Ищем строку с learningArticles
    if (strpos($content, 'learningArticles') !== false) {
        echo "✓ Сниппет learningArticles вызывается в learningMaterialsTemplate\n";
        // Показываем контекст
        preg_match('/.*learningArticles.*/', $content, $matches);
        if (!empty($matches)) {
            echo "Строка вызова: " . trim($matches[0]) . "\n";
        }
    } else {
        echo "✗ Сниппет learningArticles НЕ вызывается в learningMaterialsTemplate\n";
    }
}

echo "\n=== РЕКОМЕНДАЦИИ ===\n";
echo "1. Проверьте в консоли браузера (F12 → Console) наличие ошибок JavaScript\n";
echo "2. Проверьте в консоли MODX наличие сниппета learningArticles\n";
echo "3. Убедитесь что у страницы 149 есть дочерние ресурсы (статьи)\n";
echo "4. Очистите кэш MODX после изменений\n";
