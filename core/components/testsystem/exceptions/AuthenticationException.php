<?php
/**
 * AuthenticationException Class
 *
 * Исключение для ошибок аутентификации
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-13
 */

class AuthenticationException extends TestSystemException
{
    /**
     * HTTP код ответа
     * @var int
     */
    protected $httpCode = 401;

    /**
     * Конструктор
     *
     * @param string $message Сообщение об ошибке
     * @param mixed $data Дополнительные данные (опционально)
     * @param Exception|null $previous Предыдущее исключение
     */
    public function __construct($message = 'Authentication required', $data = null, Exception $previous = null)
    {
        parent::__construct($message, 401, $data, $previous);
    }

    /**
     * Рендер HTML алерта для отображения пользователю
     *
     * @param object $modx MODX объект
     * @param string|null $customMessage Пользовательское сообщение
     * @return string HTML alert
     */
    public function renderAlert($modx, $customMessage = null)
    {
        $authPageId = Config::getPageId('auth', 0);
        $authUrl = $authPageId > 0 ? $modx->makeUrl($authPageId) : '#';

        $message = $customMessage ?? 'Для выполнения этого действия необходимо войти в систему.';

        return '<div class="alert alert-warning">
            <h4>Требуется авторизация</h4>
            <p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>
            <a href="' . htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary">Войти</a>
        </div>';
    }
}
