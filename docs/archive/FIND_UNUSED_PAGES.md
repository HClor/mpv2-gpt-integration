# Анализ страниц сайта и определение лишних

## Диагностика в MODX Console

Запусти эти запросы в **MODX Console** чтобы получить полный список страниц:

### ЗАПРОС 1: Все страницы сайта с полной информацией

```php
$stmt = $modx->query("
SELECT
    id,
    pagetitle,
    alias,
    published,
    deleted,
    parent,
    template
FROM modx_site_content
ORDER BY parent ASC, id ASC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== ВСЕ СТРАНИЦЫ САЙТА ===\n";
foreach ($rows as $row) {
    $status = $row['deleted'] ? '❌ УДАЛЕНА' : ($row['published'] ? '✓' : '○');
    printf("ID: %-3d | %s | Alias: %-25s | Template: %-5s | Parent: %s\n",
        $row['id'],
        $status,
        "'" . $row['alias'] . "'",
        $row['template'],
        $row['parent']
    );
    echo "         Заголовок: {$row['pagetitle']}\n\n";
}
```

### ЗАПРОС 2: Страницы которые используются в LMS (имеют сниппеты)

```php
// Найти какие страницы содержат LMS сниппеты
$snippets = ['testRunner', 'categoriesAndTests', 'testResults', 'testHistory', 'getUserStats', 'leaderboard', 'achievements'];

$stmt = $modx->query("
SELECT
    id,
    pagetitle,
    alias,
    content
FROM modx_site_content
WHERE deleted = 0
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== СТРАНИЦЫ С LMS СНИППЕТАМИ ===\n";
foreach ($rows as $row) {
    foreach ($snippets as $snippet) {
        if (strpos($row['content'], $snippet) !== false) {
            echo "✓ ID: {$row['id']} | '{$row['alias']}' | Сниппет: {$snippet}\n";
        }
    }
}
```

### ЗАПРОС 3: Служебные страницы (robots.txt, sitemap, 404, карта сайта)

```php
$stmt = $modx->query("
SELECT
    id,
    pagetitle,
    alias,
    published,
    deleted,
    content
FROM modx_site_content
WHERE alias IN ('robots.txt', 'sitemap.xml', 'sitemap', 'map')
   OR pagetitle IN ('Страница не найдена', 'Карта сайта', 'robots.txt', 'sitemap.xml')
   OR alias LIKE '%error%'
   OR alias LIKE '%404%'
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) > 0) {
    echo "=== СЛУЖЕБНЫЕ СТРАНИЦЫ ===\n";
    foreach ($rows as $row) {
        echo "ID: {$row['id']} | Alias: '{$row['alias']}' | Title: '{$row['pagetitle']}' | Published: " . ($row['published'] ? 'ДА' : 'НЕТ') . " | Deleted: " . ($row['deleted'] ? 'ДА' : 'НЕТ') . "\n";
    }
} else {
    echo "Служебные страницы не найдены\n";
}
```

### ЗАПРОС 4: Статистика страниц

```php
$stmt = $modx->query("SELECT COUNT(*) as cnt FROM modx_site_content WHERE deleted = 0");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Всего активных страниц: {$row['cnt']}\n\n";

$stmt = $modx->query("SELECT COUNT(*) as cnt FROM modx_site_content WHERE deleted = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Удалённых страниц: {$row['cnt']}\n\n";

$stmt = $modx->query("SELECT COUNT(*) as cnt FROM modx_site_content WHERE published = 0 AND deleted = 0");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Неопубликованных страниц: {$row['cnt']}\n";
```

---

## Ожидаемые нужные страницы для LMS:

Основываясь на инсталляторе, сайту нужны:

| ID | Alias | Назначение | Необходимо |
|-----|-------|-----------|----------|
| ? | tests | Список доступных тестов | ✓ ДА |
| ? | test-run | Интерфейс прохождения теста | ✓ ДА |
| ? | results | Результаты тестирования | ✓ ДА |
| ? | history | История сдачи тестов | ✓ ДА |
| ? | stats | Личная статистика пользователя | ✓ ДА |
| ? | leaderboard | Рейтинг пользователей | ✓ ДА |
| ? | achievements | Достижения и значки | ✓ ДА |
| ? | robots.txt | Служебная (SEO) | ? МОЖЕТ БЫТЬ НУЖНА |
| ? | sitemap.xml | Служебная (SEO) | ? МОЖЕТ БЫТЬ НУЖНА |
| ? | sitemap | Карта сайта для пользователей | ? МОЖЕТ БЫТЬ ЛИШНЕЙ |
| ? | 404 page | Страница ошибки 404 | ? МОЖЕТ БЫТЬ НУЖНА |
| 1 | (root) | Корневая страница | ✓ ДА |

---

## План действий:

1. **Запусти все 4 запроса** и пришли результаты
2. **Определим какие страницы лишние** (не используются в LMS)
3. **Создадим скрипт** для пометки страниц как deleted=1
4. **Проверим** что удаления не сломают сайт

Запусти запросы в MODX Console!
