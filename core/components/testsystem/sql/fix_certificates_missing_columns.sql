-- =============================================
-- Миграция: Добавление недостающих колонок в test_certificates
-- Дата: 2025-12-30
-- Проблема: Сниппет myCertificates не отображал сертификаты из-за
--           отсутствия колонок path_id и status в таблице
-- =============================================

-- Проверяем и добавляем колонку path_id
SET @table_name = 'modx_test_certificates';

-- Добавляем path_id если не существует
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = @table_name
     AND COLUMN_NAME = 'path_id') = 0,
    CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `path_id` INT(11) UNSIGNED NULL COMMENT ''ID траектории обучения'' AFTER `entity_id`'),
    'SELECT ''Column path_id already exists'''
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Добавляем status если не существует
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = @table_name
     AND COLUMN_NAME = 'status') = 0,
    CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `status` ENUM(''active'', ''expired'', ''revoked'') DEFAULT ''active'' COMMENT ''Статус сертификата'' AFTER `grade`'),
    'SELECT ''Column status already exists'''
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Добавляем индекс для path_id если не существует
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = @table_name
     AND INDEX_NAME = 'idx_path') = 0,
    CONCAT('ALTER TABLE `', @table_name, '` ADD INDEX `idx_path` (`path_id`)'),
    'SELECT ''Index idx_path already exists'''
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Добавляем индекс для status если не существует
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = @table_name
     AND INDEX_NAME = 'idx_status') = 0,
    CONCAT('ALTER TABLE `', @table_name, '` ADD INDEX `idx_status` (`status`)'),
    'SELECT ''Index idx_status already exists'''
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Устанавливаем status = 'active' для всех записей где он NULL или пустой
UPDATE `modx_test_certificates`
SET `status` = 'active'
WHERE `status` IS NULL OR `status` = '';

-- Добавляем внешний ключ для path_id если не существует
-- Сначала проверяем, существует ли таблица learning_paths
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'modx_test_learning_paths') > 0
    AND
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = @table_name
     AND CONSTRAINT_NAME = 'fk_cert_path') = 0,
    CONCAT('ALTER TABLE `', @table_name, '` ADD CONSTRAINT `fk_cert_path` FOREIGN KEY (`path_id`) REFERENCES `modx_test_learning_paths` (`id`) ON DELETE SET NULL'),
    'SELECT ''FK fk_cert_path already exists or learning_paths table not found'''
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Выводим результат
SELECT 'Migration completed successfully' as result;
SELECT
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'modx_test_certificates' AND COLUMN_NAME = 'path_id') as path_id_exists,
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'modx_test_certificates' AND COLUMN_NAME = 'status') as status_exists;
