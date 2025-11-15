-- ============================================
-- Test System Learning Materials Tables
-- Sprint 9: Учебные материалы
-- ============================================

-- Таблица учебных материалов
CREATE TABLE IF NOT EXISTS `modx_test_learning_materials` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL COMMENT 'Название материала',
    `description` TEXT COMMENT 'Краткое описание',
    `category_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Категория (связь с test_categories)',
    `content_type` ENUM('text', 'video', 'document', 'presentation', 'interactive') DEFAULT 'text' COMMENT 'Тип контента',
    `status` ENUM('draft', 'published', 'archived') DEFAULT 'draft' COMMENT 'Статус публикации',
    `sort_order` INT(11) DEFAULT 0 COMMENT 'Порядок сортировки',
    `duration_minutes` INT(11) DEFAULT NULL COMMENT 'Примерная длительность изучения (мин)',
    `created_by` INT(11) UNSIGNED NOT NULL COMMENT 'Создатель (user_id)',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `published_at` DATETIME DEFAULT NULL COMMENT 'Дата публикации',
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category_id`),
    KEY `idx_created_by` (`created_by`),
    KEY `idx_status` (`status`),
    KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Учебные материалы';

-- Таблица контента материалов (блоки контента)
CREATE TABLE IF NOT EXISTS `modx_test_learning_content` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `material_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID материала',
    `block_type` ENUM('text', 'heading', 'image', 'video', 'code', 'quote', 'list') DEFAULT 'text' COMMENT 'Тип блока',
    `content_html` LONGTEXT COMMENT 'HTML контент блока',
    `content_data` TEXT COMMENT 'Дополнительные данные (JSON)',
    `sort_order` INT(11) DEFAULT 0 COMMENT 'Порядок блока',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_material` (`material_id`),
    KEY `idx_sort` (`sort_order`),
    CONSTRAINT `fk_content_material` FOREIGN KEY (`material_id`)
        REFERENCES `modx_test_learning_materials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Контент учебных материалов';

-- Таблица вложений (файлы)
CREATE TABLE IF NOT EXISTS `modx_test_learning_attachments` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `material_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID материала',
    `title` VARCHAR(255) NOT NULL COMMENT 'Название файла',
    `file_path` VARCHAR(500) NOT NULL COMMENT 'Путь к файлу',
    `file_type` VARCHAR(100) COMMENT 'MIME тип',
    `file_size` INT(11) UNSIGNED COMMENT 'Размер файла (байт)',
    `attachment_type` ENUM('document', 'video', 'image', 'other') DEFAULT 'document' COMMENT 'Тип вложения',
    `is_primary` TINYINT(1) DEFAULT 0 COMMENT 'Основной файл материала',
    `download_count` INT(11) DEFAULT 0 COMMENT 'Количество скачиваний',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_material` (`material_id`),
    KEY `idx_type` (`attachment_type`),
    CONSTRAINT `fk_attachment_material` FOREIGN KEY (`material_id`)
        REFERENCES `modx_test_learning_materials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Вложения к учебным материалам';

-- Таблица прогресса пользователей по материалам
CREATE TABLE IF NOT EXISTS `modx_test_material_progress` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя',
    `material_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID материала',
    `status` ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started' COMMENT 'Статус прохождения',
    `progress_pct` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Процент прохождения (0-100)',
    `time_spent_minutes` INT(11) DEFAULT 0 COMMENT 'Время изучения (минуты)',
    `started_at` DATETIME DEFAULT NULL COMMENT 'Дата начала',
    `completed_at` DATETIME DEFAULT NULL COMMENT 'Дата завершения',
    `last_accessed_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Последний доступ',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_user_material` (`user_id`, `material_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_material` (`material_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_progress_material` FOREIGN KEY (`material_id`)
        REFERENCES `modx_test_learning_materials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Прогресс пользователей по материалам';

-- Таблица связи материалов с тестами (опционально)
CREATE TABLE IF NOT EXISTS `modx_test_material_test_links` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `material_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID материала',
    `test_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID теста',
    `link_type` ENUM('prerequisite', 'recommended', 'related') DEFAULT 'related' COMMENT 'Тип связи',
    `sort_order` INT(11) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_material_test` (`material_id`, `test_id`),
    KEY `idx_material` (`material_id`),
    KEY `idx_test` (`test_id`),
    CONSTRAINT `fk_link_material` FOREIGN KEY (`material_id`)
        REFERENCES `modx_test_learning_materials` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_link_test` FOREIGN KEY (`test_id`)
        REFERENCES `modx_test_tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Связи материалов с тестами';

-- Таблица тегов для материалов (для поиска и фильтрации)
CREATE TABLE IF NOT EXISTS `modx_test_material_tags` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `material_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID материала',
    `tag` VARCHAR(50) NOT NULL COMMENT 'Тег',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_material` (`material_id`),
    KEY `idx_tag` (`tag`),
    CONSTRAINT `fk_tag_material` FOREIGN KEY (`material_id`)
        REFERENCES `modx_test_learning_materials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Теги учебных материалов';

-- ============================================
-- Индексы для производительности
-- ============================================

-- Составной индекс для быстрого поиска опубликованных материалов категории
ALTER TABLE `modx_test_learning_materials`
    ADD INDEX `idx_category_status_sort` (`category_id`, `status`, `sort_order`);

-- Индекс для поиска материалов конкретного автора
ALTER TABLE `modx_test_learning_materials`
    ADD INDEX `idx_created_status` (`created_by`, `status`);

-- Полнотекстовый поиск по названию и описанию
ALTER TABLE `modx_test_learning_materials`
    ADD FULLTEXT INDEX `idx_fulltext_search` (`title`, `description`);

-- ============================================
-- Комментарии к таблицам
-- ============================================

-- Примечания:
-- 1. content_type определяет основной тип материала
-- 2. learning_content позволяет создавать структурированный контент из блоков
-- 3. attachments хранит файлы (PDF, видео, презентации и т.д.)
-- 4. material_progress отслеживает прогресс каждого студента
-- 5. material_test_links связывает материалы с тестами для создания траекторий обучения
-- 6. material_tags позволяет организовать материалы по тегам для поиска
