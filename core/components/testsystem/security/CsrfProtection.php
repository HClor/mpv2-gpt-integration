<?php
/**
 * CSRF Protection Class
 *
 * Обеспечивает защиту от CSRF атак генерацией и валидацией токенов
 *
 * @package MPV2\TestSystem\Security
 * @version 1.0.0
 */

namespace MPV2\TestSystem\Security;

class CsrfProtection
{
    /**
     * @var string Имя параметра токена в сессии
     */
    private const TOKEN_SESSION_KEY = 'csrf_token';

    /**
     * @var string Имя параметра токена в запросе
     */
    private const TOKEN_REQUEST_KEY = 'csrf_token';

    /**
     * @var int Время жизни токена в секундах (1 час)
     */
    private const TOKEN_LIFETIME = 3600;

    /**
     * Генерирует новый CSRF токен
     *
     * @return string CSRF токен
     */
    public static function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_SESSION_KEY] = [
            'token' => $token,
            'timestamp' => time()
        ];

        return $token;
    }

    /**
     * Получает текущий CSRF токен или генерирует новый
     *
     * @return string CSRF токен
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Проверяем, существует ли токен и не истек ли он
        if (isset($_SESSION[self::TOKEN_SESSION_KEY])) {
            $tokenData = $_SESSION[self::TOKEN_SESSION_KEY];
            $timestamp = $tokenData['timestamp'] ?? 0;

            // Если токен не истек, возвращаем его
            if (time() - $timestamp < self::TOKEN_LIFETIME) {
                return $tokenData['token'];
            }
        }

        // Генерируем новый токен
        return self::generateToken();
    }

    /**
     * Валидирует CSRF токен из запроса
     *
     * @param string|null $token Токен для проверки (если null, берется из $_POST или $_GET)
     * @return bool True если токен валиден
     */
    public static function validateToken(?string $token = null): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Получаем токен из запроса, если не передан
        if ($token === null) {
            $token = $_POST[self::TOKEN_REQUEST_KEY] ??
                     $_GET[self::TOKEN_REQUEST_KEY] ??
                     self::getTokenFromHeader() ??
                     '';
        }

        // Проверяем наличие токена в сессии
        if (!isset($_SESSION[self::TOKEN_SESSION_KEY])) {
            return false;
        }

        $sessionTokenData = $_SESSION[self::TOKEN_SESSION_KEY];
        $sessionToken = $sessionTokenData['token'] ?? '';
        $timestamp = $sessionTokenData['timestamp'] ?? 0;

        // Проверяем время жизни токена
        if (time() - $timestamp >= self::TOKEN_LIFETIME) {
            return false;
        }

        // Проверяем совпадение токенов (используем hash_equals для защиты от timing attacks)
        return hash_equals($sessionToken, $token);
    }

    /**
     * Получает токен из HTTP заголовка X-CSRF-Token
     *
     * @return string|null Токен или null
     */
    private static function getTokenFromHeader(): ?string
    {
        $headers = getallheaders();
        return $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? null;
    }

    /**
     * Проверяет токен и генерирует исключение при ошибке
     *
     * @param string|null $token Токен для проверки
     * @throws \Exception Если токен невалиден
     */
    public static function requireToken(?string $token = null): void
    {
        if (!self::validateToken($token)) {
            throw new \Exception('CSRF token validation failed', 403);
        }
    }

    /**
     * Генерирует HTML скрытого поля с CSRF токеном для форм
     *
     * @return string HTML код скрытого поля
     */
    public static function getTokenField(): string
    {
        $token = self::getToken();
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars(self::TOKEN_REQUEST_KEY, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Возвращает имя параметра токена для использования в JavaScript
     *
     * @return string Имя параметра
     */
    public static function getTokenKey(): string
    {
        return self::TOKEN_REQUEST_KEY;
    }

    /**
     * Удаляет токен из сессии
     */
    public static function removeToken(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION[self::TOKEN_SESSION_KEY]);
    }
}
