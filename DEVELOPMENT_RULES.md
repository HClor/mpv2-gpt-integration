# Правила разработки и решения проблем

> MODX Revolution + Fenom: критические правила, типичные ошибки, решения и недокументированные особенности.
> Консолидировано из опыта разработки 17 спринтов. Обновлено: 2026-02-27

---

## 0. Окружение и архитектурные принципы

### 0.1. Окружение

| Параметр | Значение |
|---|---|
| PHP | 8.2.28 |
| MySQL | 5.7.21 |
| CMS | MODX Revolution 2.x + Fenom |
| CSS-фреймворк | Bootstrap 5 (LMS-часть) |
| Иконки | Bootstrap Icons |

Все современные конструкции PHP 8.x поддерживаются: `[]`, `??`, `?->`, `match`, named arguments, union types и т.д.

### 0.2. Архитектурные принципы

1. **Контроллеры** — только HTTP: валидация входа → вызов сервиса → формат ответа
2. **Сервисы** — вся бизнес-логика и SQL
3. **SQL** — только в сервисах (и в repositories)
4. **Валидация** — на границе входа (контроллер)
5. **UI** не содержит бизнес-логики
6. **Никаких «быстрых правок в snippet»** — новый код только через контроллеры и сервисы
7. **Триггеры MySQL** — не используются; вся логика в PHP-сервисах (архитектурное решение, см. 5.2)

---

## 1. Критические правила MODX + Fenom (вызывают 500)

### 1.1. Фигурные скобки `{}` — конфликт с Fenom

Fenom интерпретирует `{` и `}` как разметку шаблонизатора. Inline JavaScript с фигурными скобками в **выводе PHP-сниппета** вызовет ошибку 500, потому что вывод сниппета обрабатывается Fenom.

**В PHP-сниппетах** — выносить JS в отдельные файлы:
```php
// НЕВЕРНО — inline JS с фигурными скобками в output
$output .= '<script>function test() { console.log("test"); }</script>';

// ВЕРНО — выносить JS в отдельные файлы
$modx->regClientScript('/assets/components/testsystem/js/my-script.js');
```

**В Fenom-шаблонах (.tpl)** — использовать `{ignore}`:
```html
{ignore}
<script>
function test() {
    console.log("ok");
}
</script>
{/ignore}
```

### 1.2. HEREDOC с JavaScript — запрещено

HEREDOC содержащий JS код с `{}` проблематичен из-за Fenom (вывод сниппета проходит через шаблонизатор).

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

### 2.2.1. Для `fetch()` к `testsystem.php` обязательно использовать общий helper

Для всех новых и изменяемых фронтенд-модулей TestSystem запрещено дублировать локальные реализации:
- `getCsrfToken()`
- `refreshCsrfToken()`
- `apiCall()` с retry после `CSRF token validation failed`

Вместо этого **обязательно** подключать `/assets/components/testsystem/js/csrf-helper.js` **раньше** модуля и вызывать `window.TestSystemCSRF.apiCall(...)`.

```html
<script src="/assets/components/testsystem/js/csrf-helper.js"></script>
<script src="/assets/components/testsystem/js/learning-paths.js"></script>
```

```javascript
async function apiCall(action, data = {}) {
    return window.TestSystemCSRF.apiCall(action, data);
}
```

Причины:
1. единое поведение для long-running UI;
2. автоматическое обновление CSRF-токена после истечения;
3. отсутствие расхождений между сниппетами `learningPaths`, `testRunner`, `categoriesAndTests` и другими фронтенд-модулями.

Если модуль работает с `FormData` или нестандартным endpoint, helper всё равно должен использоваться как единый источник функций `getToken()` / `refreshToken()`, а не копироваться вручную.

Если даже после автоматического refresh CSRF-ошибка сохраняется, фронтенд **обязан** показать пользователю явную инструкцию следующего действия, например: `Обновите страницу и повторите попытку.` Нельзя оставлять только общее сообщение вида `Ошибка` или `Ошибка соединения с сервером`.

### 2.3. XSS-защита в Fenom-шаблонах

В Fenom **всегда** экранировать пользовательские данные через `| escape`:

```html
<!-- ЗАПРЕЩЕНО — XSS-вектор -->
<h1>{$pagetitle}</h1>
<span>{$_modx->user.username}</span>
<meta content="{$description}">

<!-- ВЕРНО — экранирование через | escape -->
<h1>{$pagetitle | escape}</h1>
<span>{$_modx->user.username | escape}</span>
<meta content="{$description | escape}">
```

**Исключение:** `| raw` допустим только для полей с намеренным HTML-контентом (`content`, `richtext`), проверенных перед сохранением.

В PHP-сниппетах — `htmlspecialchars()`:
```php
$output .= '<span>' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '</span>';
```

### 2.4. Сниппеты, использующие сервисы `Config` / ACL / CSRF, обязаны подключать bootstrap

Если PHP-сниппет обращается к классам компонента (`Config`, `PermissionHelper`, `CategoryPermissionService`, `CsrfProtection` и т.п.) вне гарантированного bootstrap-контекста, он обязан явно подключать:

```php
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';
```

Если сниппет зависит от классов компонента, нельзя полагаться на внешнюю «магическую» загрузку.

### 2.5. Нельзя использовать `$modx->resource->get(...)` без guard-проверки

Если сниппет может вызываться вне стандартного page-render context, нельзя обращаться к `$modx->resource->get(...)` без guard-проверки. Нужно сначала проверить, что `$modx->resource` существует, и только потом использовать `get(...)`. Fallback должен быть явным и безопасным для конкретного сценария (`''`, `site_start` или controlled error block).

```php
// НЕВЕРНО
$pageUrl = $modx->makeUrl($modx->resource->get('id'), '', '', 'abs');

// ВЕРНО
$pageUrl = $modx->resource ? $modx->makeUrl($modx->resource->get('id'), '', '', 'abs') : '';
```

---

## 3. Работа с базой данных через MODX API

### 3.1. Ключевое правило: НЕ использовать xPDOConnection

`xPDOConnection` (из `$modx->getConnection()`) имеет только 4 метода: `__construct()`, `connect()`, `getOption()`, `isMutable()`. У него **НЕТ** `prepare()`, `query()`, `quote()`.

```php
// НЕВЕРНО — Fatal error: undefined method
$db = $modx->getConnection();
$stmt = $db->prepare("SELECT ...");  // НЕ СУЩЕСТВУЕТ

// ВЕРНО — использовать методы $modx напрямую (см. 3.2–3.4)
```

### 3.1.1. Совместимость MODX: `modX::getPDO()` может отсутствовать

Для переносимого кода компонента нельзя опираться на наличие `$modx->getPDO()` во всех MODX 2.x инсталляциях. Использовать DB-API MODX напрямую: `$modx->prepare()`, `$modx->query()`, `$modx->exec()`, `$modx->lastInsertId()`.

```php
// НЕВЕРНО (непереносимо между инсталляциями MODX)
$pdo = $modx->getPDO();
$stmt = $pdo->prepare("SELECT ...");

// ВЕРНО
$stmt = $modx->prepare("SELECT ...");
```

### 3.2. Основной подход: `prepare()` + `bindValue()` (параметризованные запросы)

Для любого SQL с пользовательскими данными — **только prepared statements**:

```php
// ВЕРНО — безопасно, чисто, рефакторится легко
$stmt = $modx->prepare("SELECT id, title FROM modx_test_tests WHERE resource_id = :catId AND publication_status = :status");
$stmt->bindValue(':catId', $categoryId, PDO::PARAM_INT);
$stmt->bindValue(':status', 'public', PDO::PARAM_STR);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

Для INSERT/UPDATE/DELETE:
```php
$stmt = $modx->prepare("UPDATE modx_test_tests SET title = :title WHERE id = :id");
$stmt->bindValue(':title', $title, PDO::PARAM_STR);
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$affected = $stmt->rowCount();
```

### 3.3. Альтернатива: `query()` + `quote()` (простые запросы)

Для запросов без пользовательских данных или с простыми параметрами:

```php
// Простой SELECT без параметров
$stmt = $modx->query("SELECT COUNT(*) FROM modx_test_tests");

// С экранированием (допустимо для простых случаев)
$sql = "DELETE FROM modx_test_tests WHERE id = " . intval($id);
$modx->exec($sql);
```

### 3.4. Таблица API-методов

| Операция | Метод | Возвращает | Когда использовать |
|----------|-------|-----------|-------------------|
| SELECT (параметры) | `$modx->prepare()` + `execute()` | PDOStatement | Основной подход |
| SELECT (простой) | `$modx->query()` | PDOStatement\|false | Запросы без параметров |
| INSERT/UPDATE/DELETE | `$modx->prepare()` + `execute()` | PDOStatement | Основной подход |
| INSERT/UPDATE/DELETE (простой) | `$modx->exec()` | int | Простые запросы |
| Экранирование | `$modx->quote()` | string | Только если не используется prepare |

### 3.5. Обязательная проверка результата

```php
// Для prepare():
$stmt = $modx->prepare($sql);
if ($stmt === false) {
    error_log("[TS] SQL prepare error: {$sql}");
    return false;
}
if (!$stmt->execute()) {
    error_log("[TS] SQL execute error: " . implode(' ', $stmt->errorInfo()));
    return false;
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Для query():
$stmt = $modx->query($sql);
if ($stmt === false) {
    error_log("[TS] SQL error: {$sql}");
    return false;
}
```

### 3.5.1. Сниппеты отчётности не должны отдавать 500 при проблемах БД

Пользовательские и админские reporting-snippets не должны отдавать HTTP 500 из-за SQL/schema-ошибок или отсутствия необязательных данных. Вместо этого они должны:
- писать понятную ошибку в лог;
- возвращать безопасный error block / пустое состояние / диагностическое сообщение, подходящее для UI.

Исключение: внутренние dev-only или staging-only diagnostic snippets, где допустим fail-fast для отладки.

```php
try {
    $stmt = $modx->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('SQL prepare failed');
    }
    if (!$stmt->execute($params)) {
        throw new RuntimeException('SQL execute failed');
    }
} catch (Throwable $e) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[snippetName] ' . $e->getMessage());
    return '<div class="ts-alert ts-alert-danger">Не удалось загрузить данные. Проверьте логи и структуру БД.</div>';
}
```

### 3.6. Транзакции — обязательны при изменении 2+ таблиц

Любая операция, изменяющая данные в нескольких таблицах, — только в транзакции:

```php
$modx->beginTransaction();
try {
    $stmt1 = $modx->prepare("UPDATE modx_test_sessions SET status = 'completed' WHERE id = :id");
    $stmt1->bindValue(':id', $sessionId, PDO::PARAM_INT);
    $stmt1->execute();

    $stmt2 = $modx->prepare("INSERT INTO modx_test_certificates (user_id, test_id) VALUES (:uid, :tid)");
    $stmt2->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt2->bindValue(':tid', $testId, PDO::PARAM_INT);
    $stmt2->execute();

    $modx->commit();
} catch (Exception $e) {
    $modx->rollBack();
    error_log("[TS] Transaction failed: " . $e->getMessage());
}
```

### 3.7. Защита от N+1 Query

Запрещено выполнять SQL внутри цикла. Использовать `IN (...)` или JOIN:

```php
// ЗАПРЕЩЕНО — N+1 запросов
foreach ($tests as $test) {
    $stmt = $modx->prepare("SELECT name FROM modx_test_categories WHERE id = :id");
    $stmt->bindValue(':id', $test['resource_id'], PDO::PARAM_INT);
    $stmt->execute();
}

// ВЕРНО — один запрос
$ids = array_map('intval', array_column($tests, 'resource_id'));
$in = implode(',', $ids);
$stmt = $modx->query("SELECT id, name FROM modx_test_categories WHERE id IN ({$in})");
$categories = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $categories[$row['id']] = $row['name'];
}
```

### 3.8. Индексы и производительность (MySQL 5.7)

Чеклист для новых или изменяемых запросов:
- [ ] Проверен `EXPLAIN` для SELECT — нет `type: ALL` на таблицах > 1000 строк
- [ ] Есть индексы по foreign keys (`resource_id`, `user_id`, `test_id`, `session_id`)
- [ ] Есть индексы по полям в WHERE и ORDER BY
- [ ] MySQL 5.7: нет функций MySQL 8.0+ (`ROW_NUMBER()`, `JSON_TABLE()`, `WITH CTE`)

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
**Архитектурное решение:** Все триггеры MySQL удалены, новые не создаются. Бизнес-логика приложения (XP, награды, уведомления, сертификаты) — только в PHP-сервисах. Теоретически триггеры допустимы для аудита или технических гарантий целостности, но в данном проекте вся логика реализуется в PHP-слое.

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

### 5.8. MySQL 5.7.21 — несовместимость с MySQL 8.0+ синтаксисом
**Проблема:** Используются `IF NOT EXISTS` в DDL (17 случаев), `CREATE OR REPLACE VIEW`, partial indexes.
**Решение:** Версия MySQL: 5.7.21. Запрещены функции MySQL 8.0+: `ROW_NUMBER()`, `JSON_TABLE()`, `WITH ... AS (CTE)`, `GROUPING()`, оконные функции.

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

### 7.1 PHP/MODX

- [ ] Нет inline JavaScript с `{}` в выводе PHP-сниппетов (или используется `{ignore}` в .tpl)
- [ ] SQL с пользовательскими данными через `$modx->prepare()` + `bindValue()`
- [ ] `$stmt === false` проверяется перед `fetch()`
- [ ] Нет SQL внутри циклов (N+1 Query)
- [ ] Операции на 2+ таблицы — в транзакции (`beginTransaction/commit/rollBack`)
- [ ] `htmlspecialchars()` в PHP, `| escape` в Fenom для пользовательских данных
- [ ] Скрипты подключены через `regClientScript()`
- [ ] CSRF-токен в сниппетах и AJAX-запросах
- [ ] Все `apiCall()` из JS имеют маппинг в `ControllerFactory.php`
- [ ] `publication_status`, `c.name`, `t.resource_id` — актуальные поля
- [ ] Нет `error_log()` без условия `ts_debug` (кроме обработки ошибок)
- [ ] Очищен кеш MODX перед тестированием
- [ ] `php -l file.php` — проверка синтаксиса

### 7.2 UI / Стиль — автоматические grep-проверки

Запустить перед коммитом. Все команды должны возвращать **0 результатов**.

```bash
# Не должно быть Font Awesome иконок (fas/far/fab)
grep -r "fas fa-\|far fa-\|fab fa-" core/elements/ assets/components/testsystem/

# Не должно быть hex-цветов в CSS (кроме ts-variables.css и SVG)
grep -rE "#[0-9a-fA-F]{6}\b" assets/components/testsystem/css/ | grep -v "ts-variables.css" | grep -v "\.svg"

# Не должно быть inline <style> в шаблонах, чанках и сниппетах
grep -rn "<style" core/elements/

# Не должно быть Bootstrap-классов кнопок в PHP-сниппетах
grep -r 'class="btn btn-' core/elements/snippets/
```

**Быстрая проверка всех 4 правил одной командой:**
```bash
echo "--- FA иконки ---" && \
grep -rc "fas fa-\|far fa-\|fab fa-" core/elements/ assets/components/testsystem/ | grep -v ":0" && \
echo "--- Hex в CSS ---" && \
grep -rEn "#[0-9a-fA-F]{6}\b" assets/components/testsystem/css/ | grep -v "ts-variables.css" | grep -v "\.svg" && \
echo "--- Inline style ---" && \
grep -rn "<style" core/elements/ && \
echo "--- Bootstrap btn ---" && \
grep -rn 'class="btn btn-' core/elements/snippets/ && \
echo "=== Все проверки пройдены ==="
```

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

**Правило:** `error_log()` для отладки нельзя оставлять в продакшене без условия. Использовать флаг:
```php
// ЗАПРЕЩЕНО — мусор в логах на продакшене
error_log("[TS] Debug: user_id = {$userId}");

// ВЕРНО — условное логирование
if ($modx->getOption('ts_debug')) {
    error_log("[TS] Debug: user_id = {$userId}");
}
```

**Просмотр логов:**
```bash
tail -f /var/log/php-fpm/error.log         # PHP
tail -f /var/log/apache2/error.log         # Apache
tail -f core/cache/logs/testsystem*.log    # Приложение
```

### 8.4. Правила диагностического кода

Диагностический код — **временные** отладочные вставки. Удаляется после решения проблемы.

**Сквозная нумерация** — обязательна. Один DIAG-код может выдавать несколько ответов или ошибок. Нумерация позволяет однозначно определить, на какой именно диагностический вызов пришёл ответ:

```php
error_log('[DIAG-1] Start processSession, sessionId=' . $sessionId);
// ... код ...
error_log('[DIAG-2] After query, rows=' . count($rows));
// ... код ...
error_log('[DIAG-3] Before updateStatus, status=' . $status);
// ... код ...
error_log('[DIAG-4] After updateStatus, affected=' . $affected);
```

```javascript
console.log('[DIAG-1] Script loaded, testId:', testId);
console.log('[DIAG-2] API response:', response);
console.log('[DIAG-3] After render, elements:', document.querySelectorAll('.ts-card').length);
```

**Правила:**
- Нумерация **сквозная** по всему отлаживаемому потоку (DIAG-1, DIAG-2, ... DIAG-N)
- Каждый DIAG-номер уникален — если в логе видишь `[DIAG-3]`, точно знаешь, какая строка кода его породила
- Указывать ключевые переменные рядом с номером для контекста
- **Не путать** с постоянным логированием (там используется `[TS][Controller][method]`)

**Постоянное логирование** (не удаляется) — структурный формат:
```php
error_log('[TS][SessionController][finish] Session completed: id=' . $sessionId);
error_log('[TS][UploadController][uploadImage] Invalid MIME: ' . $mimeType);
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

## 11. Уроки последних двух недель (2026-03-10 — 2026-03-24)

Ниже — правила, добавленные по итогам анализа серии повторных фиксов (auth/register, CSRF, learning paths, кэширование шаблонов и совместимость схемы БД). Цель — не наступать повторно на одинаковые классы инцидентов.

### 11.1. Правило «повторный баг = stop-and-fix»

Если баг повторился повторно или видно, что предыдущий фикс был временным, следующий PR обязан содержать:
1. краткое описание root cause;
2. описание постоянного исправления вместо симптоматического;
3. regression-checklist для сценария, в котором баг уже возникал.

Без этих трёх пунктов фикс считается временным и не должен мержиться в `main`.

### 11.2. Контракт совместимости схемы перед релизом

Для модулей, где уже встречались расхождения схемы (`published` vs `publication_status`, `resource_id` и т.п.), перед merge обязателен lightweight schema-compat check:
- `DESCRIBE` / `SHOW COLUMNS` для затронутых таблиц;
- явный fallback в коде при отсутствии опциональной колонки;
- лог с понятным текстом несовместимости (без 500 для пользовательского сценария, если это не критический security path).

### 11.3. Изменения auth/registration/reset — только с эксплуатационными гарантиями

Любой PR, меняющий `authHandler`-поток (регистрация, активация, reset, resend), обязан явно проверить:
- технические таймауты и поведение при недоступном SMTP (fail-fast, без зависших HTTP-запросов);
- отсутствие побочных разрывов DB-соединения после отправки письма;
- понятное пользовательское сообщение при деградации внешнего сервиса.

Если вводится async-очередь писем, должен быть задокументирован fallback: что происходит при падении worker.

### 11.4. UI/Template-кэш и роли: обязательный smoke-набор

Для изменений в header/menu/login/logout и role-based видимости обязательна ручная проверка минимум для ролей `anonymous`, `student` и `admin`; если в затронутом модуле есть дополнительные специальные роли, проверять и их тоже:
1. видимость пунктов меню,
2. logout и повторный login без hard reload,
3. отсутствие артефактов кэша шаблона (устаревшие ссылки/кнопки).

Для Fenom/MODX-шаблонов в PR должен быть указан выбранный подход к кэшированию (`uncached`/`cacheable`) и почему.

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

---

## 12. Экранирование в JS: onclick-атрибуты с пользовательскими данными

Типичный паттерн — кнопка, сгенерированная через JS-шаблон с данными из БД:

```js
html += `<button onclick="App.action(${item.id}, '${escapeHtml(item.name)}')">`;
```

### 12.1. Почему `escapeHtml()` недостаточно

`escapeHtml()` через `div.textContent + div.innerHTML` экранирует `&`, `<`, `>` — но **не экранирует `"` и `\n`** в текстовых нодах. Это ломает onclick-атрибут:

| Символ в названии | Результат в HTML | Эффект |
|---|---|---|
| `"` (двойная кавычка) | `onclick="App.fn('Name "X"')"` | Атрибут обрывается на первой `"` — onclick не выполняется вообще |
| `\n` (перенос строки) | `onclick="App.fn('Name\n Here')"` | `SyntaxError: Invalid or unexpected token` при рендере карточки |
| `\` (обратный слэш) | `onclick="App.fn('Path\dir')"` | `\d` интерпретируется как escape-последовательность JS |
| `'` (одинарная кавычка) | `onclick="App.fn('it's')"` | JS-строка обрывается — SyntaxError |

### 12.2. Правильное решение — отдельная функция экранирования для onclick

```js
function escapeForOnclick(text) {
    return escapeHtml(text)          // &, <, > → HTML-entities
        .replace(/\\/g, '\\\\')      // \ → \\ (первым, иначе двойное экранирование)
        .replace(/"/g, '&quot;')     // " → &quot; (браузер декодирует при парсинге атрибута)
        .replace(/'/g, "\\'")        // ' → \' (внутри одинарных кавычек JS)
        .replace(/\r/g, '')          // \r — удалить
        .replace(/\n/g, ' ');        // \n → пробел
}
```

Использование:
```js
// НЕВЕРНО
html += `<button onclick="App.fn(${id}, '${escapeHtml(name).replace(/'/g, "\\'")}')">`;

// ВЕРНО
html += `<button onclick="App.fn(${id}, '${escapeForOnclick(name)}')">`;
```

### 12.3. Диагностика

В браузерной консоли при рендере карточек:
- `Uncaught SyntaxError: Invalid or unexpected token (at script.js:N:COL)` → перенос строки или обратный слэш в названии
- Кнопка не реагирует на клик без ошибок в консоли → двойная кавычка обрывает HTML-атрибут (проверить через DevTools: Inspect → посмотреть значение атрибута `onclick`)

### 12.4. Альтернатива — передавать данные через data-атрибуты

Полностью избегает проблемы экранирования:
```html
<button class="my-btn" data-id="5" data-name="Проектант &quot;0&quot;">Действие</button>
```
```js
document.querySelectorAll('.my-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        App.fn(btn.dataset.id, btn.dataset.name);
    });
});
```
Браузер сам декодирует HTML-entities в `dataset`. Это надёжнее для данных с любыми спецсимволами.

---

## 11. Среда тестирования

**Инструменты:** MODX Admin → дополнение **Console** (выполнение PHP-кода), консоль браузера (JS).

**Контексты авторизации в MODX:**
- Контекст `mgr` (админка): пользователь с `id = 1`.
- Контекст `web` (фронтенд): пользователь с `id = 2`.

В Console MODX нельзя напрямую выполнить код от имени пользователя `id=2`, но можно эмулировать его авторизацию:
```php
$modx->user = $modx->getObject('modUser', 2);
$modx->user->addSessionContext('web');
```

**Доступ к окружению:** полный доступ к файлам сайта и MySQL через phpMyAdmin.

## 8. Устранение дублирования между сниппетами

### 8.1. Единый поток импорта вопросов

`addTestForm` не должен повторять бизнес-логику разбора CSV/XLSX. Его зона ответственности: создать тест, принять файл и передать управление в `csvImportForm` (включая `?file=` для автозагрузки).

- Логика парсинга/валидации содержимого файла — только в `csvImportForm` (или в выделенном сервисе/хелпере, используемом им).
- UI результата импорта — один источник истины (`csvImportForm`).


### 8.2. Переиспользование UI-блоков импорта

Если один и тот же UI-блок используется в нескольких сниппетах, его нельзя дублировать. Разметка должна быть вынесена в общий chunk/partial или общий helper — в зависимости от архитектуры конкретного участка.

---


## 14. Кеширование страниц и сниппетов (MODX): обязательные правила

1. **По умолчанию ресурс оставлять `cacheable = true`.**
2. **Весь ресурс делать некешируемым только если почти вся страница зависит от пользователя/запроса.**
3. **Если динамичен только один блок — делать uncached только этот сниппет:** `[[!SnippetName]]`.
4. **Плейсхолдеры, которые заполняет uncached-сниппет, выводить тоже uncached:** `[[!+placeholder]]`.
5. **Не вкладывать uncached-вызовы внутрь cached-конструкций без крайней необходимости.**
6. **Формы, токены, авторизация, регистрация, reset password, redirect-логика — только uncached.**
7. **Персональные данные пользователя никогда не выводить кешируемым сниппетом.**
8. **Общие тяжёлые выборки кешировать отдельно на уровне сниппета/`CacheManager`, а не отключать кеш страницы целиком.**
9. **После изменения логики кешируемых сниппетов или шаблонов очищать кеш MODX.**
10. **Если есть сомнение: сначала сделать блок uncached, убедиться в корректности, потом думать об оптимизации.**

### 14.1. Важно: не смешивать синтаксис MODX-тегов и Fenom без необходимости

Правило `[[!+placeholder]]` в п.4 — это пример для **классических MODX-тегов** (без Fenom).

- Если шаблон/чанк написан в обычных MODX-тегах, используем `[[+placeholder]]` / `[[!+placeholder]]`.
- Если шаблон на Fenom, используем Fenom-вывод плейсхолдера (`{$placeholder}` или `{$_modx->getPlaceholder('placeholder')}`), а **uncached** обеспечиваем на источнике данных (например, сниппет вызывается как `[[!SnippetName]]`).
- Не вводить правило «все плейсхолдеры только в одном синтаксисе для всего проекта». Синтаксис выбирается по контексту конкретного шаблона, но в пределах одного блока рендера придерживаться единого стиля.

Цель: избежать ситуации, когда из-за смешивания синтаксисов в одном фрагменте теряется предсказуемость кеширования и усложняется отладка.
