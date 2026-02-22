# Скрипт удаления лишних страниц для LMS

## Анализ результатов

На основе диагностики определены **ЛИШНИЕ страницы** которые не используются LMS:

| ID | Alias | Заголовок | Причина удаления |
|----|-------|-----------|------------------|
| 3 | about | Информация о нас | Не для LMS |
| 4 | specialists | Наши сотрудники | Не для LMS |
| 5-9 | spec-1 to spec-5 | Сотрудники 1-5 | Не для LMS |
| 10 | reviews | Отзывы наших клиентов | Не для LMS |
| 11-13 | review-1 to review-3 | Отзывы 1-3 | Не для LMS |
| 14 | gallery | Галерея | Не для LMS |
| 15 | news | Новости компании | Не для LMS |
| 16-18 | news-1 to news-3 | Новости 1-3 | Не для LMS |
| 19 | contacts | Контактная информация | Не для LMS |
| 21 | site-map | Карта сайта | Дублирует sitemap.xml |
| 23 | arhiv | Архив | Неопубликована (○) |
| 92 | materialyi-dlya-obucheni | Обучение | Дублирует /tests |
| 115 | favorites | Избранное | Не используется |
| 125 | moi-oblasti-znanij | Мои области знаний | Не используется |
| 126 | my-tests | Мои тесты | Дублирует /tests |
| 129 | privatnyie-testyi | Приватные тесты | Дублирует /tests |
| 148 | samorazvitie | Саморазвитие | Дублирует /tests |
| 150 | stranicza-prosmotra-materiala | Материала просмотр | Не используется |
| 151 | stranicza-redaktirovaniya-materiala | Материала редактирование | Не используется |
| 153 | traektorii-obucheniya | Траектории обучения | **Template: 0 (неработающая!)** |
| 154 | prosmotr-traektorii-obucheniya | Просмотр траектории | Не используется |

---

## 🚀 СКРИПТ 1: Пометить на удаление (deleted=1)

Выполни в **MODX Console**:

```php
// IDs всех лишних страниц
$deleteIds = [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 21, 23, 92, 115, 125, 126, 129, 148, 150, 151, 153, 154];

echo "=== УДАЛЕНИЕ ЛИШНИХ СТРАНИЦ ===\n";
echo "Будут помечены как deleted: " . count($deleteIds) . " страниц\n\n";

$count = 0;
foreach ($deleteIds as $id) {
    try {
        // Получить информацию о странице
        $stmt = $modx->query("SELECT pagetitle, alias FROM modx_site_content WHERE id = " . intval($id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Пометить как удалённую
            $modx->exec("UPDATE modx_site_content SET deleted = 1 WHERE id = " . intval($id));
            echo "✓ ID: {$id} | Alias: '{$row['alias']}' | Title: '{$row['pagetitle']}'\n";
            $count++;
        } else {
            echo "✗ ID: {$id} | Страница не найдена\n";
        }
    } catch (Exception $e) {
        echo "✗ ID: {$id} | Ошибка: " . $e->getMessage() . "\n";
    }
}

echo "\n=== РЕЗУЛЬТАТ ===\n";
echo "Удалено: {$count} страниц\n";

// Проверка
$stmt = $modx->query("SELECT COUNT(*) as cnt FROM modx_site_content WHERE deleted = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Всего удалённых страниц: {$row['cnt']}\n";

$stmt = $modx->query("SELECT COUNT(*) as cnt FROM modx_site_content WHERE deleted = 0");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Активных страниц осталось: {$row['cnt']}\n";
```

---

## ✅ СКРИПТ 2: Проверка результата

После выполнения скрипта 1, запусти этот скрипт для проверки:

```php
echo "=== ПРОВЕРКА: АКТИВНЫЕ СТРАНИЦЫ ===\n";

$stmt = $modx->query("
SELECT
    id,
    pagetitle,
    alias,
    published,
    template
FROM modx_site_content
WHERE deleted = 0
ORDER BY id ASC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $status = $row['published'] ? '✓' : '○';
    printf("ID: %-3d | %s | Alias: %-30s | Template: %-2s | %s\n",
        $row['id'],
        $status,
        "'" . $row['alias'] . "'",
        $row['template'],
        $row['pagetitle']
    );
}

echo "\nВсего активных: " . count($rows) . "\n";
```

---

## 📋 ОЖИДАЕМЫЙ РЕЗУЛЬТАТ

После удаления должны остаться **ТОЛЬКО** эти страницы (26 шт):

```
✓ ID: 1   | index             | Главная
✓ ID: 2   | robots            | robots.txt
✓ ID: 20  | 404               | Страница не найдена
✓ ID: 22  | sitemap           | sitemap.xml

✓ ID: 24  | auth              | Авторизация
✓ ID: 25  | activate          | Активация аккаунта
✓ ID: 26  | forgot-password   | Забыли пароль
✓ ID: 27  | reset-password    | Сброс пароля
✓ ID: 28  | profile           | Профиль

✓ ID: 29  | import-csv        | Импорт вопросов
✓ ID: 34  | leaderboard       | Таблица лидеров
✓ ID: 35  | tests             | Тесты (ГЛАВНАЯ LMS)
✓ ID: 36  | add-test          | Создать тест
✓ ID: 43  | manage-users      | Пользователи
✓ ID: 113 | 403               | Доступ запрещен

✓ ID: 145 | oblast-znanij     | Область знаний
✓ ID: 146 | list              | Список тестов
✓ ID: 147 | run               | Прохождение теста
✓ ID: 149 | uchebnyie-materialyi | Учебные материалы
✓ ID: 152 | prava-dostupa     | Права доступа

✓ ID: 155 | test-run          | Прохождение теста (NEW)
✓ ID: 156 | results           | Результаты
✓ ID: 157 | history           | История тестов
✓ ID: 158 | stats             | Моя статистика
✓ ID: 159 | achievements      | Достижения
```

**Итого:** 26 активных страниц

---

## ⚠️ ВАЖНО

1. **Страницы не удаляются**, а помечаются как `deleted=1`
   - Это безопасно - можно всегда восстановить
   - В MODX часто используется soft delete

2. **Очистить кеш MODX** после удаления:
   - Админка → System → Clear Cache

3. **Проверить сайт** что всё работает

---

## 🔄 Если нужно восстановить страницу

Если какая-то страница всё же нужна, просто запусти:

```php
// Восстановить страницу с ID: 21
$modx->exec("UPDATE modx_site_content SET deleted = 0 WHERE id = 21");
echo "✓ Страница восстановлена\n";
```

