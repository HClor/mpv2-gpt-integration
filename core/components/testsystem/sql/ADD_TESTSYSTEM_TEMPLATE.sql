-- ============================================
-- Test System v2.0 - Add Template and Chunks
-- ============================================
--
-- Этот скрипт добавляет шаблон TestSystem и chunks
-- в базу данных MODX
--
-- Выполните ПОСЛЕ установки MODX_INSTALL.sql
--
-- ============================================

-- Получить ID категории Test System
SET @category_id = (SELECT `id` FROM `modx_categories` WHERE `category` = 'Test System' LIMIT 1);

-- ============================================
-- 1. УДАЛИТЬ СУЩЕСТВУЮЩИЕ CHUNKS
-- ============================================

DELETE FROM `modx_site_htmlsnippets` WHERE `name` IN (
    'tsHead', 'tsHeader', 'tsFooter', 'tsScripts'
);

-- ============================================
-- 2. СОЗДАТЬ НОВЫЕ CHUNKS
-- ============================================

-- Chunk: tsHead
INSERT INTO `modx_site_htmlsnippets` (`name`, `description`, `snippet`, `category`, `locked`, `properties`) VALUES
('tsHead', 'Test System - Head секция', '<!-- Будет загружен из файла -->', @category_id, 0, NULL);

-- Chunk: tsHeader
INSERT INTO `modx_site_htmlsnippets` (`name`, `description`, `snippet`, `category`, `locked`, `properties`) VALUES
('tsHeader', 'Test System - Шапка сайта', '<!-- Будет загружен из файла -->', @category_id, 0, NULL);

-- Chunk: tsFooter
INSERT INTO `modx_site_htmlsnippets` (`name`, `description`, `snippet`, `category`, `locked`, `properties`) VALUES
('tsFooter', 'Test System - Подвал сайта', '<!-- Будет загружен из файла -->', @category_id, 0, NULL);

-- Chunk: tsScripts
INSERT INTO `modx_site_htmlsnippets` (`name`, `description`, `snippet`, `category`, `locked`, `properties`) VALUES
('tsScripts', 'Test System - JS скрипты', '<!-- Будет загружен из файла -->', @category_id, 0, NULL);

-- ============================================
-- 3. СОЗДАТЬ ШАБЛОН
-- ============================================

-- Удалить существующий шаблон если есть
DELETE FROM `modx_site_templates` WHERE `templatename` = 'TestSystem';

-- Создать шаблон TestSystem
INSERT INTO `modx_site_templates` (`templatename`, `description`, `content`, `category`, `locked`, `properties`) VALUES (
    'TestSystem',
    'Шаблон для системы тестирования Test System v2.0',
    '<!-- Будет загружен из файла -->',
    @category_id,
    0,
    NULL
);

-- Получить ID созданного шаблона
SET @template_id = LAST_INSERT_ID();

-- ============================================
-- 4. СОЗДАТЬ TV ПОЛЯ ДЛЯ ДОПОЛНИТЕЛЬНЫХ CSS/JS
-- ============================================

-- Удалить существующие TV
DELETE FROM `modx_site_tmplvars` WHERE `name` IN ('cssTV', 'jsTV');

-- TV для дополнительного CSS
INSERT INTO `modx_site_tmplvars` (`type`, `name`, `caption`, `description`, `category`, `locked`, `rank`, `display`, `default_text`) VALUES (
    'textarea',
    'cssTV',
    'Дополнительный CSS',
    'CSS код, который будет добавлен в head секцию страницы',
    @category_id,
    0,
    0,
    'default',
    ''
);

SET @tv_css_id = LAST_INSERT_ID();

-- TV для дополнительного JS
INSERT INTO `modx_site_tmplvars` (`type`, `name`, `caption`, `description`, `category`, `locked`, `rank`, `display`, `default_text`) VALUES (
    'textarea',
    'jsTV',
    'Дополнительный JS',
    'JavaScript код, который будет добавлен перед закрывающим тегом body',
    @category_id,
    0,
    1,
    'default',
    ''
);

SET @tv_js_id = LAST_INSERT_ID();

-- ============================================
-- 5. ПРИВЯЗАТЬ TV К ШАБЛОНУ
-- ============================================

-- Привязать cssTV к шаблону TestSystem
INSERT INTO `modx_site_tmplvar_templates` (`tmplvarid`, `templateid`, `rank`) VALUES
(@tv_css_id, @template_id, 0);

-- Привязать jsTV к шаблону TestSystem
INSERT INTO `modx_site_tmplvar_templates` (`tmplvarid`, `templateid`, `rank`) VALUES
(@tv_js_id, @template_id, 1);

-- ============================================
-- 6. FINALIZATION
-- ============================================

SELECT 'Template and chunks created successfully!' AS message;
SELECT CONCAT('Template ID: ', @template_id) AS template_info;
SELECT CONCAT('Category ID: ', @category_id) AS category_info;
SELECT 'IMPORTANT: Run update_template_chunks.php to load content from files!' AS warning;
