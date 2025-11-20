# Руководство по отладке LMS системы

## Текущие проблемы и их решения

### ❌ КРИТИЧЕСКАЯ ПРОБЛЕМА: Пользователь ID 2 не найден

**Описание:**
Все скрипты диагностики ссылаются на пользователя ID 2, но он не существует в базе данных MODX.

**Симптомы:**
- `✗ Пользователь ID 2 не найден` в SCRIPT 1 (LMS_SETUP_VERIFICATION.md)
- `✗ Ошибка SQL` при проверке пользователя в TEST_USER_VISIBILITY.md → SCRIPT 1
- `✗ Ошибка при получении категорий` в TEST_USER_VISIBILITY.md → SCRIPT 3

**Причина:**
База данных не содержит пользователя с ID = 2, либо пользователь был удален.

**Решение:**
См. раздел ниже "Диагностика и восстановление пользователей"

---

## 🔍 Диагностика пользователей

### СКРИПТ: Проверка существующих пользователей

Запустите в **MODX Console**:

```php
echo "═══════════════════════════════════════════════════════════════════\n";
echo "👤 ДИАГНОСТИКА ПОЛЬЗОВАТЕЛЕЙ MODX\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

// Получить всех пользователей
$stmt = $modx->query("
    SELECT
        u.id,
        u.username,
        u.active,
        up.email,
        up.fullname
    FROM modx_users u
    LEFT JOIN modx_user_attributes up ON up.internalKey = u.id
    ORDER BY u.id ASC
");

if ($stmt === false) {
    echo "✗ Ошибка SQL при получении пользователей\n";
    return;
}

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo "✗ В системе НЕТ ПОЛЬЗОВАТЕЛЕЙ!\n";
} else {
    echo "Найдено пользователей: " . count($users) . "\n\n";

    printf("%-5s | %-20s | %-30s | %-10s | %s\n",
        "ID", "Username", "Email", "Active", "Fullname");
    echo str_repeat("-", 90) . "\n";

    foreach ($users as $user) {
        printf("%-5d | %-20s | %-30s | %-10s | %s\n",
            $user['id'],
            $user['username'] ?? 'NULL',
            $user['email'] ?? 'NULL',
            $user['active'] ? 'ДА' : 'НЕТ',
            $user['fullname'] ?? ''
        );
    }
}

// Проверить специально ID 2
echo "\n" . str_repeat("─", 70) . "\n";
echo "Проверка пользователя ID 2:\n";

$stmt = $modx->query("SELECT id, username FROM modx_users WHERE id = 2");

if ($stmt !== false && $user = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "✓ Пользователь ID 2 найден: " . $user['username'] . "\n";
} else {
    echo "✗ Пользователь ID 2 НЕ СУЩЕСТВУЕТ\n";
    echo "⚠️  Требуется создание или восстановление пользователя ID 2\n";
}

echo "\n═══════════════════════════════════════════════════════════════════\n";
```

---

## 🛠️ Создание/восстановление пользователя ID 2

### СКРИПТ: Создание пользователя для тестирования LMS

Запустите в **MODX Console**:

```php
echo "═══════════════════════════════════════════════════════════════════\n";
echo "🔧 СОЗДАНИЕ/ВОССТАНОВЛЕНИЕ ПОЛЬЗОВАТЕЛЯ ID 2\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$targetUserId = 2;
$username = 'testuser';
$email = 'testuser@example.com';
$password = 'test123456'; // Измените на безопасный пароль

// Шаг 1: Проверить существует ли пользователь
echo "[1] Проверка существования пользователя ID $targetUserId...\n";

$stmt = $modx->query("SELECT id, username FROM modx_users WHERE id = " . (int)$targetUserId);

if ($stmt !== false && $user = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "✓ Пользователь уже существует: " . $user['username'] . "\n";
    echo "Пропускаем создание.\n\n";
} else {
    echo "○ Пользователь не найден, создаем...\n\n";

    // Шаг 2: Проверить свободен ли ID 2
    echo "[2] Создание пользователя с ID $targetUserId...\n";

    // Хешируем пароль
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    try {
        // Создаем пользователя в таблице modx_users
        $modx->exec("
            INSERT INTO modx_users (id, username, password, cachepwd, class_key, active)
            VALUES (
                " . (int)$targetUserId . ",
                " . $modx->quote($username) . ",
                " . $modx->quote($passwordHash) . ",
                '',
                'modUser',
                1
            )
        ");

        echo "✓ Пользователь создан в modx_users\n";

        // Создаем профиль пользователя в modx_user_attributes
        $modx->exec("
            INSERT INTO modx_user_attributes (internalKey, fullname, email, blocked, blockeduntil, blockedafter)
            VALUES (
                " . (int)$targetUserId . ",
                'Test User',
                " . $modx->quote($email) . ",
                0,
                0,
                0
            )
        ");

        echo "✓ Профиль создан в modx_user_attributes\n";

        echo "\n✓ ПОЛЬЗОВАТЕЛЬ УСПЕШНО СОЗДАН!\n";
        echo "   Username: $username\n";
        echo "   Email: $email\n";
        echo "   Password: $password\n";
        echo "   ID: $targetUserId\n";

    } catch (Exception $e) {
        echo "✗ Ошибка при создании: " . $e->getMessage() . "\n";
    }
}

// Шаг 3: Проверить активность пользователя
echo "\n[3] Проверка активности пользователя...\n";

$stmt = $modx->query("SELECT active FROM modx_users WHERE id = " . (int)$targetUserId);

if ($stmt !== false && $user = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!$user['active']) {
        echo "○ Пользователь неактивен, активируем...\n";
        $modx->exec("UPDATE modx_users SET active = 1 WHERE id = " . (int)$targetUserId);
        echo "✓ Пользователь активирован\n";
    } else {
        echo "✓ Пользователь уже активен\n";
    }
}

// Шаг 4: Очистить кеш
echo "\n[4] Очистка кеша...\n";
$modx->cacheManager->clearCache();
echo "✓ Кеш очищен\n";

echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "✓ ГОТОВО - ПОЛЬЗОВАТЕЛЬ ID 2 ГОТОВ К РАБОТЕ\n";
echo "═══════════════════════════════════════════════════════════════════\n";
```

---

## ⚠️ Несоответствие схемы БД

### Проблема: SQL ошибки "Unknown column"

**Симптомы:**
- `Unknown column 'is_public'` в запросах к `modx_test_tests`
- `Unknown column 'title'` в запросах к `modx_test_categories`
- `Unknown column 't.category_id'` в JOIN запросах

**Причина:**
Реальная структура БД отличается от ожидаемой в документации и старых скриптах.

**Ключевые отличия:**

| Старая схема | Реальная схема | Таблица |
|--------------|----------------|---------|
| `is_public` (boolean) | `publication_status` (enum: draft/private/unlisted/public) | modx_test_tests |
| `title` | `name` | modx_test_categories |
| `category_id` | `resource_id` (хранит ID категории!) | modx_test_tests |

**Решение:**

1. **Проверьте реальную структуру таблицы:**
```php
$stmt = $modx->query("DESCRIBE modx_test_tests");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " | " . $row['Type'] . "\n";
}
```

2. **Используйте правильную схему из документации:**
См. файл `docs/DATABASE_SCHEMA.md` для полной схемы БД

3. **Обновите SQL-запросы:**
- ❌ `WHERE is_public = 1` → ✅ `WHERE publication_status = 'public'`
- ❌ `SELECT c.title` → ✅ `SELECT c.name`
- ❌ `ON t.category_id = c.id` → ✅ `ON t.resource_id = c.id`

---

## 🔗 Проблемы с resource_id

### Проблема: Тесты не связаны с категориями

**Симптомы:**
- JOIN между тестами и категориями возвращает NULL
- `resource_id` содержит значения 68, 90, 103... вместо 1, 3, 4...
- Тесты не отображаются на странице `/tests`

**Причина:**
Поле `modx_test_tests.resource_id` хранит **ID категории** (из modx_test_categories), а не ID ресурса MODX! Старые ресурсы (68, 90, 103...) были удалены, поэтому связи нарушены.

**Диагностика:**

```php
// Проверить ID категорий
$stmt = $modx->query("SELECT id, name FROM modx_test_categories");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Cat ID: " . $row['id'] . " | " . $row['name'] . "\n";
}

// Проверить resource_id в тестах
$stmt = $modx->query("SELECT DISTINCT resource_id FROM modx_test_tests");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['resource_id'] . " ";
}
```

**Решение:**

1. **Создайте ресурсы MODX для категорий** (опционально):
   См. `docs/CREATE_TEST_RESOURCES.md`

2. **Обновите resource_id в тестах** чтобы указывали на правильные ID категорий:
```php
// Пример: распределить тесты по категориям
$modx->exec("UPDATE modx_test_tests SET resource_id = 1 WHERE resource_id IN (68, 90)");
$modx->exec("UPDATE modx_test_tests SET resource_id = 3 WHERE resource_id IN (103, 105)");
// ... и т.д. для всех категорий
```

3. **Проверьте результат:**
```php
$stmt = $modx->query("
    SELECT c.name, COUNT(t.id) as cnt
    FROM modx_test_categories c
    LEFT JOIN modx_test_tests t ON t.resource_id = c.id
    GROUP BY c.id, c.name
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['name'] . ": " . $row['cnt'] . " tests\n";
}
```

---

## ✅ Порядок действий для полного восстановления LMS

### Шаг 1: Проверка схемы БД
1. Откройте `docs/DATABASE_SCHEMA.md`
2. Проверьте что ваши таблицы соответствуют схеме
3. Запустите `DESCRIBE modx_test_tests` для проверки

### Шаг 2: Диагностика пользователей
Запустите **СКРИПТ: Проверка существующих пользователей** (см. выше)

### Шаг 3: Создание пользователя (если нужно)
Если пользователь ID 2 не найден, запустите **СКРИПТ: Создание пользователя** (см. выше)

### Шаг 4: Создание ресурсов MODX для категорий
1. Откройте `docs/CREATE_TEST_RESOURCES.md`
2. Запустите СКРИПТ 1 (предварительная проверка)
3. Запустите СКРИПТ 2 (создание ресурсов)
4. Запустите СКРИПТ 3 (проверка результата)

### Шаг 5: Восстановление связей тест↔категория
Обновите `resource_id` в тестах чтобы они указывали на правильные ID категорий (см. раздел "Проблемы с resource_id")

### Шаг 6: Исправление сниппетов
Обновите сниппеты чтобы использовали правильную схему БД:
- `categoriesAndTests.php`
- `testRunner.php`
- Другие сниппеты LMS

### Шаг 7: Очистка кеша и проверка
```php
$modx->cacheManager->refresh();
$modx->cacheManager->delete('testsystem/categories_list');
```

Откройте `/tests` и проверьте что категории и тесты отображаются!

---

## 📝 Частые проблемы и решения

### Проблема: "Unknown column 'is_public' in field list"
**Причина:** Используется старая схема БД
**Решение:**
- Замените `is_public = 1` на `publication_status = 'public'`
- Проверьте все SQL-запросы согласно `docs/DATABASE_SCHEMA.md`

### Проблема: "Unknown column 'title' in field list" (категории)
**Причина:** Поле называется `name`, а не `title`
**Решение:**
- Замените `c.title` на `c.name` во всех запросах к `modx_test_categories`

### Проблема: "Unknown column 't.category_id' in on clause"
**Причина:** Поле называется `resource_id`, а не `category_id`
**Решение:**
- Замените `t.category_id` на `t.resource_id` в JOIN запросах
- **ВАЖНО:** `resource_id` хранит ID категории, а не ID ресурса MODX!

### Проблема: JOIN не находит тесты (возвращает NULL)
**Причина:** `resource_id` содержит старые ID (68, 90, 103...)
**Решение:** См. раздел "Проблемы с resource_id"

### Проблема: "Ошибка SQL при проверке пользователя"
**Причина:** Пользователь не существует в базе данных
**Решение:** Запустить скрипт создания пользователя (см. выше)

### Проблема: "Ошибка при получении категорий"
**Причина:** Нет публичных тестов или категорий в системе
**Решение:** Убедиться что:
- В `modx_test_categories` есть категории
- В `modx_test_tests` есть тесты с `publication_status = 'public'` (не `is_public = 1`!)
- Запустить скрипты восстановления связей

### Проблема: "Страница /tests пустая"
**Причина:** Нет публичных тестов или неправильно настроен сниппет
**Решение:**
1. Проверить что сниппет `categoriesAndTests.php` существует в `/core/elements/snippets/`
2. Убедиться что страница ID 35 содержит `[[!categoriesAndTests]]`
3. Проверить что есть публичные тесты: `SELECT * FROM modx_test_tests WHERE publication_status = 'public' AND is_active = 1`
4. Проверить что `resource_id` указывает на правильные ID категорий

---

## 🔧 Диагностические SQL-запросы

**ВАЖНО:** Все запросы используют правильную схему БД из `docs/DATABASE_SCHEMA.md`

### Проверить всех пользователей
```sql
SELECT u.id, u.username, u.active, ua.email
FROM modx_users u
LEFT JOIN modx_user_attributes ua ON ua.internalKey = u.id
ORDER BY u.id;
```

### Проверить публичные активные тесты (правильная схема!)
```sql
SELECT id, title, resource_id, publication_status, is_active, created_by
FROM modx_test_tests
WHERE publication_status = 'public' AND is_active = 1;
```

### Проверить категории с количеством тестов (правильная схема!)
```sql
SELECT
    c.id,
    c.name,
    COUNT(t.id) as test_count
FROM modx_test_categories c
LEFT JOIN modx_test_tests t ON t.resource_id = c.id
    AND t.publication_status = 'public'
    AND t.is_active = 1
GROUP BY c.id, c.name;
```

### Проверить связи тест↔категория
```sql
SELECT
    t.id as test_id,
    t.title,
    t.resource_id,
    c.id as cat_id,
    c.name as cat_name
FROM modx_test_tests t
LEFT JOIN modx_test_categories c ON c.id = t.resource_id
LIMIT 10;
```

### Проверить содержимое страницы /tests
```sql
SELECT id, pagetitle, alias, content, published, deleted
FROM modx_site_content
WHERE id = 35;
```

### Проверить структуру таблицы
```sql
DESCRIBE modx_test_tests;
```

---

## 📞 Контакты для поддержки

При возникновении проблем:
1. Запустите все диагностические скрипты
2. Сохраните вывод каждого скрипта
3. Проверьте логи MODX в `/core/cache/logs/`
4. Предоставьте полную информацию о проблеме

---

**Последнее обновление:** 2025-11-20
