<?php
/**
 * PermissionException Class
 *
 * Исключение для ошибок прав доступа
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-13
 */

class PermissionException extends TestSystemException
{
    /**
     * HTTP код ответа
     * @var int
     */
    protected $httpCode = 403;

    /**
     * Конструктор
     *
     * @param string $message Сообщение об ошибке
     * @param mixed $data Дополнительные данные (опционально)
     * @param Exception|null $previous Предыдущее исключение
     */
    public function __construct($message = 'Permission denied', $data = null, Exception $previous = null)
    {
        parent::__construct($message, 403, $data, $previous);
    }
}
