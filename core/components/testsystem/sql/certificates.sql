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
