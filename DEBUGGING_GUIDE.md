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

## ✅ Порядок действий для восстановления LMS

### Шаг 1: Диагностика пользователей
Запустите **СКРИПТ: Проверка существующих пользователей** (см. выше)

### Шаг 2: Создание пользователя (если нужно)
Если пользователь ID 2 не найден, запустите **СКРИПТ: Создание пользователя** (см. выше)

### Шаг 3: Базовая диагностика LMS
Запустите `LMS_SETUP_VERIFICATION.md → SCRIPT 1`

### Шаг 4: Автоматическое исправление
Запустите `LMS_SETUP_VERIFICATION.md → SCRIPT 2`

### Шаг 5: Проверка видимости тестов
Запустите последовательно:
1. `TEST_USER_VISIBILITY.md → SCRIPT 1`
2. `TEST_USER_VISIBILITY.md → SCRIPT 2` (если нужно)
3. `TEST_USER_VISIBILITY.md → SCRIPT 3` (финальная проверка)

---

## 📝 Частые проблемы и решения

### Проблема: "Ошибка SQL при проверке пользователя"
**Причина:** Пользователь не существует в базе данных
**Решение:** Запустить скрипт создания пользователя (см. выше)

### Проблема: "Ошибка при получении категорий"
**Причина:** Нет публичных тестов или категорий в системе
**Решение:** Убедиться что:
- В `modx_test_categories` есть категории
- В `modx_test_tests` есть тесты с `is_public = 1`
- Запустить `LMS_SETUP_VERIFICATION.md → SCRIPT 2` для автоисправления

### Проблема: "Страница /tests пустая"
**Причина:** Нет публичных тестов или неправильно настроен сниппет
**Решение:**
1. Проверить что сниппет `categoriesAndTests.php` существует в `/core/elements/snippets/`
2. Убедиться что страница ID 35 содержит `[[!categoriesAndTests]]`
3. Проверить что есть публичные тесты: `SELECT * FROM modx_test_tests WHERE is_public = 1`

---

## 🔧 Диагностические SQL-запросы

### Проверить всех пользователей
```sql
SELECT u.id, u.username, u.active, ua.email
FROM modx_users u
LEFT JOIN modx_user_attributes ua ON ua.internalKey = u.id
ORDER BY u.id;
```

### Проверить публичные тесты
```sql
SELECT id, title, category_id, is_public, created_by
FROM modx_test_tests
WHERE is_public = 1;
```

### Проверить категории с количеством тестов
```sql
SELECT
    c.id,
    c.title,
    COUNT(t.id) as test_count
FROM modx_test_categories c
LEFT JOIN modx_test_tests t ON t.category_id = c.id AND t.is_public = 1
GROUP BY c.id, c.title;
```

### Проверить содержимое страницы /tests
```sql
SELECT id, pagetitle, alias, content, published, deleted
FROM modx_site_content
WHERE id = 35;
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
