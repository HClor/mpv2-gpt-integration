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
