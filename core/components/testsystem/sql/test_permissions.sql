-- ============================================
-- Test Permissions Table
-- Управление доступом к приватным тестам
-- ============================================

CREATE TABLE IF NOT EXISTS `modx_test_permissions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `test_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID теста',
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID пользователя',
    `granted_by` INT(11) UNSIGNED NOT NULL COMMENT 'Кто предоставил доступ',
    `can_view` TINYINT(1) DEFAULT 1 COMMENT 'Может просматривать тест',
    `can_edit` TINYINT(1) DEFAULT 0 COMMENT 'Может редактировать тест',
    `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата предоставления доступа',
    `expires_at` DATETIME DEFAULT NULL COMMENT 'Дата истечения доступа (опционально)',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_test_user` (`test_id`, `user_id`),
    KEY `idx_test` (`test_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_granted_by` (`granted_by`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Права доступа к приватным тестам';

-- ============================================
-- Миграция для существующих данных
-- ============================================

-- Если таблица уже существует без поля can_view, добавляем его
ALTER TABLE `modx_test_permissions`
    ADD COLUMN IF NOT EXISTS `can_view` TINYINT(1) DEFAULT 1 COMMENT 'Может просматривать тест' AFTER `granted_by`;

-- Если таблица уже существует без поля expires_at, добавляем его
ALTER TABLE `modx_test_permissions`
    ADD COLUMN IF NOT EXISTS `expires_at` DATETIME DEFAULT NULL COMMENT 'Дата истечения доступа (опционально)' AFTER `granted_at`;

-- Обновляем существующие записи: если есть can_edit=1, то устанавливаем can_view=1
UPDATE `modx_test_permissions` SET `can_view` = 1 WHERE `can_edit` = 1 AND `can_view` = 0;

-- ============================================
-- Описание прав доступа
-- ============================================

/*
ПРАВА ДОСТУПА К ПРИВАТНЫМ ТЕСТАМ:

1. can_view (Просмотр)
   - Пользователь может видеть тест в списке
   - Может проходить тест
   - Может видеть результаты своих попыток
   - НЕ может редактировать вопросы или настройки

2. can_edit (Редактирование)
   - Включает все права can_view
   - Может редактировать вопросы теста
   - Может импортировать вопросы
   - Может изменять настройки теста
   - НЕ может удалить тест (только владелец)
   - НЕ может управлять доступом других пользователей (только владелец)

3. Владелец (created_by)
   - Полные права на тест
   - Может удалить тест
   - Может предоставлять/отзывать доступ
   - Может изменять статус публикации (private/public)

ПРИОРИТЕТЫ:
- Глобальные админы имеют полный доступ ко всем тестам
- Глобальные эксперты могут редактировать все тесты
- Владелец теста имеет полный доступ
- Права доступа применяются только к приватным тестам
- Публичные тесты доступны всем без ограничений
*/

-- ============================================
-- Индексы для производительности
-- ============================================

-- Составной индекс для быстрого поиска прав пользователя по тестам
ALTER TABLE `modx_test_permissions`
    ADD INDEX IF NOT EXISTS `idx_user_view` (`user_id`, `can_view`);

ALTER TABLE `modx_test_permissions`
    ADD INDEX IF NOT EXISTS `idx_user_edit` (`user_id`, `can_edit`);

-- Индекс для поиска всех пользователей с доступом к тесту
ALTER TABLE `modx_test_permissions`
    ADD INDEX IF NOT EXISTS `idx_test_view` (`test_id`, `can_view`);

ALTER TABLE `modx_test_permissions`
    ADD INDEX IF NOT EXISTS `idx_test_edit` (`test_id`, `can_edit`);

-- ============================================
-- Примеры использования
-- ============================================

/*
-- Предоставить пользователю ID=123 право просмотра теста ID=45
INSERT INTO modx_test_permissions (test_id, user_id, granted_by, can_view, can_edit)
VALUES (45, 123, 1, 1, 0)
ON DUPLICATE KEY UPDATE can_view = 1, granted_by = 1, granted_at = NOW();

-- Предоставить пользователю ID=123 право редактирования теста ID=45
INSERT INTO modx_test_permissions (test_id, user_id, granted_by, can_view, can_edit)
VALUES (45, 123, 1, 1, 1)
ON DUPLICATE KEY UPDATE can_view = 1, can_edit = 1, granted_by = 1, granted_at = NOW();

-- Получить всех пользователей с доступом к тесту
SELECT
    u.id,
    u.username,
    u.fullname,
    tp.can_view,
    tp.can_edit,
    tp.granted_at,
    tp.expires_at,
    granted_by_user.username AS granted_by_username
FROM modx_test_permissions tp
JOIN modx_users u ON u.id = tp.user_id
LEFT JOIN modx_users granted_by_user ON granted_by_user.id = tp.granted_by
WHERE tp.test_id = 45
ORDER BY tp.granted_at DESC;

-- Получить все тесты, к которым пользователь имеет доступ
SELECT
    t.id,
    t.title,
    t.description,
    t.publication_status,
    tp.can_view,
    tp.can_edit,
    t.created_by,
    owner.username AS owner_username
FROM modx_test_permissions tp
JOIN modx_test_tests t ON t.id = tp.test_id
LEFT JOIN modx_users owner ON owner.id = t.created_by
WHERE tp.user_id = 123
    AND tp.can_view = 1
    AND (tp.expires_at IS NULL OR tp.expires_at > NOW())
ORDER BY tp.granted_at DESC;

-- Проверить, может ли пользователь просматривать тест
SELECT can_view FROM modx_test_permissions
WHERE test_id = 45 AND user_id = 123
    AND (expires_at IS NULL OR expires_at > NOW());

-- Проверить, может ли пользователь редактировать тест
SELECT can_edit FROM modx_test_permissions
WHERE test_id = 45 AND user_id = 123
    AND can_edit = 1
    AND (expires_at IS NULL OR expires_at > NOW());

-- Отозвать доступ пользователя к тесту
DELETE FROM modx_test_permissions
WHERE test_id = 45 AND user_id = 123;

-- Предоставить временный доступ (на 30 дней)
INSERT INTO modx_test_permissions (test_id, user_id, granted_by, can_view, can_edit, expires_at)
VALUES (45, 123, 1, 1, 0, DATE_ADD(NOW(), INTERVAL 30 DAY))
ON DUPLICATE KEY UPDATE
    can_view = 1,
    granted_by = 1,
    granted_at = NOW(),
    expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY);

-- Удалить все истекшие права доступа
DELETE FROM modx_test_permissions
WHERE expires_at IS NOT NULL AND expires_at < NOW();
*/

-- ============================================
-- Примечания
-- ============================================

/*
1. Таблица используется ТОЛЬКО для приватных тестов (publication_status = 'private')
2. Публичные тесты (publication_status = 'public') доступны всем без записей в этой таблице
3. Владелец теста (created_by) имеет полный доступ без записи в таблице
4. Глобальные админы и эксперты (из Config) имеют полный доступ без записи в таблице
5. expires_at позволяет создавать временные доступы (например, для студентов курса)
6. UNIQUE KEY на (test_id, user_id) предотвращает дублирование прав
7. При удалении теста связанные права должны удаляться автоматически (рекомендуется добавить CASCADE)
*/
