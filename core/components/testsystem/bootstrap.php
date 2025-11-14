<?php
/**
 * Bootstrap для TestSystem компонентов
 *
 * Поддержка как простых классов, так и PSR-4 namespace классов
 *
 * @package TestSystem
 * @version 2.0
 */

// Путь к компонентам
defined('TESTSYSTEM_PATH') or define('TESTSYSTEM_PATH', __DIR__);

// Подключаем Composer автозагрузчик если доступен
$composerAutoload = dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

/**
 * Автозагрузчик классов TestSystem (для обратной совместимости)
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
        'TestPermissionHelper' => TESTSYSTEM_PATH . '/helpers/TestPermissionHelper.php',
        'UrlHelper' => TESTSYSTEM_PATH . '/helpers/UrlHelper.php',

        // Repositories
        'TestRepository' => TESTSYSTEM_PATH . '/repositories/TestRepository.php',
        'BaseRepository' => TESTSYSTEM_PATH . '/repositories/BaseRepository.php',

        // Services
        'TestService' => TESTSYSTEM_PATH . '/services/TestService.php',
        'SessionService' => TESTSYSTEM_PATH . '/services/SessionService.php',
        'AccessService' => TESTSYSTEM_PATH . '/services/AccessService.php',
        'AuthService' => TESTSYSTEM_PATH . '/services/AuthService.php',

        // Exceptions
        'TestSystemException' => TESTSYSTEM_PATH . '/exceptions/TestSystemException.php',
        'NotFoundException' => TESTSYSTEM_PATH . '/exceptions/NotFoundException.php',
        'ValidationException' => TESTSYSTEM_PATH . '/exceptions/ValidationException.php',
        'PermissionException' => TESTSYSTEM_PATH . '/exceptions/PermissionException.php',
        'AuthenticationException' => TESTSYSTEM_PATH . '/exceptions/AuthenticationException.php',
    ];

    if (isset($classMap[$className])) {
        require_once $classMap[$className];
    }
});

/**
 * Вспомогательные функции для быстрого доступа к сервисам
 */

/**
 * Получить экземпляр AccessService
 *
 * @param object $modx MODX объект
 * @return MPV2\TestSystem\Services\AccessService|AccessService
 */
function getAccessService($modx) {
    static $instance = null;
    if ($instance === null) {
        // Пробуем загрузить namespace версию
        if (class_exists('MPV2\\TestSystem\\Services\\AccessService')) {
            $instance = new \MPV2\TestSystem\Services\AccessService($modx);
        }
    }
    return $instance;
}

/**
 * Получить экземпляр AuthService
 *
 * @param object $modx MODX объект
 * @return MPV2\TestSystem\Services\AuthService|AuthService
 */
function getAuthService($modx) {
    static $instance = null;
    if ($instance === null) {
        // Пробуем загрузить namespace версию
        if (class_exists('MPV2\\TestSystem\\Services\\AuthService')) {
            $instance = new \MPV2\TestSystem\Services\AuthService($modx);
        }
    }
    return $instance;
}

/**
 * Алиасы для обратной совместимости
 * Позволяют использовать новые namespace классы через старые имена
 */

// Если namespace классы загружены, создаем алиасы
if (class_exists('MPV2\\TestSystem\\Services\\AccessService')) {
    // AccessService уже загружен через простое имя в classMap выше
}

if (class_exists('MPV2\\TestSystem\\Services\\AuthService')) {
    // AuthService уже загружен через простое имя в classMap выше
}

if (class_exists('MPV2\\TestSystem\\Repositories\\BaseRepository')) {
    // BaseRepository уже загружен через простое имя в classMap выше
}
