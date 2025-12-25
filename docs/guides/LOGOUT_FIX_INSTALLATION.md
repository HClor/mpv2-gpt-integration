# 🔧 Инструкция по установке исправления logout

## Проблема
Выход пользователя на фронтенде не работает через ссылку `?action=logout`.

## Решение
Создан сниппет `logoutHandler`, который обрабатывает POST-запросы выхода только в контексте `web` (не затрагивая админку `mgr`).

---

## ⚡ Быстрая установка (рекомендуется)

### Вариант 1: Через PHP скрипт (самый простой)

1. **Откройте в браузере:**
   ```
   http://ваш-сайт.ru/install_logout_handler.php
   ```

2. **Следуйте инструкциям на экране**

3. **Удалите файл установки:**
   ```bash
   rm install_logout_handler.php
   ```

4. **Готово!** Попробуйте выйти через кнопку "Выход" на сайте.

---

### Вариант 2: Через MySQL

1. **Выполните SQL скрипт:**
   ```bash
   mysql -u username -p database_name < core/components/testsystem/sql/ADD_LOGOUT_HANDLER_SNIPPET.sql
   ```

2. **Очистите кеш MODX:**
   - Админка → Управление → Очистить кеш

3. **Готово!**

---

### Вариант 3: Через консоль MODX (в контексте mgr)

1. **Откройте консоль MODX** в админке

2. **Выполните следующий код:**

```php
<?php
// Создание сниппета logoutHandler
$snippet = $modx->getObject('modSnippet', array('name' => 'logoutHandler'));
if (!$snippet) {
    $snippet = $modx->newObject('modSnippet');
    $snippet->set('name', 'logoutHandler');
}

$snippet->set('description', 'Глобальный обработчик выхода для фронтэнда (контекст web)');

$snippet->set('snippet', '/**
 * Logout Handler - глобальный обработчик выхода для фронтэнда (контекст web)
 */

// Обработка выхода пользователя
if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\' && isset($_POST[\'login_logout\'])) {
    // Завершаем сессию пользователя в контексте web
    if ($modx->user->hasSessionContext(\'web\')) {
        $modx->user->removeSessionContext(\'web\');
    }

    // Перенаправляем на главную страницу
    $modx->sendRedirect($modx->makeUrl($modx->getOption(\'site_start\')));
    exit;
}

return \'\';');

// Ищем категорию Test System
$category = $modx->getObject('modCategory', array('category' => 'Test System'));
if ($category) {
    $snippet->set('category', $category->id);
}

if ($snippet->save()) {
    echo "✅ Сниппет logoutHandler успешно создан (ID: {$snippet->id})";
    $modx->cacheManager->refresh();
    echo "\n✅ Кеш очищен";
} else {
    echo "❌ Ошибка создания сниппета";
}
```

3. **Готово!**

---

### Вариант 4: Через админку MODX (вручную)

1. **Перейдите:** Элементы → Сниппеты → Создать новый сниппет

2. **Заполните поля:**
   - **Название:** `logoutHandler`
   - **Описание:** `Глобальный обработчик выхода для фронтэнда (контекст web)`
   - **Категория:** `Test System` (если есть, иначе оставьте пустым)

3. **Скопируйте код из файла:**
   ```
   core/elements/snippets/logoutHandler.php
   ```
   (без открывающего тега `<?php`)

4. **Сохраните**

5. **Очистите кеш:** Управление → Очистить кеш

---

## 🔍 Проверка установки

### Проверьте, что сниппет создан:

**Через MySQL:**
```sql
SELECT id, name, description FROM modx_site_snippets WHERE name = 'logoutHandler';
```

Должно вернуть:
```
id | name          | description
1  | logoutHandler | Глобальный обработчик выхода для фронтэнда (контекст web)
```

**Через админку MODX:**
- Элементы → Сниппеты
- Найдите `logoutHandler` в списке

---

## 📋 Что было исправлено

### Измененные файлы:

1. **core/elements/chunks/tsHeader.tpl**
   - Добавлен вызов `[[!logoutHandler]]` в начале
   - Ссылка logout заменена на POST форму

2. **core/elements/snippets/authHandler.php**
   - `endSession()` → `removeSessionContext('web')`

3. **core/elements/snippets/userMenu.php**
   - `runProcessor('security/logout')` → `removeSessionContext('web')`

4. **core/elements/snippets/logoutHandler.php** (новый)
   - Глобальный обработчик выхода

---

## ⚠️ Важно!

- После установки **обязательно очистите кеш MODX**
- Выход работает только через **POST** (безопаснее)
- Завершается только сессия в контексте **web**
- Авторизация в **админке (mgr) не затрагивается**

---

## 🧪 Тестирование

1. Авторизуйтесь на сайте (фронтенд)
2. Нажмите кнопку "Выход" в меню пользователя
3. Проверьте, что вы вышли из аккаунта
4. Проверьте, что авторизация в админке **НЕ нарушена** (откройте админку в другой вкладке)

---

## 🐛 Если не работает

### Проверьте:

1. **Сниппет создан в БД:**
   ```sql
   SELECT * FROM modx_site_snippets WHERE name = 'logoutHandler';
   ```

2. **Кеш очищен:**
   - Админка → Управление → Очистить кеш

3. **tsHeader.tpl содержит вызов:**
   ```
   [[!logoutHandler]]
   ```

4. **Chunk tsHeader обновлен в БД:**
   ```sql
   SELECT snippet FROM modx_site_htmlsnippets WHERE name = 'tsHeader';
   ```
   Должно содержать `[[!logoutHandler]]`

5. **Проверьте логи ошибок:**
   - `core/cache/logs/error.log`
   - Логи PHP (error_log)

---

## 📞 Поддержка

Если проблема сохраняется, проверьте:
- Версию MODX Revolution
- Наличие других плагинов авторизации
- Настройки сессий в `core/config/config.inc.php`
