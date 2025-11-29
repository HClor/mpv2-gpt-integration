<?php
/**
 * CSRF Token Snippet
 *
 * Генерирует meta тег с CSRF токеном для использования в чанках и шаблонах
 *
 * @package TestSystem
 * @version 1.0
 */

// Подключаем bootstrap для загрузки CsrfProtection
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

// Генерируем и возвращаем meta тег с CSRF токеном
return CsrfProtection::getTokenMeta();