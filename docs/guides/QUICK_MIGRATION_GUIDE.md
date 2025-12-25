# Быстрая миграция для существующей таблицы modx_test_permissions

## Проблема
Таблица `modx_test_permissions` уже существует, но в ней нет полей `can_view` и `expires_at`.

## Решение

### Шаг 1: Проверьте текущую структуру

Выполните в phpMyAdmin:

```sql
SHOW COLUMNS FROM modx_test_permissions;
```

Вы увидите список полей. Скорее всего это:
- id
- test_id
- user_id
- granted_by
- can_edit
- granted_at

---

### Шаг 2: Добавьте поле can_view

**Выполните ТОЛЬКО ОДИН из этих запросов:**

#### Вариант A: Если поля can_view НЕТ
```sql
ALTER TABLE `modx_test_permissions`
    ADD COLUMN `can_view` TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'Может просматривать тест'
    AFTER `granted_by`;
```

#### Вариант B: Если поле can_view УЖЕ ЕСТЬ
Пропустите этот шаг.

---

### Шаг 3: Добавьте поле expires_at

**Выполните ТОЛЬКО ОДИН из этих запросов:**

#### Вариант A: Если поля expires_at НЕТ
```sql
ALTER TABLE `modx_test_permissions`
    ADD COLUMN `expires_at` DATETIME DEFAULT NULL
    COMMENT 'Дата истечения доступа (опционально)'
    AFTER `granted_at`;
```

#### Вариант B: Если поле expires_at УЖЕ ЕСТЬ
Пропустите этот шаг.

---

### Шаг 4: Обновите существующие записи

```sql
-- Если у пользователя есть can_edit=1, то должен быть и can_view=1
UPDATE `modx_test_permissions`
SET `can_view` = 1
WHERE `can_edit` = 1;
```

---

### Шаг 5: Добавьте индексы (опционально, для производительности)

Выполняйте по одному запросу. Если получите ошибку "Duplicate key name" - это нормально, пропускайте.

```sql
ALTER TABLE `modx_test_permissions`
    ADD INDEX `idx_test_view` (`test_id`, `can_view`);
```

```sql
ALTER TABLE `modx_test_permissions`
    ADD INDEX `idx_expires` (`expires_at`);
```

```sql
ALTER TABLE `modx_test_permissions`
    ADD INDEX `idx_user_view` (`user_id`, `can_view`);
```

```sql
ALTER TABLE `modx_test_permissions`
    ADD INDEX `idx_user_edit` (`user_id`, `can_edit`);
```

---

### Шаг 6: Проверьте результат

```sql
SHOW COLUMNS FROM modx_test_permissions;
```

**Ожидаемый результат - должны быть все эти поля:**
- id
- test_id
- user_id
- granted_by
- **can_view** ← новое
- can_edit
- granted_at
- **expires_at** ← новое

---

## Готово! ✅

После этого можно обновлять PHP и JS файлы согласно DEPLOYMENT_GUIDE_PERMISSIONS.md

---

## Если что-то пошло не так

### Ошибка: "Duplicate column name 'can_view'"
**Решение:** Поле уже существует, пропустите добавление этого поля.

### Ошибка: "Duplicate key name 'idx_test_view'"
**Решение:** Индекс уже существует, пропустите добавление этого индекса.

### Ошибка: Синтаксическая ошибка
**Решение:** Копируйте запросы точно как написано, без изменений. Убедитесь что используете кавычки `` ` `` (backtick), а не обычные кавычки.

---

## Откат изменений (если нужно)

```sql
-- Удалить добавленные поля
ALTER TABLE `modx_test_permissions` DROP COLUMN `can_view`;
ALTER TABLE `modx_test_permissions` DROP COLUMN `expires_at`;

-- Удалить добавленные индексы
ALTER TABLE `modx_test_permissions` DROP INDEX `idx_test_view`;
ALTER TABLE `modx_test_permissions` DROP INDEX `idx_expires`;
ALTER TABLE `modx_test_permissions` DROP INDEX `idx_user_view`;
ALTER TABLE `modx_test_permissions` DROP INDEX `idx_user_edit`;
```
