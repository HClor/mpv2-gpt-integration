<?php
/**
 * Проверка структуры таблицы test_tests
 */

define('MODX_API_MODE', true);
require_once dirname(__FILE__) . '/index.php';

$modx->getService('error','error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget('ECHO');

$prefix = $modx->getOption('table_prefix');
$tableName = "{$prefix}test_tests";

echo "=== Проверка структуры таблицы {$tableName} ===\n\n";

// Получаем структуру таблицы
$stmt = $modx->query("DESCRIBE `{$tableName}`");
if ($stmt) {
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Поля таблицы:\n";
    echo str_repeat("-", 100) . "\n";
    printf("%-25s %-30s %-10s %-10s %-10s %-20s\n", "Field", "Type", "Null", "Key", "Default", "Extra");
    echo str_repeat("-", 100) . "\n";

    foreach ($columns as $col) {
        printf("%-25s %-30s %-10s %-10s %-10s %-20s\n",
            $col['Field'],
            $col['Type'],
            $col['Null'],
            $col['Key'],
            $col['Default'] ?? 'NULL',
            $col['Extra']
        );
    }
    echo str_repeat("-", 100) . "\n\n";

    // Проверяем наличие критичных полей
    $fieldNames = array_column($columns, 'Field');

    echo "Проверка критичных полей:\n";
    $requiredFields = ['publication_status', 'is_active', 'resource_id', 'category_id', 'title'];
    foreach ($requiredFields as $field) {
        $exists = in_array($field, $fieldNames);
        echo "  - {$field}: " . ($exists ? "✓ ЕСТЬ" : "✗ ОТСУТСТВУЕТ") . "\n";
    }

    // Проверяем текущие данные
    echo "\n=== Примеры данных ===\n";
    $stmt = $modx->query("SELECT * FROM `{$tableName}` ORDER BY id DESC LIMIT 3");
    if ($stmt) {
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Последние 3 теста:\n";
        foreach ($rows as $row) {
            echo "\nID: {$row['id']}\n";
            foreach ($row as $key => $val) {
                echo "  {$key}: " . (strlen($val ?? '') > 50 ? substr($val, 0, 50) . '...' : ($val ?? 'NULL')) . "\n";
            }
        }
    }
} else {
    echo "ОШИБКА: Таблица {$tableName} не найдена!\n";
}
