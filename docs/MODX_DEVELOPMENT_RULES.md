# Правила разработки для MODX с Fenom

## ⚠️ КРИТИЧЕСКИЕ ПРАВИЛА (вызывают ошибку 500)

### 1. Фигурные скобки `{` `}` - КОНФЛИКТ С FENOM

**ПРОБЛЕМА:** Fenom интерпретирует `{` и `}` как свою разметку шаблонизатора.

**НЕЛЬЗЯ делать:**
```php
// ❌ НЕПРАВИЛЬНО - inline JavaScript с фигурными скобками
$output .= '<script>
function test() {
    console.log("test");  // { и } вызовут ошибку Fenom!
}
</script>';

// ❌ НЕПРАВИЛЬНО - JSDoc комментарии
$output .= '<script>
/**
 * @param {string} name  // { и } здесь тоже проблема!
 */
</script>';
```

**ПРАВИЛЬНО делать:**
```php
// ✅ ПРАВИЛЬНО - выносить JavaScript в отдельные файлы
$modx->regClientScript('/assets/components/testsystem/js/my-script.js');

// ✅ ПРАВИЛЬНО - если нужен inline, избегать фигурных скобок в комментариях
$output .= '<script>
// Простые комментарии без фигурных скобок
var x = 1;
</script>';
```

### 2. Короткий синтаксис массивов `[]` - ВЫЗЫВАЕТ ОШИБКУ 500!

**ПРОБЛЕМА:** Короткий синтаксис `[]` **гарантированно вызывает ошибку 500** в MODX/Fenom!

**НЕЛЬЗЯ делать:**
```php
// ❌ НЕПРАВИЛЬНО - вызовет ошибку 500!
$params = ['key' => 'value'];
$array = [1, 2, 3];
$modx->makeUrl($id, '', ['param' => $value]);
```

**ПРАВИЛЬНО делать:**
```php
// ✅ ПРАВИЛЬНО - использовать array()
$params = array('key' => 'value');
$array = array(1, 2, 3);
$modx->makeUrl($id, '', array('param' => $value));
```

### 3. Операторы `??` и `?:` - ОСТОРОЖНО!

**ПРОБЛЕМА:** Null coalescing operator `??` может быть проблематичным в старых версиях PHP/MODX.

**НЕЛЬЗЯ делать:**
```php
// ❌ МОЖЕТ ВЫЗВАТЬ ПРОБЛЕМЫ
$value = $_GET['param'] ?? 'default';
$text = $var ?: 'default';
```

**ПРАВИЛЬНО делать:**
```php
// ✅ ПРАВИЛЬНО - явная проверка
$value = isset($_GET['param']) ? $_GET['param'] : 'default';
$text = !empty($var) ? $var : 'default';
```

### 4. HEREDOC с JavaScript - ОСТОРОЖНО

**ПРОБЛЕМА:** HEREDOC содержащий JS код с `{}` также проблематичен.

**НЕЛЬЗЯ делать:**
```php
// ❌ НЕПРАВИЛЬНО
$output .= <<<'HTML'
<script>
function test() {
    return {name: 'test'};  // Проблема!
}
</script>
HTML;
```

**ПРАВИЛЬНО делать:**
```php
// ✅ ПРАВИЛЬНО - выносить в отдельный JS файл
$modx->regClientScript('/path/to/script.js');
```

### 5. CSRF Protection - ОБЯЗАТЕЛЬНО для AJAX запросов

**ПРОБЛЕМА:** При POST запросах к API без CSRF токена получаем ошибку "CSRF token validation failed".

**РЕШЕНИЕ:**

1. **В PHP снипете:** Инициализировать сессию MODX и добавить CSRF meta тег
```php
// ✅ ПРАВИЛЬНО - инициализировать сессию MODX
$modx->getRequest();  // ВАЖНО! Запускает сессию

// Затем добавить meta тег
$output = CsrfProtection::getTokenMeta();
$output .= '<div class="container">...';
```

**ВАЖНО:** Без `$modx->getRequest()` сессия может быть неактивна и токен не сгенерируется!

2. **В JavaScript:** Передавать токен при AJAX запросах
```javascript
// ✅ ПРАВИЛЬНО - получить токен из meta тега
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

// Для JSON запросов - добавить в data
const requestData = {
    action: 'deleteTest',
    data: {
        test_id: testId,
        csrf_token: csrfToken  // Добавить токен
    }
};

fetch(API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(requestData)
});

// Для FormData - добавить как поле
const formData = new FormData();
formData.append('test_id', testId);
if (csrfToken) {
    formData.append('csrf_token', csrfToken);
}
```

**НЕЛЬЗЯ делать:**
```javascript
// ❌ НЕПРАВИЛЬНО - без CSRF токена
fetch(API_URL, {
    method: 'POST',
    body: JSON.stringify({
        action: 'deleteTest',
        data: { test_id: testId }  // Нет csrf_token!
    })
});
```

---

## 📋 ДИАГНОСТИКА И ДОСТУПЫ

### Доступные инструменты диагностики:

1. **MODX Manager Console** (admin panel)
   - Пользователь с ID: `1` (админ)
   - Доступ к: Система → Консоль MODX

2. **Frontend авторизация** (сайт)
   - Пользователь с ID: `2`

3. **Доступ к файлам сервера**
   - Полный доступ через SSH/FTP

4. **Доступ к MySQL**
   - Прямой доступ к базе данных

5. **Browser Console**
   - Для отладки JavaScript

### Правила диагностического кода:

**ОБЯЗАТЕЛЬНО использовать сквозную нумерацию в выводе:**

```javascript
// ✅ ПРАВИЛЬНО - с нумерацией
console.log('[DIAG-1] Starting test');
console.log('[DIAG-2] Value:', value);
console.log('[DIAG-3] Result:', result);
```

Это помогает понять, на какую часть кода относится вывод.

---

## 🔧 ОТЛАДКА ОШИБКИ 500

### Шаг 1: Проверить логи PHP
```bash
tail -f /var/log/php-fpm/error.log
# или
tail -f /var/log/apache2/error.log
```

### Шаг 2: Проверить логи MODX
В MODX Manager → Система → Системные события

### Шаг 3: Проверить синтаксис PHP
```bash
php -l /path/to/file.php
```

### Шаг 4: Временно упростить код
Закомментировать части кода и найти проблемное место.

### Шаг 5: Проверить кеш MODX
Очистить кеш: MODX Manager → Управление → Очистить кеш

---

## ✅ БЕЗОПАСНЫЕ ПРАКТИКИ

### 1. Всегда использовать htmlspecialchars()
```php
// ✅ ПРАВИЛЬНО
$output .= htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
```

### 2. Подключать внешние скрипты через regClientScript
```php
// ✅ ПРАВИЛЬНО
$modx->regClientScript('/assets/components/testsystem/js/script.js');
```

### 3. Избегать inline стилей и скриптов
```php
// ❌ ИЗБЕГАТЬ
$output .= '<style>body { background: red; }</style>';

// ✅ ПРАВИЛЬНО
$modx->regClientCSS('/assets/components/testsystem/css/styles.css');
```

### 4. Использовать константы для путей
```php
// ✅ ПРАВИЛЬНО
$assetsUrl = $modx->getOption('assets_url');
$corePath = MODX_CORE_PATH;
```

---

## 🚫 ЧАСТЫЕ ОШИБКИ

### Ошибка 1: Фигурные скобки в комментариях
```php
// ❌ ВЫЗОВЕТ 500
$output .= '<script>
/* @param {string} name */  // Фигурные скобки!
</script>';
```

### Ошибка 2: Незакрытые строки в heredoc
```php
// ❌ ВЫЗОВЕТ 500
$output .= <<<HTML
<div>Test
HTML;  // Нет закрывающей метки или отступов
```

### Ошибка 3: Использование неинициализированных переменных
```php
// ❌ МОЖЕТ ВЫЗВАТЬ WARNING
if ($unknownVar) { }

// ✅ ПРАВИЛЬНО
if (isset($unknownVar) && $unknownVar) { }
```

### Ошибка 4: SQL инъекции
```php
// ❌ ОПАСНО
$sql = "SELECT * FROM users WHERE id = " . $_GET['id'];

// ✅ ПРАВИЛЬНО
$stmt = $modx->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute(array($id));
```

---

## 📝 ЧЕКЛИСТ ПЕРЕД КОММИТОМ

- [ ] Нет inline JavaScript с фигурными скобками
- [ ] Используется `array()` вместо `[]`
- [ ] Все переменные проверяются через `isset()`
- [ ] Используется `htmlspecialchars()` для вывода
- [ ] SQL запросы через prepared statements
- [ ] Внешние скрипты подключены через `regClientScript()`
- [ ] CSRF токен добавлен в снипеты и AJAX запросы
- [ ] Проверен синтаксис: `php -l file.php`
- [ ] Очищен кеш MODX перед тестированием

---

## 🔍 ДИАГНОСТИЧЕСКИЙ КОД - ШАБЛОН

```php
// Диагностика в PHP
error_log('[DIAG-1] Start function X');
error_log('[DIAG-2] Variable: ' . var_export($var, true));
error_log('[DIAG-3] Result: ' . $result);

// Диагностика в JavaScript
console.log('[DIAG-10] Script loaded');
console.log('[DIAG-11] Element found:', element);
console.log('[DIAG-12] Value:', value);
```

---

## 📚 ПОЛЕЗНЫЕ КОМАНДЫ

```bash
# Проверить синтаксис всех PHP файлов в папке
find /path/to/snippets -name "*.php" -exec php -l {} \;

# Найти все фигурные скобки в inline скриптах
grep -r "<script>" . | grep "{"

# Найти короткий синтаксис массивов
grep -r "\['" core/elements/snippets/

# Очистить кеш MODX из командной строки
rm -rf /path/to/modx/core/cache/*
```

---

**Создано:** 2026-01-27
**Версия:** 1.0
**На основе:** реальных проблем при разработке на MODX + Fenom
