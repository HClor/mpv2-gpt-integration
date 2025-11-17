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
