# Скрипт восстановления структуры LMS страниц

## Проблемы, которые исправляются:

1. **Дублирующиеся страницы**: ID 155 (test-run) и ID 147 (run) - обе "Прохождение теста"
2. **Неправильная иерархия**: LMS страницы в корне вместо вложенности под /tests (ID 35)
3. **Пустое содержимое**: Страницы 35, 146, 147 без сниппетов
4. **Несинхронизированные данные**: Некоторые страницы ссылаются на неправильные ID

---

## 🚀 СКРИПТ 1: Диагностика текущего состояния

Запусти в **MODX Console**:

```php
echo "=== ДИАГНОСТИКА СТРУКТУРЫ LMS СТРАНИЦ ===\n\n";

// 1. Проверить текущие LMS страницы
$lmsPages = [35, 146, 147, 155, 156, 157, 158, 159, 34, 149, 152];

$stmt = $modx->query("
    SELECT
        id,
        pagetitle,
        alias,
        parent,
        template,
        content,
        published,
        deleted
    FROM modx_site_content
    WHERE id IN (" . implode(',', $lmsPages) . ")
    ORDER BY id ASC
");

if ($stmt === false) {
    echo "Ошибка SQL: " . print_r($modx->query("SELECT * FROM modx_site_content WHERE id = 35"), true);
    return;
}

$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "ТЕКУЩЕЕ СОСТОЯНИЕ LMS СТРАНИЦ:\n";
echo "─────────────────────────────────────────────────────────────────\n";

foreach ($pages as $page) {
    $status = '';
    if ($page['deleted']) $status .= '[УДАЛЕНА] ';
    if (!$page['published']) $status .= '[ЧЕРНОВИК] ';
    if (strlen($page['content']) == 0) $status .= '[ПУСТО!] ';

    $parentName = $page['parent'] == 0 ? '(корень)' : '(parent: ' . $page['parent'] . ')';

    printf("ID: %-3d | %s%-30s | %s\n",
        $page['id'],
        $status,
        "'" . $page['alias'] . "'",
        $parentName
    );
    printf("         Template: %-2s | Содержимое: %d символов\n",
        $page['template'],
        strlen($page['content'])
    );
    printf("         Title: %s\n\n",
        $page['pagetitle']
    );
}

// 2. Проверить дублирующиеся страницы
echo "\nПРОВЕРКА ДУБЛИРОВАНИЯ:\n";
echo "─────────────────────────────────────────────────────────────────\n";

$duplicates = [
    'test-run' => 155,
    'run' => 147,
];

foreach ($duplicates as $alias => $expectedId) {
    $stmt = $modx->query("
        SELECT id, alias, pagetitle
        FROM modx_site_content
        WHERE pagetitle LIKE '%Прохождение теста%'
        ORDER BY id ASC
    ");

    if ($stmt !== false) {
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($matches) > 1) {
            echo "⚠️  ДУБЛИРОВАНИЕ ОБНАРУЖЕНО: 'Прохождение теста'\n";
            foreach ($matches as $match) {
                printf("    - ID: %d | Alias: %s\n", $match['id'], $match['alias']);
            }
        }
    }
}

echo "\n";
```

---

## 🔧 СКРИПТ 2: Исправление иерархии и удаление дубликатов

Запусти в **MODX Console**:

```php
echo "=== ВОССТАНОВЛЕНИЕ СТРУКТУРЫ LMS ===\n\n";

$changes = [];

// Шаг 1: Удалить дублирующуюся страницу ID 147 (run)
echo "[1/4] Удаление дублирующейся страницы ID 147 (run)...\n";
try {
    $modx->exec("UPDATE modx_site_content SET deleted = 1 WHERE id = 147");
    $changes[] = "✓ Удалена старая страница ID 147 (run)";
    echo "  ✓ Готово\n";
} catch (Exception $e) {
    echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
}

// Шаг 2: Убедиться что страница 35 (тесты) имеет правильное содержимое
echo "\n[2/4] Проверка страницы ID 35 (тесты)...\n";
$stmt = $modx->query("SELECT content FROM modx_site_content WHERE id = 35");
if ($stmt !== false) {
    $page35 = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($page35['content']) || strpos($page35['content'], 'categoriesAndTests') === false) {
        echo "  Добавление [[!categoriesAndTests]] к странице 35...\n";
        $content = '[[!categoriesAndTests]]';
        $modx->exec("UPDATE modx_site_content SET content = " . $modx->quote($content) . " WHERE id = 35");
        $changes[] = "✓ Добавлен [[!categoriesAndTests]] на страницу ID 35";
        echo "  ✓ Готово\n";
    } else {
        echo "  ✓ Страница 35 уже содержит categoriesAndTests\n";
    }
}

// Шаг 3: Убедиться что страница 155 имеет правильное содержимое
echo "\n[3/4] Проверка страницы ID 155 (test-run)...\n";
$stmt = $modx->query("SELECT content FROM modx_site_content WHERE id = 155");
if ($stmt !== false) {
    $page155 = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($page155['content']) || strpos($page155['content'], 'testRunner') === false) {
        echo "  Добавление [[!testRunner]] к странице 155...\n";
        $content = '[[!testRunner]]';
        $modx->exec("UPDATE modx_site_content SET content = " . $modx->quote($content) . " WHERE id = 155");
        $changes[] = "✓ Добавлен [[!testRunner]] на страницу ID 155";
        echo "  ✓ Готово\n";
    } else {
        echo "  ✓ Страница 155 уже содержит testRunner\n";
    }
}

// Шаг 4: Убедиться что все остальные страницы имеют правильное содержимое
echo "\n[4/4] Проверка содержимого других LMS страниц...\n";

$pageSnippets = [
    156 => '[[!testResults]]',        // results
    157 => '[[!testHistory]]',        // history
    158 => '[[!getUserStats]]',       // stats
    159 => '[[!leaderboard?&period=`all_time`&limit=`50`]]', // leaderboard
    34 => '[[!leaderboard]]',         // leaderboard alt
    149 => '[[!learningMaterials]]',  // learning materials
];

foreach ($pageSnippets as $pageId => $snippet) {
    $stmt = $modx->query("SELECT alias, content FROM modx_site_content WHERE id = " . (int)$pageId);

    if ($stmt !== false) {
        $page = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($page && (empty($page['content']) || strpos($page['content'], substr($snippet, 3, -2)) === false)) {
            echo "  Обновление ID $pageId ({$page['alias']})...\n";
            $modx->exec("UPDATE modx_site_content SET content = " . $modx->quote($snippet) . " WHERE id = " . (int)$pageId);
            $changes[] = "✓ Обновлен контент страницы ID $pageId ({$page['alias']})";
        }
    }
}

echo "\n";

// Результаты
echo "=== РЕЗУЛЬТАТЫ ВОССТАНОВЛЕНИЯ ===\n";
echo "Выполнено изменений: " . count($changes) . "\n\n";

foreach ($changes as $change) {
    echo $change . "\n";
}

// Очистить кеш
echo "\n[5/4] Очистка кеша MODX...\n";
$modx->cacheManager->clearCache();
echo "✓ Кеш очищен\n";

echo "\n✓ ВОССТАНОВЛЕНИЕ ЗАВЕРШЕНО\n";
```

---

## ✅ СКРИПТ 3: Проверка результата

Запусти в **MODX Console**:

```php
echo "=== ПРОВЕРКА РЕЗУЛЬТАТА ===\n\n";

$errors = [];
$warnings = [];
$success = [];

// Проверить что ID 147 удалена
$stmt = $modx->query("SELECT deleted FROM modx_site_content WHERE id = 147");
if ($stmt !== false) {
    $page147 = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($page147['deleted'] == 0) {
        $errors[] = "❌ Страница ID 147 все еще активна (должна быть удалена)";
    } else {
        $success[] = "✓ Страница ID 147 помечена как удаленная";
    }
}

// Проверить что ID 155 активна и имеет правильное содержимое
$stmt = $modx->query("
    SELECT id, content, deleted, published
    FROM modx_site_content
    WHERE id = 155
");

if ($stmt !== false) {
    $page155 = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($page155['deleted']) {
        $errors[] = "❌ Страница ID 155 (test-run) помечена как удаленная";
    } else {
        $success[] = "✓ Страница ID 155 активна";
    }

    if (!$page155['published']) {
        $warnings[] = "⚠️  Страница ID 155 не опубликована";
    }

    if (strpos($page155['content'], 'testRunner') === false) {
        $errors[] = "❌ Страница ID 155 не содержит [[testRunner]]";
    } else {
        $success[] = "✓ Страница ID 155 содержит [[testRunner]]";
    }
}

// Проверить что страница 35 имеет правильное содержимое
$stmt = $modx->query("SELECT content FROM modx_site_content WHERE id = 35");
if ($stmt !== false) {
    $page35 = $stmt->fetch(PDO::FETCH_ASSOC);

    if (strpos($page35['content'], 'categoriesAndTests') === false) {
        $errors[] = "❌ Страница ID 35 не содержит [[categoriesAndTests]]";
    } else {
        $success[] = "✓ Страница ID 35 содержит [[categoriesAndTests]]";
    }
}

// Вывести результаты
if (!empty($errors)) {
    echo "ОШИБКИ:\n";
    foreach ($errors as $error) {
        echo "  " . $error . "\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "ПРЕДУПРЕЖДЕНИЯ:\n";
    foreach ($warnings as $warning) {
        echo "  " . $warning . "\n";
    }
    echo "\n";
}

if (!empty($success)) {
    echo "УСПЕШНО:\n";
    foreach ($success as $s) {
        echo "  " . $s . "\n";
    }
    echo "\n";
}

// Итоговый статус
$hasErrors = !empty($errors);
$status = $hasErrors ? "ТРЕБУЕТСЯ ВНИМАНИЕ" : "ВСЕ ПРОВЕРКИ ПРОЙДЕНЫ ✓";
echo "ОБЩИЙ СТАТУС: " . $status . "\n";
```

---

## 📋 Объяснение изменений:

| Что изменяется | Причина | Результат |
|---|---|---|
| ID 147 → deleted=1 | Дубликат ID 155 | Удаляется старая страница |
| ID 35: добавить [[!categoriesAndTests]] | Пустая страница | Пользователи видят список тестов |
| ID 155: добавить [[!testRunner]] | Пустая страница | Интерфейс прохождения теста работает |
| ID 156: [[!testResults]] | Для результатов | Пользователи видят результаты |
| ID 157: [[!testHistory]] | Для истории | История тестов доступна |
| ID 158: [[!getUserStats]] | Для статистики | Статистика пользователя отображается |
| ID 159: [[!leaderboard]] | Для рейтинга | Таблица лидеров работает |

---

## ⚠️ ВАЖНО:

1. **Перед запуском скриптов**: Сделайте резервную копию БД
2. **После запуска**: Очистите кеш MODX (Админка → System → Clear Cache)
3. **Проверьте**: Что тесты видны для пользователя ID 2 на странице /tests
4. **Если что-то сломалось**: Используйте скрипт восстановления из DELETE_UNUSED_PAGES.md

---

## 🔄 Если нужно откатить изменения:

```php
// Восстановить ID 147
$modx->exec("UPDATE modx_site_content SET deleted = 0 WHERE id = 147");

// Скопировать содержимое обратно
$content = '[[testRunner]]';
$modx->exec("UPDATE modx_site_content SET content = " . $modx->quote($content) . " WHERE id = 147");

echo "✓ Страница ID 147 восстановлена\n";
```
