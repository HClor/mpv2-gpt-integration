# Диагностические запросы для инсталлятора

Эти запросы помогут понять как работает API БД в MODX Console и как правильно писать код.

## 1. Определить класс подключения к БД

Выполни в **MODX Console**:

```php
$db = $modx->getConnection();
echo "Класс БД: " . get_class($db) . "\n";
echo "Интерфейсы: " . implode(', ', class_implements(get_class($db))) . "\n";
echo "Родительский класс: " . get_parent_class($db) . "\n";

// Показать все публичные методы
$methods = get_class_methods($db);
sort($methods);
echo "\nДоступные методы:\n";
foreach ($methods as $method) {
    echo "  - " . $method . "()\n";
}
```

**Это покажет нам какие методы на самом деле доступны.**

---

## 2. Проверить как работает SELECT запрос

Выполни в **MODX Console**:

```php
$db = $modx->getConnection();

// Пробуем разные способы
echo "=== ТЕСТ 1: Использование query() ===\n";
try {
    $result = $db->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES LIMIT 1");
    echo "query() работает!\n";
} catch (Exception $e) {
    echo "query() ошибка: " . $e->getMessage() . "\n";
}

echo "\n=== ТЕСТ 2: Использование prepare() ===\n";
try {
    $stmt = $db->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES LIMIT 1");
    echo "prepare() работает! Класс: " . get_class($stmt) . "\n";
    $stmt->execute();
    echo "execute() работает!\n";
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "fetch() работает! Результат: " . print_r($row, true) . "\n";
} catch (Exception $e) {
    echo "prepare() ошибка: " . $e->getMessage() . "\n";
}

echo "\n=== ТЕСТ 3: Использование execQuery() (MODX специфичный) ===\n";
try {
    if (method_exists($db, 'query')) {
        echo "Метод query существует\n";
    }
    if (method_exists($db, 'exec')) {
        echo "Метод exec существует\n";
    }
    if (method_exists($db, 'executeQuery')) {
        echo "Метод executeQuery существует\n";
    }
} catch (Exception $e) {
    echo "Ошибка проверки методов: " . $e->getMessage() . "\n";
}
```

**Это покажет какие из методов действительно работают.**

---

## 3. Проверить COUNT(*) запрос

Выполни в **MODX Console**:

```php
$db = $modx->getConnection();

echo "=== Проверка COUNT запроса ===\n";
try {
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM modx_test_level_config");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Результат: " . print_r($row, true);
    echo "Значение cnt: " . $row['cnt'] . "\n";
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
```

---

## 4. Проверить INSERT операцию

Выполни в **MODX Console**:

```php
$db = $modx->getConnection();

echo "=== Проверка INSERT с bindValue ===\n";
try {
    $stmt = $db->prepare("INSERT INTO modx_test_level_config (level, xp_required, title) VALUES (:level, :xp, :title)");
    $stmt->bindValue(':level', 99, PDO::PARAM_INT);
    $stmt->bindValue(':xp', 99999, PDO::PARAM_INT);
    $stmt->bindValue(':title', 'ТЕСТ', PDO::PARAM_STR);
    $result = $stmt->execute();
    echo "INSERT выполнен успешно! Result: " . ($result ? 'true' : 'false') . "\n";

    // Проверим что вставилось
    $check = $db->prepare("SELECT * FROM modx_test_level_config WHERE level = 99");
    $check->execute();
    $row = $check->fetch(PDO::FETCH_ASSOC);
    echo "Проверка: " . print_r($row, true);
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
```

---

## 5. Проверить LIKE оператор

Выполни в **MODX Console**:

```php
$db = $modx->getConnection();

echo "=== Проверка LIKE в SELECT ===\n";
try {
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM modx_site_content WHERE alias LIKE 'tests'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Результат COUNT: " . $row['cnt'] . "\n";
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
```

---

## Ожидаемые результаты

После запуска этих тестов ты увидишь:

1. **Какой класс на самом деле используется** - это позволит нам найти нужную документацию
2. **Какие методы работают** - поможет выбрать правильный способ запросов
3. **Как работают COUNT/LIKE/bindValue** - убедимся что синтаксис правильный
4. **Детальные ошибки** - если что-то не работает, мы увидим точное сообщение об ошибке

**Запусти эти тесты в порядке 1→2→3→4→5 и пришли мне результаты.**

На основе результатов я исправлю инсталлятор так, чтобы он использовал правильный API.
