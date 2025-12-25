# 🔧 БЫСТРОЕ ИСПРАВЛЕНИЕ: Обновление chunk tsHeader

## Проблема
Сниппеты не обрабатываются, выводятся как текст: `[[!logoutHandler]]`, `[[!pdoMenu?...]]`

**Причина:** Chunk `tsHeader` не обновлен в базе данных MODX после изменений в файле.

---

## ✅ Решение 1: Обновить chunk через админку MODX (рекомендуется)

### Шаг 1: Откройте chunk в админке
1. Войдите в админку MODX
2. **Элементы** → **Чанки** → **tsHeader**

### Шаг 2: Замените содержимое
1. **Удалите** весь текущий код chunk
2. **Скопируйте** содержимое из файла:
   ```
   core/elements/chunks/tsHeader.tpl
   ```
3. **Вставьте** в поле chunk
4. **Сохраните** (кнопка "Сохранить")

### Шаг 3: Очистите кеш
1. **Управление** → **Очистить кеш**
2. Или кнопка "Очистить кеш" в правом верхнем углу

### Шаг 4: Проверьте
1. Обновите страницу на фронтенде (Ctrl+F5)
2. Сниппеты должны заработать

---

## ✅ Решение 2: Через консоль MODX (быстрее)

1. Админка → **Элементы** → **Консоль**
2. Вставьте и выполните этот код:

```php
<?php
// Путь к файлу chunk
$filePath = MODX_CORE_PATH . 'elements/chunks/tsHeader.tpl';

// Проверяем существование файла
if (!file_exists($filePath)) {
    return "❌ Файл не найден: $filePath";
}

// Читаем содержимое
$content = file_get_contents($filePath);

// Находим chunk
$chunk = $modx->getObject('modChunk', array('name' => 'tsHeader'));

if (!$chunk) {
    return "❌ Chunk tsHeader не найден в БД";
}

// Обновляем
$chunk->set('snippet', $content);

if ($chunk->save()) {
    // Очищаем кеш
    $modx->cacheManager->refresh();
    return "✅ Chunk tsHeader обновлен и кеш очищен!";
} else {
    return "❌ Ошибка сохранения chunk";
}
```

3. Нажмите **"Выполнить"**
4. Должно появиться: `✅ Chunk tsHeader обновлен и кеш очищен!`

---

## ✅ Решение 3: Через SQL (если нет доступа к админке)

**Внимание:** Используйте только если нет доступа к админке!

### Вариант A: Через phpMyAdmin/MySQL

1. Откройте phpMyAdmin или подключитесь к MySQL
2. Выберите базу данных MODX
3. Откройте вкладку **SQL**
4. Подготовьте SQL запрос:

```sql
-- Получить текущий chunk
SELECT id, name, snippet FROM modx_site_htmlsnippets WHERE name = 'tsHeader';
```

5. Запишите ID chunk
6. Обновите chunk (замените [ID] и вставьте содержимое файла):

**НЕ РЕКОМЕНДУЕТСЯ** - лучше используйте Решение 1 или 2!

---

## 🔍 Проверка результата

После обновления chunk и очистки кеша:

### Что должно быть (правильно):
```html
<nav class="navbar navbar-expand-lg shadow-sm py-2">
  <div class="container">
    <!-- Логотип -->
    <a class="navbar-brand" href="/">
      <i class="fas fa-graduation-cap"></i>
      LMS Обучение
    </a>
    ...
    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
      <li class="nav-item"><a class="nav-link" href="/tests">Тесты</a></li>
      ...
```

### Что НЕ должно быть (ошибка):
```html
[[!logoutHandler]]
[[!pdoMenu?
  &parents=`0`
  ...
]]
{if $_modx->user.id > 1}
```

Если всё сделано правильно - сниппеты обработаются и меню отобразится корректно.

---

## ❓ Если проблема сохраняется

### 1. Проверьте, что chunk вызывается правильно в шаблоне

Откройте шаблон **TestSystem** (Элементы → Шаблоны → TestSystem):

Должно быть:
```
[[$tsHeader]]  ← С ДОЛЛАРОМ!
```

НЕ должно быть:
```
[[tsHeader]]   ← БЕЗ ДОЛЛАРА - НЕПРАВИЛЬНО!
```

### 2. Убедитесь, что у страницы правильный шаблон

1. Откройте страницу в админке
2. Вкладка **"Настройки"**
3. Поле **"Использует шаблон"** → должно быть **TestSystem**

### 3. Проверьте, что сниппеты зарегистрированы

```sql
-- Проверить наличие сниппетов
SELECT id, name FROM modx_site_snippets
WHERE name IN ('logoutHandler', 'pdoMenu', 'getResourceIcon', 'getNotifications');
```

Должны быть:
- `logoutHandler` - ✅
- `pdoMenu` - ✅ (если установлен pdoTools)
- `getResourceIcon` - ✅
- `getNotifications` - ✅

Если каких-то нет - см. инструкции по установке.

---

## 📝 Дополнительно: Обновить все chunks

Если нужно обновить все chunks (tsHead, tsHeader, tsFooter, tsScripts):

### Через консоль MODX:

```php
<?php
$chunksPath = MODX_CORE_PATH . 'elements/chunks/';

$chunks = array(
    'tsHead' => 'tsHead.tpl',
    'tsHeader' => 'tsHeader.tpl',
    'tsFooter' => 'tsFooter.tpl',
    'tsScripts' => 'tsScripts.tpl'
);

$results = [];

foreach ($chunks as $name => $file) {
    $filePath = $chunksPath . $file;

    if (!file_exists($filePath)) {
        $results[] = "❌ $name: файл не найден";
        continue;
    }

    $content = file_get_contents($filePath);
    $chunk = $modx->getObject('modChunk', array('name' => $name));

    if (!$chunk) {
        $results[] = "❌ $name: chunk не найден в БД";
        continue;
    }

    $chunk->set('snippet', $content);

    if ($chunk->save()) {
        $results[] = "✅ $name: обновлен";
    } else {
        $results[] = "❌ $name: ошибка сохранения";
    }
}

// Очищаем кеш
$modx->cacheManager->refresh();
$results[] = "✅ Кеш очищен";

return implode("\n", $results);
```

---

## ⚡ Итог

**Самый быстрый способ:**
1. Админка → Элементы → Чанки → tsHeader
2. Скопировать код из `core/elements/chunks/tsHeader.tpl`
3. Вставить, сохранить
4. Очистить кеш
5. Обновить страницу (Ctrl+F5)

**Время:** 1-2 минуты

Готово! 🎉
