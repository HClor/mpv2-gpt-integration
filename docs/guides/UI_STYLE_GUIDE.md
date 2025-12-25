# UI Style Guide для LMS сниппетов

Этот документ описывает единый стиль оформления всех сниппетов системы тестирования.

---

## 📋 Общая структура сниппета

```php
<?php
/**
 * Snippet Name v1.0 - Brief description
 */

// 1. Подключаем bootstrap для CSRF защиты и helpers
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

// 2. Проверка авторизации (если требуется)
try {
    PermissionHelper::requireAuthentication($modx);
} catch (AuthenticationException $e) {
    return $e->renderAlert($modx, 'Для доступа необходимо войти в систему.');
}

// 3. Получаем данные пользователя
$userId = PermissionHelper::getCurrentUserId($modx);
$prefix = $modx->getOption('table_prefix');

// 4. Загружаем данные из БД
// ...

// 5. CSRF Protection: добавляем meta тег
$output = CsrfProtection::getTokenMeta();

// 6. Формируем HTML
$output .= '<div class="snippet-container">';
// ...
$output .= '</div>';

// 7. Подключаем JS (если нужно)
$assetsUrl = rtrim($modx->getOption('assets_url', null, MODX_ASSETS_URL), '/') . '/';
$jsPath = $assetsUrl . 'components/testsystem/js/snippet-name.js';
$output .= '<script src="' . htmlspecialchars($jsPath, ENT_QUOTES, 'UTF-8') . '"></script>';

return $output;
```

---

## 🎨 Bootstrap 5 компоненты

### 1. Карточки (Cards)

**Использовать для:** основных блоков контента, секций, группировки информации

```php
$output .= '<div class="card mb-4">';
$output .= '<div class="card-header">';
$output .= '<div class="d-flex justify-content-between align-items-center">';
$output .= '<h5 class="mb-0"><i class="bi bi-star text-warning"></i> Заголовок</h5>';
$output .= '<button class="btn btn-sm btn-primary">Действие</button>';
$output .= '</div>';
$output .= '</div>';
$output .= '<div class="card-body">';
$output .= '<p>Содержимое карточки</p>';
$output .= '</div>';
$output .= '</div>';
```

### 2. Алерты (Alerts)

**Использовать для:** сообщений, уведомлений, состояний "пусто"

```php
// Информационное сообщение
$output .= '<div class="alert alert-info">';
$output .= '<h4><i class="bi bi-info-circle"></i> Информация</h4>';
$output .= '<p>Текст сообщения</p>';
$output .= '</div>';

// Предупреждение
$output .= '<div class="alert alert-warning">';
$output .= '<i class="bi bi-exclamation-triangle"></i> Предупреждение';
$output .= '</div>';

// Ошибка
$output .= '<div class="alert alert-danger">';
$output .= '<i class="bi bi-x-circle"></i> Ошибка';
$output .= '</div>';

// Успех
$output .= '<div class="alert alert-success">';
$output .= '<i class="bi bi-check-circle"></i> Успешно';
$output .= '</div>';
```

### 3. Кнопки (Buttons)

**Варианты:**
- `btn-primary` - основное действие (синий)
- `btn-success` - позитивное действие (зеленый)
- `btn-danger` - опасное действие (красный)
- `btn-warning` - предупреждение (желтый)
- `btn-secondary` - второстепенное действие (серый)
- `btn-outline-*` - контурные варианты

**Размеры:**
- `btn-lg` - большая кнопка
- `btn-sm` - маленькая кнопка
- без класса - средняя (по умолчанию)

```php
// Основная кнопка
$output .= '<button class="btn btn-primary">';
$output .= '<i class="bi bi-plus-circle"></i> Создать';
$output .= '</button>';

// Группа кнопок
$output .= '<div class="btn-group" role="group">';
$output .= '<button class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Редактировать</button>';
$output .= '<button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Удалить</button>';
$output .= '</div>';
```

### 4. Списки (List Groups)

**Использовать для:** списков элементов, меню, результатов поиска

```php
$output .= '<div class="list-group">';
$output .= '<div class="list-group-item">';
$output .= '<div class="d-flex justify-content-between align-items-start">';
$output .= '<div class="flex-grow-1">';
$output .= '<h6 class="mb-1">Заголовок элемента</h6>';
$output .= '<p class="mb-1 text-muted">Описание</p>';
$output .= '</div>';
$output .= '<span class="badge bg-primary">42</span>';
$output .= '</div>';
$output .= '</div>';
$output .= '</div>';
```

### 5. Модальные окна (Modals)

```php
$output .= '<div class="modal fade" id="exampleModal" tabindex="-1">';
$output .= '<div class="modal-dialog modal-lg">';  // modal-lg, modal-xl, modal-sm
$output .= '<div class="modal-content">';
$output .= '<div class="modal-header">';
$output .= '<h5 class="modal-title"><i class="bi bi-gear"></i> Заголовок</h5>';
$output .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
$output .= '</div>';
$output .= '<div class="modal-body">';
$output .= 'Содержимое модального окна';
$output .= '</div>';
$output .= '<div class="modal-footer">';
$output .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>';
$output .= '<button type="button" class="btn btn-primary">Сохранить</button>';
$output .= '</div>';
$output .= '</div>';
$output .= '</div>';
$output .= '</div>';
```

---

## 🎭 Bootstrap Icons

**Всегда используйте иконки!** Они улучшают UX и делают интерфейс понятнее.

### Часто используемые иконки:

| Иконка | Класс | Использование |
|--------|-------|---------------|
| ➕ | `bi-plus-circle` | Создать, добавить |
| ✏️ | `bi-pencil` | Редактировать |
| 🗑️ | `bi-trash` | Удалить |
| ⚙️ | `bi-gear` | Настройки |
| 👥 | `bi-people` | Пользователи, доступ |
| 📁 | `bi-folder` | Категория, папка |
| 📄 | `bi-file-text` | Документ, тест |
| ⭐ | `bi-star` | Избранное (пустая) |
| ⭐ | `bi-star-fill` | Избранное (заполненная) |
| ✅ | `bi-check-circle` | Успех, готово |
| ❌ | `bi-x-circle` | Ошибка, отмена |
| ⚠️ | `bi-exclamation-triangle` | Предупреждение |
| ℹ️ | `bi-info-circle` | Информация |
| 🔒 | `bi-lock` | Приватный, закрыто |
| 🌐 | `bi-globe` | Публичный |
| 🔗 | `bi-link` | Ссылка |
| 🎯 | `bi-bullseye` | Цель, тест |
| 📊 | `bi-bar-chart` | Статистика |
| 🔍 | `bi-search` | Поиск |
| 📤 | `bi-upload` | Импорт, загрузка |
| ▶️ | `bi-play-fill` | Запустить, начать |
| 📝 | `bi-pencil-square` | Черновик |

### Примеры с цветами:

```php
// Иконка с цветом
'<i class="bi bi-star-fill text-warning"></i>'  // Желтая звезда
'<i class="bi bi-folder text-primary"></i>'     // Синяя папка
'<i class="bi bi-lock text-danger"></i>'        // Красный замок
'<i class="bi bi-globe text-success"></i>'      // Зеленый глобус
```

---

## 📐 Responsive дизайн

### Flexbox классы:

```php
// Горизонтальное выравнивание с отступами
'<div class="d-flex justify-content-between align-items-center gap-3">'

// Элемент растягивается на всю ширину
'<div class="flex-grow-1">'

// Элемент не сжимается
'<div class="flex-shrink-0">'

// Вертикальное выравнивание
'<div class="d-flex align-items-start">'     // Сверху
'<div class="d-flex align-items-center">'    // По центру
'<div class="d-flex align-items-end">'       // Снизу
```

### Margins и Paddings:

```php
// Margins
'.mb-3'  // margin-bottom: 1rem
'.mt-4'  // margin-top: 1.5rem
'.mx-2'  // margin left/right: 0.5rem
'.my-3'  // margin top/bottom: 1rem

// Paddings
'.p-3'   // padding: 1rem
'.px-4'  // padding left/right: 1.5rem
'.py-2'  // padding top/bottom: 0.5rem
```

### Размеры:
- `1` = 0.25rem (4px)
- `2` = 0.5rem (8px)
- `3` = 1rem (16px)
- `4` = 1.5rem (24px)
- `5` = 3rem (48px)

---

## 🎨 Компонент: Toggle Switch (как в myFavorites)

Для создания красивого переключателя (например, для избранного):

### PHP:

```php
$output .= '<div class="favorite-toggle-wrapper">';
$output .= '<label class="favorite-toggle-switch">';
$output .= '<input type="checkbox" class="favorite-toggle-checkbox" data-item-id="' . $itemId . '" checked>';
$output .= '<span class="favorite-toggle-slider"></span>';
$output .= '</label>';
$output .= '<span class="favorite-toggle-label-text">В избранном</span>';
$output .= '</div>';
```

### CSS (добавить в конец сниппета):

```php
$output .= '<style>
/* Toggle Switch */
.favorite-toggle-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.favorite-toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
    margin: 0;
}

.favorite-toggle-checkbox {
    opacity: 0;
    width: 0;
    height: 0;
}

.favorite-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 24px;
}

.favorite-toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

.favorite-toggle-checkbox:checked + .favorite-toggle-slider {
    background-color: #ffc107;
}

.favorite-toggle-checkbox:checked + .favorite-toggle-slider:before {
    transform: translateX(26px);
}

.favorite-toggle-label-text {
    font-size: 0.875rem;
    color: #6c757d;
}
</style>';
```

---

## 🔐 CSRF Protection

**Обязательно** в каждом сниппете:

```php
// 1. В начале сниппета
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

// 2. Добавить meta тег для JavaScript
$output = CsrfProtection::getTokenMeta();

// 3. В JavaScript при AJAX запросах
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

fetch('/assets/components/testsystem/ajax/testsystem.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'someAction',
        data: {
            csrf_token: csrfToken,
            // ... другие данные
        }
    })
});
```

---

## 🔒 Проверка авторизации

```php
// Вариант 1: Обязательная авторизация
try {
    PermissionHelper::requireAuthentication($modx);
} catch (AuthenticationException $e) {
    return $e->renderAlert($modx, 'Для доступа необходимо войти в систему.');
}

// Вариант 2: Проверка прав админа
if (!PermissionHelper::isAdmin($modx)) {
    return '<div class="alert alert-danger">Доступ запрещен. Требуются права администратора.</div>';
}

// Вариант 3: Проверка владельца ресурса
$userId = PermissionHelper::getCurrentUserId($modx);
if ((int)$item['created_by'] !== $userId && !PermissionHelper::isAdmin($modx)) {
    return '<div class="alert alert-danger">Доступ запрещен. Вы не владелец этого ресурса.</div>';
}
```

---

## 📱 JavaScript уведомления

Единый стиль всплывающих уведомлений:

```javascript
function showNotification(message, type = 'success') {
    const notificationClass = type === 'error' ? 'alert-danger' :
                               type === 'warning' ? 'alert-warning' :
                               type === 'info' ? 'alert-info' : 'alert-success';

    const icon = type === 'error' ? '❌' :
                 type === 'warning' ? '⚠️' :
                 type === 'info' ? 'ℹ️' : '✅';

    const notification = document.createElement('div');
    notification.className = `alert ${notificationClass} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    notification.innerHTML = `
        ${icon} ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 150);
    }, 3000);
}

// Использование:
showNotification('Тест успешно создан!', 'success');
showNotification('Произошла ошибка', 'error');
showNotification('Внимание!', 'warning');
showNotification('Информация', 'info');
```

---

## ✅ Чеклист для нового сниппета

- [ ] Подключен `bootstrap.php`
- [ ] Добавлена проверка авторизации (если требуется)
- [ ] Добавлен CSRF meta тег
- [ ] Используются Bootstrap 5 компоненты (card, alert, btn, etc)
- [ ] Все кнопки имеют иконки
- [ ] Используется responsive дизайн (d-flex, gap, mb-, etc)
- [ ] XSS защита через `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')`
- [ ] SQL запросы через prepared statements
- [ ] Модальные окна имеют правильную структуру
- [ ] JavaScript использует CSRF токен
- [ ] Уведомления используют единый стиль
- [ ] Алерты для пустых состояний
- [ ] Консистентные цвета и размеры

---

## 🎨 Цветовая палитра

| Назначение | Класс | Цвет |
|-----------|-------|------|
| Основной | `text-primary`, `bg-primary`, `btn-primary` | Синий |
| Успех | `text-success`, `bg-success`, `btn-success` | Зеленый |
| Опасность | `text-danger`, `bg-danger`, `btn-danger` | Красный |
| Предупреждение | `text-warning`, `bg-warning`, `btn-warning` | Желтый |
| Информация | `text-info`, `bg-info`, `btn-info` | Голубой |
| Нейтральный | `text-secondary`, `bg-secondary`, `btn-secondary` | Серый |
| Приглушенный | `text-muted` | Светло-серый |

---

## 🚫 Анти-паттерны (не делайте так!)

❌ **Не используйте:**
- Inline styles (за исключением динамических значений)
- Таблицы для layout'а
- `<br>` для отступов (используйте margin/padding классы)
- `<center>`, `<font>` и другие устаревшие теги
- jQuery (используйте vanilla JS)
- alert() для уведомлений (используйте showNotification())
- Хардкод URL'ов (используйте $modx->makeUrl())
- Прямые SQL запросы без prepared statements

✅ **Используйте:**
- Bootstrap классы для всего
- Semantic HTML5 теги
- Flexbox для layout'а
- CSS классы вместо inline styles
- Prepared statements для SQL
- htmlspecialchars() для вывода
- Консистентную структуру кода

---

**Дата создания:** 2024-12-05
**Версия:** 1.0
