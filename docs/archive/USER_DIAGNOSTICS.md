# Диагностика пользователей MODX для LMS

## Описание

Этот документ содержит скрипты для диагностики и исправления проблем с пользователями в MODX.

---

## 🔍 СКРИПТ 1: Диагностика существующих пользователей

Запусти в **MODX Console**:

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
        up.fullname,
        up.blocked
    FROM modx_users u
    LEFT JOIN modx_user_attributes up ON up.internalKey = u.id
    ORDER BY u.id ASC
");

if ($stmt === false) {
    echo "✗ Ошибка SQL при получении пользователей\n";
    echo "   Проверьте структуру базы данных\n";
    return;
}

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo "✗ В СИСТЕМЕ НЕТ ПОЛЬЗОВАТЕЛЕЙ!\n";
    echo "⚠️  Критическая проблема - нужно создать пользователя\n";
} else {
    echo "✓ Найдено пользователей: " . count($users) . "\n\n";

    printf("%-5s | %-20s | %-30s | %-8s | %-8s | %s\n",
        "ID", "Username", "Email", "Active", "Blocked", "Fullname");
    echo str_repeat("-", 100) . "\n";

    foreach ($users as $user) {
        printf("%-5d | %-20s | %-30s | %-8s | %-8s | %s\n",
            $user['id'],
            $user['username'] ?? 'NULL',
            $user['email'] ?? 'NULL',
            $user['active'] ? 'ДА' : 'НЕТ',
            $user['blocked'] ? 'ДА' : 'НЕТ',
            $user['fullname'] ?? ''
        );
    }
}

// Проверить специально ID 2
echo "\n" . str_repeat("─", 70) . "\n";
echo "ПРОВЕРКА ПОЛЬЗОВАТЕЛЯ ID 2 (требуется для LMS):\n";
echo str_repeat("─", 70) . "\n";

$stmt = $modx->query("
    SELECT u.id, u.username, u.active, ua.email, ua.blocked
    FROM modx_users u
    LEFT JOIN modx_user_attributes ua ON ua.internalKey = u.id
    WHERE u.id = 2
");

if ($stmt !== false && $user = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "✓ Пользователь ID 2 найден\n";
    echo "   Username: " . ($user['username'] ?? 'NULL') . "\n";
    echo "   Email: " . ($user['email'] ?? 'NULL') . "\n";
    echo "   Active: " . ($user['active'] ? 'ДА' : 'НЕТ') . "\n";
    echo "   Blocked: " . ($user['blocked'] ? 'ДА' : 'НЕТ') . "\n";

    $problems = [];
    if (!$user['active']) {
        $problems[] = "Пользователь неактивен";
    }
    if ($user['blocked']) {
        $problems[] = "Пользователь заблокирован";
    }

    if (!empty($problems)) {
        echo "\n⚠️  ПРОБЛЕМЫ:\n";
        foreach ($problems as $problem) {
            echo "   - " . $problem . "\n";
        }
        echo "   Запустите СКРИПТ 2 для исправления\n";
    } else {
        echo "\n✓ Пользователь настроен правильно\n";
    }
} else {
    echo "✗ ПОЛЬЗОВАТЕЛЬ ID 2 НЕ СУЩЕСТВУЕТ\n";
    echo "⚠️  Это критическая проблема для LMS!\n";
    echo "\nВОЗМОЖНЫЕ РЕШЕНИЯ:\n";
    echo "   1. Запустить СКРИПТ 2 для создания пользователя ID 2\n";
    echo "   2. Или изменить ID пользователя в скриптах тестирования\n";
}

// Проверить какой ID следующий свободный
echo "\n" . str_repeat("─", 70) . "\n";
echo "ИНФОРМАЦИЯ О ID:\n";

$stmt = $modx->query("SELECT MAX(id) as max_id FROM modx_users");
if ($stmt !== false && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "   Максимальный ID: " . ($row['max_id'] ?? 0) . "\n";
}

echo "\n═══════════════════════════════════════════════════════════════════\n";
```

---

## 🔧 СКРИПТ 2: Создание/восстановление пользователя ID 2

Запусти в **MODX Console**:

```php
echo "═══════════════════════════════════════════════════════════════════\n";
echo "🔧 СОЗДАНИЕ/ВОССТАНОВЛЕНИЕ ПОЛЬЗОВАТЕЛЯ ID 2\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$targetUserId = 2;
$username = 'testuser';
$email = 'testuser@example.com';
$password = 'Test123456!'; // Измените на безопасный пароль
$fullname = 'Test User';

echo "ПАРАМЕТРЫ СОЗДАВАЕМОГО ПОЛЬЗОВАТЕЛЯ:\n";
echo "   ID: $targetUserId\n";
echo "   Username: $username\n";
echo "   Email: $email\n";
echo "   Password: $password\n";
echo "   Full Name: $fullname\n\n";

// Шаг 1: Проверить существует ли пользователь
echo "[1] Проверка существования пользователя ID $targetUserId...\n";

$stmt = $modx->query("SELECT id, username FROM modx_users WHERE id = " . (int)$targetUserId);

if ($stmt !== false && $user = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "✓ Пользователь уже существует: " . $user['username'] . "\n";
    echo "   Пропускаем создание, переходим к проверке параметров...\n\n";
    $userExists = true;
} else {
    echo "○ Пользователь не найден, создаем...\n\n";
    $userExists = false;
}

// Шаг 2: Создание пользователя (если не существует)
if (!$userExists) {
    echo "[2] Создание пользователя с ID $targetUserId...\n";

    // Проверим, занят ли ID 2
    $stmt = $modx->query("SELECT id FROM modx_users WHERE id = " . (int)$targetUserId);
    if ($stmt !== false && $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "✗ ID $targetUserId уже занят!\n";
        echo "   Попробуйте использовать другой ID или удалить существующего пользователя\n";
        return;
    }

    // Хешируем пароль
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    try {
        // Создаем пользователя в таблице modx_users
        $sql = "
            INSERT INTO modx_users (id, username, password, cachepwd, class_key, active)
            VALUES (
                " . (int)$targetUserId . ",
                " . $modx->quote($username) . ",
                " . $modx->quote($passwordHash) . ",
                '',
                'modUser',
                1
            )
        ";

        $result = $modx->exec($sql);

        if ($result === false) {
            echo "✗ Ошибка при создании пользователя в modx_users\n";
            return;
        }

        echo "   ✓ Пользователь создан в modx_users\n";

        // Создаем профиль пользователя в modx_user_attributes
        $sql = "
            INSERT INTO modx_user_attributes (
                internalKey,
                fullname,
                email,
                blocked,
                blockeduntil,
                blockedafter
            )
            VALUES (
                " . (int)$targetUserId . ",
                " . $modx->quote($fullname) . ",
                " . $modx->quote($email) . ",
                0,
                0,
                0
            )
        ";

        $result = $modx->exec($sql);

        if ($result === false) {
            echo "   ✗ Ошибка при создании профиля в modx_user_attributes\n";
            return;
        }

        echo "   ✓ Профиль создан в modx_user_attributes\n";

        echo "\n✓ ПОЛЬЗОВАТЕЛЬ УСПЕШНО СОЗДАН!\n";
        echo "   Username: $username\n";
        echo "   Email: $email\n";
        echo "   Password: $password\n";
        echo "   ID: $targetUserId\n\n";

    } catch (Exception $e) {
        echo "✗ Ошибка при создании: " . $e->getMessage() . "\n";
        return;
    }
} else {
    echo "[2] Пользователь уже существует, пропускаем создание\n\n";
}

// Шаг 3: Проверить и исправить активность пользователя
echo "[3] Проверка активности пользователя...\n";

$stmt = $modx->query("SELECT active FROM modx_users WHERE id = " . (int)$targetUserId);

if ($stmt !== false && $user = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!$user['active']) {
        echo "   ○ Пользователь неактивен, активируем...\n";
        $modx->exec("UPDATE modx_users SET active = 1 WHERE id = " . (int)$targetUserId);
        echo "   ✓ Пользователь активирован\n";
    } else {
        echo "   ✓ Пользователь уже активен\n";
    }
}

// Шаг 4: Проверить блокировку
echo "\n[4] Проверка блокировки пользователя...\n";

$stmt = $modx->query("SELECT blocked FROM modx_user_attributes WHERE internalKey = " . (int)$targetUserId);

if ($stmt !== false && $attrs = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($attrs['blocked']) {
        echo "   ○ Пользователь заблокирован, разблокируем...\n";
        $modx->exec("UPDATE modx_user_attributes SET blocked = 0 WHERE internalKey = " . (int)$targetUserId);
        echo "   ✓ Пользователь разблокирован\n";
    } else {
        echo "   ✓ Пользователь не заблокирован\n";
    }
}

// Шаг 5: Очистить кеш
echo "\n[5] Очистка кеша...\n";
$modx->cacheManager->clearCache();
echo "   ✓ Кеш очищен\n";

// Финальная проверка
echo "\n" . str_repeat("─", 70) . "\n";
echo "ФИНАЛЬНАЯ ПРОВЕРКА:\n";

$stmt = $modx->query("
    SELECT u.id, u.username, u.active, ua.email, ua.blocked, ua.fullname
    FROM modx_users u
    LEFT JOIN modx_user_attributes ua ON ua.internalKey = u.id
    WHERE u.id = " . (int)$targetUserId
);

if ($stmt !== false && $user = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "✓ Пользователь ID " . $user['id'] . " настроен:\n";
    echo "   Username: " . $user['username'] . "\n";
    echo "   Email: " . ($user['email'] ?? 'NULL') . "\n";
    echo "   Full Name: " . ($user['fullname'] ?? 'NULL') . "\n";
    echo "   Active: " . ($user['active'] ? 'ДА ✓' : 'НЕТ ✗') . "\n";
    echo "   Blocked: " . ($user['blocked'] ? 'ДА ✗' : 'НЕТ ✓') . "\n";
}

echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "✓ ГОТОВО - ПОЛЬЗОВАТЕЛЬ ID 2 ГОТОВ К РАБОТЕ\n";
echo "═══════════════════════════════════════════════════════════════════\n";
```

---

## 🔄 СКРИПТ 3: Использовать существующего пользователя

Если вы хотите использовать другого существующего пользователя вместо создания нового ID 2:

```php
echo "═══════════════════════════════════════════════════════════════════\n";
echo "🔄 ВЫБОР ПОЛЬЗОВАТЕЛЯ ДЛЯ ТЕСТИРОВАНИЯ LMS\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

// Получить всех активных пользователей
$stmt = $modx->query("
    SELECT u.id, u.username, ua.email
    FROM modx_users u
    LEFT JOIN modx_user_attributes ua ON ua.internalKey = u.id
    WHERE u.active = 1 AND (ua.blocked IS NULL OR ua.blocked = 0)
    ORDER BY u.id ASC
");

if ($stmt === false) {
    echo "✗ Ошибка SQL\n";
    return;
}

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo "✗ Нет активных пользователей в системе\n";
    echo "⚠️  Запустите СКРИПТ 2 для создания пользователя ID 2\n";
} else {
    echo "ДОСТУПНЫЕ ПОЛЬЗОВАТЕЛИ:\n\n";

    printf("%-5s | %-20s | %s\n", "ID", "Username", "Email");
    echo str_repeat("-", 60) . "\n";

    foreach ($users as $user) {
        printf("%-5d | %-20s | %s\n",
            $user['id'],
            $user['username'],
            $user['email'] ?? 'нет'
        );
    }

    echo "\n" . str_repeat("─", 70) . "\n";
    echo "ИНСТРУКЦИЯ:\n";
    echo "   Если вы хотите использовать одного из этих пользователей,\n";
    echo "   обновите скрипты диагностики, заменив \$userId = 2 на нужный ID\n";
}

echo "\n═══════════════════════════════════════════════════════════════════\n";
```

---

## 📋 Порядок действий

### 1. Диагностика
Запустите **СКРИПТ 1** чтобы понять текущую ситуацию с пользователями

### 2. Создание пользователя (если нужно)
Если пользователь ID 2 не найден:
- Запустите **СКРИПТ 2** для создания пользователя ID 2
- Или запустите **СКРИПТ 3** чтобы выбрать существующего пользователя

### 3. Проверка LMS
После создания/настройки пользователя:
1. Запустите `LMS_SETUP_VERIFICATION.md → SCRIPT 1`
2. Запустите `TEST_USER_VISIBILITY.md → SCRIPT 1`

---

## ⚠️ Важные замечания

- **ID 2** используется во всех тестовых скриптах как стандартный тестовый пользователь
- Если вы создаете пользователя вручную, убедитесь что он активен (`active = 1`)
- Пароль должен быть безопасным в production-среде
- После создания пользователя обязательно очистите кеш MODX

---

## 🔐 Безопасность

**ВНИМАНИЕ:** Тестовый пароль в скриптах (`Test123456!`) предназначен только для разработки!

В production-среде:
1. Используйте сложный уникальный пароль
2. Не храните пароли в открытом виде
3. Регулярно меняйте пароли
4. Используйте двухфакторную аутентификацию если возможно

---

## 🧪 Тестирование

После создания пользователя проверьте:

1. ✓ Пользователь может войти в систему
2. ✓ Пользователь видит страницу `/tests`
3. ✓ Пользователь может начать прохождение теста
4. ✓ Результаты сохраняются корректно

---

**Последнее обновление:** 2025-11-20
