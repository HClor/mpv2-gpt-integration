# 🔄 АНАЛИЗ ДУБЛИРОВАНИЯ КОДА

**Дата:** 2025-11-13
**Проект:** MPV2 Test System (MODX REVO)

---

## 📊 СТАТИСТИКА ДУБЛИРОВАНИЯ

| Паттерн | Вхождений | Файлов | Приоритет |
|---------|-----------|--------|-----------|
| Проверка прав доступа (LMS Admins/Experts) | 17 | 7 | 🔴 CRITICAL |
| Проверка авторизации (hasSessionContext) | 44 | 15 | 🔴 CRITICAL |
| SQL prepare + execute | 34 | 10 | 🟡 HIGH |
| htmlspecialchars с ENT_QUOTES | 62 | 11 | 🟢 MEDIUM |
| makeUrl для web контекста | 17 | 4 | 🟢 LOW |
| Получение table_prefix | 17 | 17 | 🟢 LOW |

**Общий объем дублирования:** ~30-40% кода

---

## 🔴 КРИТИЧЕСКОЕ ДУБЛИРОВАНИЕ

### 1. Проверка ролей LMS Admins / LMS Experts

**Паттерн дублируется в:**
- `testRunner.php` (6 раз)
- `addTestForm.php` (1 раз)
- `csvImportForm.php` (1 раз)
- `manageUsers.php` (4 раза)
- `testsystem.php` (1 раз)
- `upload-image.php` (1 раз)
- `userMenu.php` (2 раза)

**Пример кода (повторяется ВЕЗДЕ):**

```php
// testRunner.php:267-275
$roleStmt = $modx->prepare("SELECT mgn.`name` FROM {$tableMemberGroups} AS mg
    JOIN {$tableMemberGroupNames} AS mgn ON mgn.`id` = mg.`user_group`
    WHERE mg.`member` = :uid AND mgn.`name` IN ('LMS Admins', 'LMS Experts')");

if ($roleStmt && $roleStmt->execute([':uid' => $currentUserId])) {
    $roleNames = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('LMS Admins', $roleNames, true) || in_array('LMS Experts', $roleNames, true)) {
        $canEdit = true;
    }
}
```

```php
// addTestForm.php:32-42
$sql = "SELECT mgn.`name`
        FROM `{$prefix}member_groups` AS mg
        JOIN `{$prefix}membergroup_names` AS mgn ON mgn.`id` = mg.`user_group`
        WHERE mg.`member` = :uid
        AND mgn.`name` IN ('LMS Admins', 'LMS Experts')";

$stmt = $modx->prepare($sql);
if ($stmt && $stmt->execute([':uid' => $currentUserId])) {
    $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $canCreate = count($groups) > 0;
}
```

```php
// csvImportForm.php:79-89
$sql = "SELECT mgn.`name`
        FROM `{$prefix}member_groups` AS mg
        JOIN `{$prefix}membergroup_names` AS mgn ON mgn.`id` = mg.`user_group`
        WHERE mg.`member` = :uid
        AND mgn.`name` IN ('LMS Admins', 'LMS Experts')";

$stmt = $modx->prepare($sql);
if ($stmt && $stmt->execute([':uid' => $currentUserId])) {
    $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $isExpertOrAdmin = (count($groups) > 0);
}
```

**Проблемы:**
- ❌ Один и тот же SQL запрос выполняется 17+ раз в разных файлах
- ❌ Невозможно изменить логику прав глобально
- ❌ Нет кэширования результата
- ❌ При добавлении новой роли надо менять 17 мест

**Решение:**
```php
// Создать класс AccessService
class AccessService {
    private $modx;
    private $cache = [];

    public function isAdminOrExpert($userId) {
        if (isset($this->cache[$userId])) {
            return $this->cache[$userId];
        }

        $prefix = $this->modx->getOption('table_prefix');
        $sql = "SELECT mgn.`name`
                FROM `{$prefix}member_groups` AS mg
                JOIN `{$prefix}membergroup_names` AS mgn ON mgn.`id` = mg.`user_group`
                WHERE mg.`member` = :uid
                AND mgn.`name` IN ('LMS Admins', 'LMS Experts')";

        $stmt = $this->modx->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->cache[$userId] = count($groups) > 0;
        return $this->cache[$userId];
    }
}

// Использование
$accessService = new AccessService($modx);
if ($accessService->isAdminOrExpert($userId)) {
    // ...
}
```

---

### 2. Проверка авторизации

**Паттерн дублируется в 15 файлах:**

```php
if (!$modx->user->hasSessionContext('web')) {
    $authUrl = $modx->makeUrl($modx->getOption('lms.auth_page', null, 0));
    return '<div class="alert alert-warning">
        <a href="' . $authUrl . '">Войдите</a> в систему
    </div>';
}
$userId = (int)$modx->user->get('id');
```

**Найдено в:**
- `testRunner.php` (2 раза)
- `myFavorites.php`
- `csvImportForm.php`
- `getLearningResourceIds.php`
- `userProfile.php`
- `myTests.php`
- `userMenu.php`
- `addTestForm.php`
- `knowledgeAreasManager.php`
- `getUserRights.php`
- `categoriesList.php`
- `testsystem.php` (29 раз!)
- `upload-image.php`
- `manageCategories.php`
- `authHandler.php`

**Проблемы:**
- ❌ Невозможно глобально изменить страницу авторизации
- ❌ Разный формат сообщений об ошибке
- ❌ Нет редиректа, только вывод текста

**Решение:**
```php
class AuthGuard {
    public function requireAuth($modx) {
        if (!$modx->user->hasSessionContext('web')) {
            $authUrl = $modx->makeUrl($modx->getOption('lms.auth_page', null, 0));

            // Если AJAX
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                die(json_encode(['success' => false, 'message' => 'Authentication required']));
            }

            // Обычный запрос - редирект или вывод
            return '<div class="alert alert-warning">
                <a href="' . htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8') . '">Войдите</a> в систему
            </div>';
        }
        return null;
    }

    public function getUserId($modx) {
        return (int)$modx->user->get('id');
    }
}
```

---

### 3. Инициализация префикса таблиц

**Паттерн в 17 файлах:**

```php
$prefix = $modx->getOption('table_prefix');
$tableTests = $prefix . 'test_tests';
$tableQuestions = $prefix . 'test_questions';
// ...
```

или

```php
$prefix = $modx->getOption('table_prefix');
$Ttests = $prefix . 'test_tests';
$Tquestions = $prefix . 'test_questions';
// ...
```

или

```php
$prefix = (string)$modx->getOption('table_prefix');
$tableTests = '`' . $prefix . 'test_tests`';
$tableQuestions = '`' . $prefix . 'test_questions`';
// ...
```

**Проблемы:**
- ❌ Непоследовательное именование (`tableTests` vs `Ttests`)
- ❌ Разные подходы к экранированию (с `` ` `` или без)
- ❌ Дублирование инициализации

**Решение:**
```php
class DbTables {
    private $prefix;

    public function __construct($modx) {
        $this->prefix = $modx->getOption('table_prefix');
    }

    public function tests() {
        return $this->prefix . 'test_tests';
    }

    public function questions() {
        return $this->prefix . 'test_questions';
    }

    public function sessions() {
        return $this->prefix . 'test_sessions';
    }

    // ... и т.д.
}

// Использование
$tables = new DbTables($modx);
$sql = "SELECT * FROM {$tables->tests()} WHERE id = ?";
```

---

## 🟡 ВЫСОКИЙ ПРИОРИТЕТ

### 4. SQL запросы с prepare/execute

**Паттерн:**

```php
$stmt = $modx->prepare("SELECT ... FROM ...");
if (!$stmt) {
    $modx->log(modX::LOG_LEVEL_ERROR, 'Failed to prepare');
    return '<div class="alert alert-danger">Ошибка</div>';
}

if (!$stmt->execute([...])) {
    $modx->log(modX::LOG_LEVEL_ERROR, 'Failed to execute');
    return '<div class="alert alert-danger">Ошибка</div>';
}

$result = $stmt->fetch(PDO::FETCH_ASSOC);
```

**Найдено в 10 файлах, 34 вхождения**

**Проблемы:**
- ❌ Повторяющаяся обработка ошибок
- ❌ Нет единой точки логирования
- ❌ Разный формат сообщений об ошибках

**Решение:**
```php
class BaseRepository {
    protected $modx;
    protected $prefix;

    public function query($sql, $params = []) {
        $stmt = $this->modx->prepare($sql);

        if (!$stmt) {
            $this->logError('Failed to prepare query', $sql);
            throw new DatabaseException('Query preparation failed');
        }

        if (!$stmt->execute($params)) {
            $this->logError('Failed to execute query', $sql, $stmt->errorInfo());
            throw new DatabaseException('Query execution failed');
        }

        return $stmt;
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }
}
```

---

## 🟢 СРЕДНИЙ ПРИОРИТЕТ

### 5. Экранирование HTML

**Паттерн в 11 файлах, 62 вхождения:**

```php
htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
```

**Решение:**
```php
class HtmlHelper {
    public static function escape($value) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// Использование
echo HtmlHelper::escape($test['title']);
```

---

### 6. Генерация URL

**Паттерн в 4 файлах, 17 вхождений:**

```php
$url = $modx->makeUrl($pageId, 'web', $params, 'full');
$url = rtrim($url, '/');
```

**Решение:**
```php
class UrlHelper {
    private $modx;

    public function make($pageId, $params = [], $full = true) {
        $url = $this->modx->makeUrl($pageId, 'web', $params, $full ? 'full' : '');
        return rtrim($url, '/');
    }
}
```

---

## 📦 СПЕЦИФИЧЕСКОЕ ДУБЛИРОВАНИЕ

### 7. Проверка владельца теста

**Паттерн:**

```php
$stmt = $modx->prepare("SELECT created_by FROM {$prefix}test_tests WHERE id = ?");
$stmt->execute([$testId]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);
$isOwner = ((int)$test['created_by'] === $userId);
```

**Найдено в:**
- `testRunner.php`
- `csvImportForm.php`
- `testsystem.php` (несколько раз)

**Решение:**
```php
class TestService {
    public function isOwner($testId, $userId) {
        $test = $this->testRepository->find($testId);
        return $test && (int)$test['created_by'] === $userId;
    }
}
```

---

### 8. Проверка доступа к тесту

**Паттерн в `testRunner.php:312-361` и `testsystem.php`:**

```php
// Определяем права пользователя
$roleStmt = $modx->prepare("SELECT mgn.`name` ...");
$isAdminOrExpert = false;
// ... 15 строк кода ...

// Проверяем доступ
if ($isAdminOrExpert) {
    $hasAccess = true;
} elseif ($createdBy === $userId) {
    $hasAccess = true;
} elseif ($publicationStatus === 'public' || $publicationStatus === 'unlisted') {
    $hasAccess = true;
} elseif ($publicationStatus === 'private') {
    $stmt = $modx->prepare("SELECT COUNT(*) FROM {$prefix}test_permissions ...");
    // ...
}
```

**Проблемы:**
- ❌ 40+ строк кода повторяются в 2+ местах
- ❌ Сложная вложенная логика
- ❌ Невозможно тестировать изолированно

**Решение:**
```php
class TestAccessService {
    public function canAccess($testId, $userId) {
        $test = $this->testRepository->find($testId);

        if ($this->accessService->isAdminOrExpert($userId)) {
            return true;
        }

        if ($test['created_by'] === $userId) {
            return true;
        }

        if (in_array($test['publication_status'], ['public', 'unlisted'])) {
            return true;
        }

        if ($test['publication_status'] === 'private') {
            return $this->permissionRepository->hasAccess($testId, $userId);
        }

        return false;
    }
}
```

---

## 📈 МЕТРИКИ ДУБЛИРОВАНИЯ

### По файлам (топ-5):

| Файл | Строк | Дублированный код (оценка) | % |
|------|-------|---------------------------|---|
| testsystem.php | 3000+ | ~1200 строк | 40% |
| testRunner.php | 733 | ~250 строк | 34% |
| csvImportForm.php | 482 | ~150 строк | 31% |
| myFavorites.php | 448 | ~120 строк | 27% |
| addTestForm.php | 391 | ~100 строк | 26% |

### По категориям:

| Категория | Строк дублирования | Приоритет |
|-----------|-------------------|-----------|
| Проверка прав | ~500 | 🔴 CRITICAL |
| Проверка авторизации | ~400 | 🔴 CRITICAL |
| SQL запросы | ~600 | 🟡 HIGH |
| HTML экранирование | ~200 | 🟢 MEDIUM |
| Генерация URL | ~100 | 🟢 LOW |

**Общий объем дублирования:** ~1800 строк (~32% от общего кода)

---

## 🎯 ПЛАН УСТРАНЕНИЯ ДУБЛИРОВАНИЯ

### Этап 1: Инфраструктура (1-2 дня)
1. Создать `core/components/testsystem/services/`
2. Создать базовые классы:
   - `AccessService` - проверка прав
   - `AuthGuard` - проверка авторизации
   - `BaseRepository` - работа с БД
   - `HtmlHelper` - экранирование
   - `UrlHelper` - генерация URL

### Этап 2: Рефакторинг (3-5 дней)
1. Заменить дублирующийся код в критических местах:
   - `testsystem.php`
   - `testRunner.php`
   - `csvImportForm.php`
2. Написать unit-тесты для сервисов
3. Провести регрессионное тестирование

### Этап 3: Очистка (1-2 дня)
1. Удалить старый дублирующийся код
2. Обновить документацию
3. Code review

---

## 💰 ВЫГОДА ОТ УСТРАНЕНИЯ

### Сокращение кода:
- Удаление ~1800 строк дублированного кода
- Уменьшение размера на ~32%

### Повышение качества:
- ✅ Единая точка изменений
- ✅ Упрощение тестирования
- ✅ Уменьшение вероятности багов
- ✅ Упрощение поддержки

### Производительность:
- ✅ Кэширование прав доступа
- ✅ Меньше SQL запросов
- ✅ Оптимизация частых операций

---

**Файл создан автоматически в рамках Этапа 1 аудита кода.**
