<?php
/**
 * Bootstrap для TestSystem компонентов
 *
 * Автозагрузка классов без composer
 *
 * @package TestSystem
 * @version 1.0
 */

// Путь к компонентам
defined('TESTSYSTEM_PATH') or define('TESTSYSTEM_PATH', __DIR__);

/**
 * Автозагрузчик классов TestSystem
 */
spl_autoload_register(function ($className) {
    // Маппинг классов на файлы
    $classMap = [
        'CsrfProtection' => TESTSYSTEM_PATH . '/security/CsrfProtection.php',
        // Здесь можно добавить другие классы по мере создания
        // 'AccessService' => TESTSYSTEM_PATH . '/services/AccessService.php',
        // 'TestService' => TESTSYSTEM_PATH . '/services/TestService.php',
    ];

    if (isset($classMap[$className])) {
        require_once $classMap[$className];
    }
});
