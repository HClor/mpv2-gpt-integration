# Автоматическое создание ресурсов MODX для тестов

## Описание

Этот скрипт автоматически создает структуру ресурсов MODX для всех тестов и категорий из LMS системы.

**Структура:**
```
Тесты (ID 35)
├── Категория 1 (новый ресурс-контейнер)
│   ├── Тест 1.1 (новый ресурс)
│   └── Тест 1.2 (новый ресурс)
└── Категория 2 (новый ресурс-контейнер)
    ├── Тест 2.1 (новый ресурс)
    └── Тест 2.2 (новый ресурс)
```

---

## 🔍 СКРИПТ 1: Предварительная проверка

Запусти в **MODX Console** для проверки текущего состояния:

```php
echo "═══════════════════════════════════════════════════════════════════\n";
echo "🔍 ПРЕДВАРИТЕЛЬНАЯ ПРОВЕРКА\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

// 1. Проверить родительскую страницу "Тесты" (ID 35)
echo "[1] ПРОВЕРКА РОДИТЕЛЬСКОЙ СТРАНИЦЫ\n";
echo "────────────────────────────────────────────────────────────────\n";

$stmt = $modx->query("
    SELECT id, pagetitle, alias, isfolder
    FROM modx_site_content
    WHERE id = 35
");

if ($stmt !== false && $page = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf("✓ Страница найдена: %s (alias: %s)\n", $page['pagetitle'], $page['alias']);
    printf("  Контейнер: %s\n", $page['isfolder'] ? 'ДА' : 'НЕТ');

    if (!$page['isfolder']) {
        echo "  ⚠️  Страница должна быть контейнером! Будет исправлено.\n";
    }
} else {
    echo "✗ Страница ID 35 не найдена!\n";
    echo "  Невозможно создать структуру.\n";
    return;
}

// 2. Проверить категории
echo "\n[2] ПРОВЕРКА КАТЕГОРИЙ\n";
echo "────────────────────────────────────────────────────────────────\n";

$stmt = $modx->query("
    SELECT id, name, description, parent_id
    FROM modx_test_categories
    ORDER BY parent_id, sort_order, name
");

if ($stmt !== false) {
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ Найдено категорий: " . count($categories) . "\n\n";

    foreach ($categories as $cat) {
        printf("  ID: %-3d | %-30s | Parent: %s\n",
            $cat['id'],
            substr($cat['name'], 0, 30),
            $cat['parent_id'] ?? 'NULL'
        );
    }
}

// 3. Проверить тесты
echo "\n[3] ПРОВЕРКА ТЕСТОВ\n";
echo "────────────────────────────────────────────────────────────────\n";

$stmt = $modx->query("
    SELECT
        t.id,
        t.title,
        t.resource_id as category_id,
        t.publication_status,
        t.is_active,
        c.name as category_name
    FROM modx_test_tests t
    LEFT JOIN modx_test_categories c ON c.id = t.resource_id
    ORDER BY c.name, t.title
");

if ($stmt !== false) {
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ Найдено тестов: " . count($tests) . "\n\n";

    $byCategory = [];
    foreach ($tests as $test) {
        $catName = $test['category_name'] ?? 'БЕЗ КАТЕГОРИИ';
        if (!isset($byCategory[$catName])) {
            $byCategory[$catName] = 0;
        }
        $byCategory[$catName]++;
    }

    echo "Распределение по категориям:\n";
    foreach ($byCategory as $cat => $count) {
        printf("  %-30s : %d тестов\n", $cat, $count);
    }
}

// 4. Проверить существующие ресурсы-дети страницы 35
echo "\n[4] СУЩЕСТВУЮЩИЕ ДОЧЕРНИЕ РЕСУРСЫ\n";
echo "────────────────────────────────────────────────────────────────\n";

$stmt = $modx->query("
    SELECT id, pagetitle, alias
    FROM modx_site_content
    WHERE parent = 35 AND deleted = 0
    ORDER BY menuindex
");

if ($stmt !== false) {
    $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($existing)) {
        echo "○ Нет дочерних ресурсов (будут созданы)\n";
    } else {
        echo "⚠️  Найдено существующих ресурсов: " . count($existing) . "\n\n";
        foreach ($existing as $res) {
            printf("  ID: %-3d | %s (alias: %s)\n",
                $res['id'],
                $res['pagetitle'],
                $res['alias']
            );
        }
        echo "\n  ⚠️  Эти ресурсы будут ПРОПУЩЕНЫ при создании новых\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "✓ ПРОВЕРКА ЗАВЕРШЕНА\n";
echo "═══════════════════════════════════════════════════════════════════\n";
```

---

## 🔧 СКРИПТ 2: Создание ресурсов

**ВНИМАНИЕ:** Этот скрипт создаст новые ресурсы MODX!

Запусти в **MODX Console**:

```php
echo "═══════════════════════════════════════════════════════════════════\n";
echo "🔧 СОЗДАНИЕ РЕСУРСОВ MODX ДЛЯ ТЕСТОВ\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$parentId = 35; // ID страницы "Тесты"
$templateId = 0; // ID шаблона (0 = наследовать от родителя)
$createdBy = 1; // ID пользователя-создателя
$context = 'web';

$createdCategories = 0;
$createdTests = 0;
$errors = [];

// Убедиться что родительская страница - контейнер
$modx->exec("UPDATE modx_site_content SET isfolder = 1 WHERE id = $parentId");

// Получить все категории
$stmt = $modx->query("
    SELECT id, name, description, parent_id, sort_order
    FROM modx_test_categories
    ORDER BY parent_id, sort_order, name
");

if ($stmt === false) {
    echo "✗ Ошибка получения категорий\n";
    return;
}

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "[1] СОЗДАНИЕ РЕСУРСОВ КАТЕГОРИЙ\n";
echo "────────────────────────────────────────────────────────────────\n";

$categoryResourceMap = []; // Маппинг: category_id => resource_id

foreach ($categories as $index => $cat) {
    $alias = $modx->filterPathSegment($modx->stripTags($cat['name']));
    $menuIndex = $cat['sort_order'] ?? ($index * 10);

    // Проверить существует ли уже ресурс с таким alias
    $stmt = $modx->query("
        SELECT id FROM modx_site_content
        WHERE parent = $parentId
          AND alias = " . $modx->quote($alias) . "
          AND deleted = 0
    ");

    if ($stmt !== false && $existing = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  ○ Категория '" . $cat['name'] . "' уже существует (ID: " . $existing['id'] . ")\n";
        $categoryResourceMap[$cat['id']] = $existing['id'];
        continue;
    }

    // Создать ресурс категории
    try {
        $sql = "
            INSERT INTO modx_site_content
            (pagetitle, longtitle, description, alias, published, parent, isfolder,
             template, menuindex, content, publishedon, pub_date, createdby, createdon,
             context_key)
            VALUES (
                " . $modx->quote($cat['name']) . ",
                " . $modx->quote($cat['name']) . ",
                " . $modx->quote($cat['description'] ?? '') . ",
                " . $modx->quote($alias) . ",
                1,
                $parentId,
                1,
                $templateId,
                $menuIndex,
                '',
                UNIX_TIMESTAMP(),
                0,
                $createdBy,
                UNIX_TIMESTAMP(),
                '$context'
            )
        ";

        $result = $modx->exec($sql);

        if ($result !== false) {
            $newId = $modx->pdo->lastInsertId();
            $categoryResourceMap[$cat['id']] = $newId;
            echo "  ✓ Создана категория '" . $cat['name'] . "' (ID: $newId)\n";
            $createdCategories++;
        } else {
            $error = "Не удалось создать категорию '" . $cat['name'] . "'";
            echo "  ✗ $error\n";
            $errors[] = $error;
        }

    } catch (Exception $e) {
        $error = "Ошибка создания категории '" . $cat['name'] . "': " . $e->getMessage();
        echo "  ✗ $error\n";
        $errors[] = $error;
    }
}

echo "\n[2] СОЗДАНИЕ РЕСУРСОВ ТЕСТОВ\n";
echo "────────────────────────────────────────────────────────────────\n";

// Получить все тесты
$stmt = $modx->query("
    SELECT
        t.id,
        t.title,
        t.description,
        t.resource_id as category_id,
        t.publication_status,
        t.is_active
    FROM modx_test_tests t
    ORDER BY t.resource_id, t.title
");

if ($stmt === false) {
    echo "✗ Ошибка получения тестов\n";
    return;
}

$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($tests as $test) {
    $categoryId = $test['category_id'];

    // Проверить что категория имеет ресурс
    if (!isset($categoryResourceMap[$categoryId])) {
        echo "  ⚠️  Тест '" . $test['title'] . "' имеет несуществующую категорию (ID: $categoryId)\n";
        continue;
    }

    $categoryResourceId = $categoryResourceMap[$categoryId];
    $alias = $modx->filterPathSegment($modx->stripTags($test['title']));

    // Проверить существует ли уже ресурс с таким alias
    $stmt = $modx->query("
        SELECT id FROM modx_site_content
        WHERE parent = $categoryResourceId
          AND alias = " . $modx->quote($alias) . "
          AND deleted = 0
    ");

    if ($stmt !== false && $existing = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  ○ Тест '" . substr($test['title'], 0, 40) . "' уже существует\n";
        continue;
    }

    // Создать ресурс теста
    try {
        $published = ($test['publication_status'] === 'public' && $test['is_active']) ? 1 : 0;
        $content = '[[!testRunner?&testId=`' . $test['id'] . '`]]';

        $sql = "
            INSERT INTO modx_site_content
            (pagetitle, longtitle, description, alias, published, parent, isfolder,
             template, menuindex, content, publishedon, pub_date, createdby, createdon,
             context_key)
            VALUES (
                " . $modx->quote($test['title']) . ",
                " . $modx->quote($test['title']) . ",
                " . $modx->quote($test['description'] ?? '') . ",
                " . $modx->quote($alias) . ",
                $published,
                $categoryResourceId,
                0,
                $templateId,
                0,
                " . $modx->quote($content) . ",
                " . ($published ? "UNIX_TIMESTAMP()" : "0") . ",
                0,
                $createdBy,
                UNIX_TIMESTAMP(),
                '$context'
            )
        ";

        $result = $modx->exec($sql);

        if ($result !== false) {
            $newId = $modx->pdo->lastInsertId();
            echo "  ✓ Создан тест '" . substr($test['title'], 0, 40) . "' (ID: $newId)\n";
            $createdTests++;
        } else {
            $error = "Не удалось создать тест '" . $test['title'] . "'";
            echo "  ✗ $error\n";
            $errors[] = $error;
        }

    } catch (Exception $e) {
        $error = "Ошибка создания теста '" . $test['title'] . "': " . $e->getMessage();
        echo "  ✗ $error\n";
        $errors[] = $error;
    }
}

echo "\n[3] ОЧИСТКА КЕША\n";
echo "────────────────────────────────────────────────────────────────\n";
$modx->cacheManager->refresh();
echo "✓ Кеш очищен\n";

// Итоги
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "✓ СОЗДАНИЕ ЗАВЕРШЕНО\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

echo "Создано категорий: $createdCategories\n";
echo "Создано тестов: $createdTests\n";

if (!empty($errors)) {
    echo "\n⚠️  Ошибки (" . count($errors) . "):\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════════════\n";
```

---

## 🔄 СКРИПТ 3: Проверка созданной структуры

Запусти в **MODX Console** для проверки результата:

```php
echo "═══════════════════════════════════════════════════════════════════\n";
echo "🔍 ПРОВЕРКА СОЗДАННОЙ СТРУКТУРЫ\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

// Рекурсивная функция для вывода дерева ресурсов
function printResourceTree($modx, $parentId, $level = 0) {
    $indent = str_repeat("  ", $level);

    $stmt = $modx->query("
        SELECT id, pagetitle, alias, published, isfolder
        FROM modx_site_content
        WHERE parent = $parentId AND deleted = 0
        ORDER BY menuindex, pagetitle
    ");

    if ($stmt === false) {
        return;
    }

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = $row['published'] ? '✓' : '○';
        $type = $row['isfolder'] ? '📁' : '📄';

        printf("%s%s %s ID: %-3d | %s (%s)\n",
            $indent,
            $status,
            $type,
            $row['id'],
            $row['pagetitle'],
            $row['alias']
        );

        // Рекурсивно показать детей
        if ($row['isfolder']) {
            printResourceTree($modx, $row['id'], $level + 1);
        }
    }
}

echo "СТРУКТУРА РЕСУРСОВ:\n\n";
echo "📁 Тесты (ID: 35)\n";
printResourceTree($modx, 35, 1);

echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "✓ ПРОВЕРКА ЗАВЕРШЕНА\n";
echo "═══════════════════════════════════════════════════════════════════\n";
```

---

## ⚠️ Важные замечания

1. **Alias генерируется автоматически** из названия категории/теста
2. **Дублирующиеся ресурсы пропускаются** (проверка по alias)
3. **Порядок сортировки** берется из `modx_test_categories.sort_order`
4. **Публикация тестов** зависит от `publication_status = 'public'` и `is_active = 1`
5. **Все созданные ресурсы** будут дочерними для страницы "Тесты" (ID 35)

---

## 🔧 Если что-то пошло не так

### Удалить все созданные ресурсы
```sql
-- ОСТОРОЖНО! Удаляет ВСЕ дочерние ресурсы страницы 35
UPDATE modx_site_content
SET deleted = 1, deletedon = UNIX_TIMESTAMP(), deletedby = 1
WHERE parent = 35;

-- Или удалить конкретную категорию и её детей
UPDATE modx_site_content
SET deleted = 1, deletedon = UNIX_TIMESTAMP(), deletedby = 1
WHERE id = [ID_КАТЕГОРИИ] OR parent = [ID_КАТЕГОРИИ];
```

### Очистить кеш
```php
$modx->cacheManager->refresh();
```

---

## 📋 Чек-лист после создания

- [ ] Все категории созданы как ресурсы-контейнеры
- [ ] Все тесты созданы как ресурсы-документы
- [ ] Публичные тесты отмечены как опубликованные
- [ ] Структура отображается в дереве ресурсов MODX
- [ ] Кеш очищен
- [ ] Тесты доступны по URL (например: `/tests/kategoriya-1/test-1`)

---

**Последнее обновление:** 2025-11-20
