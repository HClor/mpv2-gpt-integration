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
}
