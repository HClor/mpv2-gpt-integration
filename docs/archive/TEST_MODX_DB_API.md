# Тестирование MODX Database API

## ТЕСТ 5 - Какие методы есть у $modx для работы с БД?

Выполни в **MODX Console**:

```php
// Показать все методы $modx которые содержат 'query', 'db', 'sql'
$methods = get_class_methods($modx);
sort($methods);

echo "Методы $modx с 'query':\n";
foreach ($methods as $method) {
    if (stripos($method, 'query') !== false) {
        echo "  - " . $method . "()\n";
    }
}

echo "\nМетоды $modx с 'db' или 'database':\n";
foreach ($methods as $method) {
    if (stripos($method, 'db') !== false || stripos($method, 'database') !== false) {
        echo "  - " . $method . "()\n";
    }
}

echo "\nМетоды $modx с 'table' или 'sql':\n";
foreach ($methods as $method) {
    if (stripos($method, 'table') !== false || stripos($method, 'sql') !== false) {
        echo "  - " . $method . "()\n";
    }
}

echo "\nВсе методы $modx (первые 50):\n";
foreach (array_slice($methods, 0, 50) as $method) {
    echo "  - " . $method . "()\n";
}
```

---

## ТЕСТ 6 - Попробуем использовать ORM вместо SQL

```php
// Попробуем создать уровень через MODX ORM
try {
    // Создать новый объект
    $level = $modx->newObject('modLevelConfig');
    if ($level) {
        echo "✓ modLevelConfig объект создан\n";
        echo "  Класс: " . get_class($level) . "\n";
    } else {
        echo "✗ Не удалось создать объект modLevelConfig\n";
    }
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n";
}

// Попробуем другие классы
echo "\n=== Попытка работы с разными классами ===\n";
$testClasses = [
    'modLevelConfig',
    'modTestLevelConfig',
    'modX2TestLevelConfig',
    'LmsLevelConfig'
];

foreach ($testClasses as $class) {
    try {
        $obj = $modx->newObject($class);
        if ($obj) {
            echo "✓ {$class} работает\n";
        }
    } catch (Exception $e) {
        echo "✗ {$class}: " . $e->getMessage() . "\n";
    }
}
```

---

## ТЕСТ 7 - Прямой доступ к подключению

```php
// Получить реальное PDO подключение
$pdo = $modx->getConnection();
echo "Класс: " . get_class($pdo) . "\n";

// Проверить есть ли свойство для доступа к PDO
$reflection = new ReflectionClass($pdo);
echo "\nСвойства xPDOConnection:\n";
foreach ($reflection->getProperties() as $property) {
    $property->setAccessible(true);
    echo "  - " . $property->getName() . ": " . get_class($property->getValue($pdo)) . "\n";
}

// Проверить может ли быть PDO внутри
if (method_exists($pdo, '__get')) {
    echo "\nМетод __get существует\n";
}
if (method_exists($pdo, 'getPdo')) {
    echo "Метод getPdo существует\n";
}
```

---

## ТЕСТ 8 - Попытка SQL через $modx->exec()

```php
try {
    // Попробуем прямой SQL
    $sql = "SELECT COUNT(*) as cnt FROM modx_test_level_config";
    $result = $modx->exec($sql);
    echo "✓ exec() работает\n";
    echo "  Результат: " . print_r($result, true) . "\n";
} catch (Exception $e) {
    echo "✗ exec() ошибка: " . $e->getMessage() . "\n";
}

// Попробуем query
try {
    $sql = "SELECT COUNT(*) as cnt FROM modx_test_level_config";
    $result = $modx->query($sql);
    echo "✓ query() работает\n";
    echo "  Результат: " . print_r($result, true) . "\n";
} catch (Exception $e) {
    echo "✗ query() ошибка: " . $e->getMessage() . "\n";
}

// Попробуем queryArray
try {
    $sql = "SELECT * FROM modx_test_level_config LIMIT 1";
    $result = $modx->queryArray($sql);
    echo "✓ queryArray() работает\n";
    echo "  Результат: " . print_r($result, true) . "\n";
} catch (Exception $e) {
    echo "✗ queryArray() ошибка: " . $e->getMessage() . "\n";
}
```

---

Запусти эти тесты 5→6→7→8 и пришли результаты. Это покажет как **правильно** работать с БД в MODX Revo!
