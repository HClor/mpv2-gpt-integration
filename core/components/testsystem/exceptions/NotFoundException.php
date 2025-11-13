<?php
/**
 * NotFoundException Class
 *
 * Исключение для случаев, когда ресурс не найден
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-13
 */

class NotFoundException extends TestSystemException
{
    /**
     * HTTP код ответа
     * @var int
     */
    protected $httpCode = 404;

    /**
     * Конструктор
     *
     * @param string $message Сообщение об ошибке
     * @param mixed $data Дополнительные данные (опционально)
     * @param Exception|null $previous Предыдущее исключение
     */
    public function __construct($message = 'Resource not found', $data = null, Exception $previous = null)
    {
        parent::__construct($message, 404, $data, $previous);
    }
}
