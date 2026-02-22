# Руководство по управлению экспертами категорий

## Обзор

Система управления экспертами позволяет администраторам назначать экспертов на категории тестов с гранулярным контролем прав доступа.

## Структура прав доступа

### Роли пользователей

1. **LMS Admins** - Полный доступ ко всем категориям и функциям
2. **LMS Experts** - Доступ только к назначенным категориям
3. **LMS Students** - Только прохождение тестов

### Права эксперта

При назначении эксперта на категорию можно установить следующие права:

- **Управление тестами** (`can_manage_tests`) - Создание, редактирование, удаление тестов
- **Управление вопросами** (`can_manage_questions`) - Создание, редактирование, удаление вопросов
- **Подтверждение изменений** (`can_approve`) - Подтверждение изменений других экспертов

## Использование UI

### Назначение эксперта

1. Перейдите на страницу "Управление категориями" (ID: 185)
2. В таблице категорий нажмите кнопку **"Эксперты"** для нужной категории
3. В открывшемся модальном окне:
   - Просмотрите текущих назначенных экспертов
   - Выберите эксперта из выпадающего списка
   - Установите нужные права доступа (чекбоксы)
   - Нажмите **"Назначить эксперта"**

### Удаление эксперта

1. Откройте модальное окно управления экспертами
2. В списке текущих экспертов нажмите кнопку **"Удалить"**
3. Подтвердите удаление в диалоговом окне

### Информация об экспертах

В списке экспертов отображается:
- Имя пользователя (username)
- Email
- Назначенные права
- Дата назначения

## API методы

### assignCategoryExpert

Назначает эксперта на категорию (только для администраторов).

```javascript
fetch('/assets/components/testsystem/ajax/testsystem.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        action: 'assignCategoryExpert',
        csrf_token: 'YOUR_CSRF_TOKEN',
        category_id: 1,
        expert_user_id: 5,
        can_manage_tests: true,
        can_manage_questions: true,
        can_approve: false
    })
});
```

**Ответ:**
```json
{
    "success": true,
    "message": "Эксперт успешно назначен на категорию"
}
```

### removeCategoryExpert

Удаляет эксперта из категории (только для администраторов).

```javascript
fetch('/assets/components/testsystem/ajax/testsystem.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        action: 'removeCategoryExpert',
        csrf_token: 'YOUR_CSRF_TOKEN',
        category_id: 1,
        expert_user_id: 5
    })
});
```

**Ответ:**
```json
{
    "success": true,
    "message": "Эксперт успешно удален из категории"
}
```

### getCategoryExperts

Получает список экспертов категории.

```javascript
fetch('/assets/components/testsystem/ajax/testsystem.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        action: 'getCategoryExperts',
        csrf_token: 'YOUR_CSRF_TOKEN',
        category_id: 1
    })
});
```

**Ответ:**
```json
{
    "success": true,
    "data": [
        {
            "user_id": 5,
            "username": "expert1",
            "email": "expert1@example.com",
            "can_manage_tests": 1,
            "can_manage_questions": 1,
            "can_approve": 0,
            "assigned_at": "2025-12-04 10:30:00",
            "assigned_by": 1
        }
    ]
}
```

### getAvailableExperts

Получает список всех доступных экспертов (только для администраторов).

```javascript
fetch('/assets/components/testsystem/ajax/testsystem.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        action: 'getAvailableExperts',
        csrf_token: 'YOUR_CSRF_TOKEN'
    })
});
```

**Ответ:**
```json
{
    "success": true,
    "data": [
        {
            "id": 5,
            "username": "expert1",
            "email": "expert1@example.com"
        },
        {
            "id": 6,
            "username": "expert2",
            "email": "expert2@example.com"
        }
    ]
}
```

### getUserCategories

Получает категории, доступные текущему пользователю.

```javascript
fetch('/assets/components/testsystem/ajax/testsystem.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        action: 'getUserCategories',
        csrf_token: 'YOUR_CSRF_TOKEN'
    })
});
```

**Ответ для администратора:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Программирование",
            "description": "Тесты по программированию",
            "can_manage_tests": 1,
            "can_manage_questions": 1,
            "can_approve": 1,
            "role": "admin"
        }
    ]
}
```

**Ответ для эксперта:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Программирование",
            "description": "Тесты по программированию",
            "can_manage_tests": 1,
            "can_manage_questions": 1,
            "can_approve": 0,
            "role": "expert"
        }
    ]
}
```

### checkCategoryPermission

Проверяет права текущего пользователя на категорию.

```javascript
fetch('/assets/components/testsystem/ajax/testsystem.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        action: 'checkCategoryPermission',
        csrf_token: 'YOUR_CSRF_TOKEN',
        category_id: 1
    })
});
```

**Ответ:**
```json
{
    "success": true,
    "data": {
        "has_access": true,
        "can_manage_tests": true,
        "can_manage_questions": true,
        "can_approve": false,
        "role": "expert"
    }
}
```

## База данных

### Таблица modx_test_category_experts

```sql
CREATE TABLE `modx_test_category_experts` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT(11) NOT NULL COMMENT 'ID категории',
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID эксперта',
    `assigned_by` INT(11) UNSIGNED NOT NULL COMMENT 'ID администратора',
    `can_manage_tests` TINYINT(1) DEFAULT 1 COMMENT 'Может управлять тестами',
    `can_manage_questions` TINYINT(1) DEFAULT 1 COMMENT 'Может управлять вопросами',
    `can_approve` TINYINT(1) DEFAULT 0 COMMENT 'Может подтверждать изменения',
    `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата назначения',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_category_expert` (`category_id`, `user_id`),
    KEY `idx_category` (`category_id`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Файлы системы

### Backend
- `assets/components/testsystem/ajax/testsystem.php` - API endpoint с методами управления экспертами

### Frontend
- `core/elements/snippets/manageCategories.php` - UI управления категориями с модальными окнами
- `assets/components/testsystem/js/category-experts.js` - JavaScript класс CategoryExpertsManager

## Безопасность

1. **Аутентификация** - Все API методы требуют аутентификации
2. **Авторизация** - Назначение/удаление экспертов доступно только администраторам
3. **CSRF защита** - Все запросы проверяются на наличие валидного CSRF токена
4. **Валидация данных** - Все входные данные валидируются через ValidationHelper
5. **Prepared statements** - Все SQL запросы используют подготовленные выражения

## Тестирование

### Ручное тестирование

1. **Назначение эксперта:**
   - Войдите как администратор
   - Перейдите на страницу управления категориями
   - Откройте модальное окно экспертов
   - Назначьте эксперта с разными правами
   - Проверьте, что эксперт появился в списке

2. **Удаление эксперта:**
   - Откройте список экспертов категории
   - Нажмите кнопку "Удалить"
   - Подтвердите удаление
   - Проверьте, что эксперт исчез из списка

3. **Проверка прав эксперта:**
   - Войдите как назначенный эксперт
   - Убедитесь, что доступны только назначенные категории
   - Проверьте, что права соответствуют назначенным

### API тестирование

См. файл `SUMMARY_CATEGORY_EXPERTS_SYSTEM.md` для примеров тестирования через browser console.

## Возможные ошибки

### "Эксперт уже назначен на эту категорию"
- Эксперт уже имеет назначение на данную категорию
- Решение: Сначала удалите старое назначение или используйте UPDATE

### "Access denied. Admin only."
- Пользователь не является администратором
- Решение: Используйте учетную запись администратора

### "Category not found"
- Указанная категория не существует
- Решение: Проверьте существование категории в БД

### "Expert user not found or not in LMS Experts group"
- Пользователь не существует или не является экспертом
- Решение: Добавьте пользователя в группу "LMS Experts"

## Дальнейшее развитие

Возможные улучшения системы:

1. **Email уведомления** - Отправка уведомлений экспертам при назначении
2. **История изменений** - Логирование всех назначений/удалений
3. **Массовое назначение** - Назначение одного эксперта на несколько категорий сразу
4. **Временные права** - Назначение эксперта на определенный период
5. **Делегирование** - Возможность для экспертов назначать других экспертов (с ограничениями)
