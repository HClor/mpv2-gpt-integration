# Руководство по системе прав доступа TestSystem

## Обзор

Система прав доступа реализована по модели как в Moodle/Canvas и состоит из трех уровней:

1. **Группы пользователей MODX** (LMS Admins, LMS Experts, LMS Students)
2. **Роли на уровне теста** (author, editor, viewer)
3. **Статусы публикации теста** (draft, public, private)

---

## 1. Группы пользователей MODX

### LMS Admins (Администраторы)
- **Полный доступ** ко всем тестам независимо от статуса
- Могут редактировать любые тесты
- Могут управлять доступом к любым тестам
- Могут менять статус публикации любых тестов

### LMS Experts (Эксперты)
- Могут видеть все **draft** и **public** тесты
- Могут редактировать **draft** и **public** тесты
- **Не могут** редактировать чужие **private** тесты (только если есть явные права)
- **Не могут** управлять доступом к чужим тестам

### LMS Students (Студенты/Обычные пользователи)
- Видят только **public** тесты
- Могут проходить public тесты
- Могут видеть/редактировать только те тесты, на которые им дали явные права

---

## 2. Роли на уровне теста

Роли хранятся в таблице `modx_test_permissions` и дают права конкретному пользователю на конкретный тест.

### author (Автор)
- **Создатель теста** автоматически получает эту роль
- Полный доступ к тесту:
  - Редактирование теста и вопросов
  - Управление доступом (добавление/удаление пользователей)
  - Изменение статуса публикации

### editor (Редактор)
- Может редактировать тест:
  - Изменение вопросов
  - Изменение настроек теста
- **Не может**:
  - Управлять доступом
  - Менять статус публикации

### viewer (Зритель)
- Только просмотр:
  - Просмотр теста
  - Прохождение теста
- **Не может**:
  - Редактировать тест

---

## 3. Матрица прав доступа по статусам

### Draft (Черновик)

| Группа/Роль | Просмотр | Редактирование | Управление доступом | Смена статуса |
|-------------|----------|----------------|---------------------|---------------|
| LMS Admins | ✅ Да | ✅ Да | ✅ Да | ✅ Да |
| LMS Experts | ✅ Да | ✅ Да | ❌ Нет | ❌ Нет |
| Автор (author) | ✅ Да | ✅ Да | ✅ Да | ✅ Да |
| Редактор (editor) | ✅ Да | ✅ Да | ❌ Нет | ❌ Нет |
| Зритель (viewer) | ✅ Да | ❌ Нет | ❌ Нет | ❌ Нет |
| LMS Students | ❌ Нет | ❌ Нет | ❌ Нет | ❌ Нет |

### Public (Публичный)

| Группа/Роль | Просмотр | Редактирование | Управление доступом | Смена статуса |
|-------------|----------|----------------|---------------------|---------------|
| LMS Admins | ✅ Да | ✅ Да | ✅ Да | ✅ Да |
| LMS Experts | ✅ Да | ✅ Да | ❌ Нет | ❌ Нет |
| Автор (author) | ✅ Да | ✅ Да | ✅ Да | ✅ Да |
| Редактор (editor) | ✅ Да | ✅ Да | ❌ Нет | ❌ Нет |
| Зритель (viewer) | ✅ Да | ❌ Нет | ❌ Нет | ❌ Нет |
| LMS Students | ✅ Да | ❌ Нет | ❌ Нет | ❌ Нет |
| **Все (анонимы)** | ✅ Да | ❌ Нет | ❌ Нет | ❌ Нет |

### Private (Приватный)

| Группа/Роль | Просмотр | Редактирование | Управление доступом | Смена статуса |
|-------------|----------|----------------|---------------------|---------------|
| LMS Admins | ✅ Да | ✅ Да | ✅ Да | ✅ Да |
| LMS Experts | ❌ Нет* | ❌ Нет* | ❌ Нет | ❌ Нет |
| Автор (author) | ✅ Да | ✅ Да | ✅ Да | ✅ Да |
| Редактор (editor) | ✅ Да | ✅ Да | ❌ Нет | ❌ Нет |
| Зритель (viewer) | ✅ Да | ❌ Нет | ❌ Нет | ❌ Нет |
| LMS Students | ❌ Нет | ❌ Нет | ❌ Нет | ❌ Нет |

\* *Эксперты видят private тесты только если им дали явные права (author/editor/viewer)*

---

## 4. Как это работает в коде

### TestPermissionHelper - основной класс

Путь: `core/components/testsystem/helpers/TestPermissionHelper.php`

#### Методы проверки групп

```php
// Проверка группы LMS Admins
TestPermissionHelper::isAdministrator($modx, $userId);

// Проверка группы LMS Experts
TestPermissionHelper::isExpert($modx, $userId);

// Проверка LMS Admins или LMS Experts
TestPermissionHelper::isAdminOrExpert($modx, $userId);
```

#### Методы проверки ролей

```php
// Проверка создателя теста (created_by)
TestPermissionHelper::isTestCreator($modx, $testId, $userId);

// Получение роли пользователя (author, editor, viewer или null)
$role = TestPermissionHelper::getUserRole($modx, $testId, $userId);
```

#### Методы проверки прав

```php
// Может ли видеть тест
$canView = TestPermissionHelper::canView($modx, $testId, $publicationStatus, $userId);

// Может ли редактировать тест
$canEdit = TestPermissionHelper::canEdit($modx, $testId, $publicationStatus, $userId);

// Может ли управлять доступом (добавлять/удалять пользователей)
$canManageAccess = TestPermissionHelper::canManageAccess($modx, $testId, $userId);

// Может ли менять статус публикации
$canChangeStatus = TestPermissionHelper::canChangeStatus($modx, $testId, $userId);
```

#### Методы управления доступом

```php
// Дать доступ пользователю
TestPermissionHelper::grantAccess($modx, $testId, $targetUserId, 'editor', $grantedBy);

// Удалить доступ
TestPermissionHelper::revokeAccess($modx, $testId, $targetUserId);

// Получить список пользователей с доступом
$users = TestPermissionHelper::getTestUsers($modx, $testId);
```

---

## 5. Использование в сниппетах

### getTestInfoBatch - показ тестов с учетом прав

```
[[!getTestInfoBatch?
    &parents=`56`
    &depth=`3`
    &showAllStatuses=`1`
    &tpl=`testCard`
]]
```

**Параметры:**

- `&showAllStatuses=1` - показывать все статусы (draft, public, private)
  - Работает только для LMS Admins и LMS Experts
  - Для LMS Students всегда показываются только public тесты

**В чанке testCard доступны плейсхолдеры:**

```html
<div class="test-card">
    <h3>[[+pagetitle]]</h3>

    <!-- Показываем статус только админам/экспертам -->
    [[+isAdminOrExpert:is=`1`:then=`
        <span class="badge badge-[[+publication_status]]">[[+publication_status]]</span>
    `]]

    <!-- Кнопка редактирования -->
    [[+canEdit:is=`1`:then=`
        <a href="[[+url]]?action=edit" class="btn btn-primary">Редактировать</a>
    `]]

    <!-- Кнопка управления доступом -->
    [[+canManageAccess:is=`1`:then=`
        <a href="[[+url]]?action=manage-access" class="btn btn-secondary">Управление доступом</a>
    `]]

    <!-- Кнопка смены статуса -->
    [[+canChangeStatus:is=`1`:then=`
        <a href="[[+url]]?action=change-status" class="btn btn-warning">Изменить статус</a>
    `]]
</div>
```

**Доступные плейсхолдеры прав:**

- `[[+canView]]` - 1 или 0
- `[[+canEdit]]` - 1 или 0
- `[[+canManageAccess]]` - 1 или 0
- `[[+canChangeStatus]]` - 1 или 0
- `[[+userRole]]` - author, editor, viewer или none
- `[[+isCreator]]` - 1 или 0
- `[[+isAdminOrExpert]]` - 1 или 0
- `[[+publication_status]]` - draft, public, private

---

## 6. Структура базы данных

### Таблица modx_test_tests

Добавлены поля:

```sql
created_by INT(11) UNSIGNED NULL DEFAULT NULL COMMENT 'ID создателя теста'
updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Дата последнего обновления'
```

### Таблица modx_test_permissions

Новая таблица для управления правами:

```sql
CREATE TABLE `modx_test_permissions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `test_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID теста',
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя',
    `role` ENUM('author', 'editor', 'viewer') NOT NULL DEFAULT 'viewer',
    `granted_by` INT(11) UNSIGNED NOT NULL COMMENT 'Кто предоставил доступ',
    `granted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_test_user` (`test_id`, `user_id`)
);
```

---

## 7. Примеры использования

### Пример 1: Проверка прав в процессоре

```php
<?php
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

$testId = (int)$_POST['test_id'];
$userId = $modx->user->id;

// Получаем информацию о тесте
$test = $modx->getObject('TestTest', $testId);
if (!$test) {
    return ResponseHelper::error('Тест не найден');
}

$publicationStatus = $test->get('publication_status');

// Проверяем права на редактирование
if (!TestPermissionHelper::canEdit($modx, $testId, $publicationStatus, $userId)) {
    return ResponseHelper::error('У вас нет прав на редактирование этого теста');
}

// Выполняем редактирование...
```

### Пример 2: Добавление прав пользователю

```php
<?php
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

$testId = 123;
$targetUserId = 456; // Пользователь, которому дадим доступ
$currentUserId = $modx->user->id;

// Проверяем, может ли текущий пользователь управлять доступом
if (!TestPermissionHelper::canManageAccess($modx, $testId, $currentUserId)) {
    return ResponseHelper::error('У вас нет прав на управление доступом');
}

// Даем права редактора
$result = TestPermissionHelper::grantAccess($modx, $testId, $targetUserId, 'editor', $currentUserId);

if ($result) {
    return ResponseHelper::success('Права успешно предоставлены');
} else {
    return ResponseHelper::error('Ошибка при предоставлении прав');
}
```

### Пример 3: Список пользователей с доступом

```php
<?php
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

$testId = 123;
$userId = $modx->user->id;

// Проверяем права на просмотр списка
if (!TestPermissionHelper::canManageAccess($modx, $testId, $userId)) {
    return ResponseHelper::error('У вас нет прав на просмотр списка пользователей');
}

// Получаем список
$users = TestPermissionHelper::getTestUsers($modx, $testId);

foreach ($users as $user) {
    echo $user['username'] . ' - ' . $user['role'] . ' (дал доступ: ' . $user['granted_by'] . ')';
}
```

---

## 8. Миграция базы данных

Для установки системы прав выполните SQL миграцию:

**Файл:** `core/components/testsystem/migrations/003_test_permissions.sql`

```bash
# Через командную строку
mysql -u username -p database_name < core/components/testsystem/migrations/003_test_permissions.sql

# Или через phpMyAdmin:
# 1. Откройте вкладку SQL
# 2. Скопируйте содержимое файла 003_test_permissions.sql
# 3. Нажмите "Выполнить"
```

---

## 9. Настройка групп пользователей

Если вы хотите изменить названия групп, отредактируйте константы в `TestPermissionHelper.php`:

```php
class TestPermissionHelper
{
    const GROUP_ADMINISTRATOR = 'LMS Admins';  // Ваша группа админов
    const GROUP_EXPERT = 'LMS Experts';        // Ваша группа экспертов
```

---

## 10. Диагностика

### Проверка прав пользователя

```php
<?php
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

$userId = 2; // или $modx->user->id
$testId = 123;

echo "User ID: $userId\n";
echo "Test ID: $testId\n\n";

echo "isAdministrator: " . (TestPermissionHelper::isAdministrator($modx, $userId) ? 'ДА' : 'НЕТ') . "\n";
echo "isExpert: " . (TestPermissionHelper::isExpert($modx, $userId) ? 'ДА' : 'НЕТ') . "\n";
echo "isAdminOrExpert: " . (TestPermissionHelper::isAdminOrExpert($modx, $userId) ? 'ДА' : 'НЕТ') . "\n\n";

$test = $modx->getObject('TestTest', $testId);
$status = $test ? $test->get('publication_status') : 'unknown';

echo "Publication Status: $status\n\n";

echo "canView: " . (TestPermissionHelper::canView($modx, $testId, $status, $userId) ? 'ДА' : 'НЕТ') . "\n";
echo "canEdit: " . (TestPermissionHelper::canEdit($modx, $testId, $status, $userId) ? 'ДА' : 'НЕТ') . "\n";
echo "canManageAccess: " . (TestPermissionHelper::canManageAccess($modx, $testId, $userId) ? 'ДА' : 'НЕТ') . "\n";
echo "canChangeStatus: " . (TestPermissionHelper::canChangeStatus($modx, $testId, $userId) ? 'ДА' : 'НЕТ') . "\n";

$role = TestPermissionHelper::getUserRole($modx, $testId, $userId);
echo "User Role: " . ($role ?? 'none') . "\n";
```

---

## Заключение

Система прав доступа TestSystem обеспечивает гибкое управление доступом к тестам на трех уровнях:

1. **Глобальный уровень** - группы MODX (LMS Admins, LMS Experts, LMS Students)
2. **Уровень теста** - роли (author, editor, viewer)
3. **Уровень контента** - статусы публикации (draft, public, private)

Это позволяет реализовать сложные сценарии доступа, аналогичные системам LMS (Moodle, Canvas).
