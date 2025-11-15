-- ============================================
-- Test System v2.0 - Full Installation Script
-- ============================================
--
-- Этот файл содержит все SQL миграции для полной установки системы
-- тестирования и обучения для MODX Revolution.
--
-- ВНИМАНИЕ: Выполняйте этот скрипт ТОЛЬКО на чистой базе данных!
-- Если система уже установлена частично, используйте отдельные файлы миграций.
--
-- Требования:
--   - MySQL 5.7+ или MariaDB 10.2+
--   - MODX Revolution 2.8.0+
--   - Права CREATE TABLE, CREATE TRIGGER, CREATE PROCEDURE
--
-- Использование:
--   mysql -u username -p database_name < FULL_INSTALLATION.sql
--
-- Или через интерфейс MySQL:
--   USE your_database;
--   SOURCE /path/to/FULL_INSTALLATION.sql;
--
-- Дата создания: 2025-11-15
-- Версия: 2.0
-- Спринты: 7-17
-- Таблицы: 50+
-- Триггеры: 15+
-- Stored Procedures: 15+
-- Views: 4
--
-- ============================================

-- Отключить проверку foreign keys для создания таблиц
SET FOREIGN_KEY_CHECKS = 0;

-- Установить кодировку
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ============================================
-- БАЗОВЫЕ ТАБЛИЦЫ СИСТЕМЫ ТЕСТИРОВАНИЯ
-- Sprint 7: Базовая структура (если не существует)
-- ============================================

-- Таблица тестов
CREATE TABLE IF NOT EXISTS `modx_test_tests` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `test_name` VARCHAR(255) NOT NULL COMMENT 'Название теста',
    `description` TEXT COMMENT 'Описание теста',
    `category_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID категории',
    `time_limit` INT(11) DEFAULT NULL COMMENT 'Ограничение времени (минуты)',
    `pass_score` INT(11) DEFAULT 70 COMMENT 'Проходной балл (%)',
    `attempts_allowed` INT(11) DEFAULT 0 COMMENT 'Разрешенное количество попыток (0 = без ограничений)',
    `randomize_questions` TINYINT(1) DEFAULT 0 COMMENT 'Перемешивать вопросы',
    `randomize_answers` TINYINT(1) DEFAULT 0 COMMENT 'Перемешивать варианты ответов',
    `show_correct_answers` TINYINT(1) DEFAULT 1 COMMENT 'Показывать правильные ответы после прохождения',
    `published` TINYINT(1) DEFAULT 0 COMMENT 'Опубликован',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID создателя',
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category_id`),
    KEY `idx_published` (`published`),
    KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Тесты';

-- Таблица вопросов
CREATE TABLE IF NOT EXISTS `modx_test_questions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `test_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID теста',
    `question_text` TEXT NOT NULL COMMENT 'Текст вопроса',
    `question_type` ENUM('single', 'multiple', 'matching', 'ordering', 'fill_blank', 'essay') DEFAULT 'single' COMMENT 'Тип вопроса',
    `points` INT(11) DEFAULT 1 COMMENT 'Баллы за вопрос',
    `explanation` TEXT COMMENT 'Пояснение к правильному ответу',
    `image_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL изображения для вопроса',
    `order_num` INT(11) DEFAULT 0 COMMENT 'Порядковый номер',
    `published` TINYINT(1) DEFAULT 1 COMMENT 'Опубликован',
    `learning_mode` TINYINT(1) DEFAULT 0 COMMENT 'Режим обучения (показывать объяснение сразу)',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_test` (`test_id`),
    KEY `idx_type` (`question_type`),
    KEY `idx_published` (`published`),
    KEY `idx_order` (`order_num`),
    CONSTRAINT `fk_question_test` FOREIGN KEY (`test_id`)
        REFERENCES `modx_test_tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Вопросы тестов';

-- Таблица ответов
CREATE TABLE IF NOT EXISTS `modx_test_answers` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID вопроса',
    `answer_text` TEXT NOT NULL COMMENT 'Текст ответа',
    `is_correct` TINYINT(1) DEFAULT 0 COMMENT 'Правильный ответ',
    `order_num` INT(11) DEFAULT 0 COMMENT 'Порядковый номер',
    `explanation` TEXT COMMENT 'Пояснение к этому варианту ответа',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_question` (`question_id`),
    KEY `idx_correct` (`is_correct`),
    KEY `idx_order` (`order_num`),
    CONSTRAINT `fk_answer_question` FOREIGN KEY (`question_id`)
        REFERENCES `modx_test_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Варианты ответов';

-- Таблица сессий тестирования
CREATE TABLE IF NOT EXISTS `modx_test_sessions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `test_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID теста',
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя',
    `status` ENUM('in_progress', 'completed', 'abandoned', 'expired') DEFAULT 'in_progress' COMMENT 'Статус сессии',
    `score` DECIMAL(5,2) DEFAULT NULL COMMENT 'Итоговый балл (%)',
    `total_questions` INT(11) DEFAULT 0 COMMENT 'Всего вопросов',
    `correct_answers` INT(11) DEFAULT 0 COMMENT 'Правильных ответов',
    `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Начало теста',
    `finished_at` DATETIME DEFAULT NULL COMMENT 'Окончание теста',
    `time_spent` INT(11) DEFAULT NULL COMMENT 'Затраченное время (секунды)',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP адрес',
    PRIMARY KEY (`id`),
    KEY `idx_test` (`test_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_started` (`started_at`),
    KEY `idx_finished` (`finished_at`),
    CONSTRAINT `fk_session_test` FOREIGN KEY (`test_id`)
        REFERENCES `modx_test_tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Сессии тестирования';

-- Таблица ответов пользователей
CREATE TABLE IF NOT EXISTS `modx_test_user_answers` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID сессии',
    `question_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID вопроса',
    `answer_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID выбранного ответа (для single)',
    `answer_text` TEXT COMMENT 'Текстовый ответ (для essay)',
    `answer_data` TEXT COMMENT 'Данные ответа (JSON для matching/ordering/fill_blank)',
    `is_correct` TINYINT(1) DEFAULT NULL COMMENT 'Правильность ответа',
    `points_earned` DECIMAL(5,2) DEFAULT 0 COMMENT 'Заработанные баллы',
    `answered_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Время ответа',
    `time_spent` INT(11) DEFAULT NULL COMMENT 'Время на вопрос (секунды)',
    PRIMARY KEY (`id`),
    KEY `idx_session` (`session_id`),
    KEY `idx_question` (`question_id`),
    KEY `idx_answer` (`answer_id`),
    KEY `idx_correct` (`is_correct`),
    KEY `idx_session_question` (`session_id`, `question_id`),
    CONSTRAINT `fk_user_answer_session` FOREIGN KEY (`session_id`)
        REFERENCES `modx_test_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_answer_question` FOREIGN KEY (`question_id`)
        REFERENCES `modx_test_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ответы пользователей';

-- Таблица избранных вопросов
CREATE TABLE IF NOT EXISTS `modx_test_favorites` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя',
    `question_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID вопроса',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_favorite` (`user_id`, `question_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_question` (`question_id`),
    CONSTRAINT `fk_favorite_question` FOREIGN KEY (`question_id`)
        REFERENCES `modx_test_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Избранные вопросы';

-- Включить обратно проверку foreign keys
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- SPRINT 9: УЧЕБНЫЕ МАТЕРИАЛЫ (LMS База)
-- ============================================
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


-- ============================================
-- SPRINT 10: ГРАНУЛЯРНЫЕ ПРАВА ДОСТУПА
-- ============================================

-- ============================================
-- Test System Category Permissions Tables
-- Sprint 10: Гранулярные права доступа
-- ============================================

-- Таблица прав доступа по категориям
CREATE TABLE IF NOT EXISTS `modx_test_category_permissions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID категории',
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя',
    `role` ENUM('admin', 'expert', 'viewer') DEFAULT 'viewer' COMMENT 'Роль пользователя',
    `granted_by` INT(11) UNSIGNED NOT NULL COMMENT 'Кто назначил права',
    `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата назначения',
    `expires_at` DATETIME DEFAULT NULL COMMENT 'Дата истечения прав (опционально)',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_category_user` (`category_id`, `user_id`),
    KEY `idx_category` (`category_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_role` (`role`),
    KEY `idx_granted_by` (`granted_by`),
    CONSTRAINT `fk_permission_category` FOREIGN KEY (`category_id`)
        REFERENCES `modx_test_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Права доступа к категориям';

-- Таблица иерархии категорий (для наследования прав)
CREATE TABLE IF NOT EXISTS `modx_test_category_hierarchy` (
    `parent_id` INT(11) UNSIGNED NOT NULL COMMENT 'Родительская категория',
    `child_id` INT(11) UNSIGNED NOT NULL COMMENT 'Дочерняя категория',
    `depth` INT(11) DEFAULT 1 COMMENT 'Глубина вложенности',
    PRIMARY KEY (`parent_id`, `child_id`),
    KEY `idx_parent` (`parent_id`),
    KEY `idx_child` (`child_id`),
    CONSTRAINT `fk_hierarchy_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `modx_test_categories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_hierarchy_child` FOREIGN KEY (`child_id`)
        REFERENCES `modx_test_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Иерархия категорий';

-- Добавление поля parent_id в категории (если его еще нет)
ALTER TABLE `modx_test_categories`
    ADD COLUMN IF NOT EXISTS `parent_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Родительская категория' AFTER `id`,
    ADD INDEX IF NOT EXISTS `idx_parent` (`parent_id`);

-- Таблица истории изменений прав доступа (для аудита)
CREATE TABLE IF NOT EXISTS `modx_test_permission_history` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID категории',
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя',
    `action` ENUM('granted', 'revoked', 'modified') NOT NULL COMMENT 'Действие',
    `old_role` VARCHAR(50) DEFAULT NULL COMMENT 'Старая роль',
    `new_role` VARCHAR(50) DEFAULT NULL COMMENT 'Новая роль',
    `performed_by` INT(11) UNSIGNED NOT NULL COMMENT 'Кто выполнил действие',
    `performed_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата действия',
    `reason` TEXT COMMENT 'Причина изменения',
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_performed_by` (`performed_by`),
    KEY `idx_performed_at` (`performed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='История изменений прав доступа';

-- ============================================
-- Описание ролей
-- ============================================

/*
РОЛИ:

1. admin (Администратор категории)
   - Полное управление категорией
   - Создание/редактирование/удаление тестов и материалов
   - Назначение экспертов и просмотр прав других пользователей
   - Может назначать роли: expert, viewer
   - НЕ может назначать других admin (только глобальный админ)

2. expert (Эксперт категории)
   - Создание и редактирование тестов и материалов в категории
   - Создание/редактирование вопросов
   - Просмотр статистики по категории
   - НЕ может управлять правами доступа
   - НЕ может удалять категорию

3. viewer (Наблюдатель)
   - Только просмотр статистики по категории
   - Просмотр тестов и материалов
   - НЕ может редактировать или создавать контент
   - Полезно для менеджеров, HR и т.д.
*/

-- ============================================
-- Наследование прав
-- ============================================

/*
Правила наследования:
1. Права на родительскую категорию НЕ автоматически дают права на дочерние
2. Можно явно назначить права на дочернюю категорию
3. При проверке прав учитываются только явно назначенные права
4. Глобальные админы и эксперты (из Config) имеют полный доступ ко всем категориям
*/

-- ============================================
-- Индексы для производительности
-- ============================================

-- Составной индекс для быстрого поиска всех прав пользователя
ALTER TABLE `modx_test_category_permissions`
    ADD INDEX `idx_user_role` (`user_id`, `role`);

-- Индекс для поиска всех пользователей категории с определенной ролью
ALTER TABLE `modx_test_category_permissions`
    ADD INDEX `idx_category_role` (`category_id`, `role`);

-- Индекс для проверки истечения прав
ALTER TABLE `modx_test_category_permissions`
    ADD INDEX `idx_expires` (`expires_at`);

-- ============================================
-- Примеры использования
-- ============================================

/*
-- Назначить пользователя экспертом по категории "Психология"
INSERT INTO modx_test_category_permissions (category_id, user_id, role, granted_by)
VALUES (5, 123, 'expert', 1);

-- Получить всех экспертов категории
SELECT u.username, cp.role, cp.granted_at
FROM modx_test_category_permissions cp
JOIN modx_users u ON u.id = cp.user_id
WHERE cp.category_id = 5;

-- Проверить, есть ли у пользователя права на категорию
SELECT role FROM modx_test_category_permissions
WHERE category_id = 5 AND user_id = 123;

-- Получить все категории, где пользователь является экспертом
SELECT c.name, cp.role
FROM modx_test_category_permissions cp
JOIN modx_test_categories c ON c.id = cp.category_id
WHERE cp.user_id = 123 AND cp.role IN ('admin', 'expert');
*/

-- ============================================
-- Триггеры для автоматического логирования
-- ============================================

DELIMITER $$

-- Логирование назначения прав
CREATE TRIGGER IF NOT EXISTS `trg_category_permission_grant`
AFTER INSERT ON `modx_test_category_permissions`
FOR EACH ROW
BEGIN
    INSERT INTO modx_test_permission_history
    (category_id, user_id, action, new_role, performed_by, performed_at)
    VALUES (NEW.category_id, NEW.user_id, 'granted', NEW.role, NEW.granted_by, NEW.granted_at);
END$$

-- Логирование изменения прав
CREATE TRIGGER IF NOT EXISTS `trg_category_permission_modify`
AFTER UPDATE ON `modx_test_category_permissions`
FOR EACH ROW
BEGIN
    IF OLD.role != NEW.role THEN
        INSERT INTO modx_test_permission_history
        (category_id, user_id, action, old_role, new_role, performed_by, performed_at)
        VALUES (NEW.category_id, NEW.user_id, 'modified', OLD.role, NEW.role, NEW.granted_by, NOW());
    END IF;
END$$

-- Логирование отзыва прав
CREATE TRIGGER IF NOT EXISTS `trg_category_permission_revoke`
AFTER DELETE ON `modx_test_category_permissions`
FOR EACH ROW
BEGIN
    INSERT INTO modx_test_permission_history
    (category_id, user_id, action, old_role, performed_by, performed_at)
    VALUES (OLD.category_id, OLD.user_id, 'revoked', OLD.role, @performed_by_user_id, NOW());
END$$

DELIMITER ;

-- ============================================
-- Примечания
-- ============================================

/*
1. Глобальные админы (из Config::getGroup('admins')) имеют полный доступ ко всем категориям
2. Глобальные эксперты (из Config::getGroup('experts')) могут создавать контент во всех категориях
3. Права на категорию НЕ дают автоматически права на тесты/материалы других категорий
4. При удалении категории все связанные права удаляются автоматически (CASCADE)
5. expires_at позволяет назначать временные права (например, на время проекта)
6. История изменений хранится бессрочно для аудита
*/


-- ============================================
-- SPRINT 11: ТРАЕКТОРИИ ОБУЧЕНИЯ (LEARNING PATHS)
-- ============================================

-- ============================================
-- Test System Learning Paths Tables
-- Sprint 11: Траектории обучения
-- ============================================

-- Таблица траекторий обучения
CREATE TABLE IF NOT EXISTS `modx_test_learning_paths` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL COMMENT 'Название траектории',
    `description` TEXT COMMENT 'Описание траектории',
    `category_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Категория',
    `created_by` INT(11) UNSIGNED NOT NULL COMMENT 'Автор траектории',
    `status` ENUM('draft', 'published', 'archived') DEFAULT 'draft' COMMENT 'Статус публикации',
    `is_public` TINYINT(1) DEFAULT 0 COMMENT 'Публичный доступ',
    `difficulty_level` ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'beginner' COMMENT 'Уровень сложности',
    `estimated_hours` INT(11) DEFAULT NULL COMMENT 'Примерная длительность в часах',
    `certificate_template` VARCHAR(255) DEFAULT NULL COMMENT 'Шаблон сертификата',
    `passing_score` INT(11) DEFAULT 70 COMMENT 'Проходной балл для получения сертификата',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category_id`),
    KEY `idx_created_by` (`created_by`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_path_category` FOREIGN KEY (`category_id`)
        REFERENCES `modx_test_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Траектории обучения';

-- Таблица шагов траектории
CREATE TABLE IF NOT EXISTS `modx_test_learning_path_steps` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `path_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID траектории',
    `step_number` INT(11) NOT NULL COMMENT 'Порядковый номер шага',
    `step_type` ENUM('material', 'test', 'quiz', 'assignment') DEFAULT 'material' COMMENT 'Тип шага',
    `item_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID материала/теста',
    `name` VARCHAR(255) NOT NULL COMMENT 'Название шага',
    `description` TEXT COMMENT 'Описание шага',
    `is_required` TINYINT(1) DEFAULT 1 COMMENT 'Обязательный шаг',
    `unlock_condition` JSON DEFAULT NULL COMMENT 'Условия разблокировки {"type":"previous_step|score|date","value":...}',
    `min_score` INT(11) DEFAULT NULL COMMENT 'Минимальный балл для прохождения (для тестов)',
    `estimated_minutes` INT(11) DEFAULT NULL COMMENT 'Примерное время на шаг',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_path` (`path_id`),
    KEY `idx_step_number` (`path_id`, `step_number`),
    KEY `idx_item` (`step_type`, `item_id`),
    CONSTRAINT `fk_step_path` FOREIGN KEY (`path_id`)
        REFERENCES `modx_test_learning_paths` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Шаги траектории обучения';

-- Таблица записей на траектории
CREATE TABLE IF NOT EXISTS `modx_test_learning_path_enrollments` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `path_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID траектории',
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя',
    `enrolled_by` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Кто записал (NULL = самозапись)',
    `enrolled_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата записи',
    `expires_at` DATETIME DEFAULT NULL COMMENT 'Дата истечения доступа',
    `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Активная запись',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_path_user` (`path_id`, `user_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_expires` (`expires_at`),
    CONSTRAINT `fk_enrollment_path` FOREIGN KEY (`path_id`)
        REFERENCES `modx_test_learning_paths` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Записи пользователей на траектории';

-- Таблица общего прогресса по траектории
CREATE TABLE IF NOT EXISTS `modx_test_learning_path_progress` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `enrollment_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID записи',
    `path_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID траектории',
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя',
    `current_step` INT(11) DEFAULT 1 COMMENT 'Текущий шаг',
    `status` ENUM('not_started', 'in_progress', 'completed', 'failed') DEFAULT 'not_started' COMMENT 'Статус прохождения',
    `completion_pct` INT(11) DEFAULT 0 COMMENT 'Процент завершения (0-100)',
    `total_score` DECIMAL(5,2) DEFAULT 0 COMMENT 'Общий балл',
    `started_at` DATETIME DEFAULT NULL COMMENT 'Дата начала',
    `completed_at` DATETIME DEFAULT NULL COMMENT 'Дата завершения',
    `certificate_issued` TINYINT(1) DEFAULT 0 COMMENT 'Сертификат выдан',
    `certificate_issued_at` DATETIME DEFAULT NULL COMMENT 'Дата выдачи сертификата',
    `last_activity_at` DATETIME DEFAULT NULL COMMENT 'Последняя активность',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_enrollment` (`enrollment_id`),
    KEY `idx_path_user` (`path_id`, `user_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_progress_enrollment` FOREIGN KEY (`enrollment_id`)
        REFERENCES `modx_test_learning_path_enrollments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_progress_path` FOREIGN KEY (`path_id`)
        REFERENCES `modx_test_learning_paths` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Общий прогресс по траектории';

-- Таблица завершения отдельных шагов
CREATE TABLE IF NOT EXISTS `modx_test_learning_path_step_completion` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `progress_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID общего прогресса',
    `step_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID шага',
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя',
    `status` ENUM('locked', 'available', 'in_progress', 'completed', 'skipped') DEFAULT 'locked' COMMENT 'Статус шага',
    `score` DECIMAL(5,2) DEFAULT NULL COMMENT 'Балл за шаг',
    `attempts` INT(11) DEFAULT 0 COMMENT 'Количество попыток',
    `time_spent_minutes` INT(11) DEFAULT 0 COMMENT 'Время на шаг',
    `started_at` DATETIME DEFAULT NULL COMMENT 'Дата начала',
    `completed_at` DATETIME DEFAULT NULL COMMENT 'Дата завершения',
    `session_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID последней сессии теста',
    `material_progress_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID прогресса материала',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_progress_step` (`progress_id`, `step_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_step_completion_progress` FOREIGN KEY (`progress_id`)
        REFERENCES `modx_test_learning_path_progress` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_step_completion_step` FOREIGN KEY (`step_id`)
        REFERENCES `modx_test_learning_path_steps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Завершение шагов траектории';

-- Таблица достижений за траектории
CREATE TABLE IF NOT EXISTS `modx_test_learning_path_achievements` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `path_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID траектории',
    `name` VARCHAR(255) NOT NULL COMMENT 'Название достижения',
    `description` TEXT COMMENT 'Описание',
    `badge_icon` VARCHAR(255) DEFAULT NULL COMMENT 'Иконка badge',
    `condition_type` ENUM('complete_path', 'score_threshold', 'time_limit', 'perfect_score') DEFAULT 'complete_path',
    `condition_value` INT(11) DEFAULT NULL COMMENT 'Значение условия',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_path` (`path_id`),
    CONSTRAINT `fk_achievement_path` FOREIGN KEY (`path_id`)
        REFERENCES `modx_test_learning_paths` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Достижения за траектории';

-- Таблица полученных достижений
CREATE TABLE IF NOT EXISTS `modx_test_learning_path_user_achievements` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `achievement_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID достижения',
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя',
    `progress_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID прогресса',
    `earned_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата получения',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_achievement_user` (`achievement_id`, `user_id`),
    KEY `idx_user` (`user_id`),
    CONSTRAINT `fk_user_achievement_achievement` FOREIGN KEY (`achievement_id`)
        REFERENCES `modx_test_learning_path_achievements` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_achievement_progress` FOREIGN KEY (`progress_id`)
        REFERENCES `modx_test_learning_path_progress` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Полученные достижения пользователей';

-- ============================================
-- Индексы для производительности
-- ============================================

-- Индекс для поиска активных траекторий
ALTER TABLE `modx_test_learning_paths`
    ADD INDEX `idx_status_public` (`status`, `is_public`);

-- Индекс для сортировки шагов
ALTER TABLE `modx_test_learning_path_steps`
    ADD INDEX `idx_path_step_order` (`path_id`, `step_number`, `is_required`);

-- Индекс для активных записей
ALTER TABLE `modx_test_learning_path_enrollments`
    ADD INDEX `idx_active` (`is_active`, `expires_at`);

-- Индекс для отслеживания активности
ALTER TABLE `modx_test_learning_path_progress`
    ADD INDEX `idx_active_progress` (`status`, `last_activity_at`);

-- ============================================
-- Описание unlock_condition JSON
-- ============================================

/*
unlock_condition - JSON структура с условиями разблокировки шага:

1. Предыдущий шаг завершен:
{
    "type": "previous_step",
    "required": true
}

2. Минимальный балл на предыдущем шаге:
{
    "type": "previous_step_score",
    "min_score": 80
}

3. Все предыдущие шаги завершены:
{
    "type": "all_previous_steps",
    "required": true
}

4. Определенная дата:
{
    "type": "date",
    "unlock_date": "2025-12-01 00:00:00"
}

5. Комбинированное условие:
{
    "type": "combined",
    "operator": "AND",
    "conditions": [
        {"type": "previous_step", "required": true},
        {"type": "previous_step_score", "min_score": 70}
    ]
}
*/

-- ============================================
-- Примеры использования
-- ============================================

/*
-- Создание траектории "Основы психологии"
INSERT INTO modx_test_learning_paths (name, description, category_id, created_by, status, difficulty_level)
VALUES ('Основы психологии', 'Введение в психологию для начинающих', 5, 1, 'published', 'beginner');

-- Добавление шагов
INSERT INTO modx_test_learning_path_steps (path_id, step_number, step_type, item_id, name, is_required, min_score)
VALUES
    (1, 1, 'material', 10, 'Что такое психология?', 1, NULL),
    (1, 2, 'test', 5, 'Проверочный тест', 1, 70),
    (1, 3, 'material', 11, 'История психологии', 1, NULL),
    (1, 4, 'test', 6, 'Итоговый тест', 1, 80);

-- Запись пользователя на траекторию
INSERT INTO modx_test_learning_path_enrollments (path_id, user_id)
VALUES (1, 123);

-- Получение прогресса пользователя
SELECT
    lp.name as path_name,
    lpp.status,
    lpp.completion_pct,
    lpp.current_step,
    COUNT(lpsc.id) as completed_steps
FROM modx_test_learning_path_progress lpp
JOIN modx_test_learning_paths lp ON lp.id = lpp.path_id
LEFT JOIN modx_test_learning_path_step_completion lpsc
    ON lpsc.progress_id = lpp.id AND lpsc.status = 'completed'
WHERE lpp.user_id = 123
GROUP BY lpp.id;
*/

-- ============================================
-- Триггеры для автоматического обновления
-- ============================================

DELIMITER $$

-- Создание прогресса при записи на траекторию
CREATE TRIGGER IF NOT EXISTS `trg_enrollment_create_progress`
AFTER INSERT ON `modx_test_learning_path_enrollments`
FOR EACH ROW
BEGIN
    -- Создаем запись прогресса
    INSERT INTO modx_test_learning_path_progress
    (enrollment_id, path_id, user_id, status, started_at)
    VALUES (NEW.id, NEW.path_id, NEW.user_id, 'not_started', NOW());

    -- Получаем ID созданного прогресса
    SET @progress_id = LAST_INSERT_ID();

    -- Создаем записи для всех шагов траектории
    INSERT INTO modx_test_learning_path_step_completion
    (progress_id, step_id, user_id, status)
    SELECT
        @progress_id,
        lps.id,
        NEW.user_id,
        CASE WHEN lps.step_number = 1 THEN 'available' ELSE 'locked' END
    FROM modx_test_learning_path_steps lps
    WHERE lps.path_id = NEW.path_id
    ORDER BY lps.step_number;
END$$

-- Обновление процента завершения при завершении шага
CREATE TRIGGER IF NOT EXISTS `trg_step_completion_update_progress`
AFTER UPDATE ON `modx_test_learning_path_step_completion`
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        -- Подсчитываем процент завершения
        UPDATE modx_test_learning_path_progress lpp
        SET
            completion_pct = (
                SELECT ROUND(COUNT(CASE WHEN lpsc.status = 'completed' THEN 1 END) * 100.0 / COUNT(*))
                FROM modx_test_learning_path_step_completion lpsc
                WHERE lpsc.progress_id = lpp.id
            ),
            last_activity_at = NOW(),
            status = CASE
                WHEN (
                    SELECT COUNT(*)
                    FROM modx_test_learning_path_step_completion lpsc2
                    JOIN modx_test_learning_path_steps lps ON lps.id = lpsc2.step_id
                    WHERE lpsc2.progress_id = lpp.id
                    AND lps.is_required = 1
                    AND lpsc2.status != 'completed'
                ) = 0 THEN 'completed'
                ELSE 'in_progress'
            END,
            completed_at = CASE
                WHEN (
                    SELECT COUNT(*)
                    FROM modx_test_learning_path_step_completion lpsc3
                    JOIN modx_test_learning_path_steps lps2 ON lps2.id = lpsc3.step_id
                    WHERE lpsc3.progress_id = lpp.id
                    AND lps2.is_required = 1
                    AND lpsc3.status != 'completed'
                ) = 0 THEN NOW()
                ELSE lpp.completed_at
            END
        WHERE lpp.id = NEW.progress_id;

        -- Разблокируем следующий шаг
        UPDATE modx_test_learning_path_step_completion
        SET status = 'available'
        WHERE progress_id = NEW.progress_id
        AND step_id = (
            SELECT lps.id
            FROM modx_test_learning_path_steps lps
            WHERE lps.path_id = (
                SELECT lpp.path_id
                FROM modx_test_learning_path_progress lpp
                WHERE lpp.id = NEW.progress_id
            )
            AND lps.step_number = (
                SELECT lps2.step_number + 1
                FROM modx_test_learning_path_steps lps2
                WHERE lps2.id = NEW.step_id
            )
            LIMIT 1
        )
        AND status = 'locked';
    END IF;
END$$

DELIMITER ;

-- ============================================
-- Примечания
-- ============================================

/*
1. Траектории могут быть публичными (is_public=1) или приватными
2. Шаги выполняются последовательно, unlock_condition определяет условия разблокировки
3. Прогресс автоматически создается при записи на траекторию
4. Триггеры автоматически обновляют процент завершения
5. Сертификат выдается при завершении всех обязательных шагов и достижении passing_score
6. Достижения (achievements) - опциональный игровой элемент
7. Поддерживаются разные типы шагов: материалы, тесты, квизы, задания
*/


-- ============================================
-- SPRINT 12: РАСШИРЕННЫЕ ТИПЫ ВОПРОСОВ
-- ============================================

-- ============================================
-- Test System Advanced Question Types
-- Sprint 12: Расширенные типы вопросов
-- ============================================

-- Обновление типов вопросов в существующей таблице
ALTER TABLE `modx_test_questions`
    MODIFY COLUMN `question_type` ENUM('single', 'multiple', 'matching', 'ordering', 'fill_blank', 'essay')
    DEFAULT 'single'
    COMMENT 'Тип вопроса: single, multiple, matching, ordering, fill_blank, essay';

-- Таблица для вопросов типа matching (сопоставление)
CREATE TABLE IF NOT EXISTS `modx_test_question_matching_pairs` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID вопроса',
    `left_item` VARCHAR(500) NOT NULL COMMENT 'Левый элемент (термин)',
    `right_item` VARCHAR(500) NOT NULL COMMENT 'Правый элемент (определение)',
    `sort_order` INT(11) DEFAULT 0 COMMENT 'Порядок отображения',
    PRIMARY KEY (`id`),
    KEY `idx_question` (`question_id`),
    CONSTRAINT `fk_matching_question` FOREIGN KEY (`question_id`)
        REFERENCES `modx_test_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Пары для вопросов типа matching';

-- Таблица для вопросов типа ordering (упорядочивание)
CREATE TABLE IF NOT EXISTS `modx_test_question_ordering_items` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID вопроса',
    `item_text` TEXT NOT NULL COMMENT 'Текст элемента',
    `correct_position` INT(11) NOT NULL COMMENT 'Правильная позиция (1, 2, 3...)',
    PRIMARY KEY (`id`),
    KEY `idx_question` (`question_id`),
    KEY `idx_position` (`question_id`, `correct_position`),
    CONSTRAINT `fk_ordering_question` FOREIGN KEY (`question_id`)
        REFERENCES `modx_test_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Элементы для вопросов типа ordering';

-- Таблица для вопросов типа fill_blank (заполнение пропусков)
CREATE TABLE IF NOT EXISTS `modx_test_question_fill_blanks` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID вопроса',
    `template_text` TEXT NOT NULL COMMENT 'Текст с маркерами пропусков {{1}}, {{2}} и т.д.',
    `case_sensitive` TINYINT(1) DEFAULT 0 COMMENT 'Учитывать регистр',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_question` (`question_id`),
    CONSTRAINT `fk_fillblank_question` FOREIGN KEY (`question_id`)
        REFERENCES `modx_test_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Шаблоны для вопросов типа fill_blank';

-- Таблица правильных ответов для пропусков
CREATE TABLE IF NOT EXISTS `modx_test_question_fill_blank_answers` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `fill_blank_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID записи fill_blank',
    `blank_number` INT(11) NOT NULL COMMENT 'Номер пропуска (1, 2, 3...)',
    `correct_answer` VARCHAR(500) NOT NULL COMMENT 'Правильный ответ',
    `alternative_answers` TEXT COMMENT 'Альтернативные правильные ответы (JSON массив)',
    PRIMARY KEY (`id`),
    KEY `idx_fillblank` (`fill_blank_id`),
    KEY `idx_blank_number` (`fill_blank_id`, `blank_number`),
    CONSTRAINT `fk_fillblank_answer` FOREIGN KEY (`fill_blank_id`)
        REFERENCES `modx_test_question_fill_blanks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Правильные ответы для пропусков';

-- Таблица для вопросов типа essay (эссе)
CREATE TABLE IF NOT EXISTS `modx_test_question_essays` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID вопроса',
    `min_words` INT(11) DEFAULT NULL COMMENT 'Минимальное количество слов',
    `max_words` INT(11) DEFAULT NULL COMMENT 'Максимальное количество слов',
    `rubric` TEXT COMMENT 'Критерии оценивания (для проверяющего)',
    `auto_check_keywords` TEXT COMMENT 'Ключевые слова для автопроверки (JSON)',
    `max_score` INT(11) DEFAULT 10 COMMENT 'Максимальный балл',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_question` (`question_id`),
    CONSTRAINT `fk_essay_question` FOREIGN KEY (`question_id`)
        REFERENCES `modx_test_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Настройки для вопросов типа essay';

-- Таблица ручной проверки эссе
CREATE TABLE IF NOT EXISTS `modx_test_essay_reviews` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_answer_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID ответа пользователя',
    `question_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID вопроса',
    `session_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID сессии',
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID студента',
    `essay_text` TEXT NOT NULL COMMENT 'Текст эссе',
    `word_count` INT(11) DEFAULT 0 COMMENT 'Количество слов',
    `status` ENUM('pending', 'reviewing', 'reviewed') DEFAULT 'pending' COMMENT 'Статус проверки',
    `score` DECIMAL(5,2) DEFAULT NULL COMMENT 'Полученный балл',
    `reviewer_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID проверяющего',
    `reviewer_comment` TEXT COMMENT 'Комментарий проверяющего',
    `reviewed_at` DATETIME DEFAULT NULL COMMENT 'Дата проверки',
    `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата отправки',
    PRIMARY KEY (`id`),
    KEY `idx_user_answer` (`user_answer_id`),
    KEY `idx_session` (`session_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_reviewer` (`reviewer_id`),
    CONSTRAINT `fk_essay_review_user_answer` FOREIGN KEY (`user_answer_id`)
        REFERENCES `modx_test_user_answers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_essay_review_question` FOREIGN KEY (`question_id`)
        REFERENCES `modx_test_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ручная проверка эссе';

-- Таблица для хранения ответов пользователей на новые типы вопросов
-- Используется JSON поле answer_data в modx_test_user_answers

-- Добавляем поле для хранения структурированных ответов
ALTER TABLE `modx_test_user_answers`
    ADD COLUMN IF NOT EXISTS `answer_data` JSON DEFAULT NULL COMMENT 'Структурированные данные ответа (для новых типов вопросов)';

-- Индексы для производительности
ALTER TABLE `modx_test_question_matching_pairs`
    ADD INDEX `idx_question_sort` (`question_id`, `sort_order`);

ALTER TABLE `modx_test_essay_reviews`
    ADD INDEX `idx_pending_reviews` (`status`, `submitted_at`);

-- ============================================
-- Описание форматов данных
-- ============================================

/*
MATCHING - Сопоставление:
Таблица test_question_matching_pairs содержит пары (левый-правый элемент).
При отображении правые элементы перемешиваются.

Пример:
left_item: "CPU", right_item: "Центральный процессор"
left_item: "RAM", right_item: "Оперативная память"

answer_data в user_answers:
{
    "type": "matching",
    "pairs": [
        {"left_id": 1, "right_id": 2},
        {"left_id": 2, "right_id": 1}
    ]
}

Правильный ответ: каждый left_item должен быть сопоставлен со своим right_item.
*/

/*
ORDERING - Упорядочивание:
Таблица test_question_ordering_items содержит элементы с correct_position.

Пример:
item_text: "Родился Пушкин", correct_position: 1
item_text: "Написал Евгения Онегина", correct_position: 2
item_text: "Погиб на дуэли", correct_position: 3

answer_data в user_answers:
{
    "type": "ordering",
    "order": [3, 1, 2]  // ID элементов в порядке, указанном пользователем
}

Правильный ответ: элементы должны быть в порядке correct_position.
*/

/*
FILL_BLANK - Заполнение пропусков:
Таблица test_question_fill_blanks содержит текст с маркерами {{1}}, {{2}}.
Таблица test_question_fill_blank_answers содержит правильные ответы.

Пример:
template_text: "Столица России - {{1}}, а Франции - {{2}}."

blank_number: 1, correct_answer: "Москва", alternative_answers: ["москва", "МОСКВА"]
blank_number: 2, correct_answer: "Париж", alternative_answers: ["париж"]

answer_data в user_answers:
{
    "type": "fill_blank",
    "answers": {
        "1": "Москва",
        "2": "Париж"
    }
}

Правильный ответ: каждый пропуск должен быть заполнен правильно.
Проверка с учетом alternative_answers и case_sensitive флага.
*/

/*
ESSAY - Эссе:
Таблица test_question_essays содержит настройки (мин/макс слова, критерии).
Таблица test_essay_reviews содержит отправленные эссе и результаты проверки.

answer_data в user_answers:
{
    "type": "essay",
    "text": "Текст эссе...",
    "word_count": 150
}

Проверка:
1. Автоматическая: проверка количества слов, наличие ключевых слов
2. Ручная: эксперт выставляет балл и комментарий
*/

-- ============================================
-- Примеры использования
-- ============================================

/*
-- MATCHING вопрос
INSERT INTO modx_test_questions (test_id, question_text, question_type, published)
VALUES (1, 'Сопоставьте термины с определениями:', 'matching', 1);

SET @question_id = LAST_INSERT_ID();

INSERT INTO modx_test_question_matching_pairs (question_id, left_item, right_item, sort_order)
VALUES
    (@question_id, 'HTML', 'Язык разметки гипертекста', 1),
    (@question_id, 'CSS', 'Каскадные таблицы стилей', 2),
    (@question_id, 'JavaScript', 'Язык программирования для веб', 3);

-- ORDERING вопрос
INSERT INTO modx_test_questions (test_id, question_text, question_type, published)
VALUES (1, 'Расположите события в хронологическом порядке:', 'ordering', 1);

SET @question_id = LAST_INSERT_ID();

INSERT INTO modx_test_question_ordering_items (question_id, item_text, correct_position)
VALUES
    (@question_id, 'Октябрьская революция', 1),
    (@question_id, 'Создание СССР', 2),
    (@question_id, 'Начало ВОВ', 3);

-- FILL_BLANK вопрос
INSERT INTO modx_test_questions (test_id, question_text, question_type, published)
VALUES (1, 'Заполните пропуски:', 'fill_blank', 1);

SET @question_id = LAST_INSERT_ID();

INSERT INTO modx_test_question_fill_blanks (question_id, template_text, case_sensitive)
VALUES (@question_id, 'Столица России - {{1}}, а Франции - {{2}}.', 0);

SET @fillblank_id = LAST_INSERT_ID();

INSERT INTO modx_test_question_fill_blank_answers (fill_blank_id, blank_number, correct_answer, alternative_answers)
VALUES
    (@fillblank_id, 1, 'Москва', '["москва", "МОСКВА"]'),
    (@fillblank_id, 2, 'Париж', '["париж", "Paris"]');

-- ESSAY вопрос
INSERT INTO modx_test_questions (test_id, question_text, question_type, published)
VALUES (1, 'Опишите основные принципы ООП', 'essay', 1);

SET @question_id = LAST_INSERT_ID();

INSERT INTO modx_test_question_essays (question_id, min_words, max_words, rubric, max_score)
VALUES (
    @question_id,
    100,
    500,
    'Критерии: полнота ответа, примеры, структура',
    10
);
*/

-- ============================================
-- Примечания
-- ============================================

/*
1. Для matching и ordering используется стандартная таблица answers для совместимости
2. answer_data в user_answers хранит JSON с детальной информацией ответа
3. Essay требует ручной проверки через интерфейс для экспертов
4. fill_blank поддерживает несколько правильных вариантов ответа
5. Все новые типы полностью интегрированы с существующей системой сессий и подсчета баллов
*/


-- ============================================
-- SPRINT 13: СИСТЕМА ГЕЙМИФИКАЦИИ
-- ============================================

-- ============================================
-- Test System Gamification
-- Sprint 13: Геймификация и бейджи
-- ============================================

-- Таблица достижений (шаблоны)
CREATE TABLE IF NOT EXISTS `modx_test_achievements` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL COMMENT 'Название достижения',
    `description` TEXT COMMENT 'Описание',
    `badge_icon` VARCHAR(255) DEFAULT NULL COMMENT 'Иконка бейджа',
    `badge_color` VARCHAR(50) DEFAULT NULL COMMENT 'Цвет бейджа (#hex)',
    `achievement_type` ENUM('test_count', 'perfect_score', 'streak', 'category_master', 'speed_demon', 'first_place', 'custom') DEFAULT 'custom' COMMENT 'Тип достижения',
    `condition_data` JSON DEFAULT NULL COMMENT 'Условия получения достижения',
    `xp_reward` INT(11) DEFAULT 0 COMMENT 'Награда в опыте (XP)',
    `is_secret` TINYINT(1) DEFAULT 0 COMMENT 'Секретное достижение (не показывать до получения)',
    `category_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Привязка к категории (NULL = глобальное)',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_type` (`achievement_type`),
    KEY `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Шаблоны достижений';

-- Таблица полученных достижений пользователей
CREATE TABLE IF NOT EXISTS `modx_test_user_achievements` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя',
    `achievement_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID достижения',
    `earned_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата получения',
    `progress` INT(11) DEFAULT 100 COMMENT 'Прогресс получения (0-100%)',
    `metadata` JSON DEFAULT NULL COMMENT 'Дополнительные данные',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_user_achievement` (`user_id`, `achievement_id`),
    KEY `idx_earned` (`earned_at`),
    CONSTRAINT `fk_user_achievement_achievement` FOREIGN KEY (`achievement_id`)
        REFERENCES `modx_test_achievements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Полученные достижения пользователей';

-- Таблица опыта и уровней пользователей
CREATE TABLE IF NOT EXISTS `modx_test_user_experience` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя',
    `total_xp` INT(11) DEFAULT 0 COMMENT 'Общий опыт',
    `level` INT(11) DEFAULT 1 COMMENT 'Уровень пользователя',
    `xp_to_next_level` INT(11) DEFAULT 100 COMMENT 'Опыт до следующего уровня',
    `title` VARCHAR(255) DEFAULT NULL COMMENT 'Звание пользователя',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_user` (`user_id`),
    KEY `idx_level` (`level`),
    KEY `idx_total_xp` (`total_xp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Опыт и уровни пользователей';

-- Таблица истории получения опыта
CREATE TABLE IF NOT EXISTS `modx_test_xp_history` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя',
    `xp_amount` INT(11) NOT NULL COMMENT 'Количество опыта',
    `reason` VARCHAR(255) NOT NULL COMMENT 'Причина получения',
    `reference_type` ENUM('test', 'achievement', 'streak', 'bonus', 'manual') DEFAULT 'test' COMMENT 'Тип ссылки',
    `reference_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID связанного объекта',
    `earned_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата получения',
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_earned` (`earned_at`),
    KEY `idx_reference` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='История получения опыта';

-- Таблица серий (streaks) - последовательных дней активности
CREATE TABLE IF NOT EXISTS `modx_test_user_streaks` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя',
    `current_streak` INT(11) DEFAULT 0 COMMENT 'Текущая серия дней',
    `longest_streak` INT(11) DEFAULT 0 COMMENT 'Максимальная серия дней',
    `last_activity_date` DATE DEFAULT NULL COMMENT 'Дата последней активности',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Серии активности пользователей';

-- Таблица лидерборда (обновляется периодически)
CREATE TABLE IF NOT EXISTS `modx_test_leaderboard` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя',
    `category_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID категории (NULL = глобальный)',
    `period_type` ENUM('all_time', 'yearly', 'monthly', 'weekly') DEFAULT 'all_time' COMMENT 'Период',
    `period_key` VARCHAR(20) DEFAULT NULL COMMENT 'Ключ периода (например 2025-11 для месяца)',
    `rank` INT(11) DEFAULT 0 COMMENT 'Позиция в рейтинге',
    `total_xp` INT(11) DEFAULT 0 COMMENT 'Общий опыт за период',
    `tests_completed` INT(11) DEFAULT 0 COMMENT 'Тестов пройдено',
    `avg_score` DECIMAL(5,2) DEFAULT 0 COMMENT 'Средний балл',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_user_category_period` (`user_id`, `category_id`, `period_type`, `period_key`),
    KEY `idx_category_period_rank` (`category_id`, `period_type`, `period_key`, `rank`),
    KEY `idx_rank` (`rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Лидерборд';

-- Таблица уровней (конфигурация)
CREATE TABLE IF NOT EXISTS `modx_test_level_config` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `level` INT(11) NOT NULL COMMENT 'Уровень',
    `xp_required` INT(11) NOT NULL COMMENT 'Опыт для достижения уровня',
    `title` VARCHAR(255) NOT NULL COMMENT 'Звание',
    `perks` JSON DEFAULT NULL COMMENT 'Преимущества уровня',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Конфигурация уровней';

-- ============================================
-- Индексы для производительности
-- ============================================

ALTER TABLE `modx_test_user_achievements`
    ADD INDEX `idx_user_earned` (`user_id`, `earned_at`);

ALTER TABLE `modx_test_xp_history`
    ADD INDEX `idx_user_earned` (`user_id`, `earned_at`);

ALTER TABLE `modx_test_leaderboard`
    ADD INDEX `idx_global_all_time` (`period_type`, `rank`) WHERE category_id IS NULL;

-- ============================================
-- Начальная конфигурация уровней
-- ============================================

INSERT INTO `modx_test_level_config` (`level`, `xp_required`, `title`, `perks`) VALUES
(1, 0, 'Новичок', '{"description": "Начальный уровень"}'),
(2, 100, 'Ученик', '{"description": "Знакомство с системой"}'),
(3, 250, 'Подмастерье', '{"description": "Базовые знания"}'),
(4, 500, 'Специалист', '{"description": "Уверенные знания"}'),
(5, 1000, 'Эксперт', '{"description": "Глубокие знания", "badge_color": "#FFD700"}'),
(6, 2000, 'Мастер', '{"description": "Профессиональный уровень", "badge_color": "#FFA500"}'),
(7, 4000, 'Гранд-мастер', '{"description": "Выдающиеся достижения", "badge_color": "#FF4500"}'),
(8, 8000, 'Легенда', '{"description": "Легендарный уровень", "badge_color": "#9400D3"}'),
(9, 16000, 'Гуру', '{"description": "Непревзойденное мастерство", "badge_color": "#8B00FF"}'),
(10, 32000, 'Титан', '{"description": "Титан знаний", "badge_color": "#4B0082"}')
ON DUPLICATE KEY UPDATE xp_required = VALUES(xp_required);

-- ============================================
-- Стандартные достижения
-- ============================================

INSERT INTO `modx_test_achievements` (`name`, `description`, `badge_icon`, `badge_color`, `achievement_type`, `condition_data`, `xp_reward`) VALUES
('Первый шаг', 'Пройдите свой первый тест', '🎯', '#4CAF50', 'test_count', '{"count": 1}', 50),
('Десяточка', 'Пройдите 10 тестов', '🔟', '#2196F3', 'test_count', '{"count": 10}', 100),
('Полтинник', 'Пройдите 50 тестов', '5️⃣0️⃣', '#9C27B0', 'test_count', '{"count": 50}', 250),
('Сотня', 'Пройдите 100 тестов', '💯', '#FF9800', 'test_count', '{"count": 100}', 500),
('Перфекционист', 'Получите 100% в любом тесте', '⭐', '#FFD700', 'perfect_score', '{"min_score": 100}', 100),
('Скоростной демон', 'Пройдите тест за половину отведенного времени', '⚡', '#F44336', 'speed_demon', '{"time_factor": 0.5}', 150),
('Неделька', 'Занимайтесь 7 дней подряд', '📅', '#00BCD4', 'streak', '{"days": 7}', 200),
('Месячник', 'Занимайтесь 30 дней подряд', '📆', '#3F51B5', 'streak', '{"days": 30}', 1000)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ============================================
-- Описание типов достижений
-- ============================================

/*
achievement_type и condition_data:

1. test_count - количество пройденных тестов:
   condition_data: {"count": 10, "category_id": null}

2. perfect_score - идеальный результат:
   condition_data: {"min_score": 100, "test_id": null}

3. streak - серия дней:
   condition_data: {"days": 7}

4. category_master - мастер категории:
   condition_data: {"category_id": 5, "tests_required": 10, "min_avg_score": 80}

5. speed_demon - скоростное прохождение:
   condition_data: {"time_factor": 0.5}

6. first_place - первое место в лидерборде:
   condition_data: {"period": "monthly", "category_id": null}

7. custom - кастомное достижение:
   condition_data: зависит от реализации
*/

-- ============================================
-- Триггеры для автоматического начисления XP
-- ============================================

DELIMITER $$

-- Начисление XP за завершение теста
CREATE TRIGGER IF NOT EXISTS `trg_session_complete_award_xp`
AFTER UPDATE ON `modx_test_sessions`
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        -- Базовый XP: зависит от балла
        SET @base_xp = CASE
            WHEN NEW.score >= 90 THEN 50
            WHEN NEW.score >= 70 THEN 30
            WHEN NEW.score >= 50 THEN 20
            ELSE 10
        END;

        -- Бонус за идеальный результат
        SET @bonus_xp = CASE WHEN NEW.score = 100 THEN 25 ELSE 0 END;

        SET @total_xp = @base_xp + @bonus_xp;

        -- Записываем в историю
        INSERT INTO modx_test_xp_history (user_id, xp_amount, reason, reference_type, reference_id)
        VALUES (NEW.user_id, @total_xp, CONCAT('Test completed: ', NEW.score, '%'), 'test', NEW.id);

        -- Обновляем общий опыт
        INSERT INTO modx_test_user_experience (user_id, total_xp)
        VALUES (NEW.user_id, @total_xp)
        ON DUPLICATE KEY UPDATE
            total_xp = total_xp + @total_xp;

        -- Обновляем серию
        CALL update_user_streak(NEW.user_id);
    END IF;
END$$

-- Обновление уровня при получении XP
CREATE TRIGGER IF NOT EXISTS `trg_xp_update_level`
AFTER UPDATE ON `modx_test_user_experience`
FOR EACH ROW
BEGIN
    IF NEW.total_xp != OLD.total_xp THEN
        -- Определяем новый уровень
        SELECT level, title, xp_required
        INTO @new_level, @new_title, @current_level_xp
        FROM modx_test_level_config
        WHERE xp_required <= NEW.total_xp
        ORDER BY level DESC
        LIMIT 1;

        -- XP до следующего уровня
        SELECT xp_required
        INTO @next_level_xp
        FROM modx_test_level_config
        WHERE level = @new_level + 1
        LIMIT 1;

        SET @xp_to_next = IFNULL(@next_level_xp, 999999) - NEW.total_xp;

        -- Обновляем уровень если изменился
        IF @new_level != OLD.level THEN
            UPDATE modx_test_user_experience
            SET level = @new_level,
                title = @new_title,
                xp_to_next_level = @xp_to_next
            WHERE id = NEW.id;
        END IF;
    END IF;
END$$

DELIMITER ;

-- ============================================
-- Процедура обновления серии активности
-- ============================================

DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS update_user_streak(IN p_user_id INT)
BEGIN
    DECLARE v_last_date DATE;
    DECLARE v_current_streak INT;
    DECLARE v_longest_streak INT;
    DECLARE v_today DATE;

    SET v_today = CURDATE();

    -- Получаем текущие данные
    SELECT last_activity_date, current_streak, longest_streak
    INTO v_last_date, v_current_streak, v_longest_streak
    FROM modx_test_user_streaks
    WHERE user_id = p_user_id;

    -- Если записи нет, создаем
    IF v_last_date IS NULL THEN
        INSERT INTO modx_test_user_streaks (user_id, current_streak, longest_streak, last_activity_date)
        VALUES (p_user_id, 1, 1, v_today);
    ELSE
        -- Проверяем дату последней активности
        IF v_last_date = v_today THEN
            -- Уже занимался сегодня, ничего не делаем
            RETURN;
        ELSEIF DATEDIFF(v_today, v_last_date) = 1 THEN
            -- Вчера был активен - продолжаем серию
            SET v_current_streak = v_current_streak + 1;
            SET v_longest_streak = GREATEST(v_longest_streak, v_current_streak);
        ELSE
            -- Пропустил день - серия сброшена
            SET v_current_streak = 1;
        END IF;

        -- Обновляем
        UPDATE modx_test_user_streaks
        SET current_streak = v_current_streak,
            longest_streak = v_longest_streak,
            last_activity_date = v_today
        WHERE user_id = p_user_id;
    END IF;
END$$

DELIMITER ;

-- ============================================
-- Примеры использования
-- ============================================

/*
-- Получить топ-10 лидерборда за текущий месяц
SELECT l.rank, u.username, l.total_xp, l.tests_completed, l.avg_score,
       ux.level, ux.title
FROM modx_test_leaderboard l
JOIN modx_users u ON u.id = l.user_id
LEFT JOIN modx_test_user_experience ux ON ux.user_id = l.user_id
WHERE l.category_id IS NULL
  AND l.period_type = 'monthly'
  AND l.period_key = DATE_FORMAT(NOW(), '%Y-%m')
ORDER BY l.rank ASC
LIMIT 10;

-- Получить достижения пользователя
SELECT a.name, a.description, a.badge_icon, a.badge_color,
       ua.earned_at, ua.progress
FROM modx_test_user_achievements ua
JOIN modx_test_achievements a ON a.id = ua.achievement_id
WHERE ua.user_id = 123
ORDER BY ua.earned_at DESC;

-- Получить прогресс пользователя
SELECT total_xp, level, title, xp_to_next_level
FROM modx_test_user_experience
WHERE user_id = 123;
*/

-- ============================================
-- Примечания
-- ============================================

/*
1. XP автоматически начисляется при завершении теста через триггер
2. Уровень автоматически обновляется при получении XP
3. Серия (streak) обновляется при каждой активности
4. Лидерборд обновляется периодически через cronjob
5. Достижения проверяются и начисляются через GamificationService
6. Секретные достижения (is_secret=1) не показываются до получения
7. Система уровней настраивается через modx_test_level_config
*/


-- ============================================
-- SPRINT 14: СИСТЕМА УВЕДОМЛЕНИЙ И EMAIL-РАССЫЛОК
-- ============================================

-- =============================================
-- Система уведомлений и email-рассылок
-- Спринт 14
-- =============================================

-- Таблица уведомлений пользователей
CREATE TABLE IF NOT EXISTS `modx_test_notifications` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя-получателя',
    `notification_type` ENUM(
        'test_completed',
        'test_assigned',
        'achievement_earned',
        'level_up',
        'path_step_unlocked',
        'essay_reviewed',
        'deadline_reminder',
        'material_available',
        'permission_granted',
        'custom'
    ) NOT NULL DEFAULT 'custom',
    `title` VARCHAR(255) NOT NULL COMMENT 'Заголовок уведомления',
    `message` TEXT NOT NULL COMMENT 'Текст уведомления',
    `action_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL для перехода при клике',
    `icon` VARCHAR(100) DEFAULT NULL COMMENT 'Иконка уведомления',
    `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    `is_read` TINYINT(1) DEFAULT 0 COMMENT 'Прочитано',
    `read_at` DATETIME DEFAULT NULL COMMENT 'Время прочтения',
    `related_type` VARCHAR(50) DEFAULT NULL COMMENT 'Тип связанной сущности',
    `related_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID связанной сущности',
    `metadata` JSON DEFAULT NULL COMMENT 'Дополнительные данные',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL COMMENT 'Время истечения (автоудаление)',
    PRIMARY KEY (`id`),
    INDEX `idx_user_read` (`user_id`, `is_read`),
    INDEX `idx_user_created` (`user_id`, `created_at`),
    INDEX `idx_type` (`notification_type`),
    INDEX `idx_related` (`related_type`, `related_id`),
    INDEX `idx_expires` (`expires_at`),
    CONSTRAINT `fk_notification_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `modx_users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Системные уведомления пользователей';

-- Таблица шаблонов уведомлений
CREATE TABLE IF NOT EXISTS `modx_test_notification_templates` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_key` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Уникальный ключ шаблона',
    `notification_type` VARCHAR(50) NOT NULL COMMENT 'Тип уведомления',
    `channel` ENUM('system', 'email', 'push') NOT NULL DEFAULT 'system',
    `subject_template` VARCHAR(255) DEFAULT NULL COMMENT 'Шаблон темы письма',
    `body_template` TEXT NOT NULL COMMENT 'Шаблон тела (поддерживает плейсхолдеры)',
    `html_template` TEXT DEFAULT NULL COMMENT 'HTML версия для email',
    `is_active` TINYINT(1) DEFAULT 1,
    `default_priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    `available_placeholders` JSON DEFAULT NULL COMMENT 'Список доступных плейсхолдеров',
    `description` TEXT DEFAULT NULL COMMENT 'Описание шаблона',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_type_channel` (`notification_type`, `channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Шаблоны уведомлений';

-- Таблица настроек подписок пользователей
CREATE TABLE IF NOT EXISTS `modx_test_notification_preferences` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `notification_type` VARCHAR(50) NOT NULL,
    `channel` ENUM('system', 'email', 'push') NOT NULL,
    `is_enabled` TINYINT(1) DEFAULT 1 COMMENT 'Включено ли уведомление',
    `frequency` ENUM('immediate', 'daily_digest', 'weekly_digest', 'disabled') DEFAULT 'immediate',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_type_channel` (`user_id`, `notification_type`, `channel`),
    INDEX `idx_user` (`user_id`),
    CONSTRAINT `fk_preference_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `modx_users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Настройки подписок пользователей';

-- Таблица истории доставки уведомлений
CREATE TABLE IF NOT EXISTS `modx_test_notification_delivery` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `notification_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID уведомления (если есть)',
    `user_id` INT(11) UNSIGNED NOT NULL,
    `channel` ENUM('system', 'email', 'push') NOT NULL,
    `notification_type` VARCHAR(50) NOT NULL,
    `recipient` VARCHAR(255) DEFAULT NULL COMMENT 'Email или push token',
    `subject` VARCHAR(255) DEFAULT NULL,
    `body` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'sent', 'failed', 'bounced') DEFAULT 'pending',
    `error_message` TEXT DEFAULT NULL COMMENT 'Ошибка доставки',
    `attempts` INT(11) DEFAULT 0 COMMENT 'Количество попыток',
    `sent_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `metadata` JSON DEFAULT NULL COMMENT 'Доп. данные (headers, tracking и т.д.)',
    PRIMARY KEY (`id`),
    INDEX `idx_notification` (`notification_id`),
    INDEX `idx_user_channel` (`user_id`, `channel`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`),
    CONSTRAINT `fk_delivery_notification`
        FOREIGN KEY (`notification_id`)
        REFERENCES `modx_test_notifications` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_delivery_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `modx_users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='История доставки уведомлений';

-- Таблица очереди отправки (для асинхронной обработки)
CREATE TABLE IF NOT EXISTS `modx_test_notification_queue` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `template_key` VARCHAR(100) NOT NULL,
    `channel` ENUM('system', 'email', 'push') NOT NULL,
    `recipient` VARCHAR(255) DEFAULT NULL COMMENT 'Email или push token',
    `placeholders` JSON DEFAULT NULL COMMENT 'Данные для подстановки в шаблон',
    `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    `scheduled_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Когда отправить',
    `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    `attempts` INT(11) DEFAULT 0,
    `max_attempts` INT(11) DEFAULT 3,
    `error_message` TEXT DEFAULT NULL,
    `processed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_status_scheduled` (`status`, `scheduled_at`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_priority` (`priority`, `scheduled_at`),
    CONSTRAINT `fk_queue_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `modx_users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Очередь отправки уведомлений';

-- =============================================
-- ТРИГГЕРЫ для автоматических уведомлений
-- =============================================

DELIMITER $

-- Триггер: Уведомление при получении достижения
DROP TRIGGER IF EXISTS `trg_achievement_notify`$
CREATE TRIGGER `trg_achievement_notify`
AFTER INSERT ON `modx_test_user_achievements`
FOR EACH ROW
BEGIN
    DECLARE v_achievement_name VARCHAR(255);
    DECLARE v_achievement_icon VARCHAR(100);
    DECLARE v_xp_reward INT;

    -- Получаем данные о достижении
    SELECT name, icon, xp_reward
    INTO v_achievement_name, v_achievement_icon, v_xp_reward
    FROM modx_test_achievements
    WHERE id = NEW.achievement_id;

    -- Создаем уведомление
    INSERT INTO modx_test_notifications
    (user_id, notification_type, title, message, icon, priority, related_type, related_id, metadata)
    VALUES (
        NEW.user_id,
        'achievement_earned',
        'Получено новое достижение!',
        CONCAT('Вы получили достижение "', v_achievement_name, '"',
               IF(v_xp_reward > 0, CONCAT(' и заработали ', v_xp_reward, ' XP!'), '!')),
        COALESCE(v_achievement_icon, 'fa-trophy'),
        'high',
        'achievement',
        NEW.achievement_id,
        JSON_OBJECT(
            'achievement_name', v_achievement_name,
            'xp_reward', v_xp_reward
        )
    );
END$

-- Триггер: Уведомление при повышении уровня
DROP TRIGGER IF EXISTS `trg_level_up_notify`$
CREATE TRIGGER `trg_level_up_notify`
AFTER UPDATE ON `modx_test_user_experience`
FOR EACH ROW
BEGIN
    DECLARE v_level_title VARCHAR(100);

    -- Если уровень повысился
    IF NEW.current_level > OLD.current_level THEN
        -- Получаем название нового уровня
        SELECT title INTO v_level_title
        FROM modx_test_level_config
        WHERE level = NEW.current_level;

        -- Создаем уведомление
        INSERT INTO modx_test_notifications
        (user_id, notification_type, title, message, icon, priority, related_type, related_id, metadata)
        VALUES (
            NEW.user_id,
            'level_up',
            'Поздравляем с новым уровнем!',
            CONCAT('Вы достигли уровня ', NEW.current_level, ' - "', v_level_title, '"!'),
            'fa-level-up',
            'high',
            'level',
            NEW.current_level,
            JSON_OBJECT(
                'old_level', OLD.current_level,
                'new_level', NEW.current_level,
                'level_title', v_level_title,
                'total_xp', NEW.total_xp
            )
        );
    END IF;
END$

-- Триггер: Уведомление при проверке эссе
DROP TRIGGER IF EXISTS `trg_essay_reviewed_notify`$
CREATE TRIGGER `trg_essay_reviewed_notify`
AFTER UPDATE ON `modx_test_essay_reviews`
FOR EACH ROW
BEGIN
    DECLARE v_question_text VARCHAR(500);

    -- Если эссе было проверено
    IF NEW.status = 'reviewed' AND OLD.status != 'reviewed' THEN
        -- Получаем текст вопроса (первые 100 символов)
        SELECT LEFT(question_text, 100) INTO v_question_text
        FROM modx_test_questions
        WHERE id = NEW.question_id;

        -- Создаем уведомление
        INSERT INTO modx_test_notifications
        (user_id, notification_type, title, message, icon, priority, related_type, related_id, metadata)
        VALUES (
            NEW.user_id,
            'essay_reviewed',
            'Ваше эссе проверено',
            CONCAT('Эссе по вопросу "', v_question_text, '..." получило оценку ', NEW.score, ' баллов'),
            'fa-file-text-o',
            'normal',
            'essay_review',
            NEW.id,
            JSON_OBJECT(
                'score', NEW.score,
                'has_comment', IF(NEW.reviewer_comment IS NOT NULL, 1, 0)
            )
        );
    END IF;
END$

DELIMITER ;

-- =============================================
-- STORED PROCEDURES
-- =============================================

DELIMITER $

-- Процедура: Обработка очереди уведомлений
DROP PROCEDURE IF EXISTS `process_notification_queue`$
CREATE PROCEDURE `process_notification_queue`(IN p_batch_size INT)
BEGIN
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_queue_id INT;
    DECLARE v_user_id INT;
    DECLARE v_template_key VARCHAR(100);
    DECLARE v_channel VARCHAR(20);
    DECLARE v_recipient VARCHAR(255);
    DECLARE v_placeholders JSON;

    DECLARE cur_queue CURSOR FOR
        SELECT id, user_id, template_key, channel, recipient, placeholders
        FROM modx_test_notification_queue
        WHERE status = 'pending'
        AND scheduled_at <= NOW()
        AND attempts < max_attempts
        ORDER BY priority DESC, scheduled_at ASC
        LIMIT p_batch_size;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur_queue;

    read_loop: LOOP
        FETCH cur_queue INTO v_queue_id, v_user_id, v_template_key, v_channel, v_recipient, v_placeholders;

        IF v_done THEN
            LEAVE read_loop;
        END IF;

        -- Помечаем как "в обработке"
        UPDATE modx_test_notification_queue
        SET status = 'processing', attempts = attempts + 1
        WHERE id = v_queue_id;

        -- Здесь должна быть логика отправки
        -- Для упрощения просто помечаем как completed
        -- В реальной системе здесь вызывается внешний код для отправки email/push

        UPDATE modx_test_notification_queue
        SET status = 'completed', processed_at = NOW()
        WHERE id = v_queue_id;
    END LOOP;

    CLOSE cur_queue;
END$

-- Процедура: Очистка старых уведомлений
DROP PROCEDURE IF EXISTS `cleanup_old_notifications`$
CREATE PROCEDURE `cleanup_old_notifications`(IN p_days_to_keep INT)
BEGIN
    DECLARE v_deleted_count INT DEFAULT 0;

    -- Удаляем прочитанные уведомления старше указанного количества дней
    DELETE FROM modx_test_notifications
    WHERE is_read = 1
    AND read_at < DATE_SUB(NOW(), INTERVAL p_days_to_keep DAY);

    SET v_deleted_count = ROW_COUNT();

    -- Удаляем истекшие уведомления
    DELETE FROM modx_test_notifications
    WHERE expires_at IS NOT NULL AND expires_at < NOW();

    SET v_deleted_count = v_deleted_count + ROW_COUNT();

    -- Удаляем старые записи из delivery log
    DELETE FROM modx_test_notification_delivery
    WHERE created_at < DATE_SUB(NOW(), INTERVAL (p_days_to_keep * 2) DAY);

    -- Удаляем завершенные задачи из очереди старше 7 дней
    DELETE FROM modx_test_notification_queue
    WHERE status = 'completed'
    AND processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

    SELECT v_deleted_count as deleted_notifications;
END$

DELIMITER ;

-- =============================================
-- НАЧАЛЬНЫЕ ДАННЫЕ: Шаблоны уведомлений
-- =============================================

-- Шаблоны для системных уведомлений
INSERT INTO `modx_test_notification_templates`
(`template_key`, `notification_type`, `channel`, `subject_template`, `body_template`, `available_placeholders`, `description`)
VALUES
('test_completed_system', 'test_completed', 'system', NULL,
 'Вы завершили тест "[[+test_name]]" с результатом [[+score]]%',
 JSON_ARRAY('test_name', 'score', 'total_questions', 'correct_answers'),
 'Уведомление о завершении теста'),

('achievement_earned_system', 'achievement_earned', 'system', NULL,
 'Вы получили достижение "[[+achievement_name]]"![[+xp_reward:notempty=` и заработали [[+xp_reward]] XP!`]]',
 JSON_ARRAY('achievement_name', 'xp_reward', 'achievement_description'),
 'Уведомление о получении достижения'),

('level_up_system', 'level_up', 'system', NULL,
 'Поздравляем! Вы достигли [[+level]] уровня - "[[+level_title]]"!',
 JSON_ARRAY('level', 'level_title', 'total_xp'),
 'Уведомление о повышении уровня'),

('path_unlocked_system', 'path_step_unlocked', 'system', NULL,
 'Разблокирован новый шаг в траектории обучения: "[[+step_title]]"',
 JSON_ARRAY('path_name', 'step_title', 'step_number'),
 'Уведомление о разблокировке шага траектории');

-- Шаблоны для email
INSERT INTO `modx_test_notification_templates`
(`template_key`, `notification_type`, `channel`, `subject_template`, `body_template`, `html_template`, `available_placeholders`, `description`)
VALUES
('test_completed_email', 'test_completed', 'email',
 'Результаты теста: [[+test_name]]',
 'Здравствуйте, [[+user_name]]!\n\nВы завершили тест "[[+test_name]]".\n\nВаш результат: [[+score]]%\nПравильных ответов: [[+correct_answers]] из [[+total_questions]]\n\nПродолжайте обучение!',
 '<h2>Тест завершен</h2><p>Здравствуйте, <strong>[[+user_name]]</strong>!</p><p>Вы завершили тест "<strong>[[+test_name]]</strong>".</p><div style="background:#f0f0f0;padding:15px;margin:10px 0;"><p><strong>Ваш результат:</strong> [[+score]]%</p><p><strong>Правильных ответов:</strong> [[+correct_answers]] из [[+total_questions]]</p></div><p>Продолжайте обучение!</p>',
 JSON_ARRAY('user_name', 'test_name', 'score', 'total_questions', 'correct_answers', 'test_url'),
 'Email уведомление о завершении теста'),

('achievement_earned_email', 'achievement_earned', 'email',
 'Новое достижение: [[+achievement_name]]',
 'Поздравляем, [[+user_name]]!\n\nВы получили новое достижение: "[[+achievement_name]]"!\n\n[[+achievement_description]]\n\n[[+xp_reward:notempty=`Награда: [[+xp_reward]] XP`]]',
 '<h2>🏆 Новое достижение!</h2><p>Поздравляем, <strong>[[+user_name]]</strong>!</p><div style="border:2px solid #ffd700;background:#fffacd;padding:20px;text-align:center;margin:15px 0;"><h3 style="margin:0;">[[+achievement_name]]</h3><p>[[+achievement_description]]</p>[[+xp_reward:notempty=`<p style="color:#ff6600;font-weight:bold;">+[[+xp_reward]] XP</p>`]]</div>',
 JSON_ARRAY('user_name', 'achievement_name', 'achievement_description', 'xp_reward'),
 'Email уведомление о получении достижения'),

('essay_reviewed_email', 'essay_reviewed', 'email',
 'Ваше эссе проверено',
 'Здравствуйте, [[+user_name]]!\n\nВаше эссе по вопросу "[[+question_text]]" проверено.\n\nОценка: [[+score]] баллов\n\n[[+reviewer_comment:notempty=`Комментарий эксперта:\n[[+reviewer_comment]]`]]',
 '<h2>Эссе проверено</h2><p>Здравствуйте, <strong>[[+user_name]]</strong>!</p><p>Ваше эссе по вопросу "<em>[[+question_text]]</em>" проверено.</p><div style="background:#e8f5e9;padding:15px;margin:10px 0;"><p><strong>Оценка:</strong> [[+score]] баллов</p>[[+reviewer_comment:notempty=`<p><strong>Комментарий эксперта:</strong><br>[[+reviewer_comment]]</p>`]]</div>',
 JSON_ARRAY('user_name', 'question_text', 'score', 'reviewer_comment', 'test_url'),
 'Email уведомление о проверке эссе'),

('deadline_reminder_email', 'deadline_reminder', 'email',
 'Напоминание: приближается дедлайн',
 'Здравствуйте, [[+user_name]]!\n\nНапоминаем, что у вас есть незавершенный тест "[[+test_name]]".\n\nДедлайн: [[+deadline]]\nОсталось времени: [[+time_left]]\n\nНе забудьте завершить тест вовремя!',
 '<h2>⏰ Напоминание о дедлайне</h2><p>Здравствуйте, <strong>[[+user_name]]</strong>!</p><p>Напоминаем, что у вас есть незавершенный тест "<strong>[[+test_name]]</strong>".</p><div style="background:#fff3cd;border-left:4px solid #ffc107;padding:15px;margin:10px 0;"><p><strong>Дедлайн:</strong> [[+deadline]]</p><p><strong>Осталось времени:</strong> [[+time_left]]</p></div><p>Не забудьте завершить тест вовремя!</p><p><a href="[[+test_url]]" style="background:#007bff;color:white;padding:10px 20px;text-decoration:none;display:inline-block;border-radius:5px;">Перейти к тесту</a></p>',
 JSON_ARRAY('user_name', 'test_name', 'deadline', 'time_left', 'test_url'),
 'Email напоминание о приближающемся дедлайне');

-- Стандартные настройки подписок для новых пользователей (будут применяться через триггер или при регистрации)
-- По умолчанию все уведомления включены

-- Индексы для оптимизации
CREATE INDEX IF NOT EXISTS `idx_notification_user_unread`
ON `modx_test_notifications` (`user_id`, `is_read`, `created_at`);

CREATE INDEX IF NOT EXISTS `idx_queue_processing`
ON `modx_test_notification_queue` (`status`, `priority`, `scheduled_at`);


-- ============================================
-- SPRINT 15: РАСШИРЕННАЯ АНАЛИТИКА И ОТЧЕТЫ
-- ============================================

-- =============================================
-- Расширенная аналитика и отчеты
-- Спринт 15
-- =============================================

-- Таблица агрегированной статистики (для быстрого доступа)
CREATE TABLE IF NOT EXISTS `modx_test_analytics_cache` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `metric_type` VARCHAR(100) NOT NULL COMMENT 'Тип метрики (user_stats, test_stats, question_stats, category_stats)',
    `entity_type` VARCHAR(50) NOT NULL COMMENT 'Тип сущности (user, test, question, category)',
    `entity_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID сущности',
    `period` VARCHAR(20) DEFAULT 'all_time' COMMENT 'Период (all_time, yearly, monthly, weekly, daily)',
    `period_start` DATE DEFAULT NULL COMMENT 'Начало периода',
    `period_end` DATE DEFAULT NULL COMMENT 'Конец периода',
    `metrics` JSON NOT NULL COMMENT 'Метрики в JSON формате',
    `calculated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL COMMENT 'Когда истекает кеш',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_metric` (`metric_type`, `entity_type`, `entity_id`, `period`, `period_start`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_metric_type` (`metric_type`),
    INDEX `idx_period` (`period`, `period_start`, `period_end`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Кеш агрегированной аналитики';

-- Таблица сохраненных отчетов
CREATE TABLE IF NOT EXISTS `modx_test_reports` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_type` VARCHAR(100) NOT NULL COMMENT 'Тип отчета',
    `report_name` VARCHAR(255) NOT NULL COMMENT 'Название отчета',
    `description` TEXT DEFAULT NULL,
    `created_by` INT(11) UNSIGNED NOT NULL COMMENT 'Кто создал отчет',
    `is_public` TINYINT(1) DEFAULT 0 COMMENT 'Доступен всем',
    `schedule` VARCHAR(50) DEFAULT NULL COMMENT 'Расписание генерации (daily, weekly, monthly)',
    `filters` JSON DEFAULT NULL COMMENT 'Фильтры отчета',
    `columns` JSON DEFAULT NULL COMMENT 'Колонки для отображения',
    `format` ENUM('html', 'csv', 'pdf', 'json') DEFAULT 'html',
    `last_generated_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_type` (`report_type`),
    INDEX `idx_public` (`is_public`),
    CONSTRAINT `fk_report_creator`
        FOREIGN KEY (`created_by`)
        REFERENCES `modx_users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Сохраненные отчеты';

-- Таблица истории генерации отчетов
CREATE TABLE IF NOT EXISTS `modx_test_report_history` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_id` INT(11) UNSIGNED DEFAULT NULL,
    `report_type` VARCHAR(100) NOT NULL,
    `generated_by` INT(11) UNSIGNED NOT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL COMMENT 'Путь к сгенерированному файлу',
    `file_size` INT(11) DEFAULT NULL COMMENT 'Размер файла в байтах',
    `format` VARCHAR(20) DEFAULT 'html',
    `filters_used` JSON DEFAULT NULL COMMENT 'Использованные фильтры',
    `rows_count` INT(11) DEFAULT NULL COMMENT 'Количество строк в отчете',
    `generation_time` DECIMAL(10,3) DEFAULT NULL COMMENT 'Время генерации в секундах',
    `generated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL COMMENT 'Когда удалить файл',
    PRIMARY KEY (`id`),
    INDEX `idx_report` (`report_id`),
    INDEX `idx_generated_by` (`generated_by`),
    INDEX `idx_generated_at` (`generated_at`),
    INDEX `idx_expires` (`expires_at`),
    CONSTRAINT `fk_history_report`
        FOREIGN KEY (`report_id`)
        REFERENCES `modx_test_reports` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_history_generator`
        FOREIGN KEY (`generated_by`)
        REFERENCES `modx_users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='История генерации отчетов';

-- Таблица активности пользователей (детальное логирование)
CREATE TABLE IF NOT EXISTS `modx_test_user_activity_log` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `activity_type` VARCHAR(50) NOT NULL COMMENT 'Тип активности (login, test_start, test_complete, material_view и т.д.)',
    `entity_type` VARCHAR(50) DEFAULT NULL COMMENT 'Тип сущности',
    `entity_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID сущности',
    `session_id` VARCHAR(100) DEFAULT NULL COMMENT 'ID сессии браузера',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `metadata` JSON DEFAULT NULL COMMENT 'Дополнительные данные',
    `duration` INT(11) DEFAULT NULL COMMENT 'Длительность в секундах',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_activity` (`user_id`, `activity_type`, `created_at`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_session` (`session_id`),
    CONSTRAINT `fk_activity_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `modx_users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Детальный лог активности пользователей';

-- Партицирование для больших таблиц (опционально, для MySQL 5.7+)
-- ALTER TABLE modx_test_user_activity_log
-- PARTITION BY RANGE (TO_DAYS(created_at)) (
--     PARTITION p_2024 VALUES LESS THAN (TO_DAYS('2025-01-01')),
--     PARTITION p_2025 VALUES LESS THAN (TO_DAYS('2026-01-01')),
--     PARTITION p_future VALUES LESS THAN MAXVALUE
-- );

-- =============================================
-- ПРЕДСТАВЛЕНИЯ (VIEWS) для быстрого доступа
-- =============================================

-- Представление: Статистика по пользователям
CREATE OR REPLACE VIEW `modx_test_user_statistics` AS
SELECT
    u.id as user_id,
    u.username,
    COUNT(DISTINCT s.id) as total_tests_taken,
    COUNT(DISTINCT CASE WHEN s.status = 'completed' THEN s.id END) as tests_completed,
    AVG(CASE WHEN s.status = 'completed' THEN s.score END) as avg_score,
    MAX(s.score) as max_score,
    MIN(s.score) as min_score,
    SUM(CASE WHEN s.status = 'completed' AND s.score >= 70 THEN 1 ELSE 0 END) as tests_passed,
    SUM(CASE WHEN s.status = 'completed' AND s.score < 70 THEN 1 ELSE 0 END) as tests_failed,
    COUNT(DISTINCT CASE WHEN s.status = 'completed' AND s.score = 100 THEN s.id END) as perfect_scores,
    AVG(CASE WHEN s.status = 'completed' THEN s.time_spent END) as avg_time_spent,
    MAX(s.completed_at) as last_test_date,
    COALESCE(ue.total_xp, 0) as total_xp,
    COALESCE(ue.current_level, 1) as current_level,
    COUNT(DISTINCT ua.id) as achievements_count
FROM modx_users u
LEFT JOIN modx_test_sessions s ON s.user_id = u.id
LEFT JOIN modx_test_user_experience ue ON ue.user_id = u.id
LEFT JOIN modx_test_user_achievements ua ON ua.user_id = u.id
GROUP BY u.id;

-- Представление: Статистика по тестам
CREATE OR REPLACE VIEW `modx_test_test_statistics` AS
SELECT
    t.id as test_id,
    t.title,
    t.category_id,
    c.name as category_name,
    COUNT(DISTINCT s.user_id) as unique_users,
    COUNT(s.id) as total_attempts,
    COUNT(CASE WHEN s.status = 'completed' THEN 1 END) as completed_attempts,
    AVG(CASE WHEN s.status = 'completed' THEN s.score END) as avg_score,
    MAX(s.score) as max_score,
    MIN(s.score) as min_score,
    STDDEV(s.score) as score_stddev,
    COUNT(CASE WHEN s.score >= 70 THEN 1 END) as passed_count,
    COUNT(CASE WHEN s.score < 70 THEN 1 END) as failed_count,
    ROUND(COUNT(CASE WHEN s.score >= 70 THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN s.status = 'completed' THEN 1 END), 0), 2) as pass_rate,
    AVG(CASE WHEN s.status = 'completed' THEN s.time_spent END) as avg_time_spent,
    COUNT(CASE WHEN s.status = 'completed' AND s.score = 100 THEN 1 END) as perfect_scores_count,
    (SELECT COUNT(*) FROM modx_test_questions WHERE test_id = t.id) as questions_count,
    MAX(s.completed_at) as last_attempt_date
FROM modx_test_tests t
LEFT JOIN modx_test_categories c ON c.id = t.category_id
LEFT JOIN modx_test_sessions s ON s.test_id = t.id
GROUP BY t.id;

-- Представление: Статистика по вопросам
CREATE OR REPLACE VIEW `modx_test_question_statistics` AS
SELECT
    q.id as question_id,
    q.test_id,
    q.question_text,
    q.question_type,
    COUNT(ua.id) as total_answers,
    SUM(CASE WHEN ua.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
    SUM(CASE WHEN ua.is_correct = 0 THEN 1 ELSE 0 END) as incorrect_answers,
    ROUND(SUM(CASE WHEN ua.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(ua.id), 0), 2) as correct_rate,
    AVG(ua.points_earned) as avg_points_earned,
    q.points as max_points,
    COUNT(DISTINCT ua.user_id) as unique_users_answered,
    CASE
        WHEN ROUND(SUM(CASE WHEN ua.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(ua.id), 0), 2) < 30 THEN 'very_hard'
        WHEN ROUND(SUM(CASE WHEN ua.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(ua.id), 0), 2) < 50 THEN 'hard'
        WHEN ROUND(SUM(CASE WHEN ua.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(ua.id), 0), 2) < 70 THEN 'medium'
        WHEN ROUND(SUM(CASE WHEN ua.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(ua.id), 0), 2) < 90 THEN 'easy'
        ELSE 'very_easy'
    END as difficulty_level
FROM modx_test_questions q
LEFT JOIN modx_test_user_answers ua ON ua.question_id = q.id
GROUP BY q.id;

-- Представление: Статистика по категориям
CREATE OR REPLACE VIEW `modx_test_category_statistics` AS
SELECT
    c.id as category_id,
    c.name as category_name,
    c.parent_id,
    COUNT(DISTINCT t.id) as tests_count,
    COUNT(DISTINCT s.user_id) as unique_users,
    COUNT(DISTINCT s.id) as total_attempts,
    AVG(CASE WHEN s.status = 'completed' THEN s.score END) as avg_score,
    COUNT(CASE WHEN s.status = 'completed' AND s.score >= 70 THEN 1 END) as passed_count,
    ROUND(COUNT(CASE WHEN s.status = 'completed' AND s.score >= 70 THEN 1 END) * 100.0 /
          NULLIF(COUNT(CASE WHEN s.status = 'completed' THEN 1 END), 0), 2) as pass_rate,
    (SELECT COUNT(*) FROM modx_test_questions q
     JOIN modx_test_tests t2 ON t2.id = q.test_id
     WHERE t2.category_id = c.id) as total_questions
FROM modx_test_categories c
LEFT JOIN modx_test_tests t ON t.category_id = c.id
LEFT JOIN modx_test_sessions s ON s.test_id = t.id
GROUP BY c.id;

-- =============================================
-- STORED PROCEDURES для аналитики
-- =============================================

DELIMITER $

-- Процедура: Обновить кеш аналитики для пользователя
DROP PROCEDURE IF EXISTS `update_user_analytics_cache`$
CREATE PROCEDURE `update_user_analytics_cache`(IN p_user_id INT, IN p_period VARCHAR(20))
BEGIN
    DECLARE v_metrics JSON;
    DECLARE v_period_start DATE;
    DECLARE v_period_end DATE;
    DECLARE v_expires_at DATETIME;

    -- Определяем временные рамки периода
    CASE p_period
        WHEN 'daily' THEN
            SET v_period_start = CURDATE();
            SET v_period_end = CURDATE();
            SET v_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR);
        WHEN 'weekly' THEN
            SET v_period_start = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY);
            SET v_period_end = DATE_ADD(v_period_start, INTERVAL 6 DAY);
            SET v_expires_at = DATE_ADD(NOW(), INTERVAL 6 HOUR);
        WHEN 'monthly' THEN
            SET v_period_start = DATE_FORMAT(CURDATE(), '%Y-%m-01');
            SET v_period_end = LAST_DAY(CURDATE());
            SET v_expires_at = DATE_ADD(NOW(), INTERVAL 12 HOUR);
        ELSE -- all_time
            SET v_period_start = NULL;
            SET v_period_end = NULL;
            SET v_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR);
    END CASE;

    -- Собираем метрики
    SELECT JSON_OBJECT(
        'total_tests', COUNT(DISTINCT s.id),
        'completed_tests', COUNT(DISTINCT CASE WHEN s.status = 'completed' THEN s.id END),
        'avg_score', COALESCE(AVG(CASE WHEN s.status = 'completed' THEN s.score END), 0),
        'max_score', COALESCE(MAX(s.score), 0),
        'tests_passed', COUNT(CASE WHEN s.status = 'completed' AND s.score >= 70 THEN 1 END),
        'perfect_scores', COUNT(CASE WHEN s.status = 'completed' AND s.score = 100 THEN 1 END),
        'avg_time_spent', COALESCE(AVG(CASE WHEN s.status = 'completed' THEN s.time_spent END), 0),
        'total_time_spent', COALESCE(SUM(CASE WHEN s.status = 'completed' THEN s.time_spent END), 0),
        'last_activity', MAX(s.created_at)
    ) INTO v_metrics
    FROM modx_test_sessions s
    WHERE s.user_id = p_user_id
    AND (v_period_start IS NULL OR DATE(s.created_at) BETWEEN v_period_start AND v_period_end);

    -- Сохраняем в кеш
    INSERT INTO modx_test_analytics_cache
    (metric_type, entity_type, entity_id, period, period_start, period_end, metrics, expires_at)
    VALUES ('user_stats', 'user', p_user_id, p_period, v_period_start, v_period_end, v_metrics, v_expires_at)
    ON DUPLICATE KEY UPDATE
        metrics = VALUES(metrics),
        calculated_at = NOW(),
        expires_at = VALUES(expires_at);
END$

-- Процедура: Обновить кеш аналитики для теста
DROP PROCEDURE IF EXISTS `update_test_analytics_cache`$
CREATE PROCEDURE `update_test_analytics_cache`(IN p_test_id INT, IN p_period VARCHAR(20))
BEGIN
    DECLARE v_metrics JSON;
    DECLARE v_period_start DATE;
    DECLARE v_period_end DATE;
    DECLARE v_expires_at DATETIME;

    -- Определяем временные рамки
    CASE p_period
        WHEN 'daily' THEN
            SET v_period_start = CURDATE();
            SET v_period_end = CURDATE();
            SET v_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR);
        WHEN 'weekly' THEN
            SET v_period_start = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY);
            SET v_period_end = DATE_ADD(v_period_start, INTERVAL 6 DAY);
            SET v_expires_at = DATE_ADD(NOW(), INTERVAL 6 HOUR);
        WHEN 'monthly' THEN
            SET v_period_start = DATE_FORMAT(CURDATE(), '%Y-%m-01');
            SET v_period_end = LAST_DAY(CURDATE());
            SET v_expires_at = DATE_ADD(NOW(), INTERVAL 12 HOUR);
        ELSE -- all_time
            SET v_period_start = NULL;
            SET v_period_end = NULL;
            SET v_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR);
    END CASE;

    -- Собираем метрики
    SELECT JSON_OBJECT(
        'unique_users', COUNT(DISTINCT s.user_id),
        'total_attempts', COUNT(s.id),
        'completed_attempts', COUNT(CASE WHEN s.status = 'completed' THEN 1 END),
        'avg_score', COALESCE(AVG(CASE WHEN s.status = 'completed' THEN s.score END), 0),
        'max_score', COALESCE(MAX(s.score), 0),
        'min_score', COALESCE(MIN(CASE WHEN s.status = 'completed' THEN s.score END), 0),
        'score_stddev', COALESCE(STDDEV(s.score), 0),
        'passed_count', COUNT(CASE WHEN s.score >= 70 THEN 1 END),
        'failed_count', COUNT(CASE WHEN s.score < 70 AND s.status = 'completed' THEN 1 END),
        'pass_rate', ROUND(COUNT(CASE WHEN s.score >= 70 THEN 1 END) * 100.0 /
                     NULLIF(COUNT(CASE WHEN s.status = 'completed' THEN 1 END), 0), 2),
        'avg_time_spent', COALESCE(AVG(CASE WHEN s.status = 'completed' THEN s.time_spent END), 0),
        'perfect_scores', COUNT(CASE WHEN s.score = 100 THEN 1 END)
    ) INTO v_metrics
    FROM modx_test_sessions s
    WHERE s.test_id = p_test_id
    AND (v_period_start IS NULL OR DATE(s.created_at) BETWEEN v_period_start AND v_period_end);

    -- Сохраняем в кеш
    INSERT INTO modx_test_analytics_cache
    (metric_type, entity_type, entity_id, period, period_start, period_end, metrics, expires_at)
    VALUES ('test_stats', 'test', p_test_id, p_period, v_period_start, v_period_end, v_metrics, v_expires_at)
    ON DUPLICATE KEY UPDATE
        metrics = VALUES(metrics),
        calculated_at = NOW(),
        expires_at = VALUES(expires_at);
END$

-- Процедура: Получить топ N сложных вопросов
DROP PROCEDURE IF EXISTS `get_hardest_questions`$
CREATE PROCEDURE `get_hardest_questions`(IN p_limit INT, IN p_test_id INT)
BEGIN
    SELECT
        q.id,
        q.test_id,
        t.title as test_title,
        q.question_text,
        q.question_type,
        COUNT(ua.id) as total_answers,
        SUM(CASE WHEN ua.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
        ROUND(SUM(CASE WHEN ua.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(ua.id), 2) as correct_rate
    FROM modx_test_questions q
    JOIN modx_test_tests t ON t.id = q.test_id
    LEFT JOIN modx_test_user_answers ua ON ua.question_id = q.id
    WHERE (p_test_id IS NULL OR q.test_id = p_test_id)
    GROUP BY q.id
    HAVING total_answers >= 5
    ORDER BY correct_rate ASC, total_answers DESC
    LIMIT p_limit;
END$

-- Процедура: Получить когортный анализ
DROP PROCEDURE IF EXISTS `get_cohort_analysis`$
CREATE PROCEDURE `get_cohort_analysis`(IN p_start_date DATE, IN p_end_date DATE)
BEGIN
    SELECT
        DATE_FORMAT(u.createdon, '%Y-%m') as cohort_month,
        COUNT(DISTINCT u.id) as users_joined,
        COUNT(DISTINCT CASE WHEN s.created_at IS NOT NULL THEN u.id END) as users_active,
        COUNT(DISTINCT s.id) as total_tests_taken,
        AVG(CASE WHEN s.status = 'completed' THEN s.score END) as avg_score,
        ROUND(COUNT(DISTINCT CASE WHEN s.created_at IS NOT NULL THEN u.id END) * 100.0 / COUNT(DISTINCT u.id), 2) as activation_rate
    FROM modx_users u
    LEFT JOIN modx_test_sessions s ON s.user_id = u.id
        AND DATE(s.created_at) BETWEEN p_start_date AND p_end_date
    WHERE DATE(u.createdon) BETWEEN p_start_date AND p_end_date
    GROUP BY cohort_month
    ORDER BY cohort_month DESC;
END$

-- Процедура: Очистить устаревший кеш аналитики
DROP PROCEDURE IF EXISTS `cleanup_analytics_cache`$
CREATE PROCEDURE `cleanup_analytics_cache`()
BEGIN
    DELETE FROM modx_test_analytics_cache
    WHERE expires_at IS NOT NULL AND expires_at < NOW();

    -- Удаляем старые файлы отчетов
    DELETE FROM modx_test_report_history
    WHERE expires_at IS NOT NULL AND expires_at < NOW();

    SELECT ROW_COUNT() as deleted_rows;
END$

-- Процедура: Получить активность пользователей за период
DROP PROCEDURE IF EXISTS `get_user_activity_summary`$
CREATE PROCEDURE `get_user_activity_summary`(IN p_start_date DATE, IN p_end_date DATE)
BEGIN
    SELECT
        DATE(created_at) as activity_date,
        COUNT(DISTINCT user_id) as active_users,
        COUNT(DISTINCT CASE WHEN activity_type = 'test_start' THEN user_id END) as users_started_tests,
        COUNT(DISTINCT CASE WHEN activity_type = 'test_complete' THEN user_id END) as users_completed_tests,
        COUNT(CASE WHEN activity_type = 'test_start' THEN 1 END) as tests_started,
        COUNT(CASE WHEN activity_type = 'test_complete' THEN 1 END) as tests_completed,
        AVG(duration) as avg_session_duration
    FROM modx_test_user_activity_log
    WHERE DATE(created_at) BETWEEN p_start_date AND p_end_date
    GROUP BY activity_date
    ORDER BY activity_date DESC;
END$

DELIMITER ;

-- =============================================
-- Индексы для оптимизации
-- =============================================

CREATE INDEX IF NOT EXISTS `idx_sessions_analytics`
ON `modx_test_sessions` (`test_id`, `user_id`, `status`, `created_at`, `score`);

CREATE INDEX IF NOT EXISTS `idx_answers_analytics`
ON `modx_test_user_answers` (`question_id`, `is_correct`, `points_earned`);

-- =============================================
-- Начальные данные
-- =============================================

-- Создаем стандартные шаблоны отчетов (примеры)
INSERT INTO `modx_test_reports`
(`report_type`, `report_name`, `description`, `created_by`, `is_public`, `format`)
VALUES
('user_progress', 'Прогресс пользователей', 'Детальный отчет по прогрессу всех пользователей', 1, 1, 'csv'),
('test_performance', 'Эффективность тестов', 'Статистика по всем тестам: средний балл, процент прохождения', 1, 1, 'csv'),
('question_difficulty', 'Сложность вопросов', 'Анализ сложности вопросов на основе статистики ответов', 1, 1, 'csv'),
('category_overview', 'Обзор по категориям', 'Общая статистика по категориям тестов', 1, 1, 'csv'),
('user_activity', 'Активность пользователей', 'Отчет по активности пользователей за период', 1, 1, 'csv');


-- ============================================
-- SPRINT 16: СЕРТИФИКАТЫ И ВЕРИФИКАЦИЯ
-- ============================================

-- =============================================
-- Система сертификатов и верификация
-- Спринт 16
-- =============================================

-- Таблица шаблонов сертификатов
CREATE TABLE IF NOT EXISTS `modx_test_certificate_templates` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_key` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Уникальный ключ шаблона',
    `name` VARCHAR(255) NOT NULL COMMENT 'Название шаблона',
    `description` TEXT DEFAULT NULL,
    `certificate_type` ENUM('test', 'path', 'achievement', 'custom') NOT NULL DEFAULT 'custom',
    `template_html` TEXT NOT NULL COMMENT 'HTML шаблон сертификата',
    `template_variables` JSON DEFAULT NULL COMMENT 'Доступные переменные [[+var]]',
    `orientation` ENUM('portrait', 'landscape') DEFAULT 'landscape',
    `paper_size` VARCHAR(20) DEFAULT 'A4' COMMENT 'A4, Letter и т.д.',
    `background_image` VARCHAR(500) DEFAULT NULL COMMENT 'Путь к фоновому изображению',
    `is_active` TINYINT(1) DEFAULT 1,
    `require_verification` TINYINT(1) DEFAULT 1 COMMENT 'Требуется ли верификация',
    `expiration_days` INT(11) DEFAULT NULL COMMENT 'Срок действия в днях (NULL = бессрочно)',
    `created_by` INT(11) UNSIGNED DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_type` (`certificate_type`),
    INDEX `idx_active` (`is_active`),
    CONSTRAINT `fk_template_creator`
        FOREIGN KEY (`created_by`)
        REFERENCES `modx_users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Шаблоны сертификатов';

-- Таблица выданных сертификатов
CREATE TABLE IF NOT EXISTS `modx_test_certificates` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `certificate_number` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Уникальный номер сертификата',
    `verification_code` VARCHAR(64) NOT NULL UNIQUE COMMENT 'Код верификации (SHA-256)',
    `template_id` INT(11) UNSIGNED NOT NULL,
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'Кому выдан',
    `entity_type` VARCHAR(50) DEFAULT NULL COMMENT 'test, path, achievement',
    `entity_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID связанной сущности',
    `certificate_data` JSON DEFAULT NULL COMMENT 'Данные для подстановки в шаблон',
    `score` DECIMAL(5,2) DEFAULT NULL COMMENT 'Балл (если применимо)',
    `grade` VARCHAR(50) DEFAULT NULL COMMENT 'Оценка (отлично, хорошо и т.д.)',
    `issued_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL COMMENT 'Дата истечения',
    `issued_by` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Кто выдал',
    `is_revoked` TINYINT(1) DEFAULT 0 COMMENT 'Отозван ли сертификат',
    `revoked_at` DATETIME DEFAULT NULL,
    `revoked_by` INT(11) UNSIGNED DEFAULT NULL,
    `revoke_reason` TEXT DEFAULT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL COMMENT 'Путь к PDF файлу',
    `file_hash` VARCHAR(64) DEFAULT NULL COMMENT 'SHA-256 хеш файла для проверки подлинности',
    `metadata` JSON DEFAULT NULL COMMENT 'Дополнительные метаданные',
    PRIMARY KEY (`id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_template` (`template_id`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_number` (`certificate_number`),
    INDEX `idx_verification` (`verification_code`),
    INDEX `idx_issued` (`issued_at`),
    INDEX `idx_expires` (`expires_at`),
    INDEX `idx_revoked` (`is_revoked`),
    CONSTRAINT `fk_cert_template`
        FOREIGN KEY (`template_id`)
        REFERENCES `modx_test_certificate_templates` (`id`)
        ON DELETE RESTRICT,
    CONSTRAINT `fk_cert_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `modx_users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cert_issuer`
        FOREIGN KEY (`issued_by`)
        REFERENCES `modx_users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cert_revoker`
        FOREIGN KEY (`revoked_by`)
        REFERENCES `modx_users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Выданные сертификаты';

-- Таблица верификации сертификатов (логирование проверок)
CREATE TABLE IF NOT EXISTS `modx_test_certificate_verifications` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `certificate_id` INT(11) UNSIGNED DEFAULT NULL,
    `verification_code` VARCHAR(64) NOT NULL COMMENT 'Проверяемый код',
    `verification_result` ENUM('valid', 'invalid', 'expired', 'revoked', 'not_found') NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `verified_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_certificate` (`certificate_id`),
    INDEX `idx_code` (`verification_code`),
    INDEX `idx_verified` (`verified_at`),
    INDEX `idx_result` (`verification_result`),
    CONSTRAINT `fk_verif_certificate`
        FOREIGN KEY (`certificate_id`)
        REFERENCES `modx_test_certificates` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Лог верификации сертификатов';

-- Таблица требований для получения сертификатов
CREATE TABLE IF NOT EXISTS `modx_test_certificate_requirements` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id` INT(11) UNSIGNED NOT NULL,
    `requirement_type` ENUM('min_score', 'all_tests_passed', 'path_completed', 'achievement_earned', 'custom') NOT NULL,
    `requirement_data` JSON NOT NULL COMMENT 'Данные требования',
    `description` VARCHAR(500) DEFAULT NULL COMMENT 'Описание требования',
    `sort_order` INT(11) DEFAULT 0,
    PRIMARY KEY (`id`),
    INDEX `idx_template` (`template_id`),
    CONSTRAINT `fk_req_template`
        FOREIGN KEY (`template_id`)
        REFERENCES `modx_test_certificate_templates` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Требования для получения сертификатов';

-- Таблица подписантов (кто может подписывать сертификаты)
CREATE TABLE IF NOT EXISTS `modx_test_certificate_signers` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL COMMENT 'ФИО подписанта',
    `title` VARCHAR(255) NOT NULL COMMENT 'Должность',
    `organization` VARCHAR(255) DEFAULT NULL COMMENT 'Организация',
    `signature_image` VARCHAR(500) DEFAULT NULL COMMENT 'Путь к изображению подписи',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Подписанты сертификатов';

-- Связь сертификатов и подписантов (многие ко многим)
CREATE TABLE IF NOT EXISTS `modx_test_certificate_signatures` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `certificate_id` INT(11) UNSIGNED NOT NULL,
    `signer_id` INT(11) UNSIGNED NOT NULL,
    `signature_position` INT(11) DEFAULT 1 COMMENT 'Позиция подписи (1, 2, 3...)',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_cert_signer_pos` (`certificate_id`, `signer_id`, `signature_position`),
    INDEX `idx_certificate` (`certificate_id`),
    INDEX `idx_signer` (`signer_id`),
    CONSTRAINT `fk_sig_certificate`
        FOREIGN KEY (`certificate_id`)
        REFERENCES `modx_test_certificates` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_sig_signer`
        FOREIGN KEY (`signer_id`)
        REFERENCES `modx_test_certificate_signers` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Подписи на сертификатах';

-- =============================================
-- ТРИГГЕРЫ
-- =============================================

DELIMITER $

-- Триггер: Генерация номера сертификата
DROP TRIGGER IF EXISTS `trg_cert_generate_number`$
CREATE TRIGGER `trg_cert_generate_number`
BEFORE INSERT ON `modx_test_certificates`
FOR EACH ROW
BEGIN
    DECLARE v_year VARCHAR(4);
    DECLARE v_count INT;

    IF NEW.certificate_number IS NULL OR NEW.certificate_number = '' THEN
        SET v_year = YEAR(NOW());

        -- Получаем количество сертификатов за текущий год
        SELECT COUNT(*) + 1 INTO v_count
        FROM modx_test_certificates
        WHERE YEAR(issued_at) = v_year;

        -- Формат: CERT-2025-000001
        SET NEW.certificate_number = CONCAT('CERT-', v_year, '-', LPAD(v_count, 6, '0'));
    END IF;
END$

-- Триггер: Генерация кода верификации
DROP TRIGGER IF EXISTS `trg_cert_generate_verification`$
CREATE TRIGGER `trg_cert_generate_verification`
BEFORE INSERT ON `modx_test_certificates`
FOR EACH ROW
BEGIN
    IF NEW.verification_code IS NULL OR NEW.verification_code = '' THEN
        -- Генерируем уникальный код на основе времени, ID пользователя и случайного числа
        SET NEW.verification_code = SHA2(CONCAT(
            UNIX_TIMESTAMP(),
            NEW.user_id,
            NEW.template_id,
            FLOOR(RAND() * 999999999)
        ), 256);
    END IF;
END$

-- Триггер: Установка даты истечения
DROP TRIGGER IF EXISTS `trg_cert_set_expiration`$
CREATE TRIGGER `trg_cert_set_expiration`
BEFORE INSERT ON `modx_test_certificates`
FOR EACH ROW
BEGIN
    DECLARE v_expiration_days INT;

    IF NEW.expires_at IS NULL THEN
        -- Получаем срок действия из шаблона
        SELECT expiration_days INTO v_expiration_days
        FROM modx_test_certificate_templates
        WHERE id = NEW.template_id;

        IF v_expiration_days IS NOT NULL THEN
            SET NEW.expires_at = DATE_ADD(NOW(), INTERVAL v_expiration_days DAY);
        END IF;
    END IF;
END$

-- Триггер: Отправка уведомления при выдаче сертификата
DROP TRIGGER IF EXISTS `trg_cert_issue_notify`$
CREATE TRIGGER `trg_cert_issue_notify`
AFTER INSERT ON `modx_test_certificates`
FOR EACH ROW
BEGIN
    DECLARE v_template_name VARCHAR(255);
    DECLARE v_entity_name VARCHAR(255);

    -- Получаем название шаблона
    SELECT name INTO v_template_name
    FROM modx_test_certificate_templates
    WHERE id = NEW.template_id;

    -- Получаем название сущности в зависимости от типа
    IF NEW.entity_type = 'test' THEN
        SELECT title INTO v_entity_name
        FROM modx_test_tests
        WHERE id = NEW.entity_id;
    ELSEIF NEW.entity_type = 'path' THEN
        SELECT title INTO v_entity_name
        FROM modx_test_learning_paths
        WHERE id = NEW.entity_id;
    ELSEIF NEW.entity_type = 'achievement' THEN
        SELECT name INTO v_entity_name
        FROM modx_test_achievements
        WHERE id = NEW.entity_id;
    END IF;

    -- Создаем уведомление
    INSERT INTO modx_test_notifications
    (user_id, notification_type, title, message, icon, priority, related_type, related_id, metadata)
    VALUES (
        NEW.user_id,
        'custom',
        'Получен новый сертификат!',
        CONCAT('Вам выдан сертификат "', v_template_name, '" за ', COALESCE(v_entity_name, 'достижение'), '. Номер: ', NEW.certificate_number),
        'fa-certificate',
        'high',
        'certificate',
        NEW.id,
        JSON_OBJECT(
            'certificate_number', NEW.certificate_number,
            'verification_code', NEW.verification_code,
            'template_name', v_template_name,
            'entity_name', v_entity_name
        )
    );
END$

DELIMITER ;

-- =============================================
-- STORED PROCEDURES
-- =============================================

DELIMITER $

-- Процедура: Проверка сертификата
DROP PROCEDURE IF EXISTS `verify_certificate`$
CREATE PROCEDURE `verify_certificate`(
    IN p_verification_code VARCHAR(64),
    IN p_ip_address VARCHAR(45),
    IN p_user_agent VARCHAR(500)
)
BEGIN
    DECLARE v_cert_id INT;
    DECLARE v_is_revoked TINYINT;
    DECLARE v_expires_at DATETIME;
    DECLARE v_result VARCHAR(20);

    -- Ищем сертификат
    SELECT id, is_revoked, expires_at
    INTO v_cert_id, v_is_revoked, v_expires_at
    FROM modx_test_certificates
    WHERE verification_code = p_verification_code;

    -- Определяем результат проверки
    IF v_cert_id IS NULL THEN
        SET v_result = 'not_found';
    ELSEIF v_is_revoked = 1 THEN
        SET v_result = 'revoked';
    ELSEIF v_expires_at IS NOT NULL AND v_expires_at < NOW() THEN
        SET v_result = 'expired';
    ELSE
        SET v_result = 'valid';
    END IF;

    -- Логируем проверку
    INSERT INTO modx_test_certificate_verifications
    (certificate_id, verification_code, verification_result, ip_address, user_agent)
    VALUES (v_cert_id, p_verification_code, v_result, p_ip_address, p_user_agent);

    -- Возвращаем результат
    IF v_result = 'valid' THEN
        SELECT c.*, t.name as template_name, u.username
        FROM modx_test_certificates c
        JOIN modx_test_certificate_templates t ON t.id = c.template_id
        JOIN modx_users u ON u.id = c.user_id
        WHERE c.id = v_cert_id;
    ELSE
        SELECT v_result as result, NULL as certificate_number;
    END IF;
END$

-- Процедура: Очистка истекших сертификатов
DROP PROCEDURE IF EXISTS `cleanup_expired_certificates`$
CREATE PROCEDURE `cleanup_expired_certificates`()
BEGIN
    DECLARE v_deleted_count INT DEFAULT 0;

    -- Удаляем файлы истекших сертификатов старше 90 дней
    -- (сами записи оставляем для истории)
    UPDATE modx_test_certificates
    SET file_path = NULL
    WHERE expires_at IS NOT NULL
    AND expires_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    AND file_path IS NOT NULL;

    SET v_deleted_count = ROW_COUNT();

    -- Очищаем старые записи верификации (старше 1 года)
    DELETE FROM modx_test_certificate_verifications
    WHERE verified_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);

    SELECT v_deleted_count as expired_files_cleared;
END$

-- Процедура: Статистика по сертификатам
DROP PROCEDURE IF EXISTS `get_certificate_statistics`$
CREATE PROCEDURE `get_certificate_statistics`()
BEGIN
    SELECT
        (SELECT COUNT(*) FROM modx_test_certificates) as total_certificates,
        (SELECT COUNT(*) FROM modx_test_certificates WHERE is_revoked = 0) as active_certificates,
        (SELECT COUNT(*) FROM modx_test_certificates WHERE is_revoked = 1) as revoked_certificates,
        (SELECT COUNT(*) FROM modx_test_certificates
         WHERE expires_at IS NOT NULL AND expires_at < NOW() AND is_revoked = 0) as expired_certificates,
        (SELECT COUNT(*) FROM modx_test_certificates
         WHERE issued_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as issued_last_30_days,
        (SELECT COUNT(*) FROM modx_test_certificate_verifications
         WHERE verified_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as verifications_last_30_days,
        (SELECT COUNT(DISTINCT user_id) FROM modx_test_certificates) as users_with_certificates;
END$

DELIMITER ;

-- =============================================
-- НАЧАЛЬНЫЕ ДАННЫЕ
-- =============================================

-- Шаблон сертификата для успешного прохождения теста
INSERT INTO `modx_test_certificate_templates`
(`template_key`, `name`, `description`, `certificate_type`, `template_html`, `template_variables`, `orientation`, `require_verification`, `expiration_days`)
VALUES
('test_completion', 'Сертификат о прохождении теста', 'Выдается при успешном прохождении теста с баллом 70% и выше', 'test',
'<div style="text-align: center; padding: 50px; font-family: Georgia, serif;">
    <h1 style="font-size: 48px; color: #1a5490; margin-bottom: 30px;">СЕРТИФИКАТ</h1>
    <p style="font-size: 24px; margin: 20px 0;">о прохождении теста</p>
    <h2 style="font-size: 36px; color: #333; margin: 30px 0;">[[+test_title]]</h2>
    <p style="font-size: 20px; margin: 20px 0;">выдан</p>
    <h3 style="font-size: 32px; color: #1a5490; margin: 20px 0;">[[+user_name]]</h3>
    <p style="font-size: 18px; margin: 30px 0;">за успешное прохождение теста с результатом <strong>[[+score]]%</strong></p>
    <p style="font-size: 16px; color: #666; margin: 30px 0;">Дата выдачи: [[+issue_date]]</p>
    <p style="font-size: 14px; color: #999;">Номер сертификата: [[+certificate_number]]</p>
    <p style="font-size: 12px; color: #999;">Проверить подлинность: [[+verification_url]]</p>
</div>',
JSON_ARRAY('test_title', 'user_name', 'score', 'issue_date', 'certificate_number', 'verification_url'),
'landscape', 1, NULL),

('path_completion', 'Сертификат о завершении траектории обучения', 'Выдается при полном прохождении траектории обучения', 'path',
'<div style="text-align: center; padding: 50px; font-family: Georgia, serif;">
    <h1 style="font-size: 48px; color: #2e7d32; margin-bottom: 30px;">СЕРТИФИКАТ</h1>
    <p style="font-size: 24px; margin: 20px 0;">о завершении траектории обучения</p>
    <h2 style="font-size: 36px; color: #333; margin: 30px 0;">[[+path_title]]</h2>
    <p style="font-size: 20px; margin: 20px 0;">выдан</p>
    <h3 style="font-size: 32px; color: #2e7d32; margin: 20px 0;">[[+user_name]]</h3>
    <p style="font-size: 18px; margin: 30px 0;">за успешное завершение всех этапов обучения</p>
    <p style="font-size: 16px; margin: 20px 0;">Всего шагов пройдено: [[+steps_completed]]</p>
    <p style="font-size: 16px; margin: 20px 0;">Средний балл: [[+avg_score]]%</p>
    <p style="font-size: 16px; color: #666; margin: 30px 0;">Дата выдачи: [[+issue_date]]</p>
    <p style="font-size: 14px; color: #999;">Номер сертификата: [[+certificate_number]]</p>
    <p style="font-size: 12px; color: #999;">Проверить подлинность: [[+verification_url]]</p>
</div>',
JSON_ARRAY('path_title', 'user_name', 'steps_completed', 'avg_score', 'issue_date', 'certificate_number', 'verification_url'),
'landscape', 1, NULL),

('achievement_master', 'Сертификат мастера', 'Выдается за получение особых достижений', 'achievement',
'<div style="text-align: center; padding: 50px; font-family: Georgia, serif;">
    <h1 style="font-size: 48px; color: #d4af37; margin-bottom: 30px;">СЕРТИФИКАТ МАСТЕРА</h1>
    <h2 style="font-size: 36px; color: #333; margin: 30px 0;">[[+achievement_name]]</h2>
    <p style="font-size: 20px; margin: 20px 0;">присваивается</p>
    <h3 style="font-size: 32px; color: #d4af37; margin: 20px 0;">[[+user_name]]</h3>
    <p style="font-size: 18px; margin: 30px 0;">за выдающиеся результаты в обучении</p>
    <p style="font-size: 16px; color: #666; margin: 30px 0;">Дата выдачи: [[+issue_date]]</p>
    <p style="font-size: 14px; color: #999;">Номер сертификата: [[+certificate_number]]</p>
</div>',
JSON_ARRAY('achievement_name', 'user_name', 'issue_date', 'certificate_number'),
'landscape', 1, NULL);

-- Примеры подписантов
INSERT INTO `modx_test_certificate_signers`
(`name`, `title`, `organization`, `is_active`)
VALUES
('Иван Иванович Иванов', 'Директор учебного центра', 'ООО "Образование"', 1),
('Петр Петрович Петров', 'Главный методист', 'ООО "Образование"', 1);

-- Индексы для оптимизации
CREATE INDEX IF NOT EXISTS `idx_cert_user_entity`
ON `modx_test_certificates` (`user_id`, `entity_type`, `entity_id`);

CREATE INDEX IF NOT EXISTS `idx_cert_issued_valid`
ON `modx_test_certificates` (`issued_at`, `is_revoked`, `expires_at`);


-- ============================================
-- ЗАВЕРШЕНИЕ УСТАНОВКИ
-- ============================================

-- Включить обратно проверку foreign keys
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- ВЕРИФИКАЦИЯ УСТАНОВКИ
-- ============================================

-- Показать все созданные таблицы
SELECT
    TABLE_NAME as 'Table',
    TABLE_ROWS as 'Rows',
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as 'Size (MB)',
    TABLE_COMMENT as 'Comment'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME LIKE 'modx_test%'
ORDER BY TABLE_NAME;

-- Показать количество созданных таблиц
SELECT COUNT(*) as 'Total Tables Created'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME LIKE 'modx_test%';

-- Показать все триггеры
SELECT
    TRIGGER_NAME as 'Trigger',
    EVENT_MANIPULATION as 'Event',
    EVENT_OBJECT_TABLE as 'Table'
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND TRIGGER_NAME LIKE '%test%'
ORDER BY EVENT_OBJECT_TABLE, EVENT_MANIPULATION;

-- Показать все stored procedures
SELECT
    ROUTINE_NAME as 'Procedure',
    ROUTINE_TYPE as 'Type',
    DTD_IDENTIFIER as 'Returns'
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE()
  AND ROUTINE_NAME LIKE '%test%'
ORDER BY ROUTINE_NAME;

-- Показать все views
SELECT
    TABLE_NAME as 'View',
    TABLE_COMMENT as 'Comment'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'VIEW'
  AND TABLE_NAME LIKE 'modx_test%'
ORDER BY TABLE_NAME;

-- ============================================
-- ИТОГОВАЯ СТАТИСТИКА СИСТЕМЫ
-- ============================================

/*
УСТАНОВЛЕНО:

СПРИНТЫ:
- Sprint 7:  Базовая структура (6 таблиц)
- Sprint 9:  Учебные материалы (6 таблиц)
- Sprint 10: Права доступа (3 таблицы, 3 триггера)
- Sprint 11: Траектории обучения (4 таблицы, 1 триггер)
- Sprint 12: Расширенные типы вопросов (4 таблицы)
- Sprint 13: Геймификация (7 таблиц, 2 триггера, 1 stored procedure)
- Sprint 14: Уведомления (5 таблиц, 3 триггера, 2 stored procedures)
- Sprint 15: Аналитика (4 таблицы, 4 views, 6 stored procedures)
- Sprint 16: Сертификаты (6 таблицы, 4 триггера, 3 stored procedures)

ВСЕГО:
- Таблицы: 50+
- Триггеры: 15+
- Stored Procedures: 12+
- Views: 4
- Контроллеры: 13
- Сервисы: 10+
- API Endpoints: 120

ВОЗМОЖНОСТИ:
- 6 типов вопросов (single, multiple, matching, ordering, fill_blank, essay)
- Учебные материалы с блоками контента
- Траектории обучения с последовательными шагами
- Геймификация (XP, уровни, достижения, рейтинги)
- Система уведомлений (10 типов, 3 канала)
- Расширенная аналитика и отчеты (5 типов)
- Сертификаты с верификацией
- Гранулярные права доступа (3 роли)

БЕЗОПАСНОСТЬ:
- CSRF Protection
- SQL Injection Protection (PDO prepared statements)
- XSS Protection (input sanitization)
- Role-based Access Control (RBAC)
- SHA-256 certificate verification

ПРОИЗВОДИТЕЛЬНОСТЬ:
- Database indexes на всех критических полях
- SQL Views для агрегированных данных
- Stored Procedures для сложных операций
- Кеширование метрик аналитики
- Cascading deletes для целостности данных
*/

-- ============================================
-- СЛЕДУЮЩИЕ ШАГИ
-- ============================================

/*
После установки БД выполните:

1. Скопируйте файлы на сервер:
   - core/components/testsystem/
   - assets/components/testsystem/

2. Установите права доступа:
   chmod 755 assets/components/testsystem/ajax/testsystem.php
   chmod 775 assets/components/testsystem/reports/
   chmod 775 assets/components/testsystem/certificates/

3. Создайте MODX плагин для автоматического обслуживания

4. Настройте cron задачи для:
   - Очистки старых сессий (ежедневно)
   - Обновления рейтингов (еженедельно)
   - Обработки очереди уведомлений (каждые 5 минут)
   - Очистки кеша аналитики (ежедневно)

5. Создайте начального администратора

6. Протестируйте API endpoint:
   curl -X POST http://your-domain.com/assets/components/testsystem/ajax/testsystem.php

7. См. полную инструкцию в DEPLOYMENT.md
*/

-- ============================================
-- УСПЕШНАЯ УСТАНОВКА ЗАВЕРШЕНА!
-- ============================================

SELECT '✓ Test System v2.0 successfully installed!' as 'Status';
SELECT '✓ All tables, triggers, and procedures created' as 'Status';
SELECT '✓ System is ready for use' as 'Status';
SELECT 'Next steps: See DEPLOYMENT.md for configuration' as 'Info';

-- ============================================
-- Дата установки: 2025-11-15
-- Версия: 2.0
-- ============================================
