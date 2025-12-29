<?php
/**
 * Сниппет: csrfTokenField - Генератор CSRF токена для форм
 * Вызывается из: tsHeader.tpl (форма logout)
 * Назначение: Генерирует скрытое input поле с CSRF токеном для защиты форм от CSRF атак
 *
 * @package TestSystem
 * @version 1.0
 */

// Подключаем bootstrap для загрузки CsrfProtection
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

// Генерируем и возвращаем input поле с CSRF токеном
return CsrfProtection::getTokenField();
