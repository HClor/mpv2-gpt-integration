-- ===============================================
-- Миграция #3: Система управления правами доступа к тестам
-- Дата: 2025-11-14
-- Описание: Добавляет таблицу прав доступа и поле created_by
-- ===============================================

-- 1. Создаем таблицу прав доступа к тестам
CREATE TABLE IF NOT EXISTS `modx_test_permissions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `test_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID теста из test_tests',
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя MODX',
    `role` ENUM('author', 'editor', 'viewer') NOT NULL DEFAULT 'viewer' COMMENT 'Роль: автор, редактор, просмотр',
    `granted_by` INT(11) UNSIGNED NOT NULL COMMENT 'Кто предоставил доступ',
    `granted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Когда предоставлен доступ',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_test_user` (`test_id`, `user_id`),
    KEY `idx_test` (`test_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Права доступа к тестам';

-- 2. Добавляем поле created_by в test_tests (если его нет)
ALTER TABLE `modx_test_tests`
ADD COLUMN `created_by` INT(11) UNSIGNED NULL DEFAULT NULL COMMENT 'ID создателя теста'
AFTER `is_active`;

-- 3. Добавляем индекс для created_by
ALTER TABLE `modx_test_tests`
ADD KEY `idx_created_by` (`created_by`);

-- 4. Добавляем поле updated_at для отслеживания изменений
ALTER TABLE `modx_test_tests`
ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Дата последнего обновления'
AFTER `created_by`;

-- 5. Устанавливаем текущего пользователя как создателя для существующих тестов
-- (выполнить вручную через phpMyAdmin если нужно)
-- UPDATE `modx_test_tests` SET `created_by` = 1 WHERE `created_by` IS NULL;

-- ===============================================
-- Справка по ролям:
--
-- author  - Создатель теста, полный доступ:
--           - Редактирование теста и вопросов
--           - Управление доступом (добавление/удаление пользователей)
--           - Изменение статуса публикации
--
-- editor  - Редактор, может редактировать:
--           - Редактирование вопросов
--           - Изменение настроек теста
--           - НЕ может: управлять доступом, менять статус
--
-- viewer  - Зритель, только просмотр:
--           - Просмотр теста
--           - Прохождение теста
--           - НЕ может: редактировать
-- ===============================================
