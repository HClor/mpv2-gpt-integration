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
        // Security
        'CsrfProtection' => TESTSYSTEM_PATH . '/security/CsrfProtection.php',

        // Helpers
        'ResponseHelper' => TESTSYSTEM_PATH . '/helpers/ResponseHelper.php',
        'ValidationHelper' => TESTSYSTEM_PATH . '/helpers/ValidationHelper.php',
        'PermissionHelper' => TESTSYSTEM_PATH . '/helpers/PermissionHelper.php',
        'UrlHelper' => TESTSYSTEM_PATH . '/helpers/UrlHelper.php',

        // Repositories
        'TestRepository' => TESTSYSTEM_PATH . '/repositories/TestRepository.php',

        // Exceptions
        'TestSystemException' => TESTSYSTEM_PATH . '/exceptions/TestSystemException.php',
        'NotFoundException' => TESTSYSTEM_PATH . '/exceptions/NotFoundException.php',
        'ValidationException' => TESTSYSTEM_PATH . '/exceptions/ValidationException.php',
        'PermissionException' => TESTSYSTEM_PATH . '/exceptions/PermissionException.php',
        'AuthenticationException' => TESTSYSTEM_PATH . '/exceptions/AuthenticationException.php',

        // Здесь можно добавить другие классы по мере создания
        // 'AccessService' => TESTSYSTEM_PATH . '/services/AccessService.php',
        // 'TestService' => TESTSYSTEM_PATH . '/services/TestService.php',
    ];

    if (isset($classMap[$className])) {
        require_once $classMap[$className];
    }
});
