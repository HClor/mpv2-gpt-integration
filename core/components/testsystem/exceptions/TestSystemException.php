<?php
/**
 * Base TestSystem Exception Class
 *
 * Базовый класс исключений для TestSystem
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-13
 */

class TestSystemException extends Exception
{
    /**
     * HTTP код ответа
     * @var int
     */
    protected $httpCode = 400;

    /**
     * Дополнительные данные об ошибке
     * @var mixed
     */
    protected $data;

    /**
     * Конструктор
     *
     * @param string $message Сообщение об ошибке
     * @param int $httpCode HTTP код (опционально)
     * @param mixed $data Дополнительные данные (опционально)
     * @param Exception|null $previous Предыдущее исключение
     */
    public function __construct($message = '', $httpCode = null, $data = null, Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);

        if ($httpCode !== null) {
            $this->httpCode = $httpCode;
        }

        $this->data = $data;
    }

    /**
     * Получение HTTP кода
     *
     * @return int
     */
    public function getHttpCode()
    {
        return $this->httpCode;
    }

    /**
     * Получение дополнительных данных
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Преобразование исключения в массив для JSON ответа
     *
     * @return array
     */
    public function toArray()
    {
        $result = [
            'success' => false,
            'message' => $this->getMessage()
        ];

        if ($this->data !== null) {
            $result['data'] = $this->data;
        }

        return $result;
    }
}
