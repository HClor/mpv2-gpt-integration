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
