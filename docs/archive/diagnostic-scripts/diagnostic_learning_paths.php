<?php
/**
 * Диагностика траекторий обучения
 *
 * Этот файл проверяет:
 * - Наличие всех таблиц
 * - Структуру таблиц
 * - Текущие данные
 * - Триггеры
 *
 * ВЫПОЛНИТЬ В: console MODX или браузере
 */

// Подключаем MODX
$configPath = dirname(__FILE__) . '/config.core.php';
if (!file_exists($configPath)) {
    die('Config file not found');
}

require_once $configPath;
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('web');

$prefix = $modx->getOption('table_prefix', null, 'modx_');

echo "<h1>Диагностика траекторий обучения</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #4CAF50; color: white; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .section { margin: 30px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #4CAF50; }
</style>";

// ============================================
// 1. ПРОВЕРКА ТАБЛИЦ
// ============================================
echo "<div class='section'>";
echo "<h2>1. Проверка таблиц траекторий обучения</h2>";

$requiredTables = [
    'test_learning_paths',
    'test_learning_path_steps',
    'test_learning_path_enrollments',
    'test_learning_path_progress',
    'test_learning_path_step_completion',
    'test_learning_path_achievements',
    'test_learning_path_user_achievements'
];

echo "<table>";
echo "<tr><th>Таблица</th><th>Статус</th><th>Записей</th></tr>";

foreach ($requiredTables as $table) {
    $fullTableName = $prefix . $table;

    // Проверяем существование таблицы
    $stmt = $modx->query("SHOW TABLES LIKE '$fullTableName'");
    $exists = $stmt && $stmt->rowCount() > 0;

    if ($exists) {
        // Считаем количество записей
        $stmt = $modx->query("SELECT COUNT(*) as cnt FROM $fullTableName");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        echo "<tr><td>$fullTableName</td><td class='success'>✓ Существует</td><td>$count</td></tr>";
    } else {
        echo "<tr><td>$fullTableName</td><td class='error'>✗ Отсутствует</td><td>-</td></tr>";
    }
}

echo "</table>";
echo "</div>";

// ============================================
// 2. ПРОВЕРКА ТРИГГЕРОВ
// ============================================
echo "<div class='section'>";
echo "<h2>2. Проверка триггеров</h2>";

$triggers = [
    'trg_enrollment_create_progress',
    'trg_step_completion_update_progress'
];

echo "<table>";
echo "<tr><th>Триггер</th><th>Статус</th></tr>";

foreach ($triggers as $trigger) {
    $stmt = $modx->query("
        SELECT TRIGGER_NAME
        FROM information_schema.TRIGGERS
        WHERE TRIGGER_SCHEMA = DATABASE()
        AND TRIGGER_NAME = '$trigger'
    ");

    $exists = $stmt && $stmt->rowCount() > 0;

    if ($exists) {
        echo "<tr><td>$trigger</td><td class='success'>✓ Существует</td></tr>";
    } else {
        echo "<tr><td>$trigger</td><td class='error'>✗ Отсутствует</td></tr>";
    }
}

echo "</table>";
echo "</div>";

// ============================================
// 3. ТЕКУЩИЕ ДАННЫЕ
// ============================================
echo "<div class='section'>";
echo "<h2>3. Текущие траектории обучения</h2>";

$sql = "SELECT
            lp.id,
            lp.name,
            lp.description,
            lp.status,
            lp.difficulty_level,
            lp.estimated_hours,
            lp.created_at,
            u.username as author,
            (SELECT COUNT(*) FROM {$prefix}test_learning_path_steps WHERE path_id = lp.id) as steps_count,
            (SELECT COUNT(*) FROM {$prefix}test_learning_path_enrollments WHERE path_id = lp.id AND is_active = 1) as enrollments_count
        FROM {$prefix}test_learning_paths lp
        LEFT JOIN {$prefix}users u ON u.id = lp.created_by
        ORDER BY lp.created_at DESC
        LIMIT 20";

$stmt = $modx->query($sql);
$paths = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

if (count($paths) > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Название</th><th>Статус</th><th>Сложность</th><th>Шагов</th><th>Записей</th><th>Автор</th></tr>";

    foreach ($paths as $path) {
        $statusClass = $path['status'] === 'published' ? 'success' : 'warning';
        echo "<tr>";
        echo "<td>{$path['id']}</td>";
        echo "<td>{$path['name']}</td>";
        echo "<td class='$statusClass'>{$path['status']}</td>";
        echo "<td>{$path['difficulty_level']}</td>";
        echo "<td>{$path['steps_count']}</td>";
        echo "<td>{$path['enrollments_count']}</td>";
        echo "<td>{$path['author']}</td>";
        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "<p class='warning'>Траектории не найдены. Создайте тестовые данные.</p>";
}

echo "</div>";

// ============================================
// 4. СТРУКТУРА ГЛАВНОЙ ТАБЛИЦЫ
// ============================================
echo "<div class='section'>";
echo "<h2>4. Структура таблицы {$prefix}test_learning_paths</h2>";

$stmt = $modx->query("DESCRIBE {$prefix}test_learning_paths");
$columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

if (count($columns) > 0) {
    echo "<table>";
    echo "<tr><th>Поле</th><th>Тип</th><th>Null</th><th>Ключ</th><th>По умолчанию</th></tr>";

    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }

    echo "</table>";
}

echo "</div>";

// ============================================
// 5. ПРОВЕРКА СВЯЗЕЙ
// ============================================
echo "<div class='section'>";
echo "<h2>5. Проверка Foreign Keys</h2>";

$sql = "SELECT
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE CONSTRAINT_SCHEMA = DATABASE()
        AND TABLE_NAME LIKE '{$prefix}test_learning_path%'
        AND REFERENCED_TABLE_NAME IS NOT NULL";

$stmt = $modx->query($sql);
$fks = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

if (count($fks) > 0) {
    echo "<table>";
    echo "<tr><th>Constraint</th><th>Таблица</th><th>Колонка</th><th>Ссылается на</th></tr>";

    foreach ($fks as $fk) {
        echo "<tr>";
        echo "<td>{$fk['CONSTRAINT_NAME']}</td>";
        echo "<td>{$fk['TABLE_NAME']}</td>";
        echo "<td>{$fk['COLUMN_NAME']}</td>";
        echo "<td>{$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}</td>";
        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "<p class='warning'>Foreign keys не найдены</p>";
}

echo "</div>";

// ============================================
// 6. ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ API
// ============================================
echo "<div class='section'>";
echo "<h2>6. Примеры API вызовов</h2>";

echo "<h3>Для создания траектории (POST):</h3>";
echo "<pre>";
echo json_encode([
    'action' => 'createPath',
    'data' => [
        'name' => 'Основы психологии',
        'description' => 'Вводный курс по психологии для начинающих',
        'difficulty_level' => 'beginner',
        'estimated_hours' => 20,
        'status' => 'published'
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "</pre>";

echo "<h3>Для добавления шага (POST):</h3>";
echo "<pre>";
echo json_encode([
    'action' => 'addStep',
    'data' => [
        'path_id' => 1,
        'step_type' => 'material',
        'item_id' => 10,
        'name' => 'Что такое психология?',
        'description' => 'Вводный материал',
        'is_required' => 1
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "</pre>";

echo "<h3>Для назначения траектории студенту (POST):</h3>";
echo "<pre>";
echo json_encode([
    'action' => 'enrollOnPath',
    'data' => [
        'path_id' => 1,
        'user_id' => 5
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "</pre>";

echo "</div>";

// ============================================
// 7. SQL ДЛЯ СОЗДАНИЯ ТЕСТОВЫХ ДАННЫХ
// ============================================
echo "<div class='section'>";
echo "<h2>7. SQL для создания тестовых данных</h2>";
echo "<p>Выполни этот SQL в phpMyAdmin для создания тестовой траектории:</p>";
echo "<textarea style='width:100%; height:300px; font-family: monospace;'>";
echo "-- Создание тестовой траектории
INSERT INTO {$prefix}test_learning_paths
    (name, description, created_by, status, difficulty_level, estimated_hours, passing_score)
VALUES
    ('Основы психологии', 'Вводный курс по основам психологии для начинающих', 1, 'published', 'beginner', 20, 70);

SET @path_id = LAST_INSERT_ID();

-- Добавление шагов (замени item_id на реальные ID материалов/тестов)
INSERT INTO {$prefix}test_learning_path_steps
    (path_id, step_number, step_type, item_id, name, description, is_required, min_score)
VALUES
    (@path_id, 1, 'material', 1, 'Введение в психологию', 'Знакомство с основными понятиями', 1, NULL),
    (@path_id, 2, 'test', 1, 'Проверочный тест', 'Тест на усвоение материала', 1, 70),
    (@path_id, 3, 'material', 2, 'История психологии', 'Развитие психологии как науки', 1, NULL),
    (@path_id, 4, 'test', 2, 'Итоговый тест', 'Финальная проверка знаний', 1, 80);
";
echo "</textarea>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>Диагностика завершена</h2>";
echo "<p>Дата проверки: " . date('Y-m-d H:i:s') . "</p>";
echo "</div>";
