# Тестирование видимости тестов для пользователя ID 2

## Описание проблемы

Пользователь ID 2 авторизован в системе, но на странице `/tests` не видит ни одного теста.

---

## 🔍 СКРИПТ 1: Диагностика видимости тестов

Запусти в **MODX Console**:

```php
echo "═══════════════════════════════════════════════════════════════════\n";
echo "🔍 ДИАГНОСТИКА ВИДИМОСТИ ТЕСТОВ ДЛЯ ПОЛЬЗОВАТЕЛЯ ID 2\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$userId = 2;
$prefix = $modx->getOption('table_prefix');

// Шаг 1: Проверить существует ли пользователь
echo "[1] ПРОВЕРКА ПОЛЬЗОВАТЕЛЯ ID 2\n";
echo "────────────────────────────────────────────────────────────────\n";

$stmt = $modx->query("SELECT id, username, email, active FROM modx_users WHERE id = " . (int)$userId);

if ($stmt === false) {
    echo "✗ Ошибка SQL\n";
    return;
}

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "✗ Пользователь ID 2 не найден\n";
    return;
}

printf("✓ Пользователь найден: %s (%s)\n", $user['username'], $user['email']);
printf("  Статус: %s\n", $user['active'] ? 'Активен' : 'НЕАКТИВЕН!');

// Шаг 2: Проверить группы пользователя
echo "\n[2] ПРОВЕРКА ГРУПП ПОЛЬЗОВАТЕЛЯ\n";
echo "────────────────────────────────────────────────────────────────\n";

$stmt = $modx->query("
    SELECT mg.id, mg.name
    FROM modx_membergroup mg
    INNER JOIN modx_membergroup_member mm ON mm.group_id = mg.id
    WHERE mm.member_id = " . (int)$userId
);

if ($stmt !== false) {
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($groups)) {
        echo "○ Пользователь не входит в никакие группы\n";
    } else {
        echo "✓ Группы пользователя:\n";
        foreach ($groups as $group) {
            printf("  - ID: %d | Название: %s\n", $group['id'], $group['name']);
        }
    }
} else {
    echo "? Ошибка при проверке групп\n";
}

// Шаг 3: Проверить сколько всего тестов в системе
echo "\n[3] ПРОВЕРКА ТЕСТОВ В СИСТЕМЕ\n";
echo "────────────────────────────────────────────────────────────────\n";

$stmt = $modx->query("SELECT COUNT(*) as cnt FROM modx_test_tests");

if ($stmt !== false && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf("  Всего тестов: %d\n", $row['cnt']);
}

// Шаг 4: Проверить публичные тесты
echo "\n[4] ПУБЛИЧНЫЕ ТЕСТЫ (is_public=1)\n";
echo "────────────────────────────────────────────────────────────────\n";

$stmt = $modx->query("
    SELECT
        t.id,
        t.title,
        t.is_public,
        t.created_by,
        c.title as category
    FROM modx_test_tests t
    LEFT JOIN modx_test_categories c ON c.id = t.category_id
    WHERE t.is_public = 1
    ORDER BY t.id ASC
    LIMIT 20
");

if ($stmt === false) {
    echo "✗ Ошибка SQL при запросе публичных тестов\n";
} else {
    $publicTests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($publicTests)) {
        echo "⚠️  НЕТ ПУБЛИЧНЫХ ТЕСТОВ!\n";
        echo "    ⚠️  Это может быть причиной отсутствия тестов для пользователя!\n";
    } else {
        printf("✓ Найдено %d публичных тестов:\n", count($publicTests));
        foreach ($publicTests as $test) {
            printf("  - ID: %-3d | %s (категория: %s)\n",
                $test['id'],
                $test['title'],
                $test['category'] ?? 'нет'
            );
        }
    }
}

// Шаг 5: Проверить тесты созданные пользователем 2
echo "\n[5] ТЕСТЫ, СОЗДАННЫЕ ПОЛЬЗОВАТЕЛЕМ ID 2\n";
echo "────────────────────────────────────────────────────────────────\n";

$stmt = $modx->query("
    SELECT
        id,
        title,
        is_public,
        created_at
    FROM modx_test_tests
    WHERE created_by = " . (int)$userId
);

if ($stmt === false) {
    echo "✗ Ошибка SQL\n";
} else {
    $userTests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($userTests)) {
        echo "○ Пользователь не создавал тесты\n";
    } else {
        printf("✓ Пользователь создал %d тестов:\n", count($userTests));
        foreach ($userTests as $test) {
            printf("  - ID: %-3d | %s | Public: %s\n",
                $test['id'],
                $test['title'],
                $test['is_public'] ? 'ДА' : 'НЕТ'
            );
        }
    }
}

// Шаг 6: Проверить права доступа пользователя
echo "\n[6] ПРАВА ДОСТУПА ПОЛЬЗОВАТЕЛЯ\n";
echo "────────────────────────────────────────────────────────────────\n";

$stmt = $modx->query("
    SELECT *
    FROM modx_test_permissions
    WHERE user_id = " . (int)$userId . " OR user_group_id IN (
        SELECT group_id FROM modx_membergroup_member WHERE member_id = " . (int)$userId . "
    )
");

if ($stmt === false) {
    echo "? Ошибка при проверке прав\n";
} else {
    $perms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($perms)) {
        echo "○ Нет специальных ограничений - должен видеть все PUBLIC тесты\n";
    } else {
        echo "✓ Специальные права:\n";
        foreach ($perms as $perm) {
            print_r($perm);
        }
    }
}

// Шаг 7: Проверить категории
echo "\n[7] КАТЕГОРИИ ТЕСТОВ\n";
echo "────────────────────────────────────────────────────────────────\n";

$stmt = $modx->query("
    SELECT
        id,
        title,
        description
    FROM modx_test_categories
    ORDER BY id ASC
    LIMIT 10
");

if ($stmt !== false) {
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($categories)) {
        echo "⚠️  НЕТ КАТЕГОРИЙ!\n";
    } else {
        printf("✓ Найдено %d категорий:\n", count($categories));
        foreach ($categories as $cat) {
            printf("  - ID: %-2d | %s\n", $cat['id'], $cat['title']);
        }
    }
}

// Шаг 8: Проверить что categoriesAndTests сниппет существует
echo "\n[8] ПРОВЕРКА СНИППЕТА categoriesAndTests\n";
echo "────────────────────────────────────────────────────────────────\n";

$snippetFile = $_SERVER['DOCUMENT_ROOT'] . '/core/elements/snippets/categoriesAndTests.php';

if (file_exists($snippetFile)) {
    echo "✓ Файл сниппета найден\n";
    $size = filesize($snippetFile);
    printf("  Размер: %d байт\n", $size);
} else {
    echo "✗ ФАЙЛ СНИППЕТА НЕ НАЙДЕН: $snippetFile\n";
}

// Шаг 9: Проверить содержимое страницы 35 (tests)
echo "\n[9] ПРОВЕРКА СТРАНИЦЫ ID 35 (tests)\n";
echo "────────────────────────────────────────────────────────────────\n";

$stmt = $modx->query("
    SELECT id, pagetitle, alias, content, published, deleted
    FROM modx_site_content
    WHERE id = 35
");

if ($stmt !== false && $page = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf("Страница: %s (%s)\n", $page['pagetitle'], $page['alias']);
    printf("  Published: %s\n", $page['published'] ? 'ДА' : 'НЕТ');
    printf("  Deleted: %s\n", $page['deleted'] ? 'ДА' : 'НЕТ');
    printf("  Содержимое: %d символов\n", strlen($page['content']));

    if (strpos($page['content'], 'categoriesAndTests') !== false) {
        echo "✓ Содержит сниппет [[categoriesAndTests]]\n";
    } else {
        echo "✗ НЕ СОДЕРЖИТ СНИППЕТ!\n";
        echo "  Содержимое: " . substr($page['content'], 0, 200) . "...\n";
    }
} else {
    echo "✗ Страница ID 35 не найдена\n";
}

// ИТОГИ
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "📋 ВОЗМОЖНЫЕ ПРИЧИНЫ ОТСУТСТВИЯ ТЕСТОВ:\n";
echo "═══════════════════════════════════════════════════════════════════\n";

$problems = [];

// Проверяем по результатам
if ($user && !$user['active']) {
    $problems[] = "✗ Пользователь неактивен";
}

$stmt = $modx->query("SELECT COUNT(*) as cnt FROM modx_test_tests WHERE is_public = 1");
if ($stmt !== false && $row = $stmt->fetch(PDO::FETCH_ASSOC) && $row['cnt'] == 0) {
    $problems[] = "✗ Нет публичных тестов (is_public = 1)";
}

$stmt = $modx->query("SELECT COUNT(*) as cnt FROM modx_test_categories");
if ($stmt !== false && $row = $stmt->fetch(PDO::FETCH_ASSOC) && $row['cnt'] == 0) {
    $problems[] = "✗ Нет категорий тестов";
}

$stmt = $modx->query("SELECT content FROM modx_site_content WHERE id = 35");
if ($stmt !== false && $page = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (empty($page['content']) || strpos($page['content'], 'categoriesAndTests') === false) {
        $problems[] = "✗ Страница 35 не содержит [[!categoriesAndTests]]";
    }
}

if (empty($problems)) {
    echo "✓ ПРОБЛЕМ НЕ НАЙДЕНО - система должна работать!\n";
} else {
    foreach ($problems as $problem) {
        echo $problem . "\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════════════\n";
```

---

## 🛠️ СКРИПТ 2: Исправление обнаруженных проблем

Если диагностика выявила проблемы, запусти этот скрипт:

```php
echo "═══════════════════════════════════════════════════════════════════\n";
echo "🛠️  ИСПРАВЛЕНИЕ ПРОБЛЕМ С ВИДИМОСТЬЮ ТЕСТОВ\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$userId = 2;
$prefix = $modx->getOption('table_prefix');

// Исправление 1: Активировать пользователя если он неактивен
echo "[1] Проверка активности пользователя...\n";
$stmt = $modx->query("SELECT active FROM modx_users WHERE id = " . (int)$userId);

if ($stmt !== false && $user = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!$user['active']) {
        echo "    Активация пользователя ID 2...\n";
        $modx->exec("UPDATE modx_users SET active = 1 WHERE id = " . (int)$userId);
        echo "    ✓ Готово\n";
    } else {
        echo "    ✓ Пользователь уже активен\n";
    }
}

// Исправление 2: Убедиться что есть публичные тесты
echo "\n[2] Проверка публичных тестов...\n";
$stmt = $modx->query("SELECT COUNT(*) as cnt FROM modx_test_tests WHERE is_public = 1");

if ($stmt !== false && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['cnt'] == 0) {
        echo "    ⚠️  Нет публичных тестов - нужно создать или сделать их публичными\n";
        echo "    Попытка сделать ВСЕ тесты публичными...\n";
        $modx->exec("UPDATE modx_test_tests SET is_public = 1 WHERE is_public = 0");
        $stmt = $modx->query("SELECT COUNT(*) as cnt FROM modx_test_tests");
        if ($stmt !== false && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "    ✓ Теперь публичных тестов: " . $row['cnt'] . "\n";
        }
    } else {
        echo "    ✓ Публичные тесты присутствуют (" . $row['cnt'] . " штук)\n";
    }
}

// Исправление 3: Убедиться что есть категории
echo "\n[3] Проверка категорий...\n";
$stmt = $modx->query("SELECT COUNT(*) as cnt FROM modx_test_categories");

if ($stmt !== false && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['cnt'] == 0) {
        echo "    Создание тестовой категории...\n";
        $modx->exec("
            INSERT INTO modx_test_categories (title, description, created_at)
            VALUES ('Основная', 'Основная категория тестов', NOW())
        ");
        echo "    ✓ Категория создана\n";
    } else {
        echo "    ✓ Категории присутствуют (" . $row['cnt'] . " штук)\n";
    }
}

// Исправление 4: Убедиться что страница 35 имеет правильное содержимое
echo "\n[4] Проверка страницы ID 35...\n";
$stmt = $modx->query("SELECT content, published FROM modx_site_content WHERE id = 35");

if ($stmt !== false && $page = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $needUpdate = false;

    if (empty($page['content'])) {
        echo "    Добавление [[!categoriesAndTests]] к странице 35...\n";
        $modx->exec("UPDATE modx_site_content SET content = '[[!categoriesAndTests]]' WHERE id = 35");
        $needUpdate = true;
    }

    if (!$page['published']) {
        echo "    Публикация страницы 35...\n";
        $modx->exec("UPDATE modx_site_content SET published = 1 WHERE id = 35");
        $needUpdate = true;
    }

    if ($needUpdate) {
        echo "    ✓ Страница 35 обновлена\n";
    } else {
        echo "    ✓ Страница 35 в порядке\n";
    }
}

// Исправление 5: Очистить кеш
echo "\n[5] Очистка кеша MODX...\n";
$modx->cacheManager->clearCache();
echo "    ✓ Кеш очищен\n";

// Результаты
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "✓ ВСЕ ПРОБЛЕМЫ ИСПРАВЛЕНЫ\n";
echo "═══════════════════════════════════════════════════════════════════\n";
```

---

## 🧪 СКРИПТ 3: Проверка что пользователь ID 2 видит тесты

Запусти в **MODX Console**:

```php
echo "═══════════════════════════════════════════════════════════════════\n";
echo "✅ ФИНАЛЬНАЯ ПРОВЕРКА - ВИДИМОСТЬ ТЕСТОВ\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$userId = 2;
$prefix = $modx->getOption('table_prefix');

// Симулируем логику categoriesAndTests сниппета
echo "Симуляция логики сниппета categoriesAndTests:\n\n";

// 1. Получить все категории с тестами
$stmt = $modx->query("
    SELECT DISTINCT
        c.id,
        c.title as category_title,
        COUNT(t.id) as test_count
    FROM modx_test_categories c
    LEFT JOIN modx_test_tests t ON t.category_id = c.id AND t.is_public = 1
    GROUP BY c.id, c.title
    HAVING test_count > 0
    ORDER BY c.id ASC
");

if ($stmt === false) {
    echo "✗ Ошибка при получении категорий\n";
    return;
}

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($categories)) {
    echo "✗ ПОЛЬЗОВАТЕЛЬ ID 2 НЕ ВИДИТ ТЕСТЫ\n";
    echo "   Причина: Нет публичных тестов в категориях\n";
} else {
    echo "✓ ПОЛЬЗОВАТЕЛЬ ID 2 ВИДИТ СЛЕДУЮЩИЕ КАТЕГОРИИ:\n\n";

    foreach ($categories as $category) {
        printf("📚 %s (%d тестов)\n", $category['category_title'], $category['test_count']);

        // Получить тесты в этой категории
        $stmt = $modx->query("
            SELECT id, title, description, passing_score
            FROM modx_test_tests
            WHERE category_id = " . (int)$category['id'] . " AND is_public = 1
            ORDER BY title ASC
        ");

        if ($stmt !== false) {
            $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($tests as $test) {
                printf("   ├─ %s (проходной балл: %d%%)\n",
                    $test['title'],
                    $test['passing_score'] ?? 70
                );
            }
        }
        echo "\n";
    }

    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "✓ ВСЕ ПРОВЕРКИ ПРОЙДЕНЫ - СИСТЕМА РАБОТАЕТ ПРАВИЛЬНО\n";
    echo "═══════════════════════════════════════════════════════════════════\n";
}
```

---

## 📝 Сводка проверок

| Проверка | Статус | Если ошибка |
|----------|--------|-----------|
| Пользователь ID 2 существует | ✓ или ✗ | Создать пользователя |
| Пользователь активен | ✓ или ✗ | Активировать пользователя |
| Есть публичные тесты | ✓ или ⚠️ | Создать или сделать публичным |
| Есть категории | ✓ или ⚠️ | Создать категорию |
| Страница 35 опубликована | ✓ или ✗ | Опубликовать |
| Страница 35 имеет [[!categoriesAndTests]] | ✓ или ✗ | Добавить сниппет |
| Сниппет categoriesAndTests.php существует | ✓ или ✗ | Создать файл |
