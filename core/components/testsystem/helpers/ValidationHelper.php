<?php
/**
 * Validation Helper Class
 *
 * Стандартизация валидации входных данных
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-13
 */

class ValidationHelper
{
    /**
     * Валидация и получение целого числа
     *
     * @param array $data Массив данных
     * @param string $key Ключ для поиска
     * @param string|null $errorMessage Сообщение об ошибке
     * @param bool $required Обязательное поле (по умолчанию true)
     * @param int $min Минимальное значение (по умолчанию 1)
     * @return int
     * @throws ValidationException Если валидация не прошла
     */
    public static function requireInt($data, $key, $errorMessage = null, $required = true, $min = 1)
    {
        $value = (int)($data[$key] ?? 0);

        if ($required && $value < $min) {
            $message = $errorMessage ?? ucfirst($key) . ' is required and must be at least ' . $min;
            throw new ValidationException($message);
        }

        return $value;
    }

    /**
     * Валидация и получение строки
     *
     * @param array $data Массив данных
     * @param string $key Ключ для поиска
     * @param string|null $errorMessage Сообщение об ошибке
     * @param bool $required Обязательное поле (по умолчанию true)
     * @return string
     * @throws ValidationException Если валидация не прошла
     */
    public static function requireString($data, $key, $errorMessage = null, $required = true)
    {
        $value = trim($data[$key] ?? '');

        if ($required && empty($value)) {
            $message = $errorMessage ?? ucfirst($key) . ' is required';
            throw new ValidationException($message);
        }

        return $value;
    }

    /**
     * Валидация и получение опциональной строки
     *
     * @param array $data Массив данных
     * @param string $key Ключ для поиска
     * @param string $default Значение по умолчанию
     * @return string
     */
    public static function optionalString($data, $key, $default = '')
    {
        return trim($data[$key] ?? $default);
    }

    /**
     * Валидация и получение опционального целого числа
     *
     * @param array $data Массив данных
     * @param string $key Ключ для поиска
     * @param int $default Значение по умолчанию
     * @return int
     */
    public static function optionalInt($data, $key, $default = 0)
    {
        return (int)($data[$key] ?? $default);
    }

    /**
     * Валидация и получение массива
     *
     * @param array $data Массив данных
     * @param string $key Ключ для поиска
     * @param int $minItems Минимальное количество элементов
     * @param string|null $errorMessage Сообщение об ошибке
     * @return array
     * @throws ValidationException Если валидация не прошла
     */
    public static function requireArray($data, $key, $minItems = 1, $errorMessage = null)
    {
        $value = $data[$key] ?? [];

        if (!is_array($value)) {
            $message = $errorMessage ?? ucfirst($key) . ' must be an array';
            throw new ValidationException($message);
        }

        if (count($value) < $minItems) {
            $message = $errorMessage ?? ucfirst($key) . ' must contain at least ' . $minItems . ' items';
            throw new ValidationException($message);
        }

        return $value;
    }

    /**
     * Валидация ID теста
     *
     * @param int|string $testId ID теста
     * @param string|null $errorMessage Сообщение об ошибке
     * @return int
     * @throws ValidationException Если ID невалиден
     */
    public static function validateTestId($testId, $errorMessage = null)
    {
        $id = (int)$testId;

        if ($id <= 0) {
            $message = $errorMessage ?? 'Invalid test ID';
            throw new ValidationException($message);
        }

        return $id;
    }

    /**
     * Валидация ID вопроса
     *
     * @param int|string $questionId ID вопроса
     * @param string|null $errorMessage Сообщение об ошибке
     * @return int
     * @throws ValidationException Если ID невалиден
     */
    public static function validateQuestionId($questionId, $errorMessage = null)
    {
        $id = (int)$questionId;

        if ($id <= 0) {
            $message = $errorMessage ?? 'Invalid question ID';
            throw new ValidationException($message);
        }

        return $id;
    }

    /**
     * Валидация email адреса
     *
     * @param string $email Email для проверки
     * @param string|null $errorMessage Сообщение об ошибке
     * @return string Валидный email
     * @throws ValidationException Если email невалиден
     */
    public static function validateEmail($email, $errorMessage = null)
    {
        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = $errorMessage ?? 'Invalid email address';
            throw new ValidationException($message);
        }

        return $email;
    }

    /**
     * Валидация типа вопроса
     *
     * @param string $questionType Тип вопроса
     * @return string
     * @throws ValidationException Если тип невалиден
     */
    public static function validateQuestionType($questionType)
    {
        $allowedTypes = ['single', 'multiple', 'text', 'matching'];
        $type = trim($questionType);

        if (!in_array($type, $allowedTypes, true)) {
            throw new ValidationException('Invalid question type. Allowed: ' . implode(', ', $allowedTypes));
        }

        return $type;
    }

    /**
     * Валидация статуса публикации
     *
     * @param string $status Статус публикации
     * @return string
     * @throws ValidationException Если статус невалиден
     */
    public static function validatePublicationStatus($status)
    {
        $allowedStatuses = ['public', 'private', 'unlisted'];
        $status = trim($status);

        if (!in_array($status, $allowedStatuses, true)) {
            throw new ValidationException('Invalid publication status. Allowed: ' . implode(', ', $allowedStatuses));
        }

        return $status;
    }

    /**
     * Проверка наличия хотя бы одного правильного ответа
     *
     * @param array $answers Массив ответов
     * @return bool
     * @throws ValidationException Если нет правильного ответа
     */
    public static function requireCorrectAnswer($answers)
    {
        $hasCorrect = false;

        foreach ($answers as $answer) {
            if (isset($answer['is_correct']) && $answer['is_correct'] == 1) {
                $hasCorrect = true;
                break;
            }
        }

        if (!$hasCorrect) {
            throw new ValidationException('At least one correct answer is required');
        }

        return true;
    }

    /**
     * Валидация boolean значения
     *
     * @param array $data Массив данных
     * @param string $key Ключ для поиска
     * @param bool $default Значение по умолчанию
     * @return bool
     */
    public static function optionalBool($data, $key, $default = false)
    {
        if (!isset($data[$key])) {
            return $default;
        }

        // Поддержка различных форматов: true/false, 1/0, "true"/"false", "1"/"0"
        $value = $data[$key];

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['true', '1', 'yes', 'on'], true);
        }

        return $default;
    }
}
