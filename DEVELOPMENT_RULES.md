# Правила разработки и решения проблем

> MODX Revolution + Fenom: критические правила, типичные ошибки, решения и недокументированные особенности.
> Консолидировано из опыта разработки 17 спринтов. Обновлено: 2026-02-23

---

## 1. Критические правила MODX + Fenom (вызывают 500)

### 1.1. Фигурные скобки `{}` — конфликт с Fenom

Fenom интерпретирует `{` и `}` как разметку шаблонизатора. Любой inline JavaScript с фигурными скобками в PHP-сниппете вызовет ошибку 500.

```php
// НЕВЕРНО — inline JS с фигурными скобками
$output .= '<script>function test() { console.log("test"); }</script>';

// НЕВЕРНО — JSDoc комментарии
$output .= '<script>/** @param {string} name */</script>';

// ВЕРНО — выносить JS в отдельные файлы
$modx->regClientScript('/assets/components/testsystem/js/my-script.js');
```

### 1.2. Короткий синтаксис массивов `[]` — ошибка 500

```php
// НЕВЕРНО — вызовет 500!
$params = ['key' => 'value'];
$modx->makeUrl($id, '', ['param' => $value]);

// ВЕРНО — использовать array()
$params = array('key' => 'value');
$modx->makeUrl($id, '', array('param' => $value));
```

### 1.3. Оператор `??` — осторожно

```php
// МОЖЕТ ВЫЗВАТЬ ПРОБЛЕМЫ
$value = $_GET['param'] ?? 'default';

// ВЕРНО — явная проверка
$value = isset($_GET['param']) ? $_GET['param'] : 'default';
```

### 1.4. HEREDOC с JavaScript — запрещено

HEREDOC содержащий JS код с `{}` проблематичен из-за Fenom.

```php
// ВЕРНО — выносить в отдельный JS файл
$modx->regClientScript('/path/to/script.js');
```

---

## 2. CSRF-защита (обязательна для AJAX)

### 2.1. Инициализация в PHP-сниппете

```php
// ВАЖНО! Без getRequest() сессия может быть неактивна
$modx->getRequest();

// Добавить meta-тег с токеном
$output = CsrfProtection::getTokenMeta();
$output .= '<div class="container">...';
```

### 2.2. Передача токена в JavaScript

```javascript
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

// Для JSON-запросов
fetch(API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        action: 'deleteTest',
        data: { test_id: testId, csrf_token: csrfToken }
    })
});

// Для FormData
const formData = new FormData();
formData.append('csrf_token', csrfToken);
```

---

## 3. Работа с базой данных через MODX API

### 3.1. Ключевое правило: НЕ использовать xPDOConnection

`xPDOConnection` (из `$modx->getConnection()`) имеет только 4 метода: `__construct()`, `connect()`, `getOption()`, `isMutable()`. У него **НЕТ** `prepare()`, `query()`, `quote()`.

```php
// НЕВЕРНО — Fatal error: undefined method
$db = $modx->getConnection();
$stmt = $db->prepare("SELECT ...");  // НЕ СУЩЕСТВУЕТ
$db->query("SELECT ...");            // НЕ СУЩЕСТВУЕТ
$db->quote($value);                  // НЕ СУЩЕСТВУЕТ

// ВЕРНО — использовать методы $modx напрямую
$stmt = $modx->query("SELECT ...");  // Возвращает PDOStatement
$count = $modx->exec("INSERT ...");  // Возвращает int (кол-во строк)
$safe = $modx->quote($value);        // Экранирование строк
```

### 3.2. Таблица API-методов

| Операция | Неверно | Верно | Возвращает |
|----------|---------|-------|-----------|
| SELECT | `$db->prepare()` | `$modx->query()` | PDOStatement |
| INSERT/UPDATE/DELETE | `$db->prepare()` | `$modx->exec()` | int |
| Экранирование | `$db->quote()` | `$modx->quote()` | string |

### 3.3. Обязательная проверка результата

```php
// ВЕРНО — всегда проверять перед fetch()
$stmt = $modx->query($sql);
if ($stmt === false) {
    error_log("SQL Error: {$sql}");
    return false;
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### 3.4. Экранирование значений

```php
// Строки — через $modx->quote()
$sql = "INSERT INTO table (name) VALUES (" . $modx->quote($name) . ")";

// Числа — через intval()/floatval()
$sql = "DELETE FROM table WHERE id = " . intval($id);
```

---

## 4. Несоответствия схемы БД (частая причина ошибок)

### 4.1. Таблица замен (старые поля → актуальные)

| Старое (ОШИБКА) | Актуальное | Таблица | Контекст |
|---|---|---|---|
| `is_public = 1` | `publication_status = 'public'` | `modx_test_tests` | Фильтрация публичных тестов |
| `c.title` | `c.name` | `modx_test_categories` | Название категории |
| `t.category_id` | `t.resource_id` | `modx_test_tests` | JOIN с categories |
| `modx_membergroup` | `modx_member_groups` | — | Группы пользователей |

### 4.2. Ловушка: поле `resource_id`

Поле `modx_test_tests.resource_id` хранит **ID категории** из `modx_test_categories`, а НЕ ID ресурса MODX. Старые значения (68, 90, 103...) могут указывать на несуществующие категории — проверяйте:

```sql
-- Проверить битые связи
SELECT t.id, t.title, t.resource_id, c.name
FROM modx_test_tests t
LEFT JOIN modx_test_categories c ON c.id = t.resource_id
WHERE c.id IS NULL;
```

### 4.3. Значения `publication_status`

Enum: `'draft'`, `'private'`, `'unlisted'`, `'public'`. При создании теста через `addTestForm.php` нужно **явно** указать `publication_status = 'public'`, иначе тест получит NULL и не будет виден.

### 4.4. Проверка совместимости сниппета с актуальной схемой

```bash
# Найти использование старых полей
grep -n "is_public\|category_id\|tc\.title" your_snippet.php
```

Исправленные сниппеты: `categoriesAndTests.php`, `testRunner.php`, `testHistory.php`, `testsList.php`, `categoriesList.php`. Проверенные без изменений: `testResults.php`, `getUserStats.php`, `leaderboard.php`, `achievements.php`, `getTestCategories.php`, `userProfile.php`, `myFavorites.php`, `getTestInfoBatch.php`.

---

## 5. Решённые проблемы и баги

### 5.1. Автоматическое завершение training-тестов
**Проблема:** Тесты в training-режиме не завершались автоматически.
**Решение:** Добавлен авто-финиш через 2 секунды после последнего вопроса.
**Файл:** `assets/components/testsystem/js/tsrunner.js`

### 5.2. Триггер БД блокирует UPDATE сессий
**Проблема:** Триггер `trg_session_complete_award_xp` блокировал обновление `status` теста.
**Решение:** Логика XP и streak перенесена в PHP (`SessionController.php`), триггер удалён.
**Архитектурный вывод:** Бизнес-логику держать в PHP-сервисах, не в триггерах MySQL.

### 5.3. Сертификаты не создаются при завершении теста
**Проблема:** Метод для выдачи сертификатов отсутствовал.
**Решение:** Добавлен `issueCertificateForTest()` в `SessionController`.

### 5.4. Сниппет myCertificates — неправильные поля БД
**Проблема:** SQL-запрос использовал устаревшие поля.
**Решение:** Миграция `core/components/testsystem/migrations/update_myCertificates_snippet.php`.

### 5.5. Новые тесты не отображаются на странице
**Проблема:** `addTestForm.php` не устанавливал `publication_status` → тест получал NULL → `categoriesAndTests.php` фильтровал по `publication_status = 'public'`.
**Решение:** Добавлено `publication_status = 'public'` в INSERT `addTestForm.php`. Для старых тестов:
```sql
UPDATE modx_test_tests SET publication_status = 'public'
WHERE is_active = 1 AND (publication_status IS NULL OR publication_status != 'public');
```

### 5.6. API action не найден — "Internal server error"
**Проблема:** JavaScript вызывает `apiCall('someAction', ...)`, но обработчик `case 'someAction':` отсутствует в `testsystem.php`.
**Пример:** `tsrunner.js` вызывал `apiCall("getSessionInfo")`, но case в PHP отсутствовал.
**Решение:** Всегда проверять:
```bash
# JS actions
grep -r "apiCall\(" assets/components/testsystem/js/ | grep -oP "apiCall\(['\"](\w+)" | sort -u

# PHP handlers
grep "case '" assets/components/testsystem/ajax/testsystem.php | grep -oP "case '(\w+)" | sort -u
```

### 5.7. "Call to a member function fetchAll() on bool"
**Причина:** `$modx->query($sql)` возвращает `false` при ошибке SQL (неверный синтаксис, несуществующая таблица/колонка, GROUP BY без агрегации).
**Решение:** Всегда проверять `$stmt === false` перед `fetch()`.

### 5.8. MySQL 5.7 — несовместимость с MySQL 8.0+ синтаксисом
**Проблема:** Используются `IF NOT EXISTS` в DDL (17 случаев), `CREATE OR REPLACE VIEW`, partial indexes.
**Решение:** Минимальная версия MySQL 5.7.21. Избегать MySQL 8.0+ функций.

### 5.9. Чанки отображаются как текст `[[!snippet]]`
**Причина:** Чанк не обновлён в БД после изменения файла.
**Решение:** Обновить чанк через MODX Admin → Элементы → Чанки, или через console/SQL.

---

## 6. Недокументированные особенности MODX

### 6.1. `$modx->getRequest()` запускает сессию
Без вызова `$modx->getRequest()` в сниппете сессия PHP может быть неактивна. CSRF-токен не будет работать без этого.

### 6.2. `$modx->query()` vs `$modx->exec()`
- `query()` — для SELECT, возвращает `PDOStatement|false`
- `exec()` — для INSERT/UPDATE/DELETE, возвращает `int` (кол-во затронутых строк)
- `$modx->quote()` добавляет кавычки автоматически (`'value'`), учитывайте при построении SQL

### 6.3. Кеш MODX
Всегда очищайте кеш после изменений сниппетов/чанков:
```php
$modx->cacheManager->refresh();
// или для конкретного ключа:
$modx->cacheManager->delete('testsystem/categories_list');
```
Из CLI: `rm -rf /path/to/modx/core/cache/*`

### 6.4. Регистрация скриптов
```php
$modx->regClientCSS($assetsUrl . 'css/styles.css');        // CSS в <head>
$modx->regClientScript($assetsUrl . 'js/module.js');        // JS перед </body>
$modx->regClientStartupScript($assetsUrl . 'js/early.js');  // JS в <head>
```

### 6.5. Пути и константы
```php
$assetsUrl = $modx->getOption('assets_url');   // /assets/
$corePath = MODX_CORE_PATH;                    // /path/to/core/
```

---

## 7. Чеклист перед коммитом

- [ ] Нет inline JavaScript с фигурными скобками в PHP-сниппетах
- [ ] Используется `array()` вместо `[]`
- [ ] Переменные проверяются через `isset()`
- [ ] `htmlspecialchars()` для вывода пользовательских данных
- [ ] SQL через `$modx->query()`/`$modx->exec()` с `$modx->quote()`
- [ ] `$stmt === false` проверяется перед `fetch()`
- [ ] Скрипты подключены через `regClientScript()`
- [ ] CSRF-токен в сниппетах и AJAX-запросах
- [ ] Все `apiCall()` из JS имеют маппинг в `ControllerFactory.php` (или inline case в `testsystem.php`)
- [ ] `publication_status`, `c.name`, `t.resource_id` — актуальные поля
- [ ] Очищен кеш MODX перед тестированием
- [ ] `php -l file.php` — проверка синтаксиса

---

## 8. Диагностика

### 8.1. Проверка структуры таблицы
```php
$stmt = $modx->query("DESCRIBE modx_test_tests");
if ($stmt) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " | " . $row['Type'] . "\n";
    }
}
```

### 8.2. Проверка методов MODX API
```php
$methods = get_class_methods($modx);
foreach ($methods as $m) {
    if (stripos($m, 'query') !== false) echo $m . "\n";
}
```

### 8.3. Логи

**Размещение лог-файлов:**
```
ПРАВИЛЬНО:  core/cache/logs/testsystem.log   # За пределами webroot
            /var/log/testsystem/error.log     # Системная директория

НЕПРАВИЛЬНО: assets/.../ajax/testsystem_errors.log  # Публичная директория!
```

**Текущее состояние** (ИСПРАВЛЕНО 2026-02-22):
`testsystem.php` пишет логи в `core/cache/logs/testsystem_errors.log` — за пределами webroot.
`display_errors = 0`, `log_errors = 1` — ошибки только в лог, не в ответ клиенту.

**Просмотр логов:**
```bash
tail -f /var/log/php-fpm/error.log         # PHP
tail -f /var/log/apache2/error.log         # Apache
tail -f core/cache/logs/testsystem*.log    # Приложение
```

### 8.4. Правила диагностического кода
Используйте сквозную нумерацию:
```php
error_log('[DIAG-1] Start function X');
error_log('[DIAG-2] Variable: ' . var_export($var, true));
```
```javascript
console.log('[DIAG-1] Script loaded');
console.log('[DIAG-2] Element:', element);
```

---

## 10. Правила оформления элементов интерфейса (UI)

> Полная спецификация — в `STYLE_GUIDE.md`. Здесь — обязательные правила для разработчика.

### 10.1. Иконки — только Bootstrap Icons

**Единственная разрешённая библиотека иконок: Bootstrap Icons (`bi bi-*`).**

Font Awesome (`fas fa-*`) запрещён в новом коде и подлежит замене в существующем.

```html
<!-- ЗАПРЕЩЕНО -->
<i class="fas fa-graduation-cap"></i>
<i class="fa fa-user"></i>

<!-- ВЕРНО -->
<i class="bi bi-mortarboard-fill"></i>
<i class="bi bi-person-circle"></i>
```

Таблица замен — в `STYLE_GUIDE.md`, раздел 6.

### 10.2. Цвета — только через CSS Custom Properties

Запрещено использовать жёстко заданные цвета в HTML или CSS. Все цвета — через переменные из `ts-variables.css`.

```css
/* ЗАПРЕЩЕНО */
color: #0d6efd;
background: #28a745;

/* ВЕРНО */
color: var(--color-primary);
background: var(--color-success);
```

Полная палитра переменных — в `STYLE_GUIDE.md`, раздел 2.

### 10.3. Inline-стили запрещены

```html
<!-- ЗАПРЕЩЕНО — inline стиль -->
<div style="color: red; margin-top: 10px;">...</div>

<!-- ВЕРНО — класс -->
<div class="ts-danger mt-2">...</div>
```

Исключение: динамически вычисляемые значения (например, `width: {{$percent}}%` у прогресс-баров).

### 10.4. Inline `<style>` в сниппетах и чанках запрещены

CSS-код в PHP-сниппетах и Fenom-чанках запрещён. Стили выносятся в CSS-файлы.

```php
// ЗАПРЕЩЕНО
$output .= '<style>.my-class { color: red; }</style>';

// ВЕРНО — стили в CSS-файле, подключение через сниппет
$modx->regClientCSS($assetsUrl . 'css/ts-components.css');
```

### 10.5. Классы компонентов — пространство имён `ts-`

Кастомные CSS-классы имеют префикс `ts-`. Bootstrap-классы используются как утилиты (сетка, margin, padding), но не перегружаются кастомными стилями через `.btn-primary`.

```css
/* ЗАПРЕЩЕНО — переопределять Bootstrap через его же классы */
.btn-primary { background: #0095F6; }

/* ВЕРНО — свои компоненты */
.ts-btn-primary { background: var(--color-primary); }
```

### 10.6. Таблица допустимых border-radius

| Элемент | Значение |
|---|---|
| Кнопки, поля ввода, бейджи | `8px` |
| Карточки, dropdown | `12px` |
| Модальные окна | `16px` |
| Круглые кнопки (только иконка) | `50%` |

Произвольные значения (`4px`, `6px`, `20px`, `2rem`) запрещены.

### 10.7. Тени — только из палитры

```css
/* Карточка (стандарт) */
box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);

/* Карточка (hover) */
box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);

/* Dropdown */
box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
```

Крупные декоративные тени (`0 8px 30px`, `0 20px 60px`) запрещены.

### 10.8. Hover-эффекты у кнопок — без подъёма

```css
/* ЗАПРЕЩЕНО — кнопка "прыгает" */
.btn:hover { transform: translateY(-2px); }

/* ВЕРНО — только изменение цвета */
.ts-btn-primary:hover { background: var(--color-primary-dark); }
```

Эффект `translateY(-2px)` разрешён только для карточек (`.ts-card:hover`), но не для кнопок.

### 10.9. Кнопки не должны иметь собственных градиентов

```css
/* ЗАПРЕЩЕНО */
.btn-start { background: linear-gradient(135deg, #4caf50, #45a049); }

/* ВЕРНО */
.ts-btn-success { background: var(--color-success); }
```

### 10.10. Чеклист UI перед коммитом

- [ ] Иконки только `bi bi-*`, без `fas fa-*`
- [ ] Цвета только через `var(--color-*)`, без hex-литералов
- [ ] Нет inline `style="..."` (кроме динамических значений)
- [ ] Нет `<style>` в PHP-сниппетах и Fenom-чанках
- [ ] CSS-классы кастомных компонентов с префиксом `ts-`
- [ ] Border-radius из палитры: 8px / 12px / 16px / 50%
- [ ] Кнопки без `transform` на hover

---

## 9. Правила работы с файлами

### 9.1. User-uploaded контент не должен попадать в git

Загруженные пользователями файлы (изображения к вопросам, документы) не должны отслеживаться git. В `.gitignore`:
```
assets/components/testsystem/images/q_*
```
Директория `images/` защищена `.htaccess` (запрет выполнения PHP, разрешены только jpg/png/gif/webp).

### 9.2. Где создавать новые контроллеры

```
assets/components/testsystem/controllers/  # HTTP-контроллеры (публичные)
core/components/testsystem/services/       # Бизнес-логика (приватная)
```

Контроллер = тонкая обёртка (валидация входа → вызов сервиса → формат ответа).
Сервис = вся логика и SQL.

Новый контроллер нужно:
1. Создать в `assets/.../controllers/NewController.php` (extends BaseController)
2. Зарегистрировать actions в `ControllerFactory.php` в `$actionMap`
3. **НЕ** добавлять case в switch `testsystem.php` — весь новый код только через контроллеры

Существующие контроллеры (17 шт.): Admin, Analytics, Category, Certificate, Favorite, Gamification, KnowledgeArea, LearningPath, Material, Notification, Question, Session, SpecialQuestion, Test, Upload + BaseController + ControllerFactory.

### 9.3. Где создавать новые JS-модули

Отдельный файл в `assets/components/testsystem/js/` для каждого функционального модуля. Подключать через сниппет:
```php
$modx->regClientScript($assetsUrl . 'js/new-module.js');
```
