<?php
/**
 * Response Helper Class
 *
 * Стандартизация JSON ответов API
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-13
 */

class ResponseHelper
{
    /**
     * Возвращает успешный JSON ответ
     *
     * @param mixed $data Данные для возврата
     * @param string|null $message Опциональное сообщение
     * @return array
     */
    public static function success($data = null, $message = null)
    {
        $response = ['success' => true];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $response;
    }

    /**
     * Возвращает ответ с ошибкой
     *
     * @param string $message Сообщение об ошибке
     * @param mixed $data Опциональные данные об ошибке
     * @return array
     */
    public static function error($message, $data = null)
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $response;
    }

    /**
     * Отправляет JSON ответ и завершает выполнение
     *
     * @param array $response Ответ для отправки
     * @return void
     */
    public static function send($response)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
        exit;
    }

    /**
     * Отправляет успешный ответ и завершает выполнение
     *
     * @param mixed $data Данные для возврата
     * @param string|null $message Опциональное сообщение
     * @return void
     */
    public static function sendSuccess($data = null, $message = null)
    {
        self::send(self::success($data, $message));
    }

    /**
     * Отправляет ответ с ошибкой и завершает выполнение
     *
     * @param string $message Сообщение об ошибке
     * @param mixed $data Опциональные данные об ошибке
     * @return void
     */
    public static function sendError($message, $data = null)
    {
        self::send(self::error($message, $data));
    }
}
