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
