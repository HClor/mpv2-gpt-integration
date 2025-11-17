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
