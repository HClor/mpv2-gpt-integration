<?php
/**
 * Configuration Helper
 *
 * Класс для работы с конфигурацией системы тестирования
 * Предоставляет удобный API для получения настроек
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class Config {
    /**
     * Кешированная конфигурация
     * @var array|null
     */
    private static $config = null;

    /**
     * Загрузка конфигурации из файла
     *
     * @return array
     */
    private static function load() {
        if (self::$config === null) {
            $configPath = dirname(__DIR__) . '/config/site.config.php';

            if (!file_exists($configPath)) {
                throw new Exception('Configuration file not found: ' . $configPath);
            }

            self::$config = require $configPath;
        }

        return self::$config;
    }

    /**
     * Получение значения из конфигурации
     *
     * @param string $key Ключ в формате 'section.key' или 'section.subsection.key'
     * @param mixed $default Значение по умолчанию
     * @return mixed
     */
    public static function get($key, $default = null) {
        $config = self::load();
        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Получение ID страницы
     *
     * @param string $page Название страницы
     * @param int $default Значение по умолчанию
     * @return int
     */
    public static function getPageId($page, $default = 0) {
        return (int)self::get("pages.{$page}", $default);
    }

    /**
     * Получение названия группы пользователей
     *
     * @param string $group Тип группы (admins, experts, students)
     * @param string $default Значение по умолчанию
     * @return string
     */
    public static function getGroup($group, $default = '') {
        return (string)self::get("groups.{$group}", $default);
    }

    /**
     * Получение всех групп пользователей
     *
     * @return array
     */
    public static function getAllGroups() {
        return self::get('groups', []);
    }

    /**
     * Получение массива названий групп для SQL запросов
     *
     * @param array $types Типы групп (admins, experts, students)
     * @return array Массив названий групп
     */
    public static function getGroupNames(array $types = ['admins', 'experts']) {
        $groups = [];
        foreach ($types as $type) {
            $groupName = self::getGroup($type);
            if (!empty($groupName)) {
                $groups[] = $groupName;
            }
        }
        return $groups;
    }

    /**
     * Получение настройки теста
     *
     * @param string $setting Название настройки
     * @param mixed $default Значение по умолчанию
     * @return mixed
     */
    public static function getTestSetting($setting, $default = null) {
        return self::get("test_settings.{$setting}", $default);
    }

    /**
     * Получение настройки безопасности
     *
     * @param string $setting Название настройки
     * @param mixed $default Значение по умолчанию
     * @return mixed
     */
    public static function getSecuritySetting($setting, $default = null) {
        return self::get("security.{$setting}", $default);
    }

    /**
     * Получение настройки кеша
     *
     * @param string $setting Название настройки
     * @param mixed $default Значение по умолчанию (секунды)
     * @return int
     */
    public static function getCacheTTL($setting, $default = 3600) {
        return (int)self::get("cache.{$setting}", $default);
    }

    /**
     * Проверка, существует ли конфигурационный ключ
     *
     * @param string $key Ключ в формате 'section.key'
     * @return bool
     */
    public static function has($key) {
        $config = self::load();
        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    /**
     * Сброс кеша конфигурации
     * (используется в тестах или при обновлении конфига)
     */
    public static function reset() {
        self::$config = null;
    }

    /**
     * Получение всей конфигурации
     *
     * @return array
     */
    public static function all() {
        return self::load();
    }
}
