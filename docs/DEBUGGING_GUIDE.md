# Гайд по отладке инсталлятора LMS

## 🎯 Цель

Документируем процесс отладки инсталлятора LMS, где мы столкнулись с неверным использованием MODX API для работы с базой данных. Этот гайд поможет другим разработчикам избежать аналогичных ошибок.

---

## 📋 Проблема: "Call to undefined method"

### Симптомы ошибки

```
Fatal error: Uncaught Error: Call to undefined method xPDOConnection::prepare()
Fatal error: Uncaught Error: Call to undefined method xPDOConnection::query()
```

Инсталлятор падал на различных этапах работы с БД:
- Этап 3: Проверка таблиц → `prepare()` не существует
- Этап 4: Инициализация данных → `prepare()` не существует
- INSERT операции → `prepare()` не существует

### Ошибочное предположение

Мы предположили, что `xPDOConnection` (объект БД из `$modx->getConnection()`) имеет стандартный PDO API:
- `prepare(sql)` - подготовить запрос
- `execute()` - выполнить запрос
- `query(sql)` - выполнить запрос напрямую
- `quote(string)` - экранировать строку

**Это было неверно!**

---

## 🔍 Процесс диагностики

### Шаг 1: Исследование класса подключения

**Тест:**
```php
$db = $modx->getConnection();
echo "Класс БД: " . get_class($db) . "\n";

$methods = get_class_methods($db);
sort($methods);
echo "Доступные методы:\n";
foreach ($methods as $method) {
    echo "  - " . $method . "()\n";
}
```

**Результат:**
```
Класс БД: xPDOConnection
Доступные методы:
- __construct()
- connect()
- getOption()
- isMutable()
```

**Вывод:** xPDOConnection имеет только 4 метода! Нет `prepare()`, `query()`, `quote()`.

### Шаг 2: Поиск правильного API на объекте $modx

**Тест:**
```php
$methods = get_class_methods($modx);
sort($methods);

echo "Методы $modx с 'query':\n";
foreach ($methods as $method) {
    if (stripos($method, 'query') !== false) {
        echo "  - " . $method . "()\n";
    }
}
```

**Результат:**
```
Методы $modx с 'query':
- newQuery()
- query()
- toQueryString()
```

**Вывод:** Нужно использовать методы на самом объекте `$modx`, а не на `xPDOConnection`!

### Шаг 3: Тестирование методов $modx

**Тест SELECT через query():**
```php
try {
    $sql = "SELECT COUNT(*) as cnt FROM modx_test_level_config";
    $result = $modx->query($sql);
    echo "✓ query() работает\n";
    echo "  Результат: " . print_r($result, true) . "\n";
} catch (Exception $e) {
    echo "✗ query() ошибка: " . $e->getMessage() . "\n";
}
```

**Результат:**
```
✓ query() работает
  Результат: PDOStatement Object
```

**Вывод:** `$modx->query()` работает и возвращает PDOStatement!

**Тест INSERT через exec():**
```php
try {
    $sql = "SELECT COUNT(*) as cnt FROM modx_test_level_config";
    $result = $modx->exec($sql);
    echo "✓ exec() работает\n";
    echo "  Результат: " . print_r($result, true) . "\n";
} catch (Exception $e) {
    echo "✗ exec() ошибка: " . $e->getMessage() . "\n";
}
```

**Результат:**
```
✓ exec() работает
  Результат: 0  (число затронутых строк для SELECT)
```

**Вывод:** `$modx->exec()` работает для INSERT/UPDATE/DELETE!

---

## ❌ Неверные подходы и почему они не работали

### Подход 1: Стандартный PDO через xPDOConnection

```php
// ❌ НЕВЕРНО - xPDOConnection не имеет этих методов
$db = $modx->getConnection();
$stmt = $db->prepare("SELECT ...");  // Fatal error: undefined method
$stmt->execute();
```

**Почему не работает:**
- xPDOConnection - это обёртка MODX, а не стандартное PDO подключение
- Она делегирует реальное PDO подключение для внутреннего использования
- Разработчики MODX не предоставляют прямой доступ к PDO через xPDOConnection

**Ошибка разработчика:**
- Мы предположили, что если класс работает с БД, то он имеет стандартный PDO API
- Это частая ошибка при работе с фреймворками - предположение о наличии стандартных методов

### Подход 2: query() на xPDOConnection

```php
// ❌ НЕВЕРНО - query() не существует в xPDOConnection
$db = $modx->getConnection();
$result = $db->query("SELECT ...");  // Fatal error: undefined method
```

**Почему не работает:**
- То же самое - xPDOConnection не имеет метода query()
- Метод query() есть на самом объекте $modx, а не на подключении

**Ошибка разработчика:**
- Смешивание объектов: забыли, что получили подключение из $modx, а не сам $modx
- Нужно было использовать $modx->query(), а не $db->query()

### Подход 3: Использование prepare/bindValue/execute

```php
// ❌ НЕВЕРНО - подготовленные запросы не поддерживаются MODX API
$db = $modx->getConnection();
$stmt = $db->prepare("INSERT INTO ... VALUES (:key, :value)");
$stmt->bindValue(':key', $value, PDO::PARAM_STR);
$stmt->execute();
```

**Почему не работает:**
- xPDOConnection не предоставляет prepare()
- MODX использует другой механизм для безопасности запросов
- Нужно использовать $modx->quote() для экранирования

**Ошибка разработчика:**
- Предположение что MODX следует стандартному PDO паттерну
- Не проверили наличие методов перед использованием

---

## ✅ Правильные подходы

### Правильный подход 1: SELECT запросы

```php
// ✅ ВЕРНО - используем $modx->query()
$stmt = $modx->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()");

// Получить ВСЕ результаты сразу (избегаем unbuffered query проблемы)
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    // обработать результат
}

// ИЛИ получить один результат
$stmt = $modx->query("SELECT COUNT(*) as cnt FROM modx_test_level_config");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['cnt'];
```

**Почему это работает:**
- $modx->query() - это правильный метод MODX API
- Возвращает PDOStatement, который имеет fetch() и fetchAll()
- fetchAll() избегает проблемы "unbuffered queries" в MySQL

### Правильный подход 2: INSERT/UPDATE/DELETE запросы

```php
// ✅ ВЕРНО - используем $modx->exec() с $modx->quote()
$sql = "INSERT INTO modx_test_level_config (level, xp_required, title) VALUES (" .
       intval($level['level']) . ", " .
       intval($level['xp_required']) . ", " .
       "'" . $modx->quote($level['title']) . "')";

$count = $modx->exec($sql);  // Возвращает число затронутых строк
if ($count > 0) {
    echo "Вставлено {$count} строк";
}
```

**Почему это работает:**
- $modx->exec() - метод для выполнения INSERT/UPDATE/DELETE
- Возвращает число затронутых строк (удобно для проверки успеха)
- $modx->quote() экранирует строки для безопасности
- intval() безопасна для целых чисел

### Правильный подход 3: Экранирование значений

```php
// ✅ ВЕРНО - использовать $modx->quote() для строк
$title = "Мой заголовок с 'кавычками'";
$safe_title = $modx->quote($title);  // Вернёт: 'Мой заголовок с \'кавычками\''

// ✅ ВЕРНО - использовать intval() для чисел
$id = intval($_GET['id']);  // Преобразовать в целое число
$sql = "DELETE FROM table WHERE id = {$id}";

// ✅ ВЕРНО - строить SQL с проверками типов
$sql = "INSERT INTO table (id, name, value) VALUES (" .
       intval($row['id']) . ", " .
       "'" . $modx->quote($row['name']) . "', " .
       floatval($row['value']) . ")";
```

**Почему это работает:**
- Каждый тип данных обрабатывается правильно
- intval() и floatval() исключают SQL injection для чисел
- $modx->quote() экранирует строки

---

## 🎓 Уроки, которые нужно усвоить

### Урок 1: Всегда проверяйте документацию фреймворка

❌ **Неверно:** Предположить, что все объекты БД имеют стандартный PDO API

✅ **Верно:** Проверить документацию MODX или использовать `get_class_methods()` для исследования доступного API

```php
// Исследуйте доступные методы
$methods = get_class_methods($object);
foreach ($methods as $method) {
    echo $method . "()\n";
}
```

### Урок 2: Диагностика перед разработкой

❌ **Неверно:** Сразу писать код, предполагая как он должен работать

✅ **Верно:** Сначала создать диагностические тесты, чтобы понять реальный API

```php
// ТЕСТ 1: Какой класс?
echo get_class($db) . "\n";

// ТЕСТ 2: Какие методы?
$methods = get_class_methods($db);
sort($methods);
print_r($methods);

// ТЕСТ 3: Какие методы на родительском объекте?
$methods = get_class_methods($modx);
foreach ($methods as $m) {
    if (stripos($m, 'query') !== false) {
        echo $m . "\n";
    }
}

// ТЕСТ 4: Какой результат возвращает метод?
$result = $modx->query("SELECT 1");
echo get_class($result) . "\n";
```

### Урок 3: Обработка ошибок помогает отладке

❌ **Неверно:** Игнорировать ошибки или не выводить информацию

✅ **Верно:** Обёртывать код в try-catch и выводить детальные ошибки

```php
try {
    $stmt = $modx->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "[SUCCESS] Найдено " . count($rows) . " записей\n";
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo "[SQL] " . $sql . "\n";
    echo "[TRACE] " . $e->getTraceAsString() . "\n";
}
```

### Урок 4: Читайте стек трейс

Ошибка:
```
Fatal error: Uncaught Error: Call to undefined method xPDOConnection::prepare()
in /path/to/installer.php:239
```

**Что это говорит:**
- Класс: xPDOConnection
- Метод: prepare()
- Файл и строка: /path/to/installer.php:239

**Как это помогает:**
1. Мы узнали точный класс объекта (xPDOConnection)
2. Мы узнали что prepare() не существует на этом классе
3. Это подсказало нам искать метод на другом объекте ($modx)

---

## 📚 Итоговая таблица API

| Операция | ❌ Неверно | ✅ Верно | Возвращает |
|----------|-----------|---------|-----------|
| SELECT | `$db->prepare()` | `$modx->query()` | PDOStatement |
| COUNT | `$db->prepare()` | `$modx->query()` | PDOStatement |
| INSERT | `$db->prepare()` | `$modx->exec()` | int (кол-во строк) |
| UPDATE | `$db->prepare()` | `$modx->exec()` | int (кол-во строк) |
| DELETE | `$db->prepare()` | `$modx->exec()` | int (кол-во строк) |
| Экранирование | `$db->quote()` | `$modx->quote()` | string |
| Fetch результаты | `fetch()` | `fetch()` или `fetchAll()` | array |

---

## 🔧 Практическая проверка API

Используйте этот код для быстрой проверки MODX API в любом проекте:

```php
<?php
// test_modx_db_api.php

// 1. Какие методы есть на $modx?
echo "=== Методы $modx ===\n";
$modxMethods = get_class_methods($modx);
$dbMethods = ['query', 'exec', 'quote', 'prepare', 'newQuery'];
foreach ($dbMethods as $method) {
    $exists = in_array($method, $modxMethods) ? '✓' : '✗';
    echo "{$exists} {$method}()\n";
}

// 2. Какие методы есть на getConnection()?
echo "\n=== Методы getConnection() ===\n";
$db = $modx->getConnection();
echo "Класс: " . get_class($db) . "\n";
$connMethods = get_class_methods($db);
$dbMethods = ['query', 'exec', 'quote', 'prepare', 'execute'];
foreach ($dbMethods as $method) {
    $exists = in_array($method, $connMethods) ? '✓' : '✗';
    echo "{$exists} {$method}()\n";
}

// 3. Протестировать query()
echo "\n=== Тест query() ===\n";
try {
    $stmt = $modx->query("SELECT 1 as test");
    echo "✓ query() работает\n";
    echo "✓ Возвращает: " . get_class($stmt) . "\n";
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ fetch() работает\n";
    print_r($row);
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n";
}

// 4. Протестировать exec()
echo "\n=== Тест exec() ===\n";
try {
    $result = $modx->exec("SELECT 1");
    echo "✓ exec() работает\n";
    echo "✓ Возвращает: {$result} (тип: " . gettype($result) . ")\n";
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n";
}

// 5. Протестировать quote()
echo "\n=== Тест quote() ===\n";
try {
    $test = "Test's string";
    $quoted = $modx->quote($test);
    echo "✓ quote() работает\n";
    echo "✓ '{$test}' → {$quoted}\n";
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n";
}
?>
```

---

## 🎯 Выводы

1. **Не предполагайте** - исследуйте реальный API перед использованием
2. **Используйте диагностику** - создавайте тесты, чтобы понять как работает код
3. **Читайте стек трейс** - он содержит ценную информацию об ошибке
4. **Проверяйте документацию** - официальная документация часто содержит примеры правильного использования API
5. **Обрабатывайте ошибки** - хорошее логирование помогает быстро найти проблемы

---

## 📖 Справочные ссылки

- MODX Revolution Database API: http://rtfm.modx.com/revolution/2.x/developing-in-modx/basic-development/executing-queries
- PDOStatement: https://www.php.net/manual/en/class.pdostatement.php
- SQL Injection Prevention: https://owasp.org/www-community/attacks/SQL_Injection

