# 🔧 Инструкция по установке служебного меню для админов и экспертов

## Что было добавлено

В шапку сайта (`tsHeader.tpl`) добавлено выпадающее меню **"Управление"**, которое:

- Видно только **админам** (группа `LMS Admins`) и **экспертам** (группа `LMS Experts`)
- Отображает ресурсы из контейнера **"Служебные ресурсы"** (ID: 191)
- Автоматически определяет иконки для пунктов меню

---

## 📦 Что нужно установить

### 1. Обновить chunk tsHeader.tpl

Chunk уже обновлен в файле `core/elements/chunks/tsHeader.tpl`.

**Обновите chunk в админке MODX:**
- Админка → Элементы → Чанки → tsHeader
- Скопируйте содержимое из файла `core/elements/chunks/tsHeader.tpl`
- Сохраните

**Или выполните скрипт обновления:**
```bash
php core/components/testsystem/update_template_chunks.php
```

---

### 2. Установить snippet getResourceIcon

Этот snippet определяет иконки для пунктов меню.

#### Вариант A: Через SQL

```bash
mysql -u username -p database_name < core/components/testsystem/sql/ADD_RESOURCE_ICON_SNIPPET.sql
```

#### Вариант B: Через админку MODX вручную

1. Элементы → Сниппеты → Создать новый сниппет
2. **Название:** `getResourceIcon`
3. **Описание:** `Возвращает иконку FontAwesome для ресурса`
4. **Код:** скопируйте из файла `core/elements/snippets/getResourceIcon.php` (без `<?php`)
5. **Категория:** `Test System`
6. Сохраните

#### Вариант C: Через консоль MODX

Выполните в консоли MODX (Элементы → Консоль):

```php
$snippet = $modx->getObject('modSnippet', array('name' => 'getResourceIcon'));
if (!$snippet) {
    $snippet = $modx->newObject('modSnippet');
    $snippet->set('name', 'getResourceIcon');
}
$snippet->set('description', 'Возвращает иконку FontAwesome для ресурса');
$snippetCode = file_get_contents(MODX_CORE_PATH . 'elements/snippets/getResourceIcon.php');
$snippetCode = preg_replace('/^<\?php\s*/i', '', $snippetCode);
$snippet->set('snippet', $snippetCode);
$category = $modx->getObject('modCategory', array('category' => 'Test System'));
if ($category) $snippet->set('category', $category->id);
if ($snippet->save()) {
    echo "✅ Сниппет getResourceIcon создан (ID: {$snippet->id})";
    $modx->cacheManager->refresh();
} else {
    echo "❌ Ошибка создания сниппета";
}
```

---

### 3. Очистить кеш MODX

После установки обязательно очистите кеш:
- Админка → Управление → Очистить кеш

---

## 📋 Настройка иконок для ресурсов

Snippet `getResourceIcon` определяет иконки двумя способами:

### 1. По ID ресурса (точное совпадение)

```php
$iconMapById = [
    168 => 'folder-open',       // Управление категориями
    43  => 'users',              // Пользователи
    36  => 'plus-circle',        // Создать тест
    // ...
];
```

### 2. По ключевым словам в названии

```php
$iconMapByKeyword = [
    'категор'      => 'folder-open',
    'пользовател'  => 'users',
    'тест'         => 'clipboard-list',
    'результат'    => 'chart-bar',
    // ...
];
```

### Как добавить свою иконку

**Отредактируйте snippet `getResourceIcon`:**

1. Админка → Элементы → Сниппеты → getResourceIcon
2. Добавьте свой ID или ключевое слово в соответствующий массив
3. Сохраните
4. Очистите кеш

**Пример:**
```php
$iconMapById = [
    168 => 'folder-open',
    43  => 'users',
    200 => 'database',    // ← добавили новый ID
];
```

---

## 🎨 Список доступных иконок FontAwesome

Используются иконки FontAwesome 5 Free (Solid):

### Управление
- `tools` - инструменты
- `cog` - настройки
- `sliders-h` - параметры

### Пользователи
- `users` - пользователи
- `user` - пользователь
- `user-shield` - админ
- `user-graduate` - студент

### Файлы и данные
- `folder-open` - категории
- `file-alt` - документ
- `database` - база данных
- `file-import` - импорт
- `file-export` - экспорт

### Тестирование
- `clipboard-list` - тест
- `plus-circle` - создать
- `chart-bar` - результаты
- `chart-line` - график
- `chart-pie` - статистика

### Достижения
- `certificate` - сертификат
- `trophy` - рейтинг
- `medal` - награда
- `star` - звезда

### Обучение
- `book` - материал
- `graduation-cap` - обучение
- `chalkboard-teacher` - преподаватель

### Прочее
- `bell` - уведомления
- `envelope` - письма

Полный список: https://fontawesome.com/v5/search?m=free&s=solid

---

## 🔍 Проверка работы

### 1. Авторизуйтесь как админ или эксперт

### 2. В шапке сайта должно появиться меню "Управление"

```
┌─────────────────────────┐
│ 🛠️ Управление          ▼│
├─────────────────────────┤
│ 📁 Управление категориями│
│ 👥 Пользователи          │
│ ...                     │
└─────────────────────────┘
```

### 3. Проверьте, что:
- Меню видно только админам и экспертам
- Пункты меню берутся из контейнера "Служебные ресурсы" (ID: 191)
- У каждого пункта есть иконка
- Клик по пункту ведет на нужную страницу

---

## 🐛 Если не работает

### Проблема: Меню не отображается

**Проверьте:**
1. Пользователь в группе `LMS Admins` или `LMS Experts`?
2. Chunk `tsHeader` обновлен?
3. Кеш очищен?

**SQL проверка группы пользователя:**
```sql
SELECT ug.name
FROM modx_user_group_members ugm
JOIN modx_user_groups ug ON ugm.user_group = ug.id
WHERE ugm.member = [ваш_user_id];
```

### Проблема: Нет иконок (отображается [!getResourceIcon?...)

**Причина:** Snippet `getResourceIcon` не зарегистрирован в БД

**Решение:**
1. Проверьте наличие snippet в админке (Элементы → Сниппеты)
2. Если нет - установите по инструкции выше
3. Очистите кеш

### Проблема: Пустое меню (нет пунктов)

**Причина:** Нет дочерних ресурсов у контейнера 191

**Проверка:**
```sql
SELECT id, pagetitle FROM modx_site_content
WHERE parent = 191 AND deleted = 0;
```

**Решение:**
1. Создайте ресурсы в админке
2. Сделайте их дочерними для ресурса "Служебные ресурсы" (ID: 191)
3. Проверьте, что `hidemenu = 0` (не скрыты из меню)

---

## 📝 Настройка контейнера "Служебные ресурсы"

Если у вас другой ID контейнера, измените в `tsHeader.tpl`:

```
&parents=`191`  ← замените на свой ID
```

---

## ✨ Дополнительные возможности

### Добавить разделители в меню

Отредактируйте `tsHeader.tpl`, добавьте после pdoMenu:

```html
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="/admin"><i class="fas fa-user-shield me-2"></i>Админка MODX</a></li>
```

### Добавить счетчик (badge)

Используйте кастомный шаблон в pdoMenu:

```html
&tpl=`@INLINE <li><a class="dropdown-item" href="[[+link]]">
  <i class="fas fa-[[!getResourceIcon]]" me-2"></i>[[+menutitle]]
  <span class="badge bg-danger ms-2">3</span>
</a></li>`
```

---

## 🎯 Итог

После установки:
- ✅ Админы и эксперты видят меню "Управление"
- ✅ Обычные пользователи не видят это меню
- ✅ Пункты меню с красивыми иконками
- ✅ Автоматическое определение иконок по названию
- ✅ Легко добавлять новые пункты через админку
