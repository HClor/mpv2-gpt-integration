<?php
/**
 * ValidationException Class
 *
 * Исключение для ошибок валидации входных данных
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-13
 */

class ValidationException extends TestSystemException
{
    /**
     * HTTP код ответа
     * @var int
     */
    protected $httpCode = 400;

    /**
     * Конструктор
     *
     * @param string $message Сообщение об ошибке
     * @param mixed $data Дополнительные данные (опционально)
     * @param Exception|null $previous Предыдущее исключение
     */
    public function __construct($message = 'Validation error', $data = null, Exception $previous = null)
    {
        parent::__construct($message, 400, $data, $previous);
    }
}
