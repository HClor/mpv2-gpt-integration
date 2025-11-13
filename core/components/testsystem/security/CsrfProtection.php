<?php
/**
 * CSRF Protection Class
 *
 * Защита от Cross-Site Request Forgery атак
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-13
 */

class CsrfProtection {
    /**
     * Имя токена в сессии и формах
     */
    const TOKEN_NAME = 'csrf_token';

    /**
     * Время жизни токена (в секундах) - 2 часа
     */
    const TOKEN_LIFETIME = 7200;

    /**
     * Генерация нового CSRF токена
     *
     * @return string Сгенерированный токен
     */
    public static function generateToken() {
        // Запускаем сессию если не запущена
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Генерируем криптографически стойкий токен
        $token = bin2hex(random_bytes(32));

        // Сохраняем токен и время создания в сессии
        $_SESSION[self::TOKEN_NAME] = [
            'token' => $token,
            'created_at' => time()
        ];

        return $token;
    }

    /**
     * Валидация CSRF токена
     *
     * @param string $token Токен для проверки
     * @return bool True если токен валиден
     */
    public static function validateToken($token) {
        // Запускаем сессию если не запущена
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Проверяем наличие токена в сессии
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }

        $sessionData = $_SESSION[self::TOKEN_NAME];

        // Проверяем срок действия токена
        if (time() - $sessionData['created_at'] > self::TOKEN_LIFETIME) {
            // Токен истек
            self::clearToken();
            return false;
        }

        // Используем hash_equals для защиты от timing attacks
        $isValid = hash_equals($sessionData['token'], $token);

        // Опционально: можно регенерировать токен после каждой проверки (one-time token)
        // Для удобства оставляем токен живым в течение сессии
        // if ($isValid) {
        //     self::clearToken();
        // }

        return $isValid;
    }

    /**
     * Получение текущего токена (или создание нового)
     *
     * @return string Токен
     */
    public static function getToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Если токен существует и не истек, возвращаем его
        if (isset($_SESSION[self::TOKEN_NAME])) {
            $sessionData = $_SESSION[self::TOKEN_NAME];

            if (time() - $sessionData['created_at'] <= self::TOKEN_LIFETIME) {
                return $sessionData['token'];
            }
        }

        // Иначе генерируем новый
        return self::generateToken();
    }

    /**
     * Генерация HTML поля с токеном для форм
     *
     * @return string HTML input field
     */
    public static function getTokenField() {
        $token = self::getToken();
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Генерация meta тега с токеном для JavaScript
     *
     * @return string HTML meta tag
     */
    public static function getTokenMeta() {
        $token = self::getToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Очистка токена из сессии
     */
    public static function clearToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION[self::TOKEN_NAME]);
    }

    /**
     * Валидация токена из запроса (POST/JSON)
     *
     * Автоматически определяет источник токена
     *
     * @param array|null $data Данные запроса (опционально)
     * @return bool True если токен валиден
     */
    public static function validateRequest($data = null) {
        $token = null;

        // Попытка 1: Из переданных данных
        if ($data !== null && isset($data[self::TOKEN_NAME])) {
            $token = $data[self::TOKEN_NAME];
        }
        // Попытка 2: Из $_POST
        elseif (isset($_POST[self::TOKEN_NAME])) {
            $token = $_POST[self::TOKEN_NAME];
        }
        // Попытка 3: Из заголовка X-CSRF-Token
        elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        if ($token === null) {
            return false;
        }

        return self::validateToken($token);
    }

    /**
     * Проверка и выброс исключения при невалидном токене
     *
     * @param array|null $data Данные запроса
     * @throws Exception Если токен невалиден
     */
    public static function requireValidToken($data = null) {
        if (!self::validateRequest($data)) {
            throw new Exception('CSRF token validation failed');
        }
    }
}
