# Аудит структуры страниц и функциональности LMS

## 🔍 ДИАГНОСТИКА 1: Анализ дублирующихся страниц

### Проблема: Две страницы "Прохождение теста"
- ID: 155 | alias: test-run
- ID: 147 | alias: run (parent: 35 - Тесты)

Запусти в **MODX Console**:

```php
echo "=== АНАЛИЗ ДУБЛИРУЮЩИХСЯ СТРАНИЦ ===\n\n";

// Страницы с похожими названиями/функциям
$pages = [
    ['id' => 146, 'title' => 'Список тестов', 'type' => 'list'],
    ['id' => 35, 'title' => 'Тесты', 'type' => 'main'],
    ['id' => 147, 'title' => 'Прохождение теста', 'type' => 'run (old)'],
    ['id' => 155, 'title' => 'Прохождение теста', 'type' => 'run (new)'],
    ['id' => 156, 'title' => 'Результаты', 'type' => 'results'],
    ['id' => 145, 'title' => 'Область знаний', 'type' => 'category'],
];

foreach ($pages as $p) {
    $stmt = $modx->query("SELECT id, pagetitle, alias, parent, content FROM modx_site_content WHERE id = " . intval($p['id']));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Найти сниппеты в контенте
        preg_match_all('/\[\[([a-zA-Z0-9_-]+)/', $row['content'], $matches);
        $snippets = $matches[1] ?? [];

        printf("ID: %-3d | Alias: %-25s | Parent: %-3s | Type: %s\n",
            $row['id'], "'{$row['alias']}'", $row['parent'] ?: 'ROOT', $p['type']);
        printf("         Title: %s\n", $row['pagetitle']);
        printf("         Snippets: %s\n\n",
            count($snippets) > 0 ? implode(', ', $snippets) : 'NONE');
    }
}
```

---

## 🔍 ДИАГНОСТИКА 2: Иерархия страниц и их назначение

```php
echo "=== ИЕРАРХИЯ СТРАНИЦ ===\n\n";

// Построить дерево страниц
$stmt = $modx->query("
SELECT id, pagetitle, alias, parent, deleted
FROM modx_site_content
WHERE deleted = 0
ORDER BY parent ASC, id ASC
");
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Построить иерархию
$tree = [];
foreach ($pages as $page) {
    $parent = $page['parent'] ?: 0;
    if (!isset($tree[$parent])) {
        $tree[$parent] = [];
    }
    $tree[$parent][] = $page;
}

// Вывести иерархию
function printTree($parentId, $indent = 0, $tree) {
    if (!isset($tree[$parentId])) return;

    foreach ($tree[$parentId] as $page) {
        echo str_repeat("  ", $indent) . "├─ ID: {$page['id']} | {$page['alias']} | {$page['pagetitle']}\n";
        printTree($page['id'], $indent + 1, $tree);
    }
}

echo "ROOT (0):\n";
printTree(0, 1, $tree);
```

---

## 🔍 ДИАГНОСТИКА 3: Содержимое каждой ключевой страницы

```php
echo "=== СОДЕРЖИМОЕ КЛЮЧЕВЫХ СТРАНИЦ ===\n\n";

$keyPages = [35, 155, 156, 157, 158, 159, 34, 146, 147, 145];

foreach ($keyPages as $id) {
    $stmt = $modx->query("SELECT id, pagetitle, alias, content FROM modx_site_content WHERE id = " . intval($id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo "═══════════════════════════════════\n";
        printf("ID: %d | Alias: %s | Title: %s\n", $row['id'], $row['alias'], $row['pagetitle']);
        echo "─────────────────────────────────────\n";
        echo "Content: " . substr($row['content'], 0, 200) . "\n";
        if (strlen($row['content']) > 200) echo "...\n";
        echo "\n";
    }
}
```

---

## 🔍 ДИАГНОСТИКА 4: Проблема с видимостью тестов

Пользователь авторизован как ID 2, но не видит тесты. Проверим:

```php
echo "=== ПРОВЕРКА ПРАВ ДОСТУПА И ВИДИМОСТИ ТЕСТОВ ===\n\n";

// 1. Проверить сколько тестов в БД
$stmt = $modx->query("SELECT COUNT(*) as cnt FROM modx_test_tests");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "1. Всего тестов в БД: {$row['cnt']}\n\n";

// 2. Проверить тесты которые доступны для пользователя с ID 2
$stmt = $modx->query("
SELECT
    t.id,
    t.title,
    t.category_id,
    t.is_public,
    t.created_by,
    COUNT(q.id) as question_count
FROM modx_test_tests t
LEFT JOIN modx_test_questions q ON q.test_id = t.id
GROUP BY t.id
LIMIT 20
");
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "2. Первые 20 тестов:\n";
foreach ($tests as $test) {
    printf("   ID: %-3d | Title: %-40s | Public: %-5s | Created by: %-3s | Questions: %d\n",
        $test['id'],
        substr($test['title'], 0, 40),
        $test['is_public'] ? 'YES' : 'NO',
        $test['created_by'],
        $test['question_count']
    );
}

// 3. Проверить права доступа пользователя
echo "\n3. Права доступа пользователя ID 2:\n";
$stmt = $modx->query("
SELECT * FROM modx_test_permissions
WHERE user_id = 2 OR user_group_id IN (
    SELECT group_id FROM modx_membergroup_member
    WHERE member_id = 2
)
");
$perms = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($perms) > 0) {
    foreach ($perms as $perm) {
        print_r($perm);
    }
} else {
    echo "   Нет ограничений - должны видеть все PUBLIC тесты\n";
}

// 4. Проверить категории
echo "\n4. Категории тестов:\n";
$stmt = $modx->query("SELECT * FROM modx_test_categories");
$cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cats as $cat) {
    printf("   ID: %-3d | Title: %s\n", $cat['id'], $cat['title']);
}
```

---

## 🔍 ДИАГНОСТИКА 5: Проверка работы сниппетов

```php
echo "=== ПРОВЕРКА РАБОТЫ СНИППЕТОВ ===\n\n";

// Проверить есть ли файлы сниппетов
$snippets = [
    'categoriesAndTests' => '/core/components/testsystem/elements/snippets/categoriesAndTests.php',
    'testRunner' => '/core/components/testsystem/elements/snippets/testRunner.php',
    'testResults' => '/core/components/testsystem/elements/snippets/testResults.php',
    'testHistory' => '/core/components/testsystem/elements/snippets/testHistory.php',
    'getUserStats' => '/core/components/testsystem/elements/snippets/getUserStats.php',
    'leaderboard' => '/core/components/testsystem/elements/snippets/leaderboard.php',
    'achievements' => '/core/components/testsystem/elements/snippets/achievements.php',
];

foreach ($snippets as $name => $path) {
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $path;
    $exists = file_exists($fullPath) ? '✓' : '✗';
    echo "{$exists} {$name}: {$path}\n";
}

// Проверить есть ли сниппеты в MODX
echo "\nСниппеты в MODX:\n";
$stmt = $modx->query("SELECT id, name FROM modx_site_snippets");
$snippetsInModx = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($snippetsInModx as $s) {
    echo "  ID: {$s['id']} | Name: {$s['name']}\n";
}
```

---

## 📋 ЧТО НУЖНО ВЫЯСНИТЬ

После запуска всех диагностик, выведи результаты и мы определим:

1. **Дублирующиеся страницы:**
   - Какую из них (155 или 147) оставить?
   - Какую удалить?

2. **Правильная структура:**
   - Как должны быть организованы страницы?
   - Должны ли они быть в папке Тесты или в корне?

3. **Видимость тестов:**
   - Почему не видны тесты для пользователя ID 2?
   - Есть ли права доступа?
   - Работают ли сниппеты?

4. **Функциональность спринтов:**
   - Какие сниппеты существуют?
   - Какие файлы сниппетов есть в проекте?

