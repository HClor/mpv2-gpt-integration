-- ============================================
-- Migration for existing modx_test_permissions table
-- Добавление полей can_view и expires_at
-- ============================================

-- Шаг 1: Проверьте текущую структуру таблицы
-- Выполните этот запрос и посмотрите какие поля уже есть:

SHOW COLUMNS FROM modx_test_permissions;

-- ============================================
-- Шаг 2: Добавление поля can_view
-- ============================================

-- Проверьте есть ли поле can_view в результатах выше
-- Если НЕТ - выполните этот запрос:

ALTER TABLE `modx_test_permissions`
    ADD COLUMN `can_view` TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'Может просматривать тест'
    AFTER `granted_by`;

-- Если получили ошибку "Duplicate column name 'can_view'"
-- значит поле уже существует, пропустите этот шаг

-- ============================================
-- Шаг 3: Добавление поля expires_at
-- ============================================

-- Проверьте есть ли поле expires_at в результатах SHOW COLUMNS
-- Если НЕТ - выполните этот запрос:

ALTER TABLE `modx_test_permissions`
    ADD COLUMN `expires_at` DATETIME DEFAULT NULL
    COMMENT 'Дата истечения доступа (опционально)'
    AFTER `granted_at`;

-- Если получили ошибку "Duplicate column name 'expires_at'"
-- значит поле уже существует, пропустите этот шаг

-- ============================================
-- Шаг 4: Обновление существующих записей
-- ============================================

-- Если поле can_view было добавлено, обновите существующие записи:
-- У всех кто имеет can_edit=1, должен быть и can_view=1

UPDATE `modx_test_permissions`
SET `can_view` = 1
WHERE `can_edit` = 1 AND (`can_view` = 0 OR `can_view` IS NULL);

-- ============================================
-- Шаг 5: Добавление индексов (если их нет)
-- ============================================

-- Проверяем существующие индексы
SHOW INDEXES FROM modx_test_permissions;

-- Добавляем индексы для can_view и expires_at
-- Если получите ошибку "Duplicate key name" - пропустите

ALTER TABLE `modx_test_permissions`
    ADD INDEX `idx_test_view` (`test_id`, `can_view`);

ALTER TABLE `modx_test_permissions`
    ADD INDEX `idx_expires` (`expires_at`);

ALTER TABLE `modx_test_permissions`
    ADD INDEX `idx_user_view` (`user_id`, `can_view`);

ALTER TABLE `modx_test_permissions`
    ADD INDEX `idx_user_edit` (`user_id`, `can_edit`);

-- ============================================
-- Шаг 6: Проверка результата
-- ============================================

-- Выполните этот запрос чтобы убедиться что все поля на месте:

SHOW COLUMNS FROM modx_test_permissions;

-- Ожидаемые поля:
-- id, test_id, user_id, granted_by, can_view, can_edit, granted_at, expires_at

-- Проверьте данные:
SELECT * FROM modx_test_permissions LIMIT 5;
