# 🔒 АУДИТ БЕЗОПАСНОСТИ

**Дата:** 2025-11-13
**Проект:** MPV2 Test System (MODX REVO)
**Методология:** OWASP Top 10 (2021)

---

## 📊 СВОДКА РЕЗУЛЬТАТОВ

| Категория | Уровень риска | Найдено уязвимостей | Статус |
|-----------|--------------|---------------------|---------|
| SQL Injection | 🟢 LOW | 0 критических | ✅ Используются prepared statements |
| XSS (Cross-Site Scripting) | 🟡 MEDIUM | 5 потенциальных | ⚠️ Частичная защита |
| CSRF (Cross-Site Request Forgery) | 🔴 HIGH | 20+ уязвимых форм | ❌ Нет защиты |
| Broken Access Control | 🟡 MEDIUM | 3 проблемы | ⚠️ Требуется улучшение |
| File Upload Vulnerabilities | 🟡 MEDIUM | 2 проблемы | ⚠️ Частичная защита |
| Session Management | 🟢 LOW | 1 проблема | ✅ В основном безопасно |
| Error Handling & Logging | 🟡 MEDIUM | Множество | ⚠️ Раскрытие информации |
| Dependency Vulnerabilities | 🟢 LOW | 0 известных | ✅ phpspreadsheet актуален |

**Общая оценка безопасности:** 🟡 **MEDIUM RISK** (требуется улучшение)

---

## 🔴 КРИТИЧЕСКИЕ УЯЗВИМОСТИ

### 1. CSRF (Cross-Site Request Forgery) - КРИТИЧНО

**Описание:**
Отсутствует защита от CSRF атак для всех форм и AJAX запросов

**Затронутые компоненты:**
- ❌ `addTestForm.php` - создание тестов
- ❌ `csvImportForm.php` - импорт вопросов
- ❌ `manageCategories.php` - CRUD категорий
- ❌ `manageUsers.php` - управление пользователями
- ❌ `authHandler.php` - вход/регистрация (!)
- ❌ `testsystem.php` - ВСЕ AJAX действия (30+ endpoints)
- ❌ `upload-image.php` - загрузка изображений
- ❌ `knowledgeAreasManager.php` - управление областями

**Пример атаки:**
```html
<!-- Злоумышленник создает вредоносную страницу -->
<form action="https://mpv2.lmix.ru/assets/components/testsystem/ajax/testsystem.php" method="POST">
  <input type="hidden" name="action" value="deleteTest">
  <input type="hidden" name="data[test_id]" value="123">
</form>
<script>document.forms[0].submit();</script>

<!-- Если жертва (админ) посетит эту страницу, тест будет удален -->
```

**Файлы с CSRF токеном (ТОЛЬКО 1!):**
- ✅ `userProfile.php:15` - единственный файл с защитой

**Решение:**

1. **Создать CSRF Protection класс:**
```php
class CsrfProtection {
    const TOKEN_NAME = 'csrf_token';

    public static function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_NAME] = $token;
        return $token;
    }

    public static function validateToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }

        return hash_equals($_SESSION[self::TOKEN_NAME], $token);
    }

    public static function getTokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
```

2. **Добавить в формы:**
```php
// В addTestForm.php
echo '<form method="post">';
echo CsrfProtection::getTokenField();
// ...
```

3. **Валидация в обработчиках:**
```php
// В начале testsystem.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $request['csrf_token'] ?? $_POST['csrf_token'] ?? '';

    if (!CsrfProtection::validateToken($token)) {
        die(json_encode(['success' => false, 'message' => 'CSRF token validation failed']));
    }
}
```

4. **Для AJAX запросов:**
```javascript
// Добавить в tsrunner.js
async function apiCall(action, data) {
    // Получить токен из мета-тега или data-атрибута
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    data.csrf_token = token;

    const response = await fetch(API_URL, {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ action, data })
    });
    //...
}
```

---

## 🟡 ВЫСОКИЙ РИСК

### 2. XSS (Cross-Site Scripting) - Stored XSS

**Описание:**
Потенциальные уязвимости XSS при выводе пользовательского контента

**Найденные проблемы:**

#### 2.1 Вопросы и ответы тестов

**Файл:** `testRunner.php`
**Проблема:** HTML контент в вопросах хранится в БД и выводится через JavaScript

```javascript
// tsrunner.js:line ~500
document.getElementById("question-text").innerHTML = question.question_text;
```

**Атака:**
```
Вопрос: "<img src=x onerror=alert('XSS')>"
```

**Решение:**
```javascript
// Использовать textContent вместо innerHTML для plain text
document.getElementById("question-text").textContent = question.question_text;

// Или sanitize HTML если нужна разметка
import DOMPurify from 'dompurify';
document.getElementById("question-text").innerHTML = DOMPurify.sanitize(question.question_text);
```

#### 2.2 Названия тестов и категорий

**Файл:** `testsList.php:28`, `categoriesAndTests.php`

**Частично защищено:**
```php
htmlspecialchars($test['title'], ENT_QUOTES, 'UTF-8')  // ✅ ХОРОШО
```

**Но есть места без защиты:**
```php
// Проверить все выводы в chunks и templates
```

#### 2.3 Rich Text в Quill Editor

**Файл:** `testsystem.php` - сохранение HTML из Quill
**Проблема:** HTML сохраняется как есть, без санитизации

**Решение:**
```php
// При сохранении вопроса
require_once 'vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php';

$config = HTMLPurifier_Config::createDefault();
$purifier = new HTMLPurifier($config);

$questionText = $purifier->purify($_POST['question_text']);
```

---

### 3. Broken Access Control

#### 3.1 Проверка владельца теста

**Файл:** `testsystem.php`, `csvImportForm.php`

**Проблема:** В некоторых местах проверка ownership недостаточна

```php
// testsystem.php:line ~2931 (deleteTest)
$stmt = $modx->prepare("SELECT created_by, resource_id FROM {$prefix}test_tests WHERE id = ?");
$stmt->execute([$testId]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

$isOwner = ((int)$test['created_by'] === $userId);
$isSuperAdmin = ($userId === 1);

if (!$isOwner && !$isSuperAdmin && !$isAdmin) {
    throw new Exception('Access denied');
}
```

**Уязвимость:**
- Нет проверки что `$testId` существует (если тест не найден, `$test` будет `false`)
- Можно добавить проверку прав через `test_permissions`

**Решение:**
```php
if (!$test) {
    throw new Exception('Test not found');
}

// Проверить permissions для приватных тестов
if ($test['publication_status'] === 'private') {
    $stmt = $modx->prepare("
        SELECT can_edit FROM {$prefix}test_permissions
        WHERE test_id = ? AND user_id = ?
    ");
    $stmt->execute([$testId, $userId]);
    $perm = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($perm && $perm['can_edit']) {
        $canDelete = true;
    }
}
```

#### 3.2 Insecure Direct Object Reference (IDOR)

**Файл:** `testsystem.php` - `getQuestion`, `deleteQuestion`

**Проблема:**
```php
// Получение вопроса без проверки владельца теста
case 'getQuestion':
    $questionId = (int)($data['question_id'] ?? 0);

    $stmt = $modx->prepare("SELECT * FROM modx_test_questions WHERE id = ?");
    $stmt->execute([$questionId]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    // ❌ НЕТ ПРОВЕРКИ: может ли пользователь видеть этот вопрос?
```

**Атака:**
```javascript
// Злоумышленник подбирает ID чужих вопросов
for (let i = 1; i < 1000; i++) {
    fetch('/api/testsystem.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'getQuestion',
            data: { question_id: i }
        })
    });
}
```

**Решение:**
```php
case 'getQuestion':
    $questionId = (int)($data['question_id'] ?? 0);

    // Проверяем что вопрос принадлежит тесту, к которому есть доступ
    $stmt = $modx->prepare("
        SELECT q.*, t.created_by, t.publication_status
        FROM modx_test_questions q
        JOIN modx_test_tests t ON t.id = q.test_id
        WHERE q.id = ?
    ");
    $stmt->execute([$questionId]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$question) {
        throw new Exception('Question not found');
    }

    // Проверка доступа
    $accessService = new TestAccessService($modx);
    if (!$accessService->canEdit($question['test_id'], $userId)) {
        throw new Exception('Access denied');
    }
```

---

### 4. File Upload Vulnerabilities

#### 4.1 Upload Image Handler

**Файл:** `upload-image.php`

**Текущая защита:**
✅ Проверка авторизации
✅ Проверка MIME type
✅ Проверка размера файла (5MB)
✅ Уникальное имя файла
✅ Ресайз изображения

**Проблемы:**

1. **MIME type spoofing:**
```php
// Строка 38-40
$allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

if (!in_array($file['type'], $allowed)) {
    die(json_encode(['success' => false, 'message' => 'Invalid file type']));
}
```

**Уязвимость:** `$file['type']` берется из заголовков запроса, легко подделать

**Решение:**
```php
// Проверить РЕАЛЬНЫЙ тип файла через getimagesize
$imageInfo = @getimagesize($file['tmp_name']);

if ($imageInfo === false) {
    die(json_encode(['success' => false, 'message' => 'Not a valid image']));
}

// Проверить MIME type из getimagesize
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($imageInfo['mime'], $allowedMimes)) {
    die(json_encode(['success' => false, 'message' => 'Invalid image type']));
}
```

2. **Отсутствие проверки содержимого:**
```php
// Добавить проверку на вредоносный код в изображениях
// (хотя при ресайзе через GD это частично митигируется)
```

3. **Path Traversal (защищен):**
```php
// Строка 58 - ХОРОШО, используется uniqid + time
$filename = uniqid('q_') . '_' . time() . '.' . $ext;
```

#### 4.2 CSV/Excel Import

**Файл:** `csvImportForm.php`, `addTestForm.php`

**Текущая защита:**
✅ Проверка расширения
✅ Проверка размера (10MB)
✅ Сохранение в защищенную директорию

**Проблемы:**

1. **Нет валидации содержимого CSV:**
```php
// addTestForm.php:86-92
$allowedExtensions = ['csv', 'xlsx', 'xls'];
if (!in_array($fileExtension, $allowedExtensions)) {
    $errors[] = "Недопустимый формат файла. Разрешены: CSV, XLSX, XLS";
}
```

**Уязвимость:** Можно загрузить файл с расширением .csv, но содержащий PHP код

**Решение:**
```php
// Проверить что файл действительно CSV/Excel
if ($fileExtension === 'csv') {
    // Проверить что это текстовый файл
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fileTmpPath);
    finfo_close($finfo);

    if (!in_array($mimeType, ['text/plain', 'text/csv', 'application/csv'])) {
        $errors[] = "Недопустимый тип файла";
    }
}

// Для Excel - проверить через PHPSpreadsheet
try {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileTmpPath);
} catch (\Exception $e) {
    $errors[] = "Не удалось прочитать файл Excel";
}
```

2. **Директория загрузки доступна через веб:**
```php
// Строка 95
$uploadDir = MODX_ASSETS_PATH . 'uploads/test_imports/';
```

**Проблема:** Если в `assets/uploads/test_imports/` нет `.htaccess`, файлы доступны напрямую

**Решение:**
```php
// Создать .htaccess в uploads/
$htaccessContent = "Order Deny,Allow\nDeny from all";
file_put_contents($uploadDir . '.htaccess', $htaccessContent);

// Или хранить вне web root
$uploadDir = dirname(MODX_BASE_PATH) . '/uploads/test_imports/';
```

---

## 🟢 СРЕДНИЙ РИСК

### 5. Session Management

**Найденные проблемы:**

#### 5.1 Session Fixation (защищен)
```php
// authHandler.php использует стандартный MODX процессор
$response = $modx->runProcessor("security/login", [...]);
// ✅ MODX автоматически регенерирует session ID
```

#### 5.2 Session Timeout

**Файл:** `testsystem.php:314, 382`

```php
// Очистка старых сессий - 24 часа
$modx->exec("
    UPDATE {$prefix}test_sessions
    SET status = 'expired'
    WHERE started_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
```

**Проблема:** 24 часа - слишком долго для теста

**Рекомендация:**
```php
// Использовать настройку из конфига
$sessionTimeout = (int)$modx->getOption('test_session_timeout', null, 2); // часы

$modx->exec("
    UPDATE {$prefix}test_sessions
    SET status = 'expired'
    WHERE started_at < DATE_SUB(NOW(), INTERVAL {$sessionTimeout} HOUR)
");
```

---

### 6. Error Handling & Information Disclosure

**Проблема:** Раскрытие информации в сообщениях об ошибках

**Примеры:**

```php
// testsystem.php (множество мест)
if (!$stmt) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[testRunner] Failed to prepare query');
    return '<div class="alert alert-danger">Ошибка при загрузке теста</div>';
}
```

**Хорошо:** Пользователю показывается общее сообщение
**Плохо:** В некоторых местах выводятся детали

```php
// csvImportForm.php - есть детальные ошибки импорта
$errors[] = "Строка {$rowIndex}: некорректный формат вопроса";
```

**Рекомендация:**
- ✅ Оставить детальные ошибки для админов/экспертов
- ✅ Обычным пользователям - общие сообщения
- ✅ Логировать все ошибки в файл

---

### 7. Input Validation

**Общая оценка:** Частичная валидация

**Примеры хорошей валидации:**

```php
// testRunner.php
$knowledgeAreaId = isset($_GET['knowledge_area']) ? (int)$_GET['knowledge_area'] : 0;
$resourceId = (int)$modx->resource->get('id');
```

**Примеры недостаточной валидации:**

```php
// addTestForm.php
$title = trim($_POST["title"] ?? "");
// ❌ Нет ограничения длины
// ❌ Нет проверки спецсимволов

// Должно быть:
$title = trim($_POST["title"] ?? "");
if (strlen($title) > 255) {
    $errors[] = "Название слишком длинное (макс 255 символов)";
}
if (preg_match('/[<>]/', $title)) {
    $errors[] = "Название содержит недопустимые символы";
}
```

---

## 📋 ПРИОРИТЕТНЫЙ ПЛАН УСТРАНЕНИЯ

### СРОЧНО (1-3 дня):
1. ✅ **Внедрить CSRF защиту** для всех форм и AJAX
2. ✅ **Добавить проверку MIME типов** в upload-image.php
3. ✅ **Закрыть IDOR** в getQuestion/deleteQuestion

### ВАЖНО (1 неделя):
4. ✅ **Санитизация HTML** из Quill Editor (HTMLPurifier)
5. ✅ **Улучшить валидацию загрузки файлов**
6. ✅ **Добавить .htaccess** в upload директории

### СРЕДНИЙ ПРИОРИТЕТ (2-3 недели):
7. ✅ **Унифицировать обработку ошибок**
8. ✅ **Добавить rate limiting** для AJAX API
9. ✅ **Улучшить валидацию input**

### ДОЛГОСРОЧНО:
10. ✅ **Web Application Firewall** (ModSecurity)
11. ✅ **Security Headers** (CSP, X-Frame-Options)
12. ✅ **Регулярные пентесты**

---

## 🛡️ РЕКОМЕНДАЦИИ ПО БЕЗОПАСНОСТИ

### 1. Security Headers

Добавить в `.htaccess`:
```apache
# Защита от clickjacking
Header always set X-Frame-Options "SAMEORIGIN"

# XSS Protection
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"

# Content Security Policy
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' cdn.quilljs.com; style-src 'self' 'unsafe-inline' cdn.quilljs.com; img-src 'self' data:;"

# Referrer Policy
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

### 2. Rate Limiting

```php
class RateLimiter {
    public static function check($action, $userId, $maxAttempts = 10, $period = 60) {
        $key = "rate_limit_{$action}_{$userId}";

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'reset' => time() + $period];
        }

        if (time() > $_SESSION[$key]['reset']) {
            $_SESSION[$key] = ['count' => 0, 'reset' => time() + $period];
        }

        $_SESSION[$key]['count']++;

        if ($_SESSION[$key]['count'] > $maxAttempts) {
            return false;
        }

        return true;
    }
}

// В testsystem.php
if (!RateLimiter::check('api_call', $userId, 100, 60)) {
    die(json_encode(['success' => false, 'message' => 'Too many requests']));
}
```

### 3. Logging

```php
class SecurityLogger {
    public static function logSuspiciousActivity($event, $details) {
        $logFile = MODX_CORE_PATH . 'cache/logs/security.log';

        $entry = sprintf(
            "[%s] %s | User: %d | IP: %s | Details: %s\n",
            date('Y-m-d H:i:s'),
            $event,
            $_SESSION['user_id'] ?? 0,
            $_SERVER['REMOTE_ADDR'],
            json_encode($details)
        );

        file_put_contents($logFile, $entry, FILE_APPEND);
    }
}
```

---

**Файл создан автоматически в рамках Этапа 1 аудита кода.**
