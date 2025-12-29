<?php
/**
 * Сниппет: csrfToken - Генератор CSRF токена
 * Вызывается из: tsHead.tpl, tsHeader.tpl
 * Назначение: Генерирует meta тег с CSRF токеном для защиты форм от CSRF атак
 *
 * @package TestSystem
 * @version 1.0
 */

// Подключаем bootstrap для загрузки CsrfProtection
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

// Генерируем и возвращаем meta тег с CSRF токеном
return CsrfProtection::getTokenMeta();