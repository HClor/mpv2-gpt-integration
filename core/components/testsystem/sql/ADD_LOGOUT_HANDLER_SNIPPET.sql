-- ============================================
-- Регистрация сниппета logoutHandler в MODX
-- ============================================
-- Этот скрипт создает сниппет для обработки выхода пользователя
-- во фронтэнде (контекст web)
--
-- Использование через MySQL:
--   mysql -u username -p database_name < ADD_LOGOUT_HANDLER_SNIPPET.sql
--
-- Или через консоль MODX (в контексте mgr):
--   Скопируйте и выполните SQL ниже
-- ============================================

-- Удалить существующий сниппет если есть
DELETE FROM `modx_site_snippets` WHERE `name` = 'logoutHandler';

-- Получить ID категории Test System (если она есть)
SET @category_id = (SELECT `id` FROM `modx_categories` WHERE `category` = 'Test System' LIMIT 1);

-- Если категория не найдена, использовать 0 (без категории)
SET @category_id = IFNULL(@category_id, 0);

-- Создать сниппет logoutHandler
INSERT INTO `modx_site_snippets` (`name`, `description`, `snippet`, `category`, `locked`, `properties`, `moduleguid`) VALUES (
    'logoutHandler',
    'Глобальный обработчик выхода пользователя для фронтэнда (контекст web)',
    '<?php
/**
 * Logout Handler - глобальный обработчик выхода для фронтэнда (контекст web)
 * Вызывается в tsHeader.tpl для обработки POST запросов logout
 */

// Обработка выхода пользователя
if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\' && isset($_POST[\'login_logout\'])) {
    // Завершаем сессию пользователя в контексте web
    if ($modx->user->hasSessionContext(\'web\')) {
        $modx->user->removeSessionContext(\'web\');
    }

    // Перенаправляем на главную страницу
    $modx->sendRedirect($modx->makeUrl($modx->getOption(\'site_start\')));
    exit;
}

// Сниппет не возвращает никакого вывода - только обрабатывает logout
return \'\';
',
    @category_id,
    0,
    NULL,
    ''
);

-- Вывести результат
SELECT
    CONCAT('✅ Сниппет logoutHandler успешно создан (ID: ', LAST_INSERT_ID(), ')') AS result;

-- ============================================
-- ВАЖНО: После выполнения этого скрипта
-- очистите кеш MODX через админку!
-- ============================================
