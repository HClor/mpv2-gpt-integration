-- ============================================
-- МИГРАЦИЯ НА СИСТЕМУ КАТЕГОРИЙ (ВАРИАНТ B)
-- Дата: 2025-12-04
-- ============================================

-- ВНИМАНИЕ: Создайте backup перед выполнением!
-- mysqldump -u root -p lmixru_mpv2 > backup_$(date +%Y%m%d_%H%M%S).sql

-- ============================================
-- 1. ПРОВЕРКА СУЩЕСТВУЮЩИХ ТАБЛИЦ
-- ============================================

-- Проверяем таблицу категорий
SELECT 'Проверка modx_test_categories...' as 'Шаг';
SELECT * FROM modx_test_categories ORDER BY name;

-- ============================================
-- 2. ДОБАВЛЕНИЕ ПОЛЕЙ
-- ============================================

-- Добавляем category_id в таблицу тестов
SELECT 'Добавление category_id в modx_test_tests...' as 'Шаг';

ALTER TABLE modx_test_tests
ADD COLUMN IF NOT EXISTS `category_id` INT(11) NULL COMMENT 'ID категории из modx_test_categories'
AFTER `description`;

ALTER TABLE modx_test_tests
ADD INDEX IF NOT EXISTS `idx_category` (`category_id`);

-- Добавляем category_id в учебные материалы
SELECT 'Добавление category_id в modx_test_learning_materials...' as 'Шаг';

-- Проверяем существует ли таблица
SELECT COUNT(*) as 'Таблица modx_test_learning_materials существует'
FROM information_schema.tables
WHERE table_schema = DATABASE()
AND table_name = 'modx_test_learning_materials';

-- Если таблица существует, добавляем поле
SET @table_exists = (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
    AND table_name = 'modx_test_learning_materials'
);

SET @sql = IF(@table_exists > 0,
    'ALTER TABLE modx_test_learning_materials
     ADD COLUMN IF NOT EXISTS `category_id` INT(11) NULL COMMENT ''ID категории''
     AFTER `id`',
    'SELECT "Таблица modx_test_learning_materials не найдена, пропускаем" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 3. СОЗДАНИЕ ТАБЛИЦЫ ЭКСПЕРТОВ КАТЕГОРИЙ
-- ============================================

SELECT 'Создание modx_test_category_experts...' as 'Шаг';

CREATE TABLE IF NOT EXISTS `modx_test_category_experts` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT(11) NOT NULL COMMENT 'ID категории',
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID эксперта',
    `assigned_by` INT(11) UNSIGNED NOT NULL COMMENT 'Кто назначил',
    `can_manage_tests` TINYINT(1) DEFAULT 1 COMMENT 'Может управлять тестами',
    `can_manage_questions` TINYINT(1) DEFAULT 1 COMMENT 'Может управлять вопросами',
    `can_approve` TINYINT(1) DEFAULT 0 COMMENT 'Может утверждать контент',
    `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_category_expert` (`category_id`, `user_id`),
    INDEX `idx_category` (`category_id`),
    INDEX `idx_user` (`user_id`),
    CONSTRAINT `fk_category_expert_category`
        FOREIGN KEY (`category_id`)
        REFERENCES `modx_test_categories` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Назначение экспертов на категории';

-- ============================================
-- 4. МИГРАЦИЯ ДАННЫХ: Назначение категорий тестам
-- ============================================

SELECT 'Миграция данных: назначение категорий тестам на основе ресурсов...' as 'Шаг';

-- Создаем временное сопоставление parent -> category
-- Сначала нужно создать категории для существующих родителей

-- Находим уникальные родительские ресурсы
INSERT IGNORE INTO modx_test_categories (name, description, sort_order)
SELECT DISTINCT
    parent_r.pagetitle as name,
    CONCAT('Категория из ресурса MODX (', parent_r.id, ')') as description,
    parent_r.menuindex as sort_order
FROM modx_site_content parent_r
WHERE parent_r.id IN (
    SELECT DISTINCT r.parent
    FROM modx_test_tests t
    JOIN modx_site_content r ON r.id = t.resource_id
    WHERE t.resource_id IS NOT NULL AND r.parent IS NOT NULL AND r.parent > 0
)
AND parent_r.id NOT IN (
    -- Пропускаем если категория с таким же названием уже есть
    SELECT id FROM modx_test_categories WHERE 1=0
);

-- Обновляем category_id в тестах на основе parent ресурса
UPDATE modx_test_tests t
JOIN modx_site_content r ON r.id = t.resource_id
JOIN modx_site_content parent_r ON parent_r.id = r.parent
JOIN modx_test_categories c ON c.name = parent_r.pagetitle
SET t.category_id = c.id
WHERE t.resource_id IS NOT NULL
AND r.parent IS NOT NULL
AND r.parent > 0
AND t.category_id IS NULL;

-- ============================================
-- 5. СОЗДАНИЕ КАТЕГОРИЙ "БЕЗ КАТЕГОРИИ" и "ПРИВАТНЫЕ"
-- ============================================

SELECT 'Создание служебных категорий...' as 'Шаг';

INSERT IGNORE INTO modx_test_categories (id, name, description, sort_order)
VALUES
    (0, 'Без категории', 'Тесты без назначенной категории', 999);

-- ============================================
-- 6. ФИНАЛЬНАЯ ПРОВЕРКА
-- ============================================

SELECT 'Финальная проверка...' as 'Шаг';

-- Количество тестов по категориям
SELECT
    COALESCE(c.name, 'Без категории') as 'Категория',
    COUNT(t.id) as 'Количество тестов',
    c.id as 'Category ID'
FROM modx_test_tests t
LEFT JOIN modx_test_categories c ON c.id = t.category_id
WHERE t.is_active = 1
GROUP BY c.id, c.name
ORDER BY c.name;

-- Тесты БЕЗ категории
SELECT
    t.id,
    t.title as 'Тест без категории',
    t.publication_status
FROM modx_test_tests t
WHERE t.is_active = 1 AND t.category_id IS NULL
ORDER BY t.title;

-- ============================================
-- ЗАВЕРШЕНО
-- ============================================

SELECT '✓ Миграция завершена!' as 'Статус';
SELECT 'Следующий шаг: назначьте категории тестам без category_id' as 'Действие';
