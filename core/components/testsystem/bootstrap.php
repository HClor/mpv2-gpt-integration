<?php
/**
 * Bootstrap для системы тестирования MPV2
 *
 * Инициализирует автозагрузку классов и базовые сервисы
 *
 * @package MPV2\TestSystem
 * @version 1.0.0
 */

// Определяем базовый путь к компоненту
if (!defined('MPV2_TESTSYSTEM_PATH')) {
    define('MPV2_TESTSYSTEM_PATH', __DIR__ . '/');
}

// Подключаем автозагрузчик Composer
$autoloadPath = dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    throw new \Exception('Composer autoloader not found. Please run "composer install".');
}

require_once $autoloadPath;

/**
 * Получает экземпляр сервиса
 *
 * @param string $serviceName Название сервиса
 * @param \modX $modx Экземпляр MODX
 * @return object Экземпляр сервиса
 */
function getService(string $serviceName, $modx)
{
    static $services = [];

    if (isset($services[$serviceName])) {
        return $services[$serviceName];
    }

    $className = "MPV2\\TestSystem\\Services\\{$serviceName}";

    if (!class_exists($className)) {
        throw new \Exception("Service {$serviceName} not found");
    }

    $services[$serviceName] = new $className($modx);

    return $services[$serviceName];
}

/**
 * Получает экземпляр репозитория
 *
 * @param string $repositoryName Название репозитория
 * @param \modX $modx Экземпляр MODX
 * @return object Экземпляр репозитория
 */
function getRepository(string $repositoryName, $modx)
{
    static $repositories = [];

    if (isset($repositories[$repositoryName])) {
        return $repositories[$repositoryName];
    }

    $className = "MPV2\\TestSystem\\Repositories\\{$repositoryName}";

    if (!class_exists($className)) {
        throw new \Exception("Repository {$repositoryName} not found");
    }

    $repositories[$repositoryName] = new $className($modx);

    return $repositories[$repositoryName];
}

// Алиасы для удобного доступа к сервисам
use MPV2\TestSystem\Services\AccessService;
use MPV2\TestSystem\Services\AuthService;
use MPV2\TestSystem\Security\CsrfProtection;
use MPV2\TestSystem\Repositories\BaseRepository;
