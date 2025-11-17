-- ============================================
-- Test System v2.0 - MODX Component Installation
-- ============================================
--
-- Этот скрипт регистрирует компонент Test System в MODX Revolution
-- Выполните этот скрипт ПОСЛЕ установки FULL_INSTALLATION_FIXED.sql
--
-- Что делает этот скрипт:
-- 1. Создает namespace для компонента
-- 2. Регистрирует system settings
-- 3. Устанавливает snippets
-- 4. Устанавливает chunks
-- 5. Создает меню в админке MODX
--
-- Использование:
--   mysql -u username -p database_name < MODX_INSTALL.sql
--
-- Дата создания: 2025-11-17
-- Версия: 2.0
-- ============================================

-- ============================================
-- 1. СОЗДАНИЕ NAMESPACE
-- ============================================

-- Удалить существующий namespace если есть
DELETE FROM `modx_namespaces` WHERE `name` = 'testsystem';

-- Создать namespace для компонента
INSERT INTO `modx_namespaces` (`name`, `path`, `assets_path`)
VALUES (
    'testsystem',
    '{core_path}components/testsystem/',
    '{assets_path}components/testsystem/'
);

-- ============================================
-- 2. SYSTEM SETTINGS
-- ============================================

-- Удалить существующие настройки
DELETE FROM `modx_system_settings` WHERE `key` LIKE 'testsystem.%';

-- Основные настройки
INSERT INTO `modx_system_settings` (`key`, `value`, `xtype`, `namespace`, `area`, `editedon`) VALUES
('testsystem.core_path', '{core_path}components/testsystem/', 'textfield', 'testsystem', 'Paths', NOW()),
('testsystem.assets_path', '{assets_path}components/testsystem/', 'textfield', 'testsystem', 'Paths', NOW()),
('testsystem.assets_url', '{assets_url}components/testsystem/', 'textfield', 'testsystem', 'Paths', NOW()),
('testsystem.connectors_url', '{assets_url}components/testsystem/ajax/', 'textfield', 'testsystem', 'Paths', NOW());

-- Настройки тестирования
INSERT INTO `modx_system_settings` (`key`, `value`, `xtype`, `namespace`, `area`, `editedon`) VALUES
('testsystem.default_pass_score', '70', 'numberfield', 'testsystem', 'Test Settings', NOW()),
('testsystem.default_time_limit', '30', 'numberfield', 'testsystem', 'Test Settings', NOW()),
('testsystem.default_questions_per_session', '20', 'numberfield', 'testsystem', 'Test Settings', NOW()),
('testsystem.allow_test_review', '1', 'combo-boolean', 'testsystem', 'Test Settings', NOW()),
('testsystem.randomize_questions', '1', 'combo-boolean', 'testsystem', 'Test Settings', NOW()),
('testsystem.randomize_answers', '1', 'combo-boolean', 'testsystem', 'Test Settings', NOW());

-- Настройки геймификации
INSERT INTO `modx_system_settings` (`key`, `value`, `xtype`, `namespace`, `area`, `editedon`) VALUES
('testsystem.enable_gamification', '1', 'combo-boolean', 'testsystem', 'Gamification', NOW()),
('testsystem.xp_per_correct_answer', '10', 'numberfield', 'testsystem', 'Gamification', NOW()),
('testsystem.xp_per_test_completed', '50', 'numberfield', 'testsystem', 'Gamification', NOW()),
('testsystem.xp_bonus_perfect_score', '100', 'numberfield', 'testsystem', 'Gamification', NOW()),
('testsystem.enable_leaderboard', '1', 'combo-boolean', 'testsystem', 'Gamification', NOW()),
('testsystem.leaderboard_update_interval', '3600', 'numberfield', 'testsystem', 'Gamification', NOW());

-- Настройки уведомлений
INSERT INTO `modx_system_settings` (`key`, `value`, `xtype`, `namespace`, `area`, `editedon`) VALUES
('testsystem.enable_notifications', '1', 'combo-boolean', 'testsystem', 'Notifications', NOW()),
('testsystem.enable_email_notifications', '1', 'combo-boolean', 'testsystem', 'Notifications', NOW()),
('testsystem.notification_email_from', 'noreply@example.com', 'textfield', 'testsystem', 'Notifications', NOW()),
('testsystem.notification_batch_size', '100', 'numberfield', 'testsystem', 'Notifications', NOW());

-- Настройки сертификатов
INSERT INTO `modx_system_settings` (`key`, `value`, `xtype`, `namespace`, `area`, `editedon`) VALUES
('testsystem.enable_certificates', '1', 'combo-boolean', 'testsystem', 'Certificates', NOW()),
('testsystem.certificate_expiration_days', '365', 'numberfield', 'testsystem', 'Certificates', NOW()),
('testsystem.certificate_require_verification', '1', 'combo-boolean', 'testsystem', 'Certificates', NOW());

-- Настройки производительности
INSERT INTO `modx_system_settings` (`key`, `value`, `xtype`, `namespace`, `area`, `editedon`) VALUES
('testsystem.enable_caching', '1', 'combo-boolean', 'testsystem', 'Performance', NOW()),
('testsystem.cache_lifetime', '3600', 'numberfield', 'testsystem', 'Performance', NOW()),
('testsystem.analytics_cache_lifetime', '86400', 'numberfield', 'testsystem', 'Performance', NOW()),
('testsystem.session_timeout_days', '30', 'numberfield', 'testsystem', 'Performance', NOW());

-- Настройки безопасности
INSERT INTO `modx_system_settings` (`key`, `value`, `xtype`, `namespace`, `area`, `editedon`) VALUES
('testsystem.enable_csrf_protection', '1', 'combo-boolean', 'testsystem', 'Security', NOW()),
('testsystem.require_authentication', '1', 'combo-boolean', 'testsystem', 'Security', NOW()),
('testsystem.allow_guest_tests', '0', 'combo-boolean', 'testsystem', 'Security', NOW());

-- ============================================
-- 3. CATEGORIES
-- ============================================

-- Создать категорию для элементов компонента
INSERT INTO `modx_categories` (`category`, `parent`, `rank`)
VALUES ('Test System', 0, 0)
ON DUPLICATE KEY UPDATE `category` = 'Test System';

-- Получить ID категории
SET @category_id = (SELECT `id` FROM `modx_categories` WHERE `category` = 'Test System' LIMIT 1);

-- ============================================
-- 4. SNIPPETS
-- ============================================

-- Удалить существующие snippets
DELETE FROM `modx_site_snippets` WHERE `name` IN (
    'testRunner', 'testsList', 'myTests', 'userProfile', 'categoriesAndTests',
    'addTestForm', 'csvImportForm', 'manageCategories', 'manageUsers',
    'leaderboard', 'leaderboardCompact', 'leaderboardCategories',
    'knowledgeAreasManager', 'learningMaterials', 'myFavorites',
    'getUserStats', 'getSystemStats', 'getTestCategories', 'getTopUsers',
    'authHandler', 'userMenu', 'MenuWithACL', 'adminDataIntegrity'
);

-- Создать snippets
-- ВАЖНО: Содержимое snippets будет загружено из файлов отдельно через PHP скрипт
-- Здесь создаем только записи-заглушки

INSERT INTO `modx_site_snippets` (`name`, `description`, `snippet`, `category`, `locked`, `properties`, `moduleguid`) VALUES
('testRunner', 'Интерфейс прохождения теста', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('testsList', 'Список доступных тестов', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('myTests', 'Мои тесты и результаты', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('userProfile', 'Профиль пользователя', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('categoriesAndTests', 'Категории и тесты', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('addTestForm', 'Форма создания теста', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('csvImportForm', 'Импорт вопросов из CSV', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('manageCategories', 'Управление категориями', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('manageUsers', 'Управление пользователями', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('leaderboard', 'Таблица лидеров', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('leaderboardCompact', 'Компактная таблица лидеров', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('leaderboardCategories', 'Категории для таблицы лидеров', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('knowledgeAreasManager', 'Управление областями знаний', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('learningMaterials', 'Учебные материалы', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('myFavorites', 'Избранные вопросы', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('getUserStats', 'Статистика пользователя', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('getSystemStats', 'Системная статистика', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('getTestCategories', 'Получить категории тестов', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('getTopUsers', 'Топ пользователей', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('authHandler', 'Обработчик авторизации', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('userMenu', 'Меню пользователя', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('MenuWithACL', 'Меню с контролем доступа', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, ''),
('adminDataIntegrity', 'Проверка целостности данных', '<?php\nreturn \'Use snippet installation script\';', @category_id, 0, NULL, '');

-- ============================================
-- 5. CHUNKS
-- ============================================

-- Удалить существующие chunks
DELETE FROM `modx_site_htmlsnippets` WHERE `name` IN (
    'aside', 'block.gallery', 'child_list', 'contact_form', 'content',
    'content_default', 'content_main', 'content_spec', 'content_spec_list',
    'footer', 'form.contact_form', 'gallery', 'head', 'header', 'menu', 'scripts'
);

-- Создать chunks
-- ВАЖНО: Содержимое chunks будет загружено из файлов отдельно через PHP скрипт

INSERT INTO `modx_site_htmlsnippets` (`name`, `description`, `snippet`, `category`, `locked`, `properties`) VALUES
('aside', 'Боковая панель', 'Use chunk installation script', @category_id, 0, NULL),
('block.gallery', 'Блок галереи', 'Use chunk installation script', @category_id, 0, NULL),
('child_list', 'Список дочерних страниц', 'Use chunk installation script', @category_id, 0, NULL),
('contact_form', 'Контактная форма', 'Use chunk installation script', @category_id, 0, NULL),
('content', 'Контент', 'Use chunk installation script', @category_id, 0, NULL),
('content_default', 'Контент по умолчанию', 'Use chunk installation script', @category_id, 0, NULL),
('content_main', 'Главный контент', 'Use chunk installation script', @category_id, 0, NULL),
('content_spec', 'Спец контент', 'Use chunk installation script', @category_id, 0, NULL),
('content_spec_list', 'Список спец контента', 'Use chunk installation script', @category_id, 0, NULL),
('footer', 'Подвал', 'Use chunk installation script', @category_id, 0, NULL),
('form.contact_form', 'Форма обратной связи', 'Use chunk installation script', @category_id, 0, NULL),
('gallery', 'Галерея', 'Use chunk installation script', @category_id, 0, NULL),
('head', 'Head секция', 'Use chunk installation script', @category_id, 0, NULL),
('header', 'Шапка', 'Use chunk installation script', @category_id, 0, NULL),
('menu', 'Меню', 'Use chunk installation script', @category_id, 0, NULL),
('scripts', 'Скрипты', 'Use chunk installation script', @category_id, 0, NULL);

-- ============================================
-- 6. ACTIONS (для админ-меню)
-- ============================================

-- Удалить существующие actions
DELETE FROM `modx_actions` WHERE `namespace` = 'testsystem';

-- Создать actions для админ панели
INSERT INTO `modx_actions` (`id`, `namespace`, `controller`, `haslayout`, `lang_topics`, `assets`) VALUES
(NULL, 'testsystem', 'index', 1, 'testsystem:default', ''),
(NULL, 'testsystem', 'tests', 1, 'testsystem:tests', ''),
(NULL, 'testsystem', 'categories', 1, 'testsystem:categories', ''),
(NULL, 'testsystem', 'users', 1, 'testsystem:users', ''),
(NULL, 'testsystem', 'statistics', 1, 'testsystem:statistics', ''),
(NULL, 'testsystem', 'certificates', 1, 'testsystem:certificates', ''),
(NULL, 'testsystem', 'settings', 1, 'testsystem:settings', '');

-- ============================================
-- 7. ADMIN MENU
-- ============================================

-- Удалить существующее меню
DELETE FROM `modx_menus` WHERE `namespace` = 'testsystem';

-- Получить ID actions
SET @action_index = (SELECT `id` FROM `modx_actions` WHERE `namespace` = 'testsystem' AND `controller` = 'index' LIMIT 1);
SET @action_tests = (SELECT `id` FROM `modx_actions` WHERE `namespace` = 'testsystem' AND `controller` = 'tests' LIMIT 1);
SET @action_categories = (SELECT `id` FROM `modx_actions` WHERE `namespace` = 'testsystem' AND `controller` = 'categories' LIMIT 1);
SET @action_users = (SELECT `id` FROM `modx_actions` WHERE `namespace` = 'testsystem' AND `controller` = 'users' LIMIT 1);
SET @action_statistics = (SELECT `id` FROM `modx_actions` WHERE `namespace` = 'testsystem' AND `controller` = 'statistics' LIMIT 1);
SET @action_certificates = (SELECT `id` FROM `modx_actions` WHERE `namespace` = 'testsystem' AND `controller` = 'certificates' LIMIT 1);
SET @action_settings = (SELECT `id` FROM `modx_actions` WHERE `namespace` = 'testsystem' AND `controller` = 'settings' LIMIT 1);

-- Создать главное меню
INSERT INTO `modx_menus` (`text`, `parent`, `action`, `description`, `icon`, `menuindex`, `params`, `handler`, `permissions`, `namespace`) VALUES
('Test System', 'components', @action_index, 'Система тестирования и обучения', 'images/icons/plugin.gif', 0, '', '', '', 'testsystem');

SET @menu_parent = LAST_INSERT_ID();

-- Создать подменю
INSERT INTO `modx_menus` (`text`, `parent`, `action`, `description`, `icon`, `menuindex`, `params`, `handler`, `permissions`, `namespace`) VALUES
('Тесты', @menu_parent, @action_tests, 'Управление тестами', '', 0, '', '', '', 'testsystem'),
('Категории', @menu_parent, @action_categories, 'Управление категориями', '', 1, '', '', '', 'testsystem'),
('Пользователи', @menu_parent, @action_users, 'Управление пользователями', '', 2, '', '', '', 'testsystem'),
('Статистика', @menu_parent, @action_statistics, 'Статистика и аналитика', '', 3, '', '', '', 'testsystem'),
('Сертификаты', @menu_parent, @action_certificates, 'Управление сертификатами', '', 4, '', '', '', 'testsystem'),
('Настройки', @menu_parent, @action_settings, 'Настройки системы', '', 5, '', '', '', 'testsystem');

-- ============================================
-- 8. FINALIZATION
-- ============================================

-- Очистить кеш MODX
-- ВАЖНО: Кеш нужно очистить вручную через админку или через скрипт

SELECT 'Test System component installed successfully!' AS message;
SELECT 'IMPORTANT: Clear MODX cache and reload snippets/chunks from files!' AS warning;
SELECT CONCAT('Category ID: ', @category_id) AS info;
