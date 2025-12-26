-- ============================================
-- Learning Paths v2: Templates & Cloning
-- Миграция для поддержки шаблонов и клонирования
-- ============================================

-- 1. Добавляем поле is_template в таблицу траекторий
-- Если траектория помечена как шаблон, её можно клонировать
ALTER TABLE `modx_test_learning_paths`
ADD COLUMN IF NOT EXISTS `is_template` TINYINT(1) DEFAULT 0
COMMENT 'Является шаблоном для клонирования' AFTER `is_public`;

-- 2. Добавляем поле parent_id для отслеживания откуда клонировано
ALTER TABLE `modx_test_learning_paths`
ADD COLUMN IF NOT EXISTS `parent_id` INT(11) UNSIGNED DEFAULT NULL
COMMENT 'ID родительской траектории (откуда клонировано)' AFTER `is_template`;

-- 3. Добавляем внешний ключ для parent_id
ALTER TABLE `modx_test_learning_paths`
ADD CONSTRAINT `fk_path_parent` FOREIGN KEY (`parent_id`)
    REFERENCES `modx_test_learning_paths` (`id`) ON DELETE SET NULL;

-- 4. Добавляем индекс для поиска шаблонов
ALTER TABLE `modx_test_learning_paths`
ADD INDEX `idx_template` (`is_template`, `status`);

-- 5. Добавляем индекс для поиска клонов
ALTER TABLE `modx_test_learning_paths`
ADD INDEX `idx_parent` (`parent_id`);

-- 6. Добавляем дедлайн в таблицу записей на траектории
ALTER TABLE `modx_test_learning_path_enrollments`
ADD COLUMN IF NOT EXISTS `deadline` DATETIME DEFAULT NULL
COMMENT 'Срок выполнения траектории' AFTER `expires_at`;

-- 7. Добавляем индекс для дедлайнов
ALTER TABLE `modx_test_learning_path_enrollments`
ADD INDEX `idx_deadline` (`deadline`);

-- ============================================
-- Примечания по использованию
-- ============================================

/*
ШАБЛОНЫ ТРАЕКТОРИЙ:
- is_template = 1: траектория может быть клонирована
- При клонировании создаётся новая траектория с parent_id = ID шаблона
- Клонируются все шаги траектории

СЦЕНАРИИ:
1. Админ создаёт шаблон "Онбординг менеджера" (is_template=1)
2. Админ клонирует шаблон для сотрудника Иванова
3. При необходимости редактирует клон (индивидуальная настройка)
4. Назначает дедлайн при записи сотрудника

ЗАПРОС ШАБЛОНОВ:
SELECT * FROM modx_test_learning_paths
WHERE is_template = 1 AND status = 'published';

ЗАПРОС КЛОНОВ ШАБЛОНА:
SELECT * FROM modx_test_learning_paths
WHERE parent_id = :template_id;

НАЗНАЧЕННЫЕ С ДЕДЛАЙНОМ:
SELECT lp.*, e.deadline, e.enrolled_by, u.fullname as enrolled_by_name
FROM modx_test_learning_path_enrollments e
JOIN modx_test_learning_paths lp ON lp.id = e.path_id
LEFT JOIN modx_user_attributes u ON u.internalKey = e.enrolled_by
WHERE e.user_id = :user_id
AND e.deadline IS NOT NULL
AND e.is_active = 1;
*/
